<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<span>
    <a href="<?php echo wp_login_url( $_SERVER['REQUEST_URI'] ) ?>">Log in</a>
</span>