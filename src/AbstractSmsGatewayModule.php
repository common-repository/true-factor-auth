<?php

namespace TrueFactor;

use TrueFactor\Exception\ExpiredCredentialsException;
use TrueFactor\Exception\InvalidCredentialsException;
use TrueFactor\Module\SmsModule;

abstract class AbstractSmsGatewayModule extends OptionalModule {

	const SESSION_KEY = '_tfa_otp_sms';
	const CODE_EXPIRATION = 600;

	function activate() {
		SmsModule::instance()->register_gateway( $this );
	}

	function is_configured() {
		return true;
	}

	abstract public function send( $message, $phone_number );

	/**
	 * Send the OTP.
	 *
	 * Mobile Number $mob_number is without country code and country code $country_code is without plus sign.
	 *
	 * @param $number
	 *
	 * @return array|mixed {boolean} Returns true if the message sent successfully Returns true if the message sent successfully
	 */
	function send_otp( $number ) {
		$message = $this->create_otp_message( $number );
		try {
			$this->send( $message, $number );

			return [
				'success' => true,
			];
		} catch ( \Exception $e ) {
			return [
				'success'       => false,
				'error_message' => $e->getMessage(),
			];
		}
	}

	function get_otp_template() {
		return SmsModule::instance()->get_otp_sms_template();
	}


	function create_otp_message( $number ) {
		$code             = $this->create_otp_code( $number );
		$message_template = $this->get_otp_template();

		return View::mustache_render( $message_template, [
			'code' => $code,
		] );
	}

	function create_otp_code( $number ) {
		$code = mt_rand( 100000, 999999 );

		$_SESSION[ self::SESSION_KEY ][ $number ] = [
			'code'    => (string) $code,
			'timeout' => time() + self::CODE_EXPIRATION,
		];

		return (string) $code;
	}

	/**
	 * Verify if the OTP entered is correct.
	 *
	 * Mobile Number $mob_number is without country code and country code $country_code is without plus sign.
	 *
	 * @param  string  $number
	 * @param  string  $code
	 *
	 * @return boolean
	 * @throws InvalidCredentialsException
	 * @throws ExpiredCredentialsException
	 */
	function check_otp( $number, $code ) {
		if ( defined( 'TRUE_FACTOR_SMS_CODE_PASS' ) && TRUE_FACTOR_SMS_CODE_PASS === $code ) {
			return true;
		}

		if ( ! $code
		     || ! is_string( $code )
		     || ! preg_match( '/^[\w\d]{4,}$/', $code )
		     || empty( $_SESSION[ self::SESSION_KEY ][ $number ] )
		     || $code !== $_SESSION[ self::SESSION_KEY ][ $number ]['code']
		) {
			throw new InvalidCredentialsException( 'Entered code is incorrect' );
		}

		if ( $_SESSION[ self::SESSION_KEY ][ $number ]['timeout'] < time() ) {
			throw new ExpiredCredentialsException( 'Entered code is outdated. Try requesting a new one.' );
		}

		unset( $_SESSION[ self::SESSION_KEY ][ $number ] );

		return true;
	}
}
