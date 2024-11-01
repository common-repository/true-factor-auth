<?php
/**
 * IHS_Send_Programmable_SMS Class to send programmable SMS
 *
 * Require the bundled autoload file - the path may need to change
 * based on where you downloaded and unzipped the SDK
 *
 * @package Orion SMS OTP Verification.
 */

namespace TrueFactor;

use TrueFactor\Exception\SecurityCheckFailedException;

class Token {

	const SESSION_KEY = 'tfa_token';
	const DEFAULT_EXPIRATION = 60;

	static function add( $type, $expires = null ) {
		self::clearOutdated();

		$token = uniqid( 't' );

		$_SESSION[ self::SESSION_KEY ][ $token ] = [
			'type'    => $type,
			'expires' => microtime( true ) + ( $expires ?: self::DEFAULT_EXPIRATION ),
		];

		return $token;
	}

	static function check( $token, $type = 0 ) {
		self::clearOutdated();

		if ( ! is_string( $token )
		     || ! preg_match( '/^[\w\d]+$/', $token )
		     || empty( $_SESSION[ self::SESSION_KEY ][ $token ] )
		     || $_SESSION[ self::SESSION_KEY ][ $token ]['type'] != $type
		) {
			throw new SecurityCheckFailedException();
		}

		if ( $_SESSION[ self::SESSION_KEY ][ $token ]['expires'] < microtime( true ) ) {
			throw new SecurityCheckFailedException( 'Security token outdated' );
		}

		if ( $_POST ) {
			// unset( $_SESSION[ self::SESSION_KEY ][ $token ] );
		}

		return true;
	}

	static function clearOutdated() {
		if ( ! empty( $_SESSION[ self::SESSION_KEY ] ) ) {
			foreach ( $_SESSION[ self::SESSION_KEY ] as $token => $token_info ) {
				if ( $token_info['expires'] < microtime( true ) - 60 ) {
					unset( $_SESSION[ self::SESSION_KEY ][ $token ] );
				}
			}
		}
	}

}