<?php

namespace TrueFactor\Orm;

use TrueFactor\AbstractVerificationHandlerModule;
use TrueFactor\Exception\Required2FAException;
use TrueFactor\Module\AccessRulesModule;
use TrueFactor\Module\VerificationModule;
use TrueFactor\Options;
use TrueFactor\Orm;
use TrueFactor\View;

class AccessRule extends Orm {

	const STATUS_ACTIVE = 1;
	const STATUS_DISABLED = 10;

	static $table = 'rule';

	static $statuses = [
		self::STATUS_ACTIVE   => 'Active',
		self::STATUS_DISABLED => 'Disabled',
	];

	const REQUEST_TYPE_GET = 0;
	const REQUEST_TYPE_POST = 1;
	static $request_methods = [
		self::REQUEST_TYPE_GET  => 'GET',
		self::REQUEST_TYPE_POST => 'POST',
	];

	static $cols = [
		'id'              => [ 'bigint', null, 1, null, null, 'AUTO_INCREMENT PRIMARY KEY' ],
		'status'          => [ 'tinyint', null, 1 ],
		'title'           => [ 'varchar', 128 ],
		'request_method'  => [ 'tinyint', null, 1 ],
		'request_url'     => [ 'varchar', 1000, 1 ],
		'request_params'  => [ 'varchar', 1000, 1 ],
		'button_selector' => [ 'varchar', 100 ],
		'shortcode'       => [ 'varchar', 100 ],
		'ctime'           => [ 'datetime' ],
		'mtime'           => [ 'datetime' ],
		'is_required'     => [ 'tinyint', null, 1 ],
		'is_editable'     => [ 'tinyint', null, 1 ],
		'config'          => [ 'json' ],
	];

	public $id;
	public $title;
	public $status = self::STATUS_ACTIVE;
	public $ctime;
	public $mtime;
	public $request_params;
	public $request_method;
	public $request_url;
	public $button_selector;
	public $shortcode;
	public $is_required;
	public $is_editable;
	public $is_allow_guest;
	public $config = [];

	static function editable_actions_count() {
		return self::count( [
			'where' => [
				'status'      => AccessRule::STATUS_ACTIVE,
				'is_editable' => 1,
			],
		] );
	}

	/**
	 * Checks if current request must be protected with 2FA.
	 *
	 * @return bool
	 */
	function is_applicable() {
		if ( $_SERVER['REQUEST_METHOD'] != self::$request_methods[ $this->request_method ] ) {
			return false;
		}

		if ( ! $this->request_url && ! $this->request_params ) {
			return false;
		}

		if ( $this->request_url ) {
			if ( '~' == mb_substr( $this->request_url, 0, 1 ) ) {
				if ( ! preg_match( $this->request_url, $_SERVER['REQUEST_URI'] ) ) {
					return false;
				}
			} elseif ( mb_strpos( $_SERVER['REQUEST_URI'], $this->request_url ) === false ) {
				return false;
			}
		}

		if ( $this->request_params ) {
			$pairs = explode( "\n", $this->request_params );
			foreach ( $pairs as $pair ) {
				$key_value = explode( '=', trim( $pair ), 2 );
				if ( ! isset( $_REQUEST[ $key_value[0] ] ) ) {
					return false;
				}
				if ( isset( $key_value[1] ) ) {
					if ( mb_substr( $key_value[1], 0, 1 ) == '~' ) {
						if ( ! preg_match( $key_value[1], $_REQUEST[ $key_value[0] ] ) ) {
							return false;
						}
					} else {
						if ( $_REQUEST[ $key_value[0] ] != $key_value[1] ) {
							return false;
						}
					}
				}
			}
		}

		return true;
	}

	function get_verification_required_popup() {
		$tpl    = empty( $this->config['verification_required_tpl'] ) ? $this->get_popup_template( '2fa' ) : $this->config['verification_required_tpl'];
		$values = [
			'action_id'    => $this->id,
			'action_title' => $this->title,
			'settings_url' => Options::get( 'verification_settings_url' ),
		];

		return View::mustache_render( $tpl, $values );
	}

	function get_popup_template( $type ) {
		return empty( $this->config['tpl'][ $type ] ) ? self::get_default_template( $type ) : $this->config['tpl'][ $type ];
	}

	static function get_default_template( $type ) {
		return self::get_popups()[ $type ]['tpl'];
	}

	// Object-level methods.

	static function get_popups() {
		return [

			'check_failed' => [
				'name' => 'Security Check Failed',
				'tpl'  => '<h1>Security Check Failed</h1>
<p>Sorry, your request can not be processed because it requires verification.</p>
<p>Please return to the previous page, reload it and try to repeat the request.</p>
<p>If this won\'t work, please contact support.</p>
<div><a href="javascript:history.back()" class="button">Go Back</a></div>',
			],

			'check_failed_get' => [
				'name' => 'Security Check Required',
				'tpl'  => '<h1>Security Check Required</h1>
<p>In order to access this page, you need to pass security check.</p>
<p>Please click the button below to proceed with check.</p>
<div><a href="{{check_url}}" class="button">Proceed with security check</a></div>',
			],

			'2fa' => [
				'name' => '2FA Required',
				'tpl'  => '<div class="tfa-popup-title">Verification Required</div>
<p>Please activate at least one verification method in order to perform {{action_title}}</p>
<div class="tfa-popup-footer">
{{#settings_url}}
<a href="{{{settings_url}}}" target="_blank"><button type="button">Go to Settings</button></a>
{{/settings_url}}
<button type="button" class="tfa-popup-close">Ok</button>
</div>',
			],

			'ok' => [
				'name' => 'Thank you',
				'tpl'  => '
<div class="tfa-popup-title">Thank you </div>
<p>{{action_title}} had been confirmed.</p>
<div class="tfa-popup-footer">
<button type="button" onclick="true_factor_auth_popup_callback()">Ok</button>
</div>',
			],
		];
	}

	/**
	 *
	 * @param $user_id
	 *
	 * @return bool|AbstractVerificationHandlerModule
	 * @throws Required2FAException When 2FA is required, but user did not configure any suitable method.
	 */
	function get_handler_for_user( $user_id ) {
		$settings         = AccessRulesModule::get_user_settings( $user_id );
		$user_types       = VerificationModule::instance()->get_user_handlers( $user_id );
		$enabled_types    = $this->get_handlers();
		$applicable_types = array_intersect_key( $user_types, $enabled_types );

		if ( $this->is_editable ) {
			if ( ! empty( $settings[ $this->id ] ) ) {
				$type = $settings[ $this->id ];
				if ( ! empty( $applicable_types[ $type ] ) ) {
					return $applicable_types[ $type ];
				}
			}
		}

		if ( ! empty( $applicable_types ) ) {
			return current( $applicable_types );
		}

		if ( $this->is_required ) {
			throw new Required2FAException();
		}

		return false;
	}

	protected $_enabled_handlers = null;

	/**
	 * @param  bool  $reload
	 *
	 * @return AbstractVerificationHandlerModule[] List of verification types enabled for given action.
	 */
	function get_handlers( $reload = false ) {
		if ( $reload || $this->_enabled_handlers === null ) {
			$this->_enabled_handlers = [];

			$handlers = VerificationModule::instance()->get_handlers();
			foreach ( $handlers as $id => $handler ) {
				if ( ! empty( $this->config['handler'][ $id ]['on'] ) ) {
					$this->_enabled_handlers[ $id ] = $handler;
				}
			}
		}

		return $this->_enabled_handlers;
	}

	function is_handler_enabled( $id ) {
		return ! empty( $this->get_handlers()[ $id ] );
	}

}