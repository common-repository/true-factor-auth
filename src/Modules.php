<?php
/**
 * Initialization class.
 *
 * @package TrueFactorAuth
 */

namespace TrueFactor;

class Modules {

	/** @var OptionalModule[] */
	static protected $modules;

	static function get( $id ) {
		return empty( self::$modules[ $id ] ) ? null : self::$modules[ $id ];
	}

	static function register( AbstractModule $module ) {
		self::$modules[ $module::get_module_id() ] = $module;
	}

	static function is_enabled( $id ) {
		$module = self::get( $id );

		return $module && $module->is_enabled();
	}

}