<?php

namespace TrueFactor\Module;

use TrueFactor\AbstractVerificationHandlerModule;
use TrueFactor\View;

class PasswordVerificationHandlerModule extends AbstractVerificationHandlerModule {

	/**
	 * Class constructor
	 */
	protected function activate() {
		VerificationModule::instance()->add_handler( $this );
	}

	function is_optional() {
		return false;
	}

	// Verification Handler methods

	function get_handler_id() {
		return 'pwd';
	}

	function get_handler_name() {
		return 'Password';
	}

	function get_module_desc() {
		return tf_auth__( 'Allows users to confirm certain actions with password.' );
	}

	function get_module_group() {
		return 'Verification Method';
	}

	function verify( $input, \WP_User $user = null, $mode = null ) {
		$check_user = wp_authenticate_username_password( null, $user->user_login, $_POST['password'] );
		if ( is_wp_error( $check_user ) ) {
			throw new \Exception( 'Invalid password' );
		}

		return true;
	}

	function get_verify_popup( \WP_User $user, $data = [] ) {
		$tpl = apply_filters( 'tfa_custom_tpl', $this->get_default_verify_popup_template(), 'pwd_verify_tpl' );

		return View::mustache_render( $tpl, $data );
	}

	function get_default_verify_popup_template() {
		return '<form action="{{form_action}}" method="post" class="tfa-confirm tfa-confirm-pwd">
<div class="tfa-popup-title">Enter your Password
<button type="button" class="tfa-popup-close">&times;</button></div>
<div class="tfa-popup-text">{{{intro}}}</div>
<div class="tfa-code-input-row">
<label>Password</label>
<input type="password" name="password" />
</div>
<input type="hidden" name="action_id" value="{{action_id}}" />
<div class="form-row form-buttons"><button type="submit" class="button">Confirm</button></div>
{{{hidden_fields}}}
</form>';
	}

	function get_activate_popup( \WP_User $user ) {
		// TODO: Implement get_activate_popup() method.
	}

	function get_deactivate_popup( \WP_User $user ) {
		// TODO: Implement get_deactivate_popup() method.
	}

	function get_default_activate_popup_template() {
		// TODO: Implement get_default_activate_popup_template() method.
	}

	function get_default_deactivate_popup_template() {
		// TODO: Implement get_default_deactivate_popup_template() method.
	}
}

