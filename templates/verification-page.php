<?php
/**
 * @var int $user_id
 * @var string $popup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<!DOCTYPE html>

<html class="no-js" <?php language_attributes(); ?>>

<head>

    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

	<?php
	@wp_enqueue_scripts();
	@wp_print_head_scripts();
	@wp_print_styles();
	?>

</head>

<body <?php body_class(); ?>>

<main id="site-content" role="main">

	<?php \TrueFactor\View::showNotices() ?>

    <div class="tfa-popup-wrapper tfa-modal">
        <div class="tfa-popup">
			<?php echo $popup ?>
        </div>
    </div>

	<?php wp_footer(); ?>

</body>
</html>