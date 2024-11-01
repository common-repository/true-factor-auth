<?php

use TrueFactor\Orm\AccessRule;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * @var int $user_id
 * @var string[] $verification_types
 * @var string[] $user_verification_types
 * @var string[] $user_settings
 * @var AccessRule[] $user_actions
 */
?>

<div class="tfa-password-reset">
    <form action="" method="post">
        <div class="form-row"></div>
        <div class="form-row form-buttons">
            <input type="tel" name="tel" class="tfa-reset-pass-tel" placeholder="+..."/>
            <input type="hidden" name="tfa_password_reset" value="1">
            <button type="submit"><?php echo tf_auth__( 'Request new password' ) ?></button>
        </div>
    </form>
</div>