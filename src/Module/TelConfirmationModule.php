<?php

namespace TrueFactor\Module;

use TrueFactor\Helper\Html;
use TrueFactor\OptionalModule;
use TrueFactor\Options;
use TrueFactor\Orm\User;
use TrueFactor\PhoneNumber;
use TrueFactor\View;

/**
 * This module adds "Phone number confirmation" feature.
 *
 * @package TrueFactor\Module
 */
class TelConfirmationModule extends OptionalModule {

	const REG_SESSION_KEY = 'tfa_reg_tel';
	const GUEST_SESSION_KEY = 'tfa_guest_tel';

	function activate() {
		add_action( 'init', [ $this, 'action__init' ] );
	}

	function action__init() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'login_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		if ( get_current_user_id() ) {
			// Make sure that the phone number is stored in valid format.
			add_action( 'updated_user_meta', [ $this, 'updated_user_meta' ], 10, 4 );
			$this->add_ajax_action( 'confirm_phone_number' );

			if ( is_admin() ) {
				add_filter( 'tfa_admin_pages', [ $this, 'get_admin_pages' ] );
				add_filter( 'tfa_admin_page_settings_tfa_tel_confirmation', [ $this, 'get_admin_settings' ] );
				add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
			}

			return;
		}

		$this->add_ajax_action( 'reg_confirm_phone_number' );

		add_action( 'user_register', [ $this, 'filter_registration' ], 10, 1 );
		add_filter( 'registration_errors', [ $this, 'filter_registration_errors' ], 10, 1 );

		add_action( 'um_submit_form_errors_hook__registration', [ $this, 'um_submit_form_errors_hook__registration' ], 10, 1 );
		add_action( 'um_registration_complete', [ $this, 'um_registration_complete' ], 10, 2 );
	}

	function updated_user_meta( $meta_id, $object_id, $meta_key, $_meta_value ) {
		if ( $meta_key == SmsModule::get_tel_number_meta_key() ) {
			$number = PhoneNumber::normalize( $_meta_value );
			if ( $_meta_value != $number ) {
				update_user_meta( $object_id, $meta_key, $number );
			}
		}
	}

	function um_submit_form_errors_hook__registration( $args ) {
		if ( ! $this->is_valid_registration_request() ) {
			wp_die( 'Please confirm your phone number' );
		}
	}

	function um_registration_complete( $user_id, $args ) {
		$this->filter_registration( $user_id );
	}

	function get_dependencies() {
		return [ 'Sms' ];
	}

	function get_module_name() {
		return tf_auth__( 'Phone Number Confirmation' );
	}

	function get_module_desc() {
		return tf_auth__( 'Provides phone number confirmation feature in various profile forms and on the registration form.' );
	}

	public function action_confirm_phone_number() {
		$user_id = $this->login_required();

		$this->submit_confirm_phone_number();

		$confirmed_number = SmsModule::instance()->get_confirmed_tel_number( $user_id );

		if ( empty( $_GET['tel'] ) ) {
			// User wants to clear out his phone number.
			wp_send_json( [
				'skip'    => 1,
				'success' => 1,
				//'tel'     => $confirmed_number,
			] );
			exit;
		}

		$new_number = PhoneNumber::normalize( $_GET['tel'] );
		if ( ! $new_number ) {
			return $this->return_error( 'Invalid phone number' );
		}

		if ( $new_number == $confirmed_number ) {
			SmsModule::instance()->set_tel_number( $user_id, $new_number );
			SmsModule::instance()->set_confirmed_tel_number( $user_id, $new_number );
			wp_send_json( [
				'success' => 1,
				'tel'     => "+{$new_number}",
			] );
			exit;
		}

		$tpl  = $this->get_popup_template();
		$data = [
			'form_action'   => esc_attr( admin_url( 'admin-ajax.php?action=tfa_confirm_phone_number' ) ),
			'phone_number'  => $new_number,
			'hidden_fields' => Html::hidden( 'tfa_confirm_phone_number', 1 )
			                   . SmsModule::popup_hidden_fields(),
		];

		$content = View::mustache_render( $tpl, $data );
		wp_send_json( [
			'content' => $content,
		] );
	}

	/**
	 * Display phone number confirmation popup on registration form.
	 */
	public function action_reg_confirm_phone_number() {
		$this->submit_confirm_phone_number();

		if ( $this->is_valid_registration_request() ) {
			wp_send_json( [
				'skip' => 1,
				'tel'  => $_SESSION[ self::REG_SESSION_KEY ],
			] );
			exit;
		}

		$new_number = PhoneNumber::normalize( $_GET['tel'] );

		$tpl  = $this->get_popup_template();
		$data = [
			'form_action'   => esc_attr( admin_url( 'admin-ajax.php?action=tfa_reg_confirm_phone_number' ) ),
			'phone_number'  => "+{$new_number}",
			'hidden_fields' => Html::hidden( 'tfa_confirm_phone_number', 1 )
			                   . SmsModule::popup_hidden_fields(),
		];

		$content = View::mustache_render( $tpl, $data );
		wp_send_json( [
			'content' => $content,
		] );
	}

	/**
	 * Display phone number confirmation popup on custom form for guest user.
	 */
	public function action_guest_confirm_phone_number() {
		$this->submit_confirm_phone_number();

		if ( $this->is_valid_registration_request() ) {
			wp_send_json( [
				'skip' => 1,
				'tel'  => $_SESSION[ self::REG_SESSION_KEY ],
			] );
			exit;
		}

		$new_number = PhoneNumber::normalize( $_GET['tel'] );

		$tpl  = $this->get_popup_template();
		$data = [
			'form_action'   => esc_attr( admin_url( 'admin-ajax.php?action=tfa_reg_confirm_phone_number' ) ),
			'phone_number'  => $new_number,
			'hidden_fields' => Html::hidden( 'tfa_confirm_phone_number', 1 )
			                   . SmsModule::popup_hidden_fields(),
		];

		$content = View::mustache_render( $tpl, $data );
		wp_send_json( [
			'content' => $content,
		] );
	}

	protected function submit_confirm_phone_number() {
		if ( empty( $_POST['tfa_confirm_phone_number'] ) && empty( $_POST['tfa_reg_confirm_phone_number'] ) ) {
			return;
		}

		$user_id = get_current_user_id();

		try {
			$new_number = PhoneNumber::normalize( $_POST['tel'] );
			if ( ! $new_number ) {
				throw new \Exception( 'Invalid number' );
			}

			if ( empty( $_POST['code'] )
			     || ! is_string( $_POST['code'] )
			     || ! preg_match( '/^[\w\d]{4,}$/', $_POST['code'] )
			) {
				throw new \Exception( Options::get( 't_pls_enter_otp', 'Please enter OTP' ) );
			}

			SmsModule::instance()->check_otp( $new_number, $_POST['code'] );

			$existing = User::getByTel( $new_number );
			if ( $existing ) {
				if ( ! $user_id || $existing->ID != $user_id ) {
					throw new \Exception( 'This phone number is already registered. Please log in or contact administrator.' );
				}
			}

			if ( $user_id ) {
				SmsModule::instance()->set_tel_number( $user_id, $new_number );
				SmsModule::instance()->set_confirmed_tel_number( $user_id, $new_number );
				SmsModule::clear_user_attempts( $user_id );
			} else {
				// Store the confirmed number in session to save it on the next step.
				$_SESSION[ self::REG_SESSION_KEY ] = $new_number;
			}

			if ( wp_is_json_request() ) {
				wp_send_json( [
					'tel'     => $new_number,
					'success' => true,
				] );
				exit;
			}

			wp_safe_redirect( wp_get_referer() );
			exit;
		} catch ( \Exception $e ) {
			if ( $user_id ) {
				SmsModule::add_user_attempt( $user_id );
			} else {
				SmsModule::add_ip_send( ip2long( $_SERVER['REMOTE_ADDR'] ) );
			}
			if ( ! empty( $response['incorrect'] ) ) {
				$response['error_message'] = Options::get( 't_otp_incorrect', 'OTP Entered is Incorrect' );
			}
			$this->return_error( $e->getMessage() );
		}
	}

	function filter_registration_errors( \WP_Error $errors ) {
		if ( ! $this->is_valid_registration_request() ) {
			$errors->add( 'confirm_tel', '<strong>ERROR</strong>: Please confirm your phone number' );
		}

		return $errors;
	}

	protected function is_valid_registration_request() {
		if ( ! empty( $_SESSION[ self::REG_SESSION_KEY ] ) ) {
			return true;
		}
		if ( ! Options::get_bool( 'tel_confirmation_require_on_reg' ) ) {
			return true;
		}

		return false;
	}

	protected function is_valid_guest_request() {
		if ( ! empty( $_SESSION[ self::GUEST_SESSION_KEY ] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Handler for the "login" hook.
	 * Adds security token check.
	 *
	 * @param  int  $user_id
	 */
	function filter_registration( $user_id ) {

		if ( empty( $_SESSION[ self::REG_SESSION_KEY ] ) ) {
			return;
		}

		$tel = $_SESSION[ self::REG_SESSION_KEY ];
		unset( $_SESSION[ self::REG_SESSION_KEY ] );

		SmsModule::instance()->set_tel_number( $user_id, $tel );
		SmsModule::instance()->set_confirmed_tel_number( $user_id, $tel );
	}

	function get_admin_pages( $pages ) {
		$pages['tfa_tel_confirmation'] = [
			'title'      => 'Phone Number Confirmation Settings',
			'intro'      => '<p>You can add phone number confirmation feature to any form on your site.</p>',
			'position'   => 60,
			'menu_title' => 'Phone Number Confirmation',
		];

		return $pages;
	}

	function get_admin_settings( $settings ) {
		$settings['general'] = [
			'title'  => '',
			'fields' => [
				'tel_confirmation_input_selector' => [
					'label' => 'Input selector',
					'_hint' => '<p>Please provide valid and unique CSS selector, e.g <kbd>#your-profile [name=phone_number], .um-profile [name^=phone_number]</kbd>.</p>
<p>When value in this input gets changed, user is asked to confirm the new tel number.</p>
<p>Please ensure that provided input corresponds to the same meta key that is defined in SMS Settings.</p>',
				],

				'tel_confirmation_exclude_url' => [
					'label' => 'Exclude pages',
					'attrs' => [
						'type' => 'textarea',
						'rows' => 3,
					],
					'_hint' => '<p>Do not add phone confirmation on pages which url contain one of given strings (one pattern per line).</p>
<p>May be useful on pages where admin can edit other users\' profiles and must be able to edit phone number without confirmation.</p>
<p><b>Example:</b></p>
<div class="tfa-template-example is-not-code">/wp-admin/user-edit.php
/some/other/url</div>',
				],

				'tel_confirmation_require' => [
					'label' => 'Require Verification',
					'type'  => 'boolean',
					'_hint' => 'User won\'t be allowed to change phone number without verification. Verification popup will be displayed each time when user attempts to change his number in profile.',
				],

				'tel_confirmation_add_button' => [
					'label' => 'Add "Verify" button',
					'type'  => 'boolean',
					'_hint' => 'The "Verify" button will be displayed next to phone number input.',
				],

				'tel_confirmation_verify_caption' => [
					'label'   => '"Verify" caption',
					'default' => 'Verify',
					'_hint'   => 'This badge is clickable. When clicked, tel number verification popup gets displayed.',
				],

				'tel_confirmation_verified_caption' => [
					'label'   => '"Verified" caption',
					'default' => 'Verified',
				],

				'tel_confirmation_require_on_reg' => [
					'label' => 'Require Phone Number Verification on Registration',
					'type'  => 'boolean',
					'_hint' => 'User won\'t be allowed to register without phone number verification.',
				],

				'tel_confirmation_reg_forms' => [
					'label' => 'Registration Forms Selectors',
					'attrs' => [
						'type'  => 'textarea',
						'rows'  => 6,
						'_hint' => '
<p>Please enter line-by line:
<br/>- CSS selector of the registration form,
<br/>- Phone number input name
</p>
<p>You can define multiple forms separated by empty line.</p>
<p>If phone number input won\'t be found in the registration form, user will be asked to enter his phone number in popup dialog.</p>
<p><b>Example</b></p>
<div class="tfa-template-example is-not-code">.um-register form
phone_number-123

.some-registration-form
input-name</div>',
					],
				],

			],
		];

		return $settings;
	}

	/**
	 * Enqueue Styles and Scripts.
	 */
	function enqueue_scripts() {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return $this->enqueue_scripts_guest();
		}

		$exclude = array_filter( array_map( 'trim', explode( '\n', Options::get( 'tel_confirmation_exclude_url', '' ) ) ) );
		foreach ( $exclude as $pattern ) {
			if ( mb_strpos( $_SERVER['REQUEST_URI'], $pattern ) !== false ) {
				// Skip this page.
				return;
			}
		}

		$this->do_enqueue_script( 'tel_confirmation', [ 'tfa_sms' ], true );

		$verified_number = SmsModule::instance()->get_confirmed_tel_number( $user_id );

		wp_localize_script( 'tfa_tel_confirmation', 'tfa_tel_confirmation', [
			'verified_number'  => $verified_number,
			'input_selector'   => Options::get( 'tel_confirmation_input_selector' ),
			'add_button'       => Options::get_bool( 'tel_confirmation_add_button' ),
			'require'          => Options::get_bool( 'tel_confirmation_require' ),
			'verified_caption' => Options::get( 'tel_confirmation_verified_caption' ) ?? tf_auth__( 'Verified' ),
			'verify_caption'   => Options::get( 'tel_confirmation_verify_caption' ) ?? tf_auth__( 'Verify' ),
		] );
	}

	function enqueue_scripts_guest() {
		$selectors = Options::get( 'tel_confirmation_reg_forms' ) . '

#registerform
_tfa_tel';

		$this->do_enqueue_script( 'reg_tel_confirmation', [ 'tfa_sms' ], true, [
			'tfa_reg_tel_confirmation' => [
				'selectors' => $selectors,
			],
		] );
	}

	function get_popup_template() {
		return $this->get_default_popup_template();
	}

	function get_default_popup_template() {
		return '<div class="tfa-popup-title">Verify your phone number</div>
<form method="post" action="{{{form_action}}}" class="tfa-confirm-sms">
<div class="form-row">
<label>Phone number:</label>
<input type="tel" name="tel" value="{{phone_number}}" />
</div>
<div class="form-row tfa-code-input-row">
<label>SMS Code</label>
<input type="text" name="code" class="tfa-code-input" />
</div>
<div class="tfa-popup-footer">
<button type="button" class="button tfa-popup-close">Cancel</button>
<button type="button" class="button tfa-send-sms-button">Send SMS</button>
<button type="submit" class="button">Verify</button>
</div>
{{{hidden_fields}}}
</form>';
	}
}

