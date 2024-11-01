<?php

/**
 * Settings page.
 *
 * @var \TrueFactor\Helper\Form $form
 * @var array $sections
 * @var array $admin_pages
 * @var string $admin_page
 */

uasort( $sections, function ( $s1, $s2 ) {
	if ( ! isset( $s1['position'] ) ) {
		if ( ! isset( $s2['position'] ) ) {
			return 0;
		}

		return $s2['position'] > 0 ? - 1 : 1;
	}

	if ( ! isset( $s2['position'] ) ) {
		return $s1['position'] > 0 ? 1 : - 1;
	}

	return $s1['position'] > $s2['position'] ? - 1 : (int) ( $s1['position'] < $s2['position'] );
} );

$fields_count = 0;

?>

<?php include __DIR__ . '/tabs.php' ?>

<div class="wrap">

    <h1><?php echo get_admin_page_title() ?></h1>

	<?php \TrueFactor\View::showNotices() ?>

	<?php if ( ! empty( $admin_pages[ $admin_page ]['intro'] ) ) { ?>
        <div class="tfa-admin-settings-page-intro">
			<?php echo $admin_pages[ $admin_page ]['intro'] ?>
        </div>
	<?php } ?>

    <form method="post">

		<?php foreach ( $sections as $section_id => $section ) { ?>
            <div class="tfa-admin-section" data-section-id="<?php echo $section_id ?>">
				<?php if ( ! empty( $section['title'] ) ) { ?>
                    <h2><?php echo $section['title'] ?></h2>
				<?php } ?>
				<?php if ( ! empty( $section['intro'] ) ) { ?>
                    <div class="tfa-admin-intro"><?php echo $section['intro'] ?></div>
				<?php } ?>
				<?php if ( ! empty( $section['fields'] ) ) { ?>
                    <table class="form-table" role="presentation">
                        <tbody>
						<?php foreach ( $section['fields'] as $field_name => $field ) {
							echo $form->getRenderer()->renderElement( $field_name );
							$fields_count ++;
						}
						?>
                        </tbody>
                    </table>
				<?php } ?>
            </div>
		<?php } ?>

		<?php if ( $fields_count ) { ?>
			<?php wp_nonce_field( 'tfau_settings', 'tfau_settings_nonce' ) ?>
            <button type="submit" class="button button-primary"><?php echo tf_auth__( 'Apply' ) ?></button>
		<?php } ?>
    </form>

	<?php include __DIR__ . '/footer-support.php' ?>

</div>