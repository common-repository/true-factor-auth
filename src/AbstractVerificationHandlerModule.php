<?php

namespace TrueFactor;

use WP_User;

abstract class AbstractVerificationHandlerModule extends OptionalModule {

	/**
	 * @return bool If true, user will be able to enable/disable this handler.
	 */
	function is_switchable() {
		return false;
	}

	/**
	 * Check whether verification handler was configured and activated by user.
	 * This method must ensure that all required parameters are present.
	 *
	 * @param  integer  $user_id
	 *
	 * @return bool
	 */
	function is_configured_for_user( $user_id ) {
		return true;
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
	 * @param  WP_User|null  $user
	 * @param  null  $mode
	 *
	 * @return boolean
	 */
	abstract function verify( $input, WP_User $user = null, $mode = null );

	/**
	 * @param  WP_User  $user
	 * @param  array  $data
	 *
	 * @return string
	 */
	abstract function get_verify_popup( WP_User $user, $data = [] );

	/**
	 * @param  WP_User  $user
	 *
	 * @return mixed
	 */
	abstract function get_activate_popup( WP_User $user );

	/**
	 * @param  WP_User  $user
	 *
	 * @return mixed
	 */
	abstract function get_deactivate_popup( WP_User $user );

	abstract function get_default_verify_popup_template();

	abstract function get_default_activate_popup_template();

	abstract function get_default_deactivate_popup_template();
}

