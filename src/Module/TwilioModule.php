<?php

namespace TrueFactor\Module;

use Exception;
use TrueFactor\AbstractSmsGatewayModule;
use TrueFactor\Helper\Filter\ValueInvalidException;
use TrueFactor\Options;
use TrueFactor\PhoneNumber;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Rest\Client;

class TwilioModule extends AbstractSmsGatewayModule {

	function activate() {
		parent::activate();
		if ( is_admin() ) {
			add_filter( 'tfa_admin_page_settings_' . SmsModule::ADMIN_MENU_SLUG, [ $this, 'get_admin_settings' ] );
		}
	}

	function get_module_desc() {
		return tf_auth__( 'Adds Twilio SMS gateway support.' );
	}

	function get_module_group() {
		return 'SMS Gateway';
	}

	function is_configured() {
		return Options::get( 'twilio_sid_key' )
		       && Options::get( 'twilio_auth_token' )
		       && Options::get( 'twilio_phone_number' );
	}

	/**
	 * Sends the Message using Twilio Api
	 *
	 * @param $message
	 * @param $phone_number
	 *
	 * @return array
	 *
	 * @return boolean True is sent successfully
	 * @throws ConfigurationException
	 */
	public function send( $message, $phone_number ) {
		$sid           = Options::get( 'twilio_sid_key' );
		$token         = Options::get( 'twilio_auth_token' );
		$twilio_mob_no = Options::get( 'twilio_phone_number' );

		$phone_number  = trim( $phone_number );
		$twilio_mob_no = trim( $twilio_mob_no );
		$message       = trim( $message );

		$client                   = new Client( $sid, $token );
		$full_phone               = preg_replace( '/[^0-9]/', '', $phone_number );
		$mob_no_with_country_code = '+' . $full_phone;
		$response                 = $client->messages->create(
			$mob_no_with_country_code,
			[
				'from' => $twilio_mob_no,
				'body' => $message,
			]
		);

		if ( $response->errorMessage ) {
			throw new Exception( $response->errorMessage );
		}

		return true;
	}

	/**
	 * Send the OTP via twilio api.
	 *
	 * Mobile Number $mob_number is without country code and country code $country_code is without plus sign.
	 *
	 * @param $number
	 *
	 * @return array ['success' => true] if OTP was sent successfully.
	 * @throws Exception
	 */
	function send_verification( $number ) {
		$api_key = Options::get( 'twilio_api_key' );
		if ( ! $api_key ) {
			throw new Exception( tf_auth__( 'System not configured for sending SMS. Please contact administrator.' ) );
		}

		$number_info = PhoneNumber::get_number_info( $number );

		if ( ! $number_info ) {
			throw new Exception( tf_auth__( 'Invalid phone number.' ) );
		}
		$country_code = $number_info['dial_code'];
		$number       = $number_info['number'];

		$url = "https://api.authy.com/protected/json/phones/verification/start";

		$response = wp_remote_post( $url, [
				'method'      => 'POST',
				'timeout'     => 30,
				'redirection' => 10,
				'httpversion' => '1.1',
				'blocking'    => true,
				'headers'     => [],
				'body'        => [
					'api_key'      => $api_key,
					'via'          => 'sms',
					'phone_number' => $number,
					'country_code' => $country_code,
				],
				'cookies'     => [],
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( tf_auth__( $response->get_error_message() ) );
		}

		$decoded_response = json_decode( $response['body'], true );

		if ( ! empty( $decoded_response['success'] ) ) {
			return [
				'success' => true,
			];
		}

		if ( ! empty( $decoded_response['error_code'] ) ) {
			switch ( $decoded_response['error_code'] ) {
				case '60033':
					throw new Exception( tf_auth__( 'Phone number is invalid' ) );
				case  '60001':
					throw new Exception( tf_auth__( 'Invalid API key' ) );
				case '60082':
					throw new Exception( tf_auth__( 'Cannot send SMS to landline phone numbers' ) );
			}
		}

		throw new Exception( $decoded_response['message'] );
	}

	function check_verification( $number, $otp_entered ) {
		$api_key = Options::get( 'twilio_api_key' );
		if ( ! $api_key ) {
			throw new Exception( tf_auth__( 'System not configured for sending SMS. Please contact administrator.' ) );
		}

		$number_info = PhoneNumber::get_number_info( $number );

		if ( ! $number_info ) {
			return [
				'reCreate'      => false,
				'toomany'       => false,
				'incorrect'     => false,
				'error_code'    => '',
				'error_message' => tf_auth__( 'Invalid phone number.' ),
				'success'       => false,
			];
		}
		$country_code = $number_info['dial_code'];
		$number       = $number_info['number'];

		$url      = "https://api.authy.com/protected/json/phones/verification/check";
		$response = wp_remote_post( $url, [
				'method'      => 'GET',
				'timeout'     => 30,
				'redirection' => 10,
				'httpversion' => '1.1',
				'blocking'    => true,
				'headers'     => [
					'X-Authy-Api-Key' => $api_key,
				],
				'body'        => [
					'phone_number'      => $number,
					'country_code'      => $country_code,
					'verification_code' => $otp_entered,
				],
				'cookies'     => [],
			]
		);

		$decoded_response = json_decode( $response['body'] );

		if ( is_wp_error( $response ) ) {

			return [
				'reCreate'      => false,
				'toomany'       => false,
				'incorrect'     => false,
				'error_code'    => '',
				'error_message' => $response->get_error_message(),
				'success'       => false,
			];

		}

		if ( ! $decoded_response->success ) {

			$error_message = $decoded_response->message;
			$error_code    = $decoded_response->error_code;

			return [
				'reCreate'      => ( $error_code == '60023' ),
				'toomany'       => ( $error_code == '60003' ),
				'incorrect'     => ( $error_code == '60022' ),
				'error_code'    => $error_code,
				'error_message' => $error_message,
				'success'       => false,
			];

		}

		if ( $decoded_response->success ) {
			// If no error
			return [
				'reCreate'      => false,
				'toomany'       => false,
				'incorrect'     => false,
				'error_code'    => '',
				'error_message' => '',
				'success'       => true,
			];
		}

	}

	function get_admin_settings( $settings ) {
		$settings[ static::get_module_id() ] = [
			'title'  => 'Twilio Settings',
			'intro'  => 'Register and obtain your Twilio credentials: <a href="https://www.twilio.com/referral/wPFI4S">www.twilio.com</a>',
			'fields' => [
				'twilio_sid_key'      => [
					'_rules' => [
						'trim',
						function ( $value ) {
							if ( ! preg_match( '/^[_\w\d]{32,}$/', $value ) ) {
								throw new ValueInvalidException( 'Invalid SID key' );
							}

							return $value;
						}
					],
				],
				'twilio_auth_token'   => [
					'_rules' => [
						'trim',
						function ( $value ) {
							if ( ! preg_match( '/^[_\w\d]{20,}$/', $value ) ) {
								throw new ValueInvalidException( 'Invalid token' );
							}

							return $value;
						}
					],
				],
				'twilio_phone_number' => [
					'placeholder' => '+...',
					'_rules'      => [
						'trim',
						function ( $value ) {
							if ( ! preg_match( '/^\+[\d]{6,}$/', $value ) ) {
								throw new ValueInvalidException( 'Invalid phone number' );
							}

							return $value;
						}
					],
				],
			],
		];

		return $settings;
	}
}
