<?php

namespace TrueFactor\Module;

use TrueFactor\AbstractVerificationHandlerModule;
use TrueFactor\Admin\RuleList;
use TrueFactor\Exception\Required2FAException;
use TrueFactor\Helper\Arr;
use TrueFactor\Helper\Filter\ValueInvalidException;
use TrueFactor\Helper\Form;
use TrueFactor\Helper\Html;
use TrueFactor\Modules;
use TrueFactor\OptionalModule;
use TrueFactor\Options;
use TrueFactor\Orm\AccessRule;
use TrueFactor\Token;
use TrueFactor\View;

class AccessRulesModule extends OptionalModule {

	const TOKEN_KEY = 'tfa_token';

	/**
	 * Class constructor
	 */
	function activate() {
		add_action( 'init', [ $this, 'check_request' ] );
		add_action( 'init', [ $this, 'action__init' ] );
	}

	function action__init() {
		add_action( 'login_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		add_filter( 'do_shortcode_tag', [ $this, 'do_shortcode' ], 10, 4 );

		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
			add_action( 'admin_menu', [ $this, 'create_menu' ] );
		} else {
			add_shortcode( 'true-factor-auth-verification', [ $this, 'shortcode_verification' ] );
		}

		$this->add_ajax_action( 'confirm' );
		$this->add_ajax_action( 'popup' );
	}

	function get_module_desc() {
		return tf_auth__( 'This module allows you to protect any custom page or feature on your site with password or other verification methods (e.g SMS one-time password or Authenticator).' );
	}

	/**
	 * Build admin menu.
	 */
	function create_menu() {
		$hook_rules = add_submenu_page(
			'true-factor-auth',
			'Access Rules',
			'Access Rules',
			'manage_options',
			RuleList::$screen_name,
			[ $this, 'action_rules' ],
			1
		);
		add_action( "load-{$hook_rules}", [ $this, 'load_rules' ] );

		$hook_add_rule = add_submenu_page(
			'',
			tf_auth__( 'Edit Rule' ),
			tf_auth__( 'Edit Rule' ),
			'manage_options',
			'tfa-action-edit',
			[ $this, 'action_edit_rule' ]
		);
		add_action( "load-{$hook_add_rule}", [ $this, 'load_edit_rule' ] );
	}

	static function get_user_settings( $user_id ) {
		return get_user_meta( $user_id, '_tfa_settings', true );
	}

	static function is_user_settings_allowed() {
		if ( ! Options::get_bool( 'allow_user_settings' ) ) {
			return false;
		}

		return (bool) AccessRule::count( [
			'where' => [
				'status'      => AccessRule::STATUS_ACTIVE,
				'is_editable' => 1,
			],
		] );
	}

	static function set_user_settings( $user_id, $settings ) {
		return update_user_meta( $user_id, '_tfa_settings', $settings );
	}

	/**
	 * Check whether current request should be confirmed with 2FA. If it does, check the token.
	 */
	function check_request() {
		if ( defined( 'TRUE_FACTOR_DISABLE' ) ) {
			return;
		}

		$user = wp_get_current_user();

		if ( $user->ID ) {
			$actions = AccessRule::all( [
				'where' => [
					'status' => AccessRule::STATUS_ACTIVE,
				],
			] );
		} else {
			$actions = AccessRule::all( [
				'where' => [
					'status'      => AccessRule::STATUS_ACTIVE,
					'is_required' => 1,
				],
			] );
		}

		foreach ( $actions as $action ) {
			if ( $action->is_applicable() ) {

				if ( ! $user->ID ) {
					return $this->send_login_required();
				}

				try {
					/** @var AbstractVerificationHandlerModule $handler */
					$handler = $action->get_handler_for_user( $user->ID );
				} catch ( Required2FAException $e ) {
					$this->send_verification_page( $action );
				}

				if ( ! $handler || $handler->get_handler_id() == 'non' ) {
					continue;
				}

				if ( session_status() != PHP_SESSION_ACTIVE ) {
					session_start();
				}

				if ( $action->request_method == AccessRule::REQUEST_TYPE_POST ) {
					$token = $_POST[ self::TOKEN_KEY . $action->id ] ?? null;
				} else {
					$token = $_COOKIE[ self::TOKEN_KEY . $action->id ] ?? null;
				}

				if ( $token ) {
					try {
						Token::check( $token, $action->id );

						return;
					} catch ( \Exception $e ) {
						View::addNotice( $e->getMessage() );
					}
				}

				if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
					// If POST form was submitted, we just show error message so that user can return to previous step and re-submit form.
					$template = $action->get_popup_template( 'check_failed' );
					wp_die( $template );
				}

				$this->send_verification_page( $action, $handler );
			}
		}
	}

	// Short-codes.

	/**
	 * Wraps "guarded" short-codes with special wrapper to be able to find them with JavaScript.
	 *
	 * @param $output
	 * @param $name
	 *
	 * @return string
	 */
	function do_shortcode( $output, $name ) {
		$action = AccessRule::one( [
			'where' => [
				'shortcode' => $name,
				'status'    => AccessRule::STATUS_ACTIVE,
			],
		] );

		if ( $action ) {
			$output = '<div class="tfa-shortcode-action" data-action-id="' . $action->id . '">' . $output . '</div>';
		}

		return $output;
	}

	function shortcode_verification() {
		if ( empty( $_GET['true_action_id'] ) || ! filter_var( $_GET['true_action_id'], FILTER_VALIDATE_INT ) ) {
			return;
		}
		$action_id = (int) $_GET['true_action_id'];

		$user = wp_get_current_user();
		if ( ! $user ) {
			return;
		}

		$action = AccessRule::oneById( $action_id );

		try {
			$handler = $action->get_handler_for_user( $user->ID );

			if ( ! $handler || $handler->get_handler_id() == 'non' ) {
				return;
			}

			$values = [
				'intro'         => $action->config['verification_intro'] ?? null,
				'action_title'  => $action->title,
				'form_action'   => esc_attr( admin_url( 'admin-ajax.php?action=tfa_confirm' ) ),
				'hidden_fields' => wp_nonce_field( 'tfa_confirm_' . $action->id, '_wpnonce', false, false )
				                   . Html::hidden( 'action_id', $action->id )
				                   . Html::hidden( 'goto_url', $_SERVER['REQUEST_URI'] ),
			];

			$popup = $handler->get_verify_popup( $user, $values );

			View::render( 'verification-page', [
				'popup' => $popup,
			] );
		} catch ( \Exception $e ) {
			return;
		}
	}

	// Actions.

	/** @var RuleList */
	protected $rule_list;

	/**
	 * Load User Actions table.
	 */
	function load_rules() {
		$option = 'per_page';
		$args   = [
			'label'   => 'Number of items per page:',
			'default' => 15,
			'option'  => 'user_actions_per_page',
		];
		add_screen_option( $option, $args );
		$this->rule_list = new RuleList();
		$this->rule_list->prepare_items();
	}

	/**
	 * Display Rules table.
	 */
	function action_rules() {
		View::render( 'admin/rule-list', [
			'table' => $this->rule_list,
		] );
	}

	protected $rule_edit_form;

	/**
	 * Load and process Action add/edit form.
	 */
	function load_edit_rule() {
		if ( ! empty( $_REQUEST['id'] ) && filter_var( $_REQUEST['id'], FILTER_VALIDATE_INT ) ) {
			$action = AccessRule::oneById( (int) $_REQUEST['id'] );
		} else {
			$action = null;
		}

		$custom_tpl_enable = Modules::is_enabled( 'CustomTpl' );

		$this->rule_edit_form = new Form();
		$this->rule_edit_form->setMethod( 'post' );
		$fields = [
			'title'           => [
				'maxlength' => 100,
				'_hint'     => 'Provide human-friendly name of this action. It will be used in templates as <kbd>{{action_name}}</kbd> variable.',
			],
			'status'          => [
				'type'    => 'select',
				'options' => AccessRule::$statuses,
			],
			'is_required'     => [
				'type'  => 'checkbox',
				'label' => 'Require Verification',
				'_hint' => 'When this option is enabled, user won\'t be able to access without verification. Therefore, only registered users will be able to access it.',
			],

			// Backend options
			'request_method'  => [
				'type'    => 'select',
				'options' => AccessRule::$request_methods,
				'_group'  => 'backend',
			],
			'request_url'     => [
				'placeholder' => "/some/url",
				'_hint'       => tf_auth__( 'Provide a part of url. You can use regular expression with ~ delimiter, e.g <kbd>~some\-url/\w~</kbd>' ),
				'_group'      => 'backend',
			],
			'request_params'  => [
				'type'        => 'textarea',
				'placeholder' => "param1=1\nparam2=~.+~",
				'_group'      => 'backend',
				'_hint'       => '<p>'
				                 . tf_auth__( 'Pairs of parameter names and values, e.g <kbd>action=delete</kbd>. Add one parameter per line. For values you can use regular expressions with ~ delimiter, e.g <kbd>param3=~[a-z]+~</kbd>' )
				                 . '</p>',
			],

			// Frontend options
			'button_selector' => [
				'label'     => 'Trigger selector',
				'maxlength' => 100,
				'_group'    => 'frontend',
				'_hint'     => tf_auth__( '<p>Provide a valid CSS selector of HTML element that triggers this action.</p>
<p>If provided selector returns a form, verification popup will appear on submit event. Otherwise, popup will appear on click event.</p>' ),
			],
		];

		$defaults = [];

		$handlers        = VerificationModule::instance()->get_handlers();
		$handlers_fields = [];
		$handlers_rules  = [];
		foreach ( $handlers as $handler_id => $handler ) {
			$handlers_fields["handler__{$handler_id}__on"] = [
				'type'   => 'checkbox',
				'value'  => 1,
				'label'  => sprintf( tf_auth__( 'Allow %s' ), $handler->get_handler_name() ),
				'_group' => 'handlers',
			];

			// Add validation rules.
			$handlers_rules["handler__{$handler_id}__on"] = [ 'bool' ];

			// Enable all handlers by default.
			$defaults["handler__{$handler_id}__on"] = 1;
		}

		$fields = array_replace( $fields, $handlers_fields );
		$this->rule_edit_form->setFields( $fields );
		$this->rule_edit_form->setSafeData( $defaults );

		if ( ! empty( $action ) ) {
			$data = $action->getData();
			$this->rule_edit_form->setSafeData( $data );
			foreach ( $handlers_fields as $field => $field_def ) {
				$this->rule_edit_form->set( $field, Arr::getByPath( $action->config, explode( '__', $field ) ) );
			}
			foreach ( [ 'popup_intro', 'verification_required_tpl', 'pre_callback' ] as $field ) {
				$this->rule_edit_form->set( $field, $action->config[ $field ] ?? '' );
			}
		}

		if ( $_POST ) {
			try {
				$input = stripslashes_deep( $_POST );

				$rules = array_replace( [
					'title'                     => [
						'required',
						[ 'max_length', 100 ],
					],
					'status'                    => [ [ 'is_array_key', AccessRule::$statuses ], ],
					'request_method'            => [ [ 'is_array_key', AccessRule::$request_methods ], ],
					'request_url'               => [],
					'request_params'            => [],
					'button_selector'           => [ [ 'max_length', 100 ] ],
					'shortcode'                 => [ [ 'max_length', 100 ] ],
					'is_required'               => [ 'bool', ],
					'is_editable'               => [ 'bool', ],
					'pre_callback'              => [],
					'popup_intro'               => [],
					'verification_required_tpl' => [],
				], $handlers_rules );

				$this->rule_edit_form->setRules( $rules );
				$this->rule_edit_form->setInputData( $input );
				$data = $this->rule_edit_form->getData();

				if ( empty( $data['button_selector'] ) && empty( 'shortcode' ) ) {
					throw new ValueInvalidException( 'Please provide either Button Selector or Shortcode' );
				}

				if ( empty( $data['request_url'] ) && empty( $data['request_params'] ) ) {
					throw new ValueInvalidException( 'Please provide either Request URL or at least one Request Parameter' );
				}

				if ( ! wp_verify_nonce( $input['tfa_rule_edit'], 'tfa_rule_edit' ) ) {
					throw new ValueInvalidException( 'Nonce invalid' );
				}

				if ( empty( $action ) ) {
					$action = new AccessRule();
				}

				$action->setData( $data );
				foreach ( $handlers_fields as $field => $field_def ) {
					Arr::setByPath( $action->config, explode( '__', $field ), $this->rule_edit_form->get( $field ) );
				}
				foreach ( [ 'popup_intro', 'pre_callback', 'verification_required_tpl' ] as $field ) {
					$action->config[ $field ] = $data[ $field ] ?? null;
				}
				$action->save();
				View::addNotice( 'Saved' );

				$redirect = menu_page_url( RuleList::$screen_name, false );

				if ( wp_doing_ajax() ) {
					View::ajaxRedirect( $redirect );
					exit;
				}

				wp_safe_redirect( $redirect );
				exit;
			} catch ( \Exception $e ) {
				View::addNotice( $e->getMessage() );
			}
		}
	}

	/**
	 * Display user action addition form.
	 */
	function action_edit_rule() {
		View::render( 'admin/rule-edit', [
			'form'         => $this->rule_edit_form,
			'nonce_action' => 'tfa_rule_edit',
		] );
	}

	function action_popup() {
		if ( empty( $_REQUEST['id'] ) || ! filter_var( $_REQUEST['id'], FILTER_VALIDATE_INT ) ) {
			$this->return_error( "Unknown action" );
		}

		$action = AccessRule::oneById( $_REQUEST['id'] );
		if ( ! $action ) {
			$this->return_error( "Unknown action" );
		}

		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );
		try {
			$handler = $action->get_handler_for_user( $user_id );
			if ( $handler->get_handler_id() == 'non' ) {
				wp_send_json( [
					'skip' => 1,
				] );
				exit;
			}

			if ( ! empty( $action->config['verification_intro'] ) ) {
				$intro_tpl = $action->config['verification_intro'];
			} else {
				$intro_tpl = apply_filters( 'tfa_custom_tpl', VerificationModule::get_default_verify_popup_intro_tpl(), 'verify_popup_intro_tpl' );
			}

			$intro   = View::mustache_render( tf_auth__( $intro_tpl ), [
				'action_title' => $action->title,
			] );
			$values  = [
				'intro'         => $intro,
				'action_title'  => $action->title,
				'form_action'   => esc_attr( admin_url( 'admin-ajax.php?action=tfa_confirm' ) ),
				'hidden_fields' => wp_nonce_field( 'tfa_confirm_' . $action->id, '_wpnonce', false, false )
				                   . Html::hidden( 'action_id', $action->id ),
			];
			$content = $handler->get_verify_popup( $user, $values );
			wp_send_json( [
				'content' => $content,
			] );
			exit;
		} catch ( Required2FAException $e ) {
			return $this->send_2fa_required( $action );
		} catch ( \Exception $e ) {
			return $this->return_error( $e->getMessage() );
		}
	}

	/**
	 * Confirm action with 2FA.
	 */
	function action_confirm() {
		if ( empty( $_REQUEST['action_id'] ) || ! filter_var( $_REQUEST['action_id'], FILTER_VALIDATE_INT ) ) {
			$this->return_error( "Unknown action" );
		}

		$action = AccessRule::oneById( $_REQUEST['action_id'] );
		if ( ! $action ) {
			$this->return_error( "Unknown action" );
		}

		$this->login_required();
		$user = wp_get_current_user();

		try {
			$handler = $action->get_handler_for_user( $user->ID );
		} catch ( \Exception $e ) {
			return $this->return_error( $e->getMessage() );
		}

		try {
			$handler->verify( $_POST, $user, 'action' );
			$this->send_verification_success( $action );
		} catch ( \Exception $e ) {
			do_action( 'tfa_verification_failed', $user, $action );

			if ( View::isAjaxRequest() ) {
				return $this->return_error( $e->getMessage() );
			} else {
				View::addNotice( $e->getMessage(), 'error' );
			}

			return $this->send_verification_page( $action, $handler );
		}
	}

	// Responses.

	protected function send_login_required() {
		if ( View::isAjaxRequest() ) {
			return $this->return_error( 'Please log in' );
		}

		auth_redirect();
	}

	protected function send_verification_page( AccessRule $rule, AbstractVerificationHandlerModule $handler = null, $redirect = null ) {
		$user = wp_get_current_user();

		if ( ! $redirect ) {
			if ( ! empty( $_POST['tfa_redirect'] ) ) {
				$redirect = wp_validate_redirect( $_POST['tfa_redirect'], $_SERVER['REQUEST_URI'] );
			} else {
				$redirect = $_SERVER['REQUEST_URI'];
			}
		}

		if ( $handler ) {

			$values = [
				'intro'         => $rule->config['popup_intro'] ?? null,
				'action_title'  => $rule->title,
				'form_action'   => esc_attr( admin_url( 'admin-ajax.php?action=tfa_confirm' ) ),
				'hidden_fields' => wp_nonce_field( 'tfa_confirm_' . $rule->id, '_wpnonce', false, false )
				                   . Html::hidden( 'action_id', $rule->id )
				                   . Html::hidden( 'tfa_redirect', $redirect ),
			];

			$popup = $handler->get_verify_popup( $user, $values );
		} else {
			$popup = $rule->get_verification_required_popup();
		}

		View::render( 'verification-page', [
			'popup' => $popup,
			'modal' => true,
		] );
		exit;
	}

	/**
	 * Returns a token after successful 2FA confirmation.
	 *
	 * @param $action
	 */
	protected function send_verification_success( AccessRule $action ) {
		$token = Token::add( $action->id, $action->config['expires'] ?? null );

		if ( View::isAjaxRequest() ) {
			$response = [ 'token' => $token ];
			$popup    = $action->config['tpl']['ok'] ?? null;
			if ( $popup ) {
				$response['popup'] = View::mustache_render( $popup, [
					'action_title' => $action->title,
				] );
			}
			wp_send_json( $response );
		} else {
			setcookie(
				self::TOKEN_KEY . $action->id,
				$token,
				time() + ( $action->config['expires'] ?? Token::DEFAULT_EXPIRATION ),
				'/'
			);

			if ( ! empty( $action->config['success_url'] ) ) {
				$redirect = wp_validate_redirect( $action->config['success_url'] );
			}

			if ( empty( $redirect )
			     && isset( $_POST['tfa_redirect'] )
			     && is_string( $_POST['tfa_redirect'] )
			) {
				$redirect = wp_validate_redirect( $_POST['tfa_redirect'] );
			}

			// Fallback to the referrer.
			if ( empty( $redirect ) ) {
				$redirect = wp_get_referer();
			}

			wp_safe_redirect( $redirect ?: '/' );
			exit;
		}
	}

	/**
	 * This popup should be displayed when verification is required for an action, but user did not configure any verification methods.
	 *
	 * @param  AccessRule  $action
	 */
	protected function send_2fa_required( AccessRule $action ) {
		wp_send_json( [
			'content' => $action->get_verification_required_popup(),
		] );
	}

	/**
	 * Enqueue Styles and Scripts.
	 *
	 * @param $hook
	 */
	function enqueue_scripts( $hook ) {
		$user_id = get_current_user_id();

		$actions = AccessRule::all( [
			'where' => [
				'status'             => AccessRule::STATUS_ACTIVE,
				'button_selector !=' => '',
				'shortcode'          => '',
			],
		] );

		$restricted_actions = [];
		foreach ( $actions as $action ) {
			try {
				if ( ! $action->get_handler_for_user( $user_id ) ) {
					// If user disabled 2FA for this action, skip it.
					continue;
				}
			} catch ( \Exception $e ) {
				// Pass.
			}
			$restricted_actions[] = [
				'id'              => $action->id,
				'button_selector' => $action->button_selector,
				'pre_callback'    => $action->config['pre_callback'] ?? null,
			];
		}

		$data = [
			'actions'   => $restricted_actions,
			'token_key' => self::TOKEN_KEY,
		];

		$this->do_enqueue_script( 'rules', [ 'tfa_main' ], true, [
			'tfa_rules' => $data,
		] );
	}
}

