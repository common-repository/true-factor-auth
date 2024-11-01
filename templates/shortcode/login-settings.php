<?php

use TrueFactor\Modules;

/**
 * @var int $user_id
 * @var \TrueFactor\AbstractVerificationHandlerModule[] $handlers
 * @var \TrueFactor\AbstractVerificationHandlerModule[] $user_handlers
 * @var string $selected_handler_id
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! Modules::is_enabled( 'TwoFactorLogin' ) ) {
	return;
}
if ( ! empty( $user_handlers['pwd'] ) ) {
	unset( $user_handlers['pwd'] );
}
if ( ! empty( $user_handlers['non'] ) && \TrueFactor\Options::get_bool( 'login_2fa_required' ) ) {
	// Two-factor authentication is required, so let's hide the "No Verification" option.
	unset( $user_handlers['non'] );
}
if ( ! $user_handlers ) {
	// No handlers available - nothing to show.
	return;
}
$handler_options = [];
foreach ( $user_handlers as $handler_id => $handler ) {
	$handler_options[ $handler_id ] = $handler->get_handler_name();
}
?>
<div class="tfa-user-login-verification-method">
    <form method="post">
        <div class="form-row">
			<?php echo \TrueFactor\Helper\Html::input( 'tfa_login_method', $selected_handler_id, [
				'type'    => 'select',
				'options' => $handler_options,
			] ) ?>
        </div>
        <div class="form-buttons">
            <input type="hidden" name="tfa_login_2fa_set_method" value="1"/>
			<?php wp_nonce_field( 'tfa_login_2fa_set_method' ) ?>
            <button type="submit"><?php echo tf_auth__( 'Save' ) ?></button>
        </div>

    </form>
</div>

