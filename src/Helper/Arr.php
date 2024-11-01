<?php

namespace TrueFactor\Helper;

/**
 * Utility class for arrays.
 */
class Arr {

	/**
	 * Converts multi-dimensional array to single-dimensional by moving values from sub-arrays into the top-level array.
	 * Keys are ignored.
	 *
	 * @param        $array
	 * @param  bool  $recursively
	 *
	 * @return array
	 */
	static function flatten( $array, $recursively = false ) {
		$return = [];
		foreach ( $array as $value ) {
			if ( is_array( $value ) ) {
				$return = array_merge( $return,
					$recursively ? self::flatten( $value ) : $value );
				continue;
			}
			$return[] = $value;
		}

		return $return;
	}

	/**
	 * Sets value by given path in multi-dimensional array.
	 *
	 * @param  array  $array  The target array.
	 * @param  array  $path  Single-dimensional array (list) of keys.
	 * @param  mixed  $value  The new value.
	 *
	 * @return mixed
	 * @example Arr::getByPath($source, ['key', 'subkey', 'subkey']) will return value stored in $source['key']['subkey']['subkey']
	 */
	static function setByPath( array &$array, $path, $value ) {
		$temp = &$array;
		foreach ( $path as $key ) {
			$temp = &$temp[ $key ];
		}
		$temp = $value;
	}

	/**
	 * Returns value by given path in multi-dimensional array.
	 *
	 * @param  array  $source  The source array.
	 * @param  array  $path  Single-dimensional array (list) of keys.
	 *
	 * @param  mixed  $default
	 *
	 * @return mixed
	 * @example Arr::getByPath($source, ['key', 'subkey', 'subkey']) will return value stored in $source['key']['subkey']['subkey']
	 *
	 */
	static function getByPath( $source, $path, $default = null ) {
		if ( ! is_array( $source ) ) {
			return $default;
		}
		while ( $key = array_shift( $path ) ) {
			if ( ! array_key_exists( $key, $source ) ) {
				return $default;
			}
			$source = $source[ $key ];
			if ( ! count( $path ) ) {
				return $source;
			}

			return self::getByPath( $source, $path, $default );
		}
	}

	static function unsetKeys( array &$array, $keys ) {
		foreach ( (array) $keys as $k ) {
			if ( array_key_exists( $k, $array ) ) {
				unset( $array[ $k ] );
			}
		}

		return $array;
	}
}
