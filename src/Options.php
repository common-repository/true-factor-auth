<?php

namespace TrueFactor;

class Options {

	const PREFIX = 'trufauth_';

	static function get( $key, $default = null ) {
		return get_option( self::PREFIX . $key, $default );
	}

	static function set( $key, $value, $autoload = true ) {
		return update_option( self::PREFIX . $key, $value, $autoload );
	}

	static function get_bool( $key, $default = false ) {
		return self::get( $key, $default ? 'yes' : 'no' ) == 'yes';
	}

	static function set_bool( $key, $value = false ) {
		return self::set( $key, $value ? 'yes' : 'no' );
	}
}