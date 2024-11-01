<?php

namespace TrueFactor\Admin;

use TrueFactor\AbstractModule;
use TrueFactor\Helper\Html;
use TrueFactor\Module\AdminSettingsModule;
use TrueFactor\Modules;
use TrueFactor\OptionalModule;
use TrueFactor\View;

class ModuleList extends AbstractList {

	static $screen_name = 'tfa_modules';
	static $singular = 'module';
	static $plural = 'modules';

	public function get_columns() {
		return [
			'status' => tf_auth__( 'Enable' ),
			'title'  => tf_auth__( 'Module' ),
			'group'  => tf_auth__( 'Group' ),
		];
	}

	/**
	 * Define the sortable columns
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return [];
	}

	function prepare_items() {
		$this->process_bulk_actions();

		$per_page = $this->get_items_per_page( static::$plural . '_per_page', 20 );
		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = [ $columns, $hidden, $sortable ];

		$this->items = array_filter( AdminSettingsModule::instance()->get_modules(), function ( $module ) {
			return $module->is_optional();
		} );

		uasort( $this->items, function ( $m1, $m2 ) {
			/**
			 * @var AbstractModule $m1
			 * @var AbstractModule $m2
			 */

			if ( $m1->get_module_group() < $m2->get_module_group() ) {
				return - 1;
			}

			if ( $m1->get_module_group() > $m2->get_module_group() ) {
				return 1;
			}

			if ( $m1->get_position() < $m2->get_position() ) {
				return - 1;
			}

			return (int) $m1->get_position() > $m2->get_position();
		} );

		$this->set_pagination_args( [
			'total_items' => count( $this->items ),
			'per_page'    => $per_page,
		] );
	}

	protected function process_bulk_actions() {
		if ( empty( $_POST['i'] ) || ! is_array( $_POST['i'] ) ) {
			return;
		}

		foreach ( $_POST['i'] as $mid => $status ) {
			$module = Modules::get( $mid );
			if ( $status ) {
				AdminSettingsModule::instance()->enable_module( $module );
			} else {
				AdminSettingsModule::instance()->disable_module( $module );
			}
		}

		View::addNotice( 'Changes applied' );

		wp_safe_redirect( $_SERVER['REQUEST_URI'], 303 );
	}

	/**
	 * Display the bulk actions dropdown.
	 *
	 * @param string $which The location of the bulk actions: 'top' or 'bottom'.
	 *                      This is designated as optional for backward compatibility.
	 *
	 * @since 3.1.0
	 *
	 */
	protected function bulk_actions( $which = '' ) { ?>
        <button type="submit" class="button"><?php echo tf_auth__( 'Apply' ) ?></button>
		<?php
	}

	// Column rendering.

	/**
	 * @param OptionalModule $item
	 * @param string $column_name
	 *
	 * @return mixed|string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'title':

				$text = '<b>' . $item->get_module_name() . '</b>'
				        . '<div>' . $item->get_module_desc() . '</div>';

				if ( $item->is_unavailable ) {
					return $text . '<div class="tfa-admin-module-unavailable">&bull; ' . tf_auth__( 'Not available in your version.' ) . ' ' . sprintf( tf_auth__( '<a href="%s">Upgrade to PRO?</a>' ), TRUE_FACTOR_UPGRADE_LINK ) . '</div>';
				}

				return $text;

				$deps = $item->get_dependencies();

				if ( ! $deps ) {
					return $text;
				}

				$return = [];
				foreach ( $deps as $cls ) {
					$return[] = Modules::get( $cls )->get_module_name();
				}

				return $text . '<div class="tfa-module-deps">Requires: ' . join( ', ', $return ) . '</div>';
			case 'status':
				if ( $item->is_unavailable ) {
					return '<input type="checkbox" disabled />';
				}

				$name = 'i[' . $item->get_module_id() . ']';

				return Html::hidden( $name, 0 )
				       . Html::checkbox( $name, $item->is_enabled() );
			case 'group':
				return $item->get_module_group();
		}

		return '?';
	}
}