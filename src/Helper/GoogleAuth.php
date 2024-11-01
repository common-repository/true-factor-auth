<?php

namespace TrueFactor\Helper;

class GoogleAuth {

	protected $_codeLength = 6;

	/**
	 * @param string $data - the key=value pairs separated with &
	 *
	 * @return string
	 */
	public static function encrypt_data_ga( $data, $key ) {
		$plaintext      = $data;
		$ivlen          = openssl_cipher_iv_length( $cipher = "AES-128-CBC" );
		$iv             = openssl_random_pseudo_bytes( $ivlen );
		$ciphertext_raw = openssl_encrypt( $plaintext, $cipher, $key, $options = OPENSSL_RAW_DATA, $iv );
		$hmac           = hash_hmac( 'sha256', $ciphertext_raw, $key, $as_binary = true );
		$ciphertext     = base64_encode( $iv . $hmac . $ciphertext_raw );

		return $ciphertext;
	}

	/**
	 * @param string $data - crypt response
	 *
	 * @return string
	 */
	public static function decrypt_data( $data, $key ) {
		$c       = base64_decode( $data );
		$ivlen   = openssl_cipher_iv_length( $cipher = "AES-128-CBC" );
		$iv      = substr( $c, 0, $ivlen );
		$sha2len = 32;
		//$hmac               = substr( $c, $ivlen, $sha2len );
		$ciphertext_raw = substr( $c, $ivlen + $sha2len );

		//$calcmac            = hash_hmac( 'sha256', $ciphertext_raw, $key, $as_binary = true );
		return openssl_decrypt( $ciphertext_raw, $cipher, $key, $options = OPENSSL_RAW_DATA, $iv );
	}

	function set_secret( $user_id, $secret ) {
		$key = $this->random_str( 8 );
		update_user_meta( $user_id, 'mo2f_get_auth_rnd_string', $key );
		$secret = self::encrypt_data_ga( $secret, $key );
		update_user_meta( $user_id, 'mo2f_gauth_key', $secret );
	}

	function get_secret( $user_id ) {
		$key    = get_user_meta( $user_id, 'mo2f_get_auth_rnd_string', true );
		$secret = get_user_meta( $user_id, 'mo2f_gauth_key', true );
		$secret = self::decrypt_data( $secret, $key );

		return $secret;
	}

	function random_str( $length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ' ) {
		$randomString     = '';
		$charactersLength = strlen( $keyspace );
		for ( $i = 0; $i < $length; $i ++ ) {
			$randomString .= $keyspace[ rand( 0, $charactersLength - 1 ) ];
		}

		return $randomString;

	}

	function create_secret( $secretLength = 16 ) {
		$validChars = $this->_get_base32_lookup_table();

		// Valid secret lengths are 80 to 640 bits
		if ( $secretLength < 16 || $secretLength > 128 ) {
			throw new \Exception( 'Bad secret length' );
		}
		$secret = '';
		$rnd    = false;
		if ( function_exists( 'random_bytes' ) ) {
			$rnd = random_bytes( $secretLength );
		} elseif ( function_exists( 'mcrypt_create_iv' ) ) {
			$rnd = mcrypt_create_iv( $secretLength, MCRYPT_DEV_URANDOM );
		} elseif ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
			$rnd = openssl_random_pseudo_bytes( $secretLength, $cryptoStrong );
			if ( ! $cryptoStrong ) {
				$rnd = false;
			}
		}
		if ( $rnd !== false ) {
			for ( $i = 0; $i < $secretLength; ++ $i ) {
				$secret .= $validChars[ ord( $rnd[ $i ] ) & 31 ];
			}
		} else {
			throw new \Exception( 'No source of secure random' );
		}

		return $secret;
	}

	function _get_base32_lookup_table() {
		return [
			'A',
			'B',
			'C',
			'D',
			'E',
			'F',
			'G',
			'H', //  7
			'I',
			'J',
			'K',
			'L',
			'M',
			'N',
			'O',
			'P', // 15
			'Q',
			'R',
			'S',
			'T',
			'U',
			'V',
			'W',
			'X', // 23
			'Y',
			'Z',
			'2',
			'3',
			'4',
			'5',
			'6',
			'7', // 31
			'=',  // padding char
		];
	}

	function verify_code( $code, $secret, $discrepancy = 3, $currentTimeSlice = null ) {
		if ( !is_string($code) || strlen( $code ) != 6 ) {
			return false;
		}

		if ( $currentTimeSlice === null ) {
			$currentTimeSlice = floor( time() / 30 );
		}

		for ( $i = - $discrepancy; $i <= $discrepancy; ++ $i ) {
			$calculatedCode = $this->get_code( $secret, $currentTimeSlice + $i );
			if ( $this->timing_safe_equals( $calculatedCode, $code ) ) {
				return true;
			}
		}

		return false;
	}

	function get_url( $secret, $issuer, $id ) {
		$url = "otpauth://totp/";
		$url .= $id . "?secret=" . $secret . "&issuer=" . $issuer;

		return $url;
	}

	function timing_safe_equals( $safeString, $userString ) {
		if ( function_exists( 'hash_equals' ) ) {
			return hash_equals( $safeString, $userString );
		}
		$safeLen = strlen( $safeString );
		$userLen = strlen( $userString );

		if ( $userLen != $safeLen ) {
			return false;
		}

		$result = 0;

		for ( $i = 0; $i < $userLen; ++ $i ) {
			$result |= ( ord( $safeString[ $i ] ) ^ ord( $userString[ $i ] ) );
		}

		// They are only identical strings if $result is exactly 0...
		return $result === 0;
	}

	function get_code( $secret, $timeSlice = null ) {
		if ( $timeSlice === null ) {
			$timeSlice = floor( time() / 30 );
		}

		$secretkey = $this->_base32_decode( $secret );
		// Pack time into binary string
		$time = chr( 0 ) . chr( 0 ) . chr( 0 ) . chr( 0 ) . pack( 'N*', $timeSlice );
		// Hash it with users secret key
		$hm = hash_hmac( 'SHA1', $time, $secretkey, true );

		// Use last nipple of result as index/offset
		$offset = ord( substr( $hm, - 1 ) ) & 0x0F;

		// grab 4 bytes of the result
		$hashpart = substr( $hm, $offset, 4 );
		// Unpak binary value
		$value = unpack( 'N', $hashpart );
		$value = $value[1];
		// Only 32 bits
		$value  = $value & 0x7FFFFFFF;
		$modulo = pow( 10, $this->_codeLength );

		return str_pad( $value % $modulo, $this->_codeLength, '0', STR_PAD_LEFT );
	}

	function _base32_decode( $secret ) {
		if ( empty( $secret ) ) {
			return '';
		}
		$base32chars        = $this->_get_base32_lookup_table();
		$base32charsFlipped = array_flip( $base32chars );

		$paddingCharCount = substr_count( $secret, $base32chars[32] );
		$allowedValues    = [ 6, 4, 3, 1, 0 ];
		if ( ! in_array( $paddingCharCount, $allowedValues ) ) {
			return false;
		}


		for ( $i = 0; $i < 4; ++ $i ) {
			if ( $paddingCharCount == $allowedValues[ $i ]
			     && substr( $secret, - ( $allowedValues[ $i ] ) ) != str_repeat( $base32chars[32], $allowedValues[ $i ] )
			) {
				return false;
			}
		}
		$secret       = str_replace( '=', '', $secret );
		$secret       = str_split( $secret );
		$binaryString = '';
		for ( $i = 0; $i < count( $secret ); $i = $i + 8 ) {
			$x = '';
			if ( ! in_array( $secret[ $i ], $base32chars ) ) {
				return false;
			}
			for ( $j = 0; $j < 8; ++ $j ) {

				$x .= str_pad( base_convert( @$base32charsFlipped[ @$secret[ $i + $j ] ], 10, 2 ), 5, '0', STR_PAD_LEFT );
			}
			$eightBits = str_split( $x, 8 );
			for ( $z = 0; $z < count( $eightBits ); ++ $z ) {
				$binaryString .= ( ( $y = chr( base_convert( $eightBits[ $z ], 2, 10 ) ) ) || ord( $y ) == 48 ) ? $y : '';

			}
		}

		return $binaryString;
	}
}