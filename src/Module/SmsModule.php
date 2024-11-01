<?php
/**
 * IHS_Send_Programmable_SMS Class to send programmable SMS
 *
 * Require the bundled autoload file - the path may need to change
 * based on where you downloaded and unzipped the SDK
 *
 * @package Orion SMS OTP Verification.
 */

namespace TrueFactor\Module;

use Exception;
use TrueFactor\AbstractModule;
use TrueFactor\AbstractSmsGatewayModule;
use TrueFactor\Exception\ConfigurationException;
use TrueFactor\Helper\Filter\ValueInvalidException;
use TrueFactor\Helper\GetUserByLogPwdTrait;
use TrueFactor\Helper\Html;
use TrueFactor\Options;
use TrueFactor\Orm\User;
use TrueFactor\PhoneNumber;
use TrueFactor\View;
use YellowTree\GeoipDetect\DataSources\City;

class SmsModule extends AbstractModule {

	use GetUserByLogPwdTrait;

	const META_KEY_SENT = '_tfa_sms_sent';
	const META_KEY_ATTEMPT = '_tfa_sms_attempt';
	const META_KEY_CONFIRMED_TEL = '_tfa_confirmed_tel';
	const META_KEY_TEL = '_tfa_tel';

	const GATEWAY_OPTION_KEY = 'sms_gateway';
	const ADMIN_MENU_SLUG = 'tfa_sms';

	function activate() {
		$this->gateway_id = Options::get( self::GATEWAY_OPTION_KEY );
		$this->add_ajax_action( 'send_sms' );

		add_action( 'login_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
			add_action( 'admin_notices', [ $this, 'action__admin_notices' ] );
			add_filter( 'tfa_admin_pages', [ $this, 'get_admin_pages' ] );
			add_filter( 'tfa_admin_page_settings_tfa_sms', [ $this, 'get_admin_settings' ] );
		}
	}

	function get_module_name() {
		return tf_auth__( 'SMS' );
	}


	function get_module_desc() {
		return tf_auth__( 'Allows sending SMS. Required for all modules that send SMS.' );
	}

	/** @var AbstractSmsGatewayModule[] */
	protected $gateways;

	function register_gateway( AbstractSmsGatewayModule $gateway ) {
		$this->gateways[ $gateway::get_module_id() ] = $gateway;
	}

	// Actions and filters.

	function action__admin_notices() {
		if ( ! empty( $_GET['page'] ) && $_GET['page'] == self::ADMIN_MENU_SLUG ) {
			return;
		}

		if ( ! $this->is_configured() ) {
			AdminSettingsModule::instance()->show_notice(
				'<b>' . TRUE_FACTOR_PLUGIN_NAME . '</b>: '
				. tf_auth__( 'SMS gateway is not configured. SMS messages won\'t be sent.' )
				. ' '
				. Html::tag( 'a', [ 'href' => menu_page_url( self::ADMIN_MENU_SLUG, false ) ], tf_auth__( 'Configure SMS' ) ),
				'true_factor_sms_not_configured',
				true
			);
		}
	}

	// Endpoints.

	/**
	 * Send OTP in SMS.
	 * Use cases:
	 * - Phone number confirmation during registration
	 * - Phone number confirmation during SMS 2FA activation
	 * - Phone number confirmation when user changes his phone number
	 * - User Action confirmation
	 */
	public function action_send_sms() {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return $this->send_sms_guest();
		}

		$timer = SmsModule::get_user_send_wait( $user_id );

		if ( $timer ) {
			return $this->send_please_wait( $timer );
		}

		if ( ! empty( $_POST['tel'] ) ) {

			// User wants to confirm his phone number.
			if ( ! wp_verify_nonce( $_POST['tfa_send_sms'], 'tfa_send_sms' ) ) {
				$this->return_error( tf_auth__( 'Invalid request. Please reload the page and try again.' ) );
			}
			$number = PhoneNumber::normalize( $_POST['tel'] );

			if ( ! $number ) {
				$this->return_error( tf_auth__( 'Please enter valid phone number' ) );
			}
		} else {
			$number = $this->get_confirmed_tel_number( $user_id );

			if ( empty( $number ) ) {
				$this->return_error( tf_auth__( 'Please confirm your number first' ) );
			}

			if ( empty( $_POST['action_id'] ) || ! filter_var( $_POST['action_id'], FILTER_VALIDATE_INT ) ) {
				$this->return_error( tf_auth__( 'Invalid request. Please reload the page and try again.' ) );
			}

			if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'tfa_confirm_' . $_POST['action_id'] ) ) {
				$this->return_error( tf_auth__( 'Invalid request. Please reload the page and try again.' ) );
			}
		}


		try {
			$response = SmsModule::instance()->send_otp( $number );

			if ( $user_id ) {
				update_user_meta( $user_id, '_tfa_phone_number', $number );
			}

			if ( ! empty( $response['success'] ) ) {
				SmsModule::add_user_send( $user_id );
				if ( isset( $response['message'] ) ) {
					unset( $response['message'] );
				}
			}

			wp_send_json( $response );
		} catch ( Exception $e ) {
			$this->return_error( $e->getMessage() );
		}
	}

	/**
	 * Sending SMS to guest.
	 * Use cases:
	 * - Registration form
	 * - Password in SMS.
	 */
	protected function send_sms_guest() {
		$mode = $_POST['mode'] ?? null;

		if ( $mode === 'login' ) {
			$user   = $this->get_user_by_login_password();
			$number = $this->get_confirmed_tel_number( $user->ID );
			if ( ! $number ) {
				$this->return_error( tf_auth__( 'Invalid phone number' ) );
			}
		} else {
			if ( empty( $_POST['tel'] ) ) {
				$this->return_error( tf_auth__( 'Please enter valid phone number' ) );
			}

			$number = PhoneNumber::normalize( $_POST['tel'] );

			if ( ! $number ) {
				$this->return_error( tf_auth__( 'Please enter valid phone number' ) );
			}

			if ( $mode === 'user' ) {
				$user = User::getByTel( $number );
				if ( ! $user ) {
					$this->return_error( tf_auth__( 'User not found' ) );
				}
			}
		}

		if ( ! wp_verify_nonce( $_POST['tfa_send_sms'], 'tfa_send_sms' ) ) {
			$this->return_error( tf_auth__( 'Invalid request. Please reload the page and try again.' ) );
		}

		$timer = SmsModule::get_number_send_wait( $number );

		if ( $timer ) {
			return $this->send_please_wait( $timer );
		}

		try {
			$response = SmsModule::instance()->send_otp( $number );

			if ( ! empty( $response['success'] ) ) {
				SmsModule::add_send_to_number( $number );
				if ( isset( $response['message'] ) ) {
					unset( $response['message'] );
				}
			}

			wp_send_json( $response );
		} catch ( Exception $e ) {
			$this->return_error( $e->getMessage() );
		}
	}

	function send_sms_blocked( $block_time_left ) {
		$content = View::mustache_render( $this->get_sms_blocked_default_popup_template(), [
			'block_time_left' => human_time_diff( $block_time_left ),
		] );
		View::sendJson( [
			'error' => $content,
		] );
		exit;
	}

	function send_please_wait( $seconds, $message = 'Please wait %s' ) {
		$this->return_error( sprintf( tf_auth__( $message ), human_time_diff( 0, $seconds ) ), [
			'timeout' => $seconds,
		] );
	}

	// Temporary data about SMS requests/verification attempts by phone number.

	/**
	 * Check if SMS sends to given number exceeds limit.
	 *
	 * @param $number
	 *
	 * @return bool
	 */
	static function is_number_send_quota_exceeded( $number ) {
		$limit = (int) Options::get( 'sms_send_limit', 3 );

		if ( ! $limit ) {
			return false;
		}

		$sends = self::get_sends_to_number( $number );

		return is_array( $sends ) && count( $sends ) >= $limit;
	}

	/**
	 * Check if verification attempts with given number exceeds limit.
	 *
	 * @param $user_id
	 *
	 * @return bool
	 */
	static function is_number_retry_limit_exceeded( $user_id ) {
		$limit = Options::get( 'sms_attempt_limit' );
		if ( ! $limit ) {
			return false;
		}

		$attempts = self::get_user_attempts( $user_id );

		if ( is_array( $attempts ) && count( $attempts ) >= $limit ) {
			return true;
		}

		return false;
	}

	static function get_number_send_wait( $number ) {
		return self::get_wait( self::get_sends_to_number( $number ) )
			?: self::get_attempt_wait_for_number( $number );
	}

	static function get_attempt_wait_for_number( $number ) {
		return self::get_attempt_wait( self::get_attempts_for_number( $number ) );
	}

	static function add_send_to_number( $number ) {
		$sends   = self::get_sends_to_number( $number );
		$sends[] = [
			'ts' => time(),
		];
		self::save_sends_to_number( $number, $sends );
	}

	static function get_sends_to_number( $tel ) {
		$sends = get_transient( '_tfa_tel_sent_' . $tel );
		if ( ! $sends ) {
			return [];
		}

		$sends = json_decode( $sends, true );

		$filtered = self::filter_ts( $sends );

		if ( count( $filtered ) != count( $sends ) ) {
			self::save_sends_to_number( $tel, $filtered );
		}

		return $filtered;
	}

	static function save_sends_to_number( $number, $sends ) {
		set_transient( '_tfa_tel_sent_' . $number, json_encode( $sends ) );
	}

	static function add_number_attempt( $number ) {
		$sends   = self::get_attempts_for_number( $number );
		$sends[] = [
			'ts' => time(),
		];
		self::save_attempts_for_number( $number, $sends );
	}

	static function get_attempts_for_number( $number ) {
		$attempts = get_transient( '_tfa_sms_num_try_' . $number );
		if ( ! $attempts ) {
			return [];
		}

		$attempts = json_decode( $attempts, true );

		$filtered = self::filter_ts( $attempts );

		if ( count( $filtered ) != count( $attempts ) ) {
			self::save_attempts_for_number( $number, $filtered );
		}

		return $filtered;
	}

	static function save_attempts_for_number( $number, $attempts ) {
		set_transient( '_tfa_sms_num_try_' . $number, json_encode( $attempts ) );
	}

	// Temporary data about SMS requests/verification attempts from IP.

	static function add_ip_send( $ip ) {
		$sends   = self::get_ip_sends( $ip );
		$sends[] = [
			'ts' => time(),
		];
		self::save_ip_sends( $ip, $sends );
	}

	static function get_ip_sends( $ip ) {
		if ( ! is_int( $ip ) ) {
			$ip = ip2long( $ip );
		}

		$sends = get_transient( '_tfa_sms_sent_ip_' . $ip );
		if ( ! $sends ) {
			return [];
		}

		$sends = json_decode( $sends, true );

		$filtered = self::filter_ts( $sends );

		if ( count( $filtered ) != count( $sends ) ) {
			self::save_ip_sends( $ip, $filtered );
		}

		return $filtered;
	}

	static function save_ip_sends( $ip, $sends ) {
		if ( ! is_int( $ip ) ) {
			$ip = ip2long( $ip );
		}

		set_transient( '_tfa_sms_sent_ip_' . $ip, json_encode( $sends ) );
	}

	// Temporary data about SMS requests/verification by user.

	static function get_user_send_wait( $user_id ) {
		return self::get_wait( self::get_user_sends( $user_id ) )
			?: self::get_user_retry_wait( $user_id );
	}

	static function get_user_retry_wait( $user_id ) {
		return self::get_attempt_wait( self::get_user_attempts( $user_id ) );
	}

	static function add_user_send( $user_id ) {
		$attempts   = self::get_user_sends( $user_id );
		$attempts[] = [
			'ts' => time(),
		];
		self::save_user_sends( $user_id, $attempts );
	}

	static function get_user_sends( $user_id ) {
		$sends    = get_user_meta( $user_id, self::META_KEY_SENT, true ) ?: [];
		$filtered = self::filter_ts( $sends );

		if ( count( $filtered ) != count( $sends ) ) {
			self::save_user_sends( $user_id, $filtered );
		}

		return $filtered;
	}

	static function add_user_attempt( $user_id ) {
		$attempts   = self::get_user_attempts( $user_id );
		$attempts[] = [
			'ts' => time(),
		];
		self::save_user_attempts( $user_id, $attempts );
	}

	static function get_user_attempts( $user_id ) {
		$attempts = get_user_meta( $user_id, self::META_KEY_ATTEMPT, true ) ?: [];
		$filtered = self::filter_ts( $attempts );

		if ( count( $filtered ) != count( $attempts ) ) {
			self::save_user_attempts( $user_id, $filtered );
		}

		return $filtered;
	}

	static function clear_user_attempts( $user_id ) {
		self::save_user_sends( $user_id, [] );
		self::save_user_attempts( $user_id, [] );
	}

	static function save_user_attempts( $user_id, $attempts ) {
		update_user_meta( $user_id, self::META_KEY_ATTEMPT, $attempts );
	}

	static function save_user_sends( $user_id, $sends ) {
		update_user_meta( $user_id, self::META_KEY_SENT, $sends );
	}

	function get_tel_number( $user_id ) {
		return get_user_meta( $user_id, self::get_tel_number_meta_key(), true );
	}

	function set_tel_number( $user_id, $number ) {
		$number = PhoneNumber::normalize( $number );
		if ( ! $number ) {
			delete_user_meta( $user_id, self::get_tel_number_meta_key() );
		} else {
			update_user_meta( $user_id, self::get_tel_number_meta_key(), $number );
		}
	}

	function get_confirmed_tel_number( $user_id ) {
		return get_user_meta( $user_id, self::META_KEY_CONFIRMED_TEL, true );
	}

	function set_confirmed_tel_number( $user_id, $number ) {
		$users = User::getAllByTel( $number );
		if ( $users ) {
			foreach ( $users as $user ) {
				if ( $user->ID != $user_id ) {
					// Do not allow other users to have the same number.
					delete_user_meta( $user->ID, self::META_KEY_CONFIRMED_TEL );
				}
			}
		}
		update_user_meta( $user_id, self::META_KEY_CONFIRMED_TEL, $number );
		do_action( 'tfa_phone_number_confirmed', $user_id, $number );
	}

	static function filter_ts( $sends ) {
		$cut_ts   = time() - self::get_block_period();
		$filtered = [];

		foreach ( $sends as $n => $s ) {
			if ( $s['ts'] < $cut_ts ) {
				continue;
			}
			$filtered[] = $s;
		}

		return $filtered;
	}

	/**
	 * Returns time till next sms send or OTP check based on list of previous attempts.
	 *
	 * @param  array  $sends  List of timestamps of attempts withing block period.
	 * @param  null|integer  $limit  Attempt limit within block period.
	 * @param  null|integer  $interval  Minimum interval between sends. Supposed to be 0 for code checks.
	 *
	 * @return float|int|null
	 */
	static function get_wait( $sends, $limit = null, $interval = null ) {
		if ( ! $sends ) {
			return 0;
		}

		if ( $limit === null ) {
			$limit = Options::get( 'sms_send_limit' );
		}

		if ( $limit && count( $sends ) >= $limit ) {
			return reset( $sends )['ts'] + self::get_block_period() - time();
		}

		if ( $interval === null ) {
			$interval = self::get_resend_timeout();
		}

		if ( ! $interval ) {
			return 0;
		}

		$timeout = end( $sends )['ts'] + $interval;
		if ( $timeout > time() ) {
			return $timeout - time();
		}

		return 0;
	}

	/**
	 * Returns time till next OTP check based on list of previous attempts.
	 *
	 * @param  array  $attempts  List of timestamps of attempts withing block period.
	 *
	 * @return int
	 */
	static function get_attempt_wait( $attempts ) {
		if ( ! $attempts ) {
			return 0;
		}

		$limit = Options::get( 'sms_send_limit' );

		if ( ! $limit || count( $attempts ) < $limit ) {
			return 0;
		}

		return reset( $attempts )['ts'] + self::get_block_period() - time();
	}

	// Global options.

	static function get_tel_number_meta_key() {
		return Options::get( 'phone_number_meta_key' ) ?: self::META_KEY_TEL;
	}

	static function get_resend_timeout() {
		return Options::get( 'resend_otp_timer' ) ?: 120;
	}

	static function get_block_period() {
		return Options::get( 'sms_block_period', 60 ) * 60;
	}

	protected $gateway_id;

	/** @return AbstractSmsGatewayModule
	 * @throws ConfigurationException
	 */
	function gateway() {
		if ( ! $this->is_configured() ) {
			throw new ConfigurationException();
		}

		return $this->gateways[ $this->gateway_id ];
	}

	function is_configured() {
		return $this->gateway_id
		       && ! empty( $this->gateways[ $this->gateway_id ] )
		       && $this->gateways[ $this->gateway_id ]->is_configured();
	}

	/**
	 * Handles Sending Order SMS.
	 *
	 * @param $phone_number
	 *
	 * @return array|mixed
	 * @throws Exception
	 */
	function send_otp( $phone_number ) {
		if ( defined( 'TRUE_FACTOR_SMS_SKIP_SEND' ) ) {
			return [ 'success' => 1 ];
		}

		return $this->gateway()->send_otp( $phone_number );
	}

	function check_otp( $number, $otp_entered ) {
		return $this->gateway()->check_otp( $number, $otp_entered );
	}

	function get_otp_sms_template() {
		return Options::get( 'sms_otp_tpl' ) ?: $this->get_otp_sms_default_template();
	}

	function get_otp_sms_default_template() {
		return '{{code}} is your OTP';
	}

	function get_sms_blocked_default_popup_template() {
		return 'SMS sending is disabled for {{block_time_left}}';
	}

	// Admin settings.

	function get_admin_pages( $pages ) {
		$pages[ self::ADMIN_MENU_SLUG ] = [
			'title' => 'SMS Settings',
		];

		return $pages;
	}

	function get_admin_settings( $settings ) {
		return $settings + [
				'sms' => [
					'title'  => '',
					'fields' => [
						'resend_otp_timer'  => [
							'label'   => 'Resend Timeout',
							'type'    => 'integer',
							'attrs'   => [
								'min' => '1',
							],
							'default' => 90,
							'_rules'  => [ 'int', [ 'min', 1 ] ],
							'_hint'   => 'The minimum time interval between SMS sends is seconds.',
						],
						'sms_attempt_limit' => [
							'label'  => 'Retry limit',
							'type'   => 'integer',
							'min'    => 0,
							'_rules' => [ 'int', [ 'min', 0 ] ],
							'_hint'  => 'SMS verification will be disabled for user after he entered incorrect code N times.<br/>If empty, no limit applied.',
						],
						'sms_send_limit'    => [
							'label'  => 'SMS send limit',
							'type'   => 'integer',
							'min'    => 0,
							'_rules' => [ 'int', [ 'min', 0 ] ],
							'_hint'  => 'Maximum number of SMS sends in a row. When reached, SMS sending will be disabled for certain period (you can define block period below).<br/>If set to 0, no limit applied.',
						],

						'sms_block_period' => [
							'label'   => 'Block Period',
							'type'    => 'integer',
							'min'     => 0,
							'default' => 60,
							'_rules'  => [ 'int', [ 'min', 0 ] ],
							'_hint'   => 'For how long SMS verification will be disabled for user in case if retry limit reached. Enter value in minutes.',
						],

						'phone_number_meta_key' => [
							'_rules'  => [
								'trim',
								function ( $value ) {
									if ( ! preg_match( '/^[_\w\d\-]{4,}$/', $value ) ) {
										throw new ValueInvalidException( '%s must contain only letters, digits, hyphens and underscores and must be at least 4 characters long.' );
									}

									return $value;
								},
							],
							'_hint'   => '<p>The user meta key for mobile number, e.g <kbd>tel_number</kbd>.</p>
 <p>If provided, this meta key will be used for storing user phone number. This meta key can be used by other plugins, e.g Profile Builder.</p>
 <p>Make sure that other plugins use supported phone number format, which is <b>digits only, dial code included</b>, e.g 19998889999.</p>',
							'default' => '_tfa_tel',
						],

						'add_tel_field_on_default_profile' => [
							'label' => 'Add Phone Number field on default profile form',
							'type'  => 'boolean',
							'_hint' => 'When this option is enabled, user will be able to edit their phone number on the default Wordpress profile form (located at /wp-admin/profile.php)',
						],

						'sms_otp_tpl' => [
							'label'   => 'One-time password SMS',
							'attrs'   => [
								'type' => 'textarea',
								'rows' => 2,
							],
							'default' => '{{password}} is your OTP',
						],

						self::GATEWAY_OPTION_KEY => [
							'label'  => 'SMS Service',
							'_hint'  => 'Choose the SMS service to use.',
							'_rules' => [ [ 'is_array_key', $this->gateways ] ],
							'attrs'  => [
								'type'    => 'select',
								'options' => function () {
									$options = [];
									foreach ( $this->gateways as $gateway_id => $gateway ) {
										$options[ $gateway_id ] = $gateway->get_module_name();
									}

									return $options;
								},
								// Changing value of this input switches visibility of other sections. See admin.js for details.
								'class'   => 'js-tfa-admin-sms-gateway',
							],
						],
					],
				],
			];
	}

	/**
	 * Enqueue Styles and Scripts.
	 *
	 * @param $hook
	 */
	function enqueue_scripts( $hook ) {
		$user_id = get_current_user_id();

		$country_code  = Options::get( 'country_code' );
		$country_codes = [];
		if ( $country_code ) {
			foreach ( (array) $country_code as $code ) {
				if ( $code === 'all' ) {
					$country_codes = [ $code ];
					break;
				}
				array_push( $country_codes, $code );
			}
		}
		$data['countries'] = $country_codes;

		if ( Options::get_bool( 'user_country' ) ) {
			$user_country_meta_key = Options::get( 'country_meta' );
			if ( $user_id && $user_country_meta_key ) {
				$data['preferred_country'] = get_user_meta( $user_id, $user_country_meta_key, true );
			}
		}

		if ( empty( $data['preferred_country'] ) ) {
			if ( function_exists( 'geoip_detect2_get_info_from_current_ip' ) ) {
				/** @var City $geoip */
				$geoip                     = geoip_detect2_get_info_from_current_ip();
				$data['preferred_country'] = $geoip->country->isoCode;
			}
		}

		$this->do_enqueue_script( 'tel_input', [ 'jquery' ], true );

		wp_register_style(
			'tfa_tel_input_css',
			TRUE_FACTOR_CSS_URI . '/tel_input.css',
			[],
			filemtime( TRUE_FACTOR_PLUGIN_DIR . '/assets/css/tel_input.css' )
		);
		wp_enqueue_style( 'tfa_tel_input_css' );

		$this->do_enqueue_script( 'sms', [ 'tfa_main' ], true, [
			'tfa_sms' => [
				't_send_sms'   => Options::get( 'sms_t_send', 'Send SMS' ),
				't_resend_sms' => Options::get( 'sms_t_resend', 'Resend SMS' ),
				't_sending'    => Options::get( 'sms_t_sending', 'Sending' ),
				'resend_timer' => self::get_resend_timeout(),
			],
		] );
	}

	static function popup_hidden_fields() {
		return wp_nonce_field( 'tfa_send_sms', 'tfa_send_sms', false, false );
	}

}
