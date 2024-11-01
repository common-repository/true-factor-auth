<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'TRUE_FACTOR_PLUGIN_FILE' ) ) {
	return;
}

/* Define Constants */
define( 'TRUE_FACTOR_PLUGIN_NAME', 'True Factor Auth' );
define( 'TRUE_FACTOR_PLUGIN_FILE', __DIR__ . '/true-factor-auth.php' );
define( 'TRUE_FACTOR_PLUGIN_DIR', __DIR__ );
define( 'TRUE_FACTOR_PLUGIN_VERSION', '1.0.3' );
define( 'TRUE_FACTOR_UPGRADE_LINK', 'https://true-wp.com/purchase/' );
define( 'TRUE_FACTOR_PLUGIN_URL', plugins_url( basename( __DIR__ ) ) );
define( 'TRUE_FACTOR_JS_URI', TRUE_FACTOR_PLUGIN_URL . '/assets/js' );
define( 'TRUE_FACTOR_VENDOR_JS_URI', TRUE_FACTOR_JS_URI . '/vendor' );
define( 'TRUE_FACTOR_CSS_URI', TRUE_FACTOR_PLUGIN_URL . '/assets/css' );

require_once 'vendor/autoload.php';

if ( ! function_exists( 'tf_auth__' ) ) {
	/**
	 * Shorthand function for string translation.
	 *
	 * @param $string
	 *
	 * @return string|void
	 */
	function tf_auth__( $string ) {
		return __( $string, 'true-factor' );
	}
}

\TrueFactor\Installer::update();