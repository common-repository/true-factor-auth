<?php

namespace TrueFactor\Module;

use TrueFactor\AbstractVerificationHandlerModule;
use TrueFactor\View;

class NoneVerificationHandlerModule extends AbstractVerificationHandlerModule {

	/**
	 * Class constructor
	 */
	protected function activate() {
		VerificationModule::instance()->add_handler( $this );
	}

	// Verification Handler methods

	function is_optional() {
		return false;
	}

	function get_handler_id() {
		return 'non';
	}

	function get_handler_name() {
		return 'No Verification';
	}

	function verify( $input, \WP_User $user = null, $mode = null ) {
		return true;
	}

	function get_verify_popup( \WP_User $user, $data = [] ) {
		$tpl = $this->get_default_verify_popup_template();

		return View::mustache_render( $tpl, $data );
	}

	function get_default_verify_popup_template() {
		return [ 'skip' => 1 ];
	}

	function get_activate_popup( \WP_User $user ) {
		// This handler is always active
	}

	function get_deactivate_popup( \WP_User $user ) {
		// This handler is always active
	}

	function get_default_activate_popup_template() {
		// This handler is always active
	}

	function get_default_deactivate_popup_template() {
		// This handler is always active
	}
}

