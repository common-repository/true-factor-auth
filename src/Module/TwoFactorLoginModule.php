<?php

namespace TrueFactor\Module;

use Exception;
use TrueFactor\Exception\SecurityCheckFailedException;
use TrueFactor\Helper\Filter\ValueInvalidException;
use TrueFactor\Helper\GetUserByLogPwdTrait;
use TrueFactor\Helper\Html;
use TrueFactor\OptionalModule;
use TrueFactor\Options;
use TrueFactor\Token;
use TrueFactor\View;
use WP_Error;
use WP_User;

class TwoFactorLoginModule extends OptionalModule {

	use GetUserByLogPwdTrait;

	const TOKEN_KEY = 'tfa_login_token';
	const VERIFICATION_METHOD_META_KEY = 'tfa_login_verification_method';

	function activate() {
		add_action( 'init', [ $this, 'action__init' ] );
	}

	function get_module_name() {
		return tf_auth__( 'Two-Factor Login' );
	}

	function get_module_desc() {
		return tf_auth__( 'Adds 2-factor verification to login on your site forms.' );
	}

	// Actions and filters.

	function action__init() {

		if ( get_current_user_id() ) {
			if ( is_admin() ) {
				add_filter( 'tfa_admin_pages', [ $this, 'get_admin_pages' ] );
				add_filter( 'tfa_admin_page_settings_tfa_login_2fa', [ $this, 'get_admin_settings' ] );
			} else {
				// User settings
				add_action( 'wp_loaded', [ $this, 'submit_login_settings' ] );
				add_shortcode( 'true-factor-login-settings', [ $this, 'shortcode_login_settings' ] );
			}

			return;
		}

		add_filter( 'authenticate', [ $this, 'filter__authenticate' ], 999, 3 );

		$this->add_ajax_action( 'login_popup' );
		$this->add_ajax_action( 'login_confirm' );

		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		} else {
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
			add_action( 'login_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		}
	}

	/**
	 * Handler for the "login" hook.
	 * Adds security token check.
	 *
	 * @param $user
	 * @param $username
	 * @param $password
	 *
	 * @return WP_Error
	 */
	function filter__authenticate( $user, $username, $password ) {

		if ( defined( 'TRUE_FACTOR_DISABLE' ) ) {
			return $user;
		}

		if ( ! $user || is_wp_error( $user ) ) {
			return $user;
		}

		try {
			$handler = $this->get_verification_handler( $user );
			if ( ! $handler || $handler->get_handler_id() == 'non' ) {
				return $user;
			}
			if ( empty( $_POST[ self::TOKEN_KEY ] ) ) {
				throw new SecurityCheckFailedException();
			}
			Token::check( $_POST[ self::TOKEN_KEY ], 'login' );
		} catch ( Exception $e ) {
			$user = new WP_Error( 1, $e->getMessage() );
		}

		return $user;
	}

	function enqueue_scripts( $hook = null ) {

		if ( defined( 'TRUE_FACTOR_DISABLE' ) ) {
			return;
		}

		$form_selector = Options::get( 'login_2fa_form_selector' );
		//if ( Options::get_bool( 'login_2fa_protect_default_form', true ) ) {
		$form_selector = trim( $form_selector . "\n\n#loginform
[name=log]
[name=pwd]" );
		//}

		$this->do_enqueue_script( 'login', [ 'tfa_main' ], true, [
			'tfa_login' => [
				'login_2fa_form_selector' => $form_selector,
				'token_key'               => self::TOKEN_KEY,
			],
		] );
	}

	// Short-codes.

	public function shortcode_login_settings() {
		$user = wp_get_current_user();

		if ( ! $user->ID ) {
			return '';
		}

		$selected_handler = $this->get_verification_handler( $user );

		return View::returnRender( 'shortcode/login-settings', [
			'user_id'             => $user->ID,
			'user_handlers'       => VerificationModule::instance()->get_user_handlers( $user->ID ),
			'selected_handler_id' => $selected_handler ? $selected_handler->get_handler_id() : null,
		] );
	}

	/**
	 * Handles Login Settings Form submission.
	 */
	public function submit_login_settings() {
		$user_id = get_current_user_id();

		if ( empty( $_POST['tfa_login_2fa_set_method'] ) ) {
			return;
		}

		update_user_meta( $user_id, self::VERIFICATION_METHOD_META_KEY, $_POST['tfa_login_method'] );

		wp_safe_redirect( $_SERVER['REQUEST_URI'] );
		exit;
	}

	// Endpoints.

	/**
	 * Display 2FA popup before login.
	 */
	public function action_login_popup() {
		$user = $this->get_user_by_login_password();

		$handler = $this->get_verification_handler( $user );
		if ( ! $handler || $handler->get_handler_id() == 'non' ) {
			// If no handlers available, user should still be able to login.
			return wp_send_json( [
				'skip' => true,
			] );
			//$this->return_error('Please set up 2FA');
		}

		try {
			$content = $handler->get_verify_popup( $user, [
				'intro'         => tf_auth__( 'Please confirm login' ),
				'type_id'       => $handler->get_handler_id(),
				'type_title'    => $handler->get_handler_name(),
				'form_action'   => esc_attr( admin_url( 'admin-ajax.php?action=tfa_login_confirm' ) ),
				'hidden_fields' => wp_nonce_field( 'tfa_login_confirm', '_wpnonce', false, false )
				                   . Html::hidden( 'mode', 'login' )
				                   . Html::hidden( 'log', $user->user_login )
				                   // We can safely use $_POST['pwd'] because they was validated by get_user_by_login_password.
				                   . Html::hidden( 'pwd', $_POST['pwd'] ),
			] );
			wp_send_json( [
				'content' => $content,
			] );
			exit;
		} catch ( Exception $e ) {
			$this->return_error( $e->getMessage() );
		}
	}

	/**
	 * Confirm action with 2FA.
	 */
	public function action_login_confirm() {
		$user = $this->get_user_by_login_password();

		try {
			$handler = $this->get_verification_handler( $user );
			if ( ! $handler ) {
				$this->return_error( 'Configuration error. Please contact administrator' );
			}
			$handler->verify( $_POST, $user, 'login' );
			$token    = Token::add( 'login' );
			$response = [ 'token' => $token ];
			wp_send_json( $response );
		} catch ( Exception $e ) {
			do_action( 'tfa_verification_failed', $user );

			return $this->return_error( $e->getMessage() );
		}
	}

	protected function get_verification_handler( WP_User $user ) {

		$invalid_handlers = [ 'pwd' ];

		if ( Options::get_bool( 'login_2fa_required' ) ) {
			$invalid_handlers[] = 'non';
		}

		$handler_id = get_user_meta( $user->ID, self::VERIFICATION_METHOD_META_KEY, true );
		if ( in_array( $handler_id, $invalid_handlers ) ) {
			return null;
		}

		$handlers = VerificationModule::instance()->get_user_handlers( $user->ID );

		if ( $handler_id && ! empty( $handlers[ $handler_id ] ) ) {
			return $handlers[ $handler_id ];
		}

		foreach ( $invalid_handlers as $handler_id ) {
			if ( array_key_exists( $handler_id, $handlers ) ) {
				unset( $handlers[ $handler_id ] );
			}
		}

		if ( ! $handlers ) {
			return null;
		}

		return current( $handlers );
	}

	// Admin settings.

	function get_admin_pages( $pages ) {
		$pages['tfa_login_2fa'] = [
			'title'      => 'Login Two-Factor Verification',
			'menu_title' => 'Two-Factor Login',
			'intro'      => 'This module allows you to protect all login forms on your site with Two-Factor Verification.',
			'position'   => 2,
		];

		return $pages;
	}

	function get_admin_settings( $settings ) {
		$settings['general'] = [
			'fields' => [

				'login_2fa_required' => [
					'type'  => 'boolean',
					'label' => 'Require Two-factor login',
					'_hint' => tf_auth__( 'If not checked, user will be able to select "No Verification" option for login.' ),
				],

				/*
				'login_2fa_protect_default_form' => [
					'type'    => 'boolean',
					'label'   => 'Add 2FA to the default login form',
					'default' => 'yes',
				],
				*/

				'login_2fa_form_selector' => [
					'label'  => 'Custom Login Form Selectors',
					'attrs'  => [
						'type'  => 'textarea',
						'rows'  => 9,
						'_hint' => '
<p>Please enter line-by line:<br/>- CSS selector of the login form,<br/>-  username input selector<br/>- password input selector.<br/></p>
<p>Selectors for username and password inputs are relative to the form.</p>
<p>You can define multiple forms separated by empty line.</p>
<p><b>Example.</b></p>
<div class="tfa-template-example is-not-code">#loginform
[name=log]
[name=pwd]

.um-login form
[name^=username]
[name^=user_password]
</div>',
					],
					'_rules' => [
						function ( $val ) {
							if ( ! $val ) {
								return '';
							}

							$val   = trim( $val );
							$lines = explode( "\n", $val );

							if ( count( $lines ) % 4 != 3 ) {
								throw new ValueInvalidException( tf_auth__( 'Please provide correct form and input selectors. See examples below.' ) );
							}

							return $val;
						},
					],
				],
			],
		];

		$settings['shortcode'] = [
			'intro' => '<p>Use this short-code to allow user select login confirmation method:</p>
<p><kbd>[true-factor-login-settings]</kbd></p>
<p>This short-code only displays a dropdown with available methods and "Save" button, so take care of adding some intro on the page, e.g "Select your login verification method."</p>',
		];


		return $settings;
	}

}

