<?php

namespace TrueFactor\Module;


use TrueFactor\AbstractVerificationHandlerModule;
use TrueFactor\Helper\GoogleAuth;
use TrueFactor\Helper\Html;
use TrueFactor\Options;
use TrueFactor\View;

class GauVerificationHandlerModule extends AbstractVerificationHandlerModule {

	/**
	 * Class constructor
	 */
	protected function activate() {
		add_action( 'init', [ $this, 'action__init' ] );

		VerificationModule::instance()->add_handler( $this );
	}

	function action__init() {
		if ( ! get_current_user_id() ) {
			return;
		}

		$this->add_ajax_action( 'gau_activate' );
		$this->add_ajax_action( 'gau_deactivate' );

		add_action( 'login_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		if ( is_admin() ) {
			add_filter( 'tfa_admin_page_settings_' . VerificationModule::ADMIN_SETTINGS_PAGE, [ $this, 'get_admin_settings' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		}
	}

	// Module definition.

	function is_switchable() {
		return true;
	}

	function get_module_name() {
		return tf_auth__( 'Google Authenticator Verification' );
	}

	function get_module_desc() {
		return tf_auth__( 'Adds Google Authenticator as second factor verification method.' );
	}

	function get_module_group() {
		return 'Verification Method';
	}

	function get_position() {
		return - 9;
	}

	// Verification Handler methods.

	function get_handler_id() {
		return 'gau';
	}

	function get_handler_name() {
		return 'Authenticator';
	}

	function is_configured_for_user( $user_id ) {
		return ! ! get_user_meta( $user_id, self::SECRET_META_KEY, true );
	}

	function verify( $input, \WP_User $user = null, $mode = null ) {
		if ( ! isset( $input['code'] )
		     || ! is_string( $input['code'] )
		     || ! preg_match( '/^[\d]{6}$/', $input['code'] )
		) {
			throw new \Exception( 'Invalid verification code' );
		}

		if ( ! $user ) {
			throw new \Exception( 'Invalid user object' );
		}

		if ( defined( 'TRUE_FACTOR_GAU_CODE_PASS' ) && TRUE_FACTOR_GAU_CODE_PASS === $input['code'] ) {
			return true;
		}

		$secret = get_user_meta( $user->ID, self::SECRET_META_KEY, true );
		if ( ! $secret ) {
			throw new \Exception( 'User did not configure security settings' );
		}

		$ga = new GoogleAuth();
		if ( ! $ga->verify_code( $input['code'], $secret ) ) {
			throw new \Exception( tf_auth__( Options::get( 't_otp_incorrect_gau' ) ?: Options::get( 't_otp_incorrect' ) ?: 'Invalid code' ) );
		}

		return true;
	}

	function get_activate_popup( \WP_User $user ) {
		$tpl = apply_filters( 'tfa_custom_tpl', $this->get_default_activate_popup_template(), 'gau_activate_tpl' );

		$ga     = new GoogleAuth();
		$secret = $ga->create_secret();
		$qr_url = $ga->get_url( $secret, Options::get( 'gau_app_name', 'True Factor' ), $user->user_email );
		update_user_meta( $user->ID, '_tfa_gau_secret', $secret );

		$data['secret']         = $secret;
		$data['qr_url']         = $qr_url;
		$data['qr_url_encoded'] = urlencode( $qr_url );
		$data['form_action']    = esc_attr( admin_url( 'admin-ajax.php?action=tfa_gau_activate' ) );
		$data['hidden_fields']  = Html::hidden( 'tfa_enable_gau', 1 )
		                          . wp_nonce_field( 'tfa_enable_gau', '_wpnonce', false, false );

		return View::mustache_render( $tpl, $data );
	}

	function get_deactivate_popup( \WP_User $user ) {
		$tpl = apply_filters( 'tfa_custom_tpl', $this->get_default_deactivate_popup_template(), 'gau_deactivate_tpl' );

		$data = [
			'form_action'   => esc_attr( admin_url( 'admin-ajax.php?action=tfa_gau_deactivate' ) ),
			'hidden_fields' => wp_nonce_field( 'tfa_disable_gau', '_wpnonce', false, false )
			                   . Html::hidden( 'tfa_disable_gau', 1 ),
		];

		return View::mustache_render( $tpl, $data );
	}

	function get_verify_popup( \WP_User $user, $data = [] ) {
		$tpl = apply_filters( 'tfa_custom_tpl', $this->get_default_verify_popup_template(), 'gau_verify_tpl' );

		return View::mustache_render( $tpl, $data );
	}

	// Endpoints.

	/**
	 * Triggered when user submits activation form.
	 */
	function action_gau_activate() {
		if ( empty( $_POST['tfa_enable_gau'] ) ) {
			return;
		}

		$ga      = new GoogleAuth();
		$user_id = get_current_user_id();
		$secret  = $this->get_user_secret( $user_id );

		try {
			if ( ! $ga->verify_code( $_POST['code'], $secret ) ) {
				return $this->return_error( 'Invalid Authenticator code' );
			}

			update_user_meta( $user_id, '_tfa_enabled_gau', 1 );

			do_action( 'tfa_verification_handler_activated', $this, $user_id );

			if ( wp_is_json_request() ) {
				$response = [
					'success' => true,
					'popup'   => apply_filters( 'tfa_custom_tpl', $this->get_default_activated_popup_template(), 'gau_activated_tpl' ),
				];

				wp_send_json( $response );
			} else {
				wp_safe_redirect( wp_get_referer() ?: '/' );
				exit();
			}
		} catch ( \Exception $e ) {
			$this->return_error( $e->getMessage() );
		}
	}

	/**
	 * Triggered when user submits deactivation form.
	 */
	function action_gau_deactivate() {
		if ( empty( $_POST['tfa_disable_gau'] ) ) {
			$this->return_error( 'Invalid request' );
		}

		$user_id = get_current_user_id();

		update_user_meta( $user_id, '_tfa_enabled_gau', 0 );
		delete_user_meta( $user_id, self::SECRET_META_KEY );

		if ( wp_is_json_request() ) {
			wp_send_json_success();
		} else {
			wp_safe_redirect( wp_get_referer() ?: '/' );
			exit();
		}
	}

	// Default popup templates

	function get_default_verify_popup_template() {
		return '<form action="{{form_action}}" method="post" class="tfa-confirm tfa-confirm-{{type_id}}">
<div class="tfa-popup-title">Enter your Authenticator Code <button type="button" class="tfa-popup-close">&times;</button></div>
<div class="tfa-popup-text">{{{intro}}}</div>
<div class="tfa-code-input-row text-center">
<label>Authenticator Code</label>
<input type="text" name="code" required pattern="[0-9]{6}" class="tfa-code-input tfa-focus" />
</div>
<div class="form-row form-buttons"><button type="submit" class="button">Verify</button></div>
{{{hidden_fields}}}
</form>';
	}

	function get_default_activate_popup_template() {
		return '<form method="post" action="{{{form_action}}}" class="tfa-enable-gau tfa-json-form">
<div class="tfa-popup-title">Enable Authenticator
<button type="button" class="tfa-popup-close">&times;</button></div>
<div>
<div class="tfa-popup-caption">1. Scan the QR code with the Authenticator App:</div>
<div class="form-row">
<a href="{{{qr_url}}}" class="tfa-gau-qr js-qrcode" data-qr="{{{qr_url}}}"></a>
</div>
<div class="form-row text-center">
or add this key manually:
<input class="tfa-gau-manual text-center" value="{{secret}}" readonly /></div>
</div>
<div class="tfa-popup-caption">2. Enter the 6-digits code from Authenticator</div>
<div class="tfa-code-input-row text-center">
<input type="text" name="code" class="tfa-code-input tfa-focus" required pattern="[0-9]{6}">
</div>
<div class="form-row form-buttons"><button type="submit" class="button">Confirm</button></div>
{{{hidden_fields}}}
</form>';
	}

	function get_default_deactivate_popup_template() {
		return '<form method="post" action="{{form_action}}" class="tfa-json-form tfa-json-form-reload-on-success">
<div class="tfa-popup-title">Disable 2FA with Google Authenticator
<button type="button" class="tfa-popup-close">&times;</button></div>
<div class="form-row">Are you sure you want to disable 2-factor authentication with Google Authenticator?</div>
{{{hidden_fields}}}
<div class="form-row form-buttons"><button type="submit" class="button">Disable</div>
</form>';
	}

	function get_default_activated_popup_template() {
		return '<div class="form-row">2FA with Authenticator had been activated</div>
<div class="tfa-popup-footer"><button type="button" onclick="location.reload()">Ok</button></div>';
	}

	const SECRET_META_KEY = '_tfa_gau_secret';

	function get_user_secret( $user_id ) {
		return get_user_meta( $user_id, '_tfa_gau_secret', true );
	}

	// Admin settings.

	function get_admin_settings( $settings ) {
		$settings['tfa_settings_gau'] = [
			'title'  => 'Google Authenticator Settings',
			'fields' => [
				'gau_app_name' => [
					'label'   => 'Application Name',
					'_hint'   => 'Will be displayed in the Google Authenticator app.',
					'default' => 'True Factor',
				],
			],
		];

		return $settings;
	}

	function enqueue_scripts() {
		$this->do_enqueue_script( 'jquery-qrcode', [ 'jquery' ] );
	}
}

