<?php

namespace TrueFactor\Module;

use TrueFactor\AbstractVerificationHandlerModule;
use TrueFactor\Exception\ConfigurationException;
use TrueFactor\Exception\InvalidCredentialsException;
use TrueFactor\Exception\SmsBlockedException;
use TrueFactor\Helper\GetUserByLogPwdTrait;
use TrueFactor\Helper\Html;
use TrueFactor\Modules;
use TrueFactor\Options;
use TrueFactor\PhoneNumber;
use TrueFactor\View;

class SmsVerificationHandlerModule extends AbstractVerificationHandlerModule {

	use GetUserByLogPwdTrait;

	function is_switchable() {
		return true;
	}

	protected function activate() {
		VerificationModule::instance()->add_handler( $this );

		$this->add_ajax_action( 'sms_activate' );
		$this->add_ajax_action( 'sms_deactivate' );

		if ( is_admin() ) {
			add_filter( 'tfa_admin_page_settings_' . VerificationModule::ADMIN_SETTINGS_PAGE, [ $this, 'get_admin_settings' ] );
		}

		add_action( 'tfa_phone_number_confirmed', [ $this, 'action__phone_number_confirmed' ], 10, 2 );
	}

	function get_module_name() {
		return tf_auth__( 'SMS Verification' );
	}

	function get_dependencies() {
		return [ 'Sms' ];
	}

	function get_module_group() {
		return 'Verification Method';
	}

	function get_position() {
		return - 10;
	}

	function get_module_desc() {
		return tf_auth__( 'Adds SMS one-time passcode as second factor verification method.' );
	}

	// Actions and filters

	function action__phone_number_confirmed( $user_id, $number ) {
		if ( Options::get_bool( 'sms_verification_auto_activate' ) ) {
			update_user_meta( $user_id, '_tfa_enabled_sms', 1 );
		}
	}

	// Endpoints

	function action_sms_activate() {
		if ( empty( $_POST['tfa_enable_sms'] ) ) {
			return;
		}

		if ( empty( $_POST['tel'] ) ) {
			$this->return_error( 'Invalid request' );
		}

		$user_id = $this->login_required();

		try {
			$number = PhoneNumber::normalize( $_POST['tel'] );

			if ( empty( $_POST['code'] ) || ! is_string( $_POST['code'] ) ) {
				throw new \Exception( Options::get( 't_pls_enter_otp' ) ?: tf_auth__( 'Please enter OTP' ) );
			}

			$sms  = SmsModule::instance();
			$wait = SmsModule::get_user_retry_wait( $user_id );
			if ( $wait > 0 ) {
				return $sms->send_please_wait( $wait, 'Too many attempts. Please wait %s.' );
			}

			$sms->check_otp( $number, $_POST['code'] );
			$sms->set_tel_number( $user_id, $number );
			$sms->set_confirmed_tel_number( $user_id, $number );
			update_user_meta( $user_id, '_tfa_enabled_sms', 1 );

			SmsModule::clear_user_attempts( $user_id );
			$response = [
				'success' => 1,
				'popup'   => $this->get_default_activated_popup_template(),
			];

			if ( wp_is_json_request() ) {
				wp_send_json( $response );
				exit;
			}

			wp_safe_redirect( wp_get_referer() );
			exit;
		} catch ( \Exception $e ) {
			SmsModule::add_user_attempt( $user_id );

			if ( ! empty( $response['incorrect'] ) ) {
				$response['error_message'] = tf_auth__( Options::get( 't_otp_incorrect' ) ?: 'OTP Entered is Incorrect' );
			}

			$this->return_error( $e->getMessage() );
		}
	}

	function action_sms_deactivate() {
		if ( empty( $_POST['tfa_disable_sms'] ) ) {
			return;
		}

		$user_id = get_current_user_id();

		update_user_meta( $user_id, '_tfa_enabled_sms', 0 );

		if ( wp_is_json_request() ) {
			wp_send_json_success();
		} else {
			wp_safe_redirect( wp_get_referer() ?: '/' );
			exit();
		}
	}

	// Verification Handler methods

	function get_handler_id() {
		return 'sms';
	}

	function get_handler_name() {
		return 'SMS';
	}

	function get_verify_popup( \WP_User $user, $data = [] ) {
		$phone_number = SmsModule::instance()->get_confirmed_tel_number( $user->ID );

		if ( ! $phone_number ) {
			throw new ConfigurationException();
		}

		$data['hidden_fields'] .= SmsModule::popup_hidden_fields();

		$tpl = apply_filters( 'tfa_custom_tpl', $this->get_default_verify_popup_template(), 'sms_verification_verify_tpl' );

		return View::mustache_render( $tpl, $data );
	}

	function get_activate_popup( \WP_User $user, $data = [] ) {
		$user_id = $user->ID;
		$number  = SmsModule::instance()->get_tel_number( $user_id );

		/*
		// Activate with previously confirmed number.
		$confirmed_number = SmsModule::instance()->get_confirmed_tel_number( $user_id );
		if ( $confirmed_number && $confirmed_number == $number ) {
			update_user_meta( $user_id, '_tfa_enabled_sms', 1 );
			$content = $this->get_default_activated_popup_template();
			wp_send_json( [
				'content' => $content,
			] );
			exit;
		}
		*/

		$content = apply_filters( 'tfa_custom_tpl', $this->get_default_activate_popup_template(), 'sms_verification_activate_tpl' );

		$data = [
			'form_action'   => esc_attr( admin_url( 'admin-ajax.php?action=tfa_sms_activate' ) ),
			'hidden_fields' => Html::hidden( 'tfa_enable_sms', 1 )
			                   . wp_nonce_field( 'tfa_send_sms', 'tfa_send_sms', false, false ),
		];

		if ( ! $number ) {
			$number = get_user_meta( $user_id, '_tfa_tmp_phone_number', true );
		}

		$data['phone_number'] = '+' . PhoneNumber::normalize( $number );
		$data['timeout']      = SmsModule::get_user_send_wait( $user_id );

		return View::mustache_render( $content, $data );
	}

	function get_deactivate_popup( \WP_User $user ) {
		$tpl  = apply_filters( 'tfa_custom_tpl', $this->get_default_deactivate_popup_template(), 'sms_verification_deactivate_tpl' );
		$data = [
			'form_action'   => esc_attr( admin_url( 'admin-ajax.php?action=tfa_sms_deactivate' ) ),
			'hidden_fields' => wp_nonce_field( 'tfa_disable_sms', '_wpnonce', false, false )
			                   . Html::hidden( 'tfa_disable_sms', 1 ),
		];

		return View::mustache_render( $tpl, $data );
	}

	function is_configured_for_user( $user_id ) {
		$number           = SmsModule::instance()->get_tel_number( $user_id );
		$confirmed_number = SmsModule::instance()->get_confirmed_tel_number( $user_id );

		return $number && $number == $confirmed_number;
	}

	function verify( $input, \WP_User $user = null, $mode = null ) {
		$number = SmsModule::instance()->get_confirmed_tel_number( $user->ID );

		if ( ! isset( $input['code'] )
		     || ! is_string( $input['code'] )
		) {
			throw new InvalidCredentialsException( 'OTP not entered' );
		}

		$wait = SmsModule::get_user_retry_wait( $user->ID );
		if ( $wait > 0 ) {
			throw new SmsBlockedException( sprintf( 'Please wait %s', human_time_diff( 0, $wait ) ) );
		}

		try {
			SmsModule::instance()->check_otp( $number, $_POST['code'] );
			SmsModule::clear_user_attempts( $user->ID );

			return true;
		} catch ( \Exception $e ) {
			if ( $e instanceof InvalidCredentialsException ) {
				SmsModule::add_user_attempt( $user->ID );
			}

			if ( SmsModule::get_user_retry_wait( $user->ID ) ) {
				do_action( 'tfa_sms_blocked', $user );
			}
			throw $e;
		}
	}

	/**
	 * Verify if the OTP entered is correct.
	 *
	 * Mobile Number $mob_number is without country code and country code $country_code is without plus sign.
	 */
	public function action_verify_otp() {
		if ( ! wp_verify_nonce( $_POST['security'], 'tfa_otp_nonce_action_name' ) ) {
			return $this->return_error( 'Invalid request' );
		}

		if ( ! isset( $_POST['tel'] )
		     || ! is_string( $_POST['tel'] )
		     || strlen( $_POST['otp_entered'] ) < 6
		     || ! isset( $_POST['otp_entered'] )
		     || ! is_string( $_POST['otp_entered'] )
		     || strlen( $_POST['otp_entered'] ) < 1
		) {
			return $this->return_error( 'Invalid request' );
		}

		$number = PhoneNumber::normalize( $_POST['tel'] );
		if ( ! $number ) {
			return $this->return_error( 'Invalid request' );
		}

		$response = SmsModule::instance()->check_otp( $number, $_POST['otp_entered'] );

		wp_send_json( $response );
	}

	function get_default_verify_popup_template() {
		return '<form action="{{form_action}}" method="post" class="tfa-confirm tfa-confirm-sms" data-timeout="{{timeout}}">
<div class="tfa-popup-title">Enter your SMS code <button type="button" class="tfa-popup-close">&times;</button></div>
<div class="tfa-popup-text">{{{intro}}}</div>
<div class="tfa-code-input-row">
<label>SMS code</label>
<input type="text" name="code" class="tfa-code-input" required />
</div>
<div class="tfa-popup-footer"><button type="submit" class="button">Verify</button></div>
{{{hidden_fields}}}
</form>';
	}

	function get_default_activate_popup_template() {
		return '<form method="post" action="{{{form_action}}}" class="tfa-confirm-sms tfa-json-form" data-timeout="{{timeout}}">
<div class="tfa-popup-title">
Verify your phone number
<button type="button" class="tfa-popup-close">&times;</button>
</div>
<div class="tfa-code-input-row text-center">
<label>Your Mobile</label>
<input type="tel" name="tel" value="{{phone_number}}" required />
</div>
<div class="tfa-code-input-row text-center">
<label>SMS Code</label>
<input type="text" name="code" class="tfa-code-input" required />
</div>
<div class="tfa-popup-footer"><button type="submit" class="button">Verify</button></div>
{{{hidden_fields}}}
</form>';
	}

	function get_default_deactivate_popup_template() {
		return '<form method="post" action="{{form_action}}" class="tfa-form">
<div class="tfa-popup-title">Disable 2FA with SMS
<button type="button" class="tfa-popup-close">&times;</button></div>
<div class="form-row">Are you sure you want to disable 2-factor authentication with SMS?</div>
{{{hidden_fields}}}
<div class="tfa-popup-footer">
<button type="button" class="tfa-popup-close">Cancel</button>
<button type="submit" class="button">Disable</div>
</form>';
	}

	function get_default_activated_popup_template() {
		return '<div class="form-row">2FA with SMS had been activated</div>
<div class="tfa-popup-footer">
<button type="button" onclick="location.reload()">Ok</button>
</div>';
	}

	function get_admin_settings( $settings ) {

		$settings['sms'] = [
			'title'  => 'SMS Verification Settings',
			'fields' => [
				'sms_verification_auto_activate' => [
					'label' => 'Auto-activate when user confirms phone number',
					'type'  => 'boolean',
				],
			],
		];

		return $settings;
	}
}

