<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * @var int $user_id
 * @var \TrueFactor\AbstractVerificationHandlerModule[] $handlers
 * @var \TrueFactor\AbstractVerificationHandlerModule[] $user_handlers
 */

?>
<div class="tfa-user-verification-types">
    <table>

		<?php foreach ( $handlers as $handler_id => $handler ) {
			if ( ! $handler->is_switchable() ) {
				// The "Password" Verification method is always available.
				continue;
			}
			?>
            <tr>
                <td><?php echo $handler->get_handler_name() ?></td>
                <td>
					<?php if ( isset( $user_handlers[ $handler_id ] ) ) { ?>
                        <label>
                            <input type="radio" checked/>
							<?php echo tf_auth__( 'Enabled' ) ?>
                        </label>
                        <label class="tfa-popup-link"
                               data-popup-url="<?php echo admin_url( "admin-ajax.php?action=tfa_enable_handler&handler_id={$handler_id}&mode=off" ) ?>">
                            <input type="radio"/>
							<?php echo tf_auth__( 'Disabled' ) ?>
                        </label>
					<?php } else { ?>
                        <label class="tfa-popup-link"
                               data-popup-url="<?php echo admin_url( "admin-ajax.php?action=tfa_enable_handler&handler_id={$handler_id}" ) ?>">
                            <input type="radio"/>
							<?php echo tf_auth__( 'Enabled' ) ?>
                        </label>
                        <label>
                            <input type="radio" checked/>
							<?php echo tf_auth__( 'Disabled' ) ?>
                        </label>
					<?php } ?>
                </td>
            </tr>
		<?php } ?>
    </table>
</div>
