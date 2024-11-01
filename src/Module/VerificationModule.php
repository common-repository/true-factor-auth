<?php

namespace TrueFactor\Module;

use TrueFactor\AbstractGuestVerificationHandlerModule;
use TrueFactor\AbstractModule;
use TrueFactor\AbstractVerificationHandlerModule;
use TrueFactor\Helper\Html;
use TrueFactor\Modules;
use TrueFactor\Options;
use TrueFactor\PhoneNumber;
use TrueFactor\View;

class VerificationModule extends AbstractModule {

	const META_KEY_DISABLE_VERIFICATION = 'tfa_disable_verification';

	const ADMIN_SETTINGS_PAGE_TITLE = 'Verification';
	const ADMIN_SETTINGS_PAGE = 'tfa_verification_handlers';

	/** @var AbstractVerificationHandlerModule[] */
	protected $handlers = [];

	/** @var AbstractGuestVerificationHandlerModule[] */
	protected $guest_handlers = [];

	protected function __construct() {
		parent::__construct();

		$this->add_ajax_action( 'enable_handler' );

		add_action( 'login_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		if ( is_admin() ) {
			add_filter( 'tfa_admin_pages', [ $this, 'get_admin_pages' ] );
			add_filter( 'tfa_admin_page_settings_' . self::ADMIN_SETTINGS_PAGE, [ $this, 'get_admin_settings' ] );

			add_action( 'edit_user_profile_update', [ $this, 'action__save_extra_user_profile_fields' ] );
			add_action( 'personal_options_update', [ $this, 'action__save_extra_user_profile_fields' ] );

			add_action( 'show_user_profile', [ $this, 'action__extra_user_profile_fields' ] );
			add_action( 'edit_user_profile', [ $this, 'action__extra_user_profile_fields' ] );
		} else {
			add_shortcode( 'true-factor-auth-methods', [ $this, 'shortcode_verification_methods' ] );
		}
	}

	/**
	 * @param $user_id
	 *
	 * @return AbstractVerificationHandlerModule[] List of active verification types for given user.
	 */
	public function get_user_handlers( $user_id ) {
		$handlers = [];
		foreach ( $this->handlers as $id => $handler ) {
			if ( ! $handler->is_switchable() || get_user_meta( $user_id, '_tfa_enabled_' . $id, true ) ) {
				if ( $handler->is_configured_for_user( $user_id ) ) {
					$handlers[ $id ] = $handler;
				}
			}
		}

		return $handlers;
	}

	function add_handler( AbstractVerificationHandlerModule $module ) {
		$this->handlers[ $module->get_handler_id() ] = $module;
		$this->sort_handlers();
	}

	function add_guest_handler( AbstractGuestVerificationHandlerModule $module ) {
		$this->guest_handlers[ $module->get_handler_id() ] = $module;
		$this->sort_guest_handlers();
	}

	protected function sort_handlers() {
		uasort( $this->handlers, function ( AbstractVerificationHandlerModule $a, AbstractVerificationHandlerModule $b ) {
			if ( $a->get_position() < $b->get_position() ) {
				return - 1;
			}

			return (int) ( $a->get_position() > $b->get_position() );
		} );
	}

	protected function sort_guest_handlers() {
		uasort( $this->guest_handlers, function ( AbstractGuestVerificationHandlerModule $a, AbstractGuestVerificationHandlerModule $b ) {
			if ( $a->get_position() < $b->get_position() ) {
				return - 1;
			}

			return (int) ( $a->get_position() > $b->get_position() );
		} );
	}

	/**
	 * @param $id
	 *
	 * @return AbstractVerificationHandlerModule
	 * @throws \Exception
	 */
	function get_handler( $id ) {
		if ( empty( $this->handlers[ $id ] ) ) {
			throw new \Exception( 'Verification handler not initialized' );
		}

		return $this->handlers[ $id ];
	}

	/**
	 * @return AbstractVerificationHandlerModule[]
	 */
	function get_handlers() {
		return $this->handlers;
	}

	public function shortcode_verification_methods() {
		if ( ! is_user_logged_in() ) {
			return 'Please log in';
		}

		$user_id = get_current_user_id();

		return View::returnRender( 'shortcode/verification-methods', [
			'user_id'       => $user_id,
			'handlers'      => VerificationModule::instance()->get_handlers(),
			'user_handlers' => VerificationModule::instance()->get_user_handlers( $user_id ),
		] );
	}

	/**
	 * Activate/deactivate verification handler.
	 *
	 * This module only displays the activation popup.
	 * Activation endpoint for each handler must be implemented in corresponding handler module.
	 */
	public function action_enable_handler() {
		$user_id = $this->login_required();

		$user = get_userdata( $user_id );
		$off  = ( ! empty( $_GET['mode'] ) && ( $_GET['mode'] == 'off' ) );

		if ( empty( $_GET['handler_id'] ) || ! preg_match( '/^[\w\d]+$/', $_GET['handler_id'] ) ) {
			$this->return_error( 'Invalid handler ID' );

			return;
		}

		try {
			$handler       = $this->get_handler( $_GET['handler_id'] );
			$user_handlers = $this->get_user_handlers( $user_id );
			$is_enabled    = ! empty( $user_handlers[ $handler->get_handler_id() ] );
		} catch ( \Exception $e ) {
			$this->return_error( $e->getMessage() );

			return;
		}

		if ( $off ) {
			if ( ! $is_enabled ) {
				$this->return_error( tf_auth__( 'Already deactivated' ) );
			}
			$popup = $handler->get_deactivate_popup( $user );
		} else {
			if ( $is_enabled ) {
				$this->return_error( tf_auth__( 'Already activated' ) );
			}
			$popup = $handler->get_activate_popup( $user );
		}

		wp_send_json( [
			'content' => $popup,
		] );
	}

	// Filters and actions.

	function action__save_extra_user_profile_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}
		if ( array_key_exists( self::META_KEY_DISABLE_VERIFICATION, $_POST ) ) {
			update_user_meta( $user_id, self::META_KEY_DISABLE_VERIFICATION, $_POST[ self::META_KEY_DISABLE_VERIFICATION ] ? 1 : 0 );
		}
		if ( Options::get_bool( 'add_tel_field_on_default_profile' ) ) {
			SmsModule::instance()->set_tel_number( $user_id, PhoneNumber::normalize( $_POST[ SmsModule::get_tel_number_meta_key() ] ) );
		}
	}

	function action__extra_user_profile_fields( \WP_User $user ) { ?>
        <h3><?php echo tf_auth__( "True Factor Auth" ); ?></h3>

        <table class="form-table">
            <tr>
                <th><label><?php echo tf_auth__( "Bypass Verification" ); ?></label></th>
                <td>
					<?php echo Html::hidden( self::META_KEY_DISABLE_VERIFICATION, 0 ) ?>
					<?php echo Html::checkbox( self::META_KEY_DISABLE_VERIFICATION, get_user_meta( $user->ID, self::META_KEY_DISABLE_VERIFICATION, true ) ) ?>
                    <div class="description">
                        <p><?php echo tf_auth__( 'Temporarily disable verification for this user. May be useful if user can not pass verification due some reason (e.g if he had lost his phone).' ) ?></p>
                    </div>
                </td>
            </tr>
			<?php if ( Options::get_bool( 'add_tel_field_on_default_profile' ) ) { ?>
                <tr>
                    <th><label><?php echo tf_auth__( "Phone Number" ); ?></label></th>
                    <td>
						<?php echo Html::input( SmsModule::get_tel_number_meta_key(), SmsModule::instance()->get_tel_number( $user->ID ) ) ?>
                    </td>
                </tr>
			<?php } ?>
        </table>
	<?php }

	// Admin settings.

	function get_admin_pages( $pages ) {
		$pages[ VerificationModule::ADMIN_SETTINGS_PAGE ] = [
			'title'    => tf_auth__( VerificationModule::ADMIN_SETTINGS_PAGE_TITLE ),
			'position' => 11,
		];

		return $pages;
	}

	function get_admin_settings( $settings ) {

		$settings['help'] = [
			'position' => - 2,
			'title'    => 'Verification Methods Short-code',
			'intro'    => '<p>Add the following short-code on your security settings page to let users configure their 2-factor verification methods:</p>
<p><kbd>[true-factor-auth-methods]</kbd></p>
<p>Make sure to show that page only to logged-in users.</p>',
		];

		$settings['general'] = [
			'position' => - 1,
			'fields'   => [
				/*
			'verification_page_id' => [
				'label' => 'Verification Page ID',
				'attrs' => [
					'type' => 'number',
					'min'  => 1,
				],
				'_hint'  => '<p>The numeric ID of the page to be displayed when user tries to access restricted content.</p>
<p>This page must contain the <kbd>[true-factor-auth-verification]</kbd> short-code, which will display the appropriate verification popup.</p>',
			],
				*/

				'verification_settings_url' => [
					'label' => 'Verification Settings Page URL',
					'_hint' => '<p>Relative URL to the page where user can change security settings.</p>
<p>When provided, link to this page will be displayed in the "Verification Required" popup.</p>
<p>This page must contain the <kbd>[true-factor-auth-methods]</kbd> short-code.</p>',
				],
			],
		];

		return $settings;
	}

	/**
	 * Enqueue Styles and Scripts.
	 *
	 * @param $hook
	 */
	function enqueue_scripts( $hook ) {
		wp_register_style(
			'tfa-styles',
			TRUE_FACTOR_CSS_URI . '/style.css',
			[],
			defined( 'TRUE_FACTOR_DEBUG_CSS' )
				? filemtime( TRUE_FACTOR_PLUGIN_DIR . '/assets/css/style.css' )
				: TRUE_FACTOR_PLUGIN_VERSION
		);
		wp_enqueue_style( 'tfa-styles' );

		$this->do_enqueue_script( 'notice', [ 'jquery' ], true );
		$this->do_enqueue_script( 'main', [ 'jquery' ], true, [
			'tfa_main' => [
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'notices'  => array_map( function ( $m ) {
					return [
						'msg' => $m['message'],
						'cls' => $m['class'],
					];
				}, View::getNotices() ),
			],
		] );
	}

	// Default templates.

	static function get_default_verify_popup_intro_tpl() {
		return 'Please confirm the following action: {{action_title}}';
	}
}

