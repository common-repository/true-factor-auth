<?php

/**
 * @var \TrueFactor\Admin\RuleList $table
 */

?>
<div class="wrap">
    <h1 class="wp-heading-inline">
		<?php echo get_admin_page_title() ?>
    </h1>
    <a href="<?php menu_page_url( 'tfa-action-edit' ) ?>" class="page-title-action"><?php echo tf_auth__( 'Add new' ) ?></a>
    <hr class="wp-header-end"/>

	<?php \TrueFactor\View::showNotices(); ?>

    <div>
		<?php $table->views(); ?>
        <div class="clear"></div>
    </div>

    <form action="" method="post">
        <div id="ak_list">
			<?php $table->display(); ?>
        </div>
    </form>

    <div id="ajax-response"></div>
    <br class="clear"/>
    <script>
        jQuery(function ($) {
            $('#ak_list_form').on('submit', function () {
                $('#ak_list').find('input').clone().appendTo($('#ak_list_form_clone_inputs'));
            });
        });
    </script>
</div>