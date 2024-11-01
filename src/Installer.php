<?php

namespace TrueFactor;

use TrueFactor\Helper\DbMigration;
use TrueFactor\Orm\AccessRule;

/**
 * Class Installer
 * Performs all required actions for module installation.
 *
 * @package TrueFactor
 */
class Installer {

	/**
	 * Plugin install
	 *
	 * @return void
	 * @throws \Exception
	 */
	static function install() {
		self::create_tables();
	}

	/**
	 * plugins table creation
	 *
	 * @global object $wpdb
	 */
	private static function create_tables() {
		// Table structure.
		DbMigration::updateSchema( AccessRule::getTableName(), AccessRule::$cols );

		// Indexes
		DbMigration::createIndex( AccessRule::getTableName(), [ 'status' ] );
	}

	/**
	 * Get list of DB update callbacks.
	 *
	 * @return array ['version' => [ function() {}, ... ]]
	 */
	static function get_db_update_callbacks() {
		return [
			/*
			'1.0.0' => [
				function () {
					global $wpdb;

					return $wpdb->query( "UPDATE {$wpdb->prefix}options SET option_name=REPLACE(option_name,'tfa_','trufauth_') WHERE option_name LIKE 'tfa_%'" );
				},
			],
			*/
		];
	}

	/**
	 * Update plugin
	 */
	static function update() {
		$current_db_version = Options::get( 'db_version', '0.0.0' );

		if ( version_compare( TRUE_FACTOR_PLUGIN_VERSION, $current_db_version, '=' ) ) {
			return;
		}

		foreach ( self::get_db_update_callbacks() as $version => $update_callbacks ) {
			if ( version_compare( $current_db_version, $version ) == - 1 ) {
				foreach ( $update_callbacks as $update_callback ) {
					call_user_func( $update_callback );
					self::update_db_version( $version );
				}
			}
		}

		self::update_db_version( $current_db_version );
	}

	/**
	 * Update DB version to current.
	 *
	 * @param  string|null  $version  .
	 */
	static function update_db_version( $version ) {
		Options::set( 'db_version', $version );
	}

}