<?php

namespace TrueFactor;

use TrueFactor\Helper\Str;
use TrueFactor\Module\AdminSettingsModule;

class AbstractModule {

	public $is_unavailable = false;

	/**
	 * Main instance
	 *
	 * @return $this
	 */
	final public static function instance() {
		static $instance;

		return $instance ?: $instance = new static();
	}

	final static function get_module_id() {
		$class_name = explode( '\\', static::class );

		// Module names should end with "Module".
		return substr( array_pop( $class_name ), 0, - 6 );
	}

	protected function __construct() {
		Modules::register( $this );
		AdminSettingsModule::instance()->register_module( $this );

		if ( $this->is_enabled() ) {
			$this->activate();
		}
	}

	function get_module_name() {
		$class_name = explode( '\\', get_called_class() );

		// Conventional module names should end with "Module".
		$name = substr( array_pop( $class_name ), 0, - 6 );

		return Str::toTitle( Str::camelCaseTo( $name ) );
	}

	function get_module_desc() {
		return null;
	}

	function get_module_group() {
		return 'General';
	}

	/**
	 * Add hooks, menus etc.
	 * Only called if module is enabled.
	 */
	protected function activate() {
		// Must be implemented in child classes.
	}

	/**
	 * @return int
	 */
	function get_position() {
		return 0;
	}

	function is_enabled() {
		return ( ! $this->is_unavailable )
		       && ( ! $this->is_optional() || Options::get_bool( 'module_' . static::get_module_id(), true ) );
	}

	/** @return static[] */
	function get_dependencies() {
		return [];
	}

	function is_optional() {
		return false;
	}

	function add_ajax_action( $name, $nopriv = true, $priv = true ) {
		if ( $nopriv ) {
			add_action( 'wp_ajax_nopriv_tfa_' . $name, [ $this, "action_{$name}" ] );
		}
		if ( $priv ) {
			add_action( 'wp_ajax_tfa_' . $name, [ $this, "action_{$name}" ] );
		}
	}

	protected function login_required() {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			if ( wp_is_json_request() ) {
				wp_send_json( [
					'error' => tf_auth__( 'Please log in' ),
				] );
			} else {
				wp_die( tf_auth__( 'Please log in' ) );
			}
		}

		return $user_id;
	}

	protected function return_error( $message, $data = [] ) {
		if ( wp_is_json_request() ) {
			wp_send_json( array_replace( [
				'error' => $message,
			], $data ) );
			exit();
		}
		wp_die( $message );
	}

	protected function do_enqueue_script( $name, $deps = [], $in_footer = false, $localise = [] ) {
		wp_register_script(
			'tfa_' . $name,
			TRUE_FACTOR_JS_URI . '/' . $name . ( defined( 'TRUE_FACTOR_DEBUG_JS' ) ? '' : '.min' ) . '.js',
			$deps,
			defined( 'TRUE_FACTOR_DEBUG_JS' ) ? filemtime( TRUE_FACTOR_PLUGIN_DIR . '/assets/js/' . $name . '.js' ) : TRUE_FACTOR_PLUGIN_VERSION,
			$in_footer
		);

		wp_enqueue_script( 'tfa_' . $name );

		foreach ( $localise as $obj_name => $obj ) {
			wp_localize_script( 'tfa_' . $name, $obj_name, $obj );
		}
	}
}

