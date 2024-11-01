<?php

/**
 * @var \TrueFactor\Admin\ModuleList $table
 */

$items = [];

/** @var \TrueFactor\AbstractModule $item */
foreach ( $table->items as $item ) {
	$items[ $item::get_module_id() ] = [
		'name' => $item->get_module_name(),
		'deps' => $item->get_dependencies(),
	];
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline">
		<?php echo get_admin_page_title() ?>
    </h1>
    <hr class="wp-header-end"/>

	<?php \TrueFactor\View::showNotices(); ?>

    <div class="tfa-admin-intro">
        <p>Here you can enable or disable True Factor Security modules.</p>
    </div>

    <div>
		<?php $table->views(); ?>
        <div class="clear"></div>
    </div>

    <form action="" method="post">
        <div id="tfa_list">
			<?php $table->display(); ?>
        </div>
    </form>

    <div id="ajax-response"></div>

    <br class="clear"/>

    <script>
        jQuery(function ($) {
            var modules = <?=json_encode( $items )?>;
            var $tbl = $('#tfa_list');

            $tbl.find('input[type=checkbox]')
                .on('change', function (evt) {
                    var id = this.name.replace('i[', '').replace(']', '');
                    var $cb = $(this);
                    var on = $cb.is(':checked');

                    if (on) {
                        // Activate required modules
                        modules[id].deps.forEach(function (did) {
                            var $dcb = $tbl.find('input[type=checkbox][name=i\\[' + did + '\\]]');
                            if (!$dcb.is(':checked')) {
                                $dcb.prop('checked', true);
                                $dcb.trigger('change');
                            }
                        });
                    } else {
                        var deps = [];

                        // Deactivate dependent modules
                        Object.keys(modules).forEach(function (mid) {
                            modules[mid].deps.forEach(function (did) {
                                if (did == id) {
                                    var $mcb = $tbl.find('input[type=checkbox][name=i\\[' + mid + '\\]]');
                                    if ($mcb.is(':checked')) {
                                        deps.push([mid, modules[mid].name, $mcb]);
                                    }
                                }
                            });
                        });

                        if (!deps.length || confirm('The following modules will be disabled:\n\n' +
                            deps.map(function (d) {
                                return '- ' + d[1];
                            }).join('\n') +
                            '\n\nDo you confirm?')) {

                            deps.forEach(function (dep) {
                                dep[2].removeProp('checked');
                                dep[2].trigger('change');
                            });
                        } else {
                            $cb.prop('checked', true);
                            $cb.trigger('change');
                        }
                    }
                })
                .trigger('change');
        });
    </script>
</div>