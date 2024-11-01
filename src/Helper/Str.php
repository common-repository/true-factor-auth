<?php

namespace TrueFactor\Helper;

/**
 * Utility class for strings
 */
class Str {

	/**
	 * Ensure that given string $input starts with $prefix.
	 *
	 * @param  string  $input
	 * @param  string  $suffix
	 *
	 * @return string
	 */
	static function appended( $input, $suffix ) {
		if ( mb_substr( $input, - mb_strlen( $suffix ) ) == $suffix ) {
			return $input;
		}

		return $input . $suffix;
	}

	/**
	 * Builds slug from given string.
	 *
	 * @param          $str
	 * @param  string  $sep
	 *
	 * @return string
	 */
	static function slugify( $str, $sep = '-' ) {
		if ( ! is_string( $sep ) ) {
			$sep = '-';
		}

		$trans[','] = $sep;
		$trans[' '] = $sep;

		$str = preg_replace( '/[^\w\d\_]/', $sep, strtr( self::trim( $str ), $trans ) );

		$count = 0;
		do {
			$str = str_replace( $sep . $sep, $sep, $str, $count );
		} while ( $count );

		if ( ! preg_match( '/^\w/', $str ) ) {
			$str = 'n' . $str;
		}

		return $str;
	}

	/**
	 * Works like native trim, but also trims special chars.
	 *
	 * @param $str
	 *
	 * @return string
	 */
	static function trim( $str ) {
		return trim( $str, chr( 0xC2 ) . chr( 0xA0 ) . chr( 20 ) . " \t\n\r\0\x0B" );
	}

	static function toTitle( $string ) {
		return ucwords( join( ' ', array_filter( preg_split( '/[_\-]/', $string ) ) ) );
	}

	static function camelCaseTo( $string, $to = '-' ) {
		return str_replace( '(_-SEP-_)', $to, preg_replace( '/(?<!^)[A-Z]/', '(_-SEP-_)$0', $string ) );
	}

	/**
	 * Same as PHP native sprintf, but with unicode support.
	 *
	 * @param         $format
	 *
	 * @param  array  $args
	 *
	 * @return string
	 */
	static function sprintf( $format, ...$args ) {
		$params = $args;

		$callback = function ( $length ) use ( &$params ) {
			$value = array_shift( $params );

			return strlen( $value ) - mb_strlen( $value ) + $length[0];
		};

		$format = preg_replace_callback( '/(?<=%|%-)\d+(?=s)/', $callback, $format );

		return sprintf( $format, ...$args );
	}

	/**
	 * Same as PHP native vsprintf, but with unicode support.
	 *
	 * @param $format
	 * @param $args
	 *
	 * @return string
	 */
	static function vsprintf( $format, $args ) {
		return self::sprintf( $format, ...$args );
	}
}
