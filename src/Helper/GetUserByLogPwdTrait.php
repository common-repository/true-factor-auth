<?php

namespace TrueFactor\Helper;

trait GetUserByLogPwdTrait {

	function get_user_by_login_password() {
		if ( empty( $_POST['log'] ) || empty( $_POST['pwd'] )
		     || ! is_string( $_POST['log'] )
		     || ! is_string( $_POST['pwd'] )
		) {
			$this->return_error( "Enter login and password" );
		}

		$user = wp_authenticate_username_password( null, $_POST['log'], $_POST['pwd'] );

		if ( is_wp_error( $user ) ) {
			$user = wp_authenticate_email_password( null, $_POST['log'], $_POST['pwd'] );
			if ( is_wp_error( $user ) ) {
				$this->return_error( $user->get_error_message() );
			}
		}
		if ( ! $user->ID ) {
			$this->return_error( "Enter login and password" );
		}

		return $user;
	}
}