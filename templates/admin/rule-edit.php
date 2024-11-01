<?php

use TrueFactor\Helper\Form;
use TrueFactor\Helper\Html;
use TrueFactor\Modules;
use TrueFactor\View;

/**
 * @var Form $form
 * @var string $nonce_action
 */
$renderer    = $form->getRenderer();
$form_id     = uniqid();
$form_fields = $form->getFields();
?>
<div class="wrap">
    <h1><?php echo get_admin_page_title() ?: tf_auth__( 'Edit Rule' ) ?></h1>
	<?php View::showNotices(); ?>

	<?php
	echo Html::inputErrors( \TrueFactor\Helper\Arr::flatten( $form->getErrorMessages() ) );
	?>

    <form <?php echo Html::attributes( $form->getAttributes() ) ?> id="<?php echo $form_id ?>">
        <h2 class="tfa-form-block-title"><?php echo tf_auth__( 'General' ) ?></h2>
        <div class="tfa-form-block">
            <table class="form-table" role="presentation">
                <tbody>
				<?php foreach ( $form_fields as $field_name => $field_data ) {
					if ( ! empty( $field_data['_group'] ) ) {
						continue;
					}
					echo $renderer->renderElement( $field_name );
				} ?>
                </tbody>
            </table>
        </div>

        <h2 class="tfa-form-block-title"><?php echo tf_auth__( 'Back-end' ) ?></h2>
        <div class="tfa-form-block-desc"><?=tf_auth__('These parameters are used to capture requests on the back-end for checking. Provide at least one parameter.')?></div>
        <div class="tfa-form-block">
            <table class="form-table" role="presentation">
                <tbody>
				<?php foreach ( $form_fields as $field_name => $field_data ) {
					if ( empty( $field_data['_group'] ) || $field_data['_group'] != 'backend' ) {
						continue;
					}
					echo $renderer->renderElement( $field_name );
				} ?>
                </tbody>
            </table>
        </div>

        <h2 class="tfa-form-block-title"><?php echo tf_auth__( 'Front-end' ) ?></h2>
        <div class="tfa-form-block-desc"><?=tf_auth__('These parameters are needed to attach verification popups on the front-end. Only one option is required.')?></div>
        <div class="tfa-form-block">
            <table class="form-table" role="presentation">
                <tbody>
				<?php foreach ( $form_fields as $field_name => $field_data ) {
					if ( empty( $field_data['_group'] ) || $field_data['_group'] != 'frontend' ) {
						continue;
					}
					echo $renderer->renderElement( $field_name );
				} ?>
                </tbody>
            </table>
        </div>

        <h2 class="tfa-form-block-title"><?php echo tf_auth__( 'Verification Methods' ) ?></h2>
        <div class="tfa-form-block">
            <table class="form-table" role="presentation">
                <tbody>
				<?php foreach ( $form_fields as $field_name => $field_data ) {
					if ( empty( $field_data['_group'] ) || $field_data['_group'] != 'handlers' ) {
						continue;
					}
					echo $renderer->renderElement( $field_name );
				} ?>
                </tbody>
            </table>
        </div>

        <p class="submit">
			<?php wp_nonce_field( $nonce_action, $nonce_action ) ?>
            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo tf_auth__( 'Save' ) ?>">
        </p>

        <div class="clear"></div>
    </form>
    <div id="ajax-response"></div>
</div>