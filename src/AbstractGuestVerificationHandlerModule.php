<?php

namespace TrueFactor;

abstract class AbstractGuestVerificationHandlerModule extends OptionalModule {

	/**
	 * @return bool If true, user will be able to enable/disable this handler.
	 */
	function is_switchable() {
		return false;
	}

	/**
	 * @return string
	 */
	abstract function get_handler_id();

	/**
	 * @return string
	 */
	abstract function get_handler_name();

	/**
	 * @param $input
	 * @param  null  $mode
	 *
	 * @return boolean
	 */
	abstract function verify( $input, $mode = null );

	/**
	 * @param  array  $data
	 *
	 * @return string
	 */
	abstract function get_verify_popup( $data = [] );

	abstract function get_default_verify_popup_template();
}

