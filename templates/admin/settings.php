<?php

/**
 * Settings page.
 *
 * @var array $settings_config
 * @var string $settings_page
 */

?>

<?php include __DIR__ . '/tabs.php' ?>

<div class="wrap">
    <h1><?php echo get_admin_page_title() ?></h1>
	<?php settings_errors() ?>
	<?php if ( ! empty( $settings_config[ $settings_page ]['intro'] ) ) { ?>
        <div class="tfa-admin-settings-page-intro">
			<?php echo $settings_config[ $settings_page ]['intro'] ?>
        </div>
	<?php } ?>
    <form method="post" action="options.php">
		<?php settings_fields( $settings_page ); ?>
		<?php do_settings_sections( $settings_page ); ?>
		<?php submit_button(); ?>
    </form>
</div>