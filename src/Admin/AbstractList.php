<?php

namespace TrueFactor\Admin;

use TrueFactor\Helper\Filter;
use TrueFactor\Orm;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

abstract class AbstractList extends \WP_List_Table {

	static $model_class;
	static $screen_name;
	static $singular;
	static $plural;
	static $order_by = null;
	static $order_dir = null;

	public $date_time_format;

	protected $_actions;

	public function __construct() {
		parent::__construct( [
			'singular' => static::$singular,
			'plural'   => static::$plural,
			'ajax'     => false,
			'screen'   => static::$screen_name,
		] );
		$this->date_time_format = get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i:s' );
	}

	/**
	 * Get a list of CSS classes for the WP_List_Table table tag.
	 *
	 * @return string[] Array of CSS classes for the table tag.
	 * @since 3.1.0
	 *
	 */
	protected function get_table_classes() {
		return array_merge( parent::get_table_classes(), [ static::$screen_name ] );
	}

	/**
	 * Prepare the items for the table to process
	 *
	 * @return Void
	 */
	public function prepare_items() {
		$this->process_bulk_actions();

		$per_page = $this->get_items_per_page( static::$plural . '_per_page', 20 );
		$paged    = $this->get_pagenum();
		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$query_args = [
			'where' => $this->prepare_filter_values( $this->get_filter_values() ),
		];
		$list_args  = [
			'limit'    => $per_page,
			'offset'   => ( $paged - 1 ) * $per_page,
			'order_by' => $this->get_order_by(),
		];


		// Query the user IDs for this page
		/** @var Orm $model_class */
		$model_class = static::$model_class;
		$list        = $model_class::all( array_replace( $query_args, $list_args ) );
		$data        = [];
		foreach ( $list as $obj ) {
			$data[] = $obj->toArray() + [
					'actions' => '',
					'_obj'    => $obj,
				];
		}

		$this->_column_headers = [ $columns, $hidden, $sortable ];
		$this->items           = $data;
		$this->set_pagination_args( [
			'total_items' => $model_class::count( $query_args ),
			'per_page'    => $per_page,
		] );
	}

	protected function process_bulk_actions() {
		// Do anything.
	}

	function get_order_by() {
		if ( ! isset( $_REQUEST['orderby'] ) ) {
			$_REQUEST['orderby'] = $_GET['orderby'] = static::$order_by;
			$_REQUEST['order']   = $_GET['order'] = static::$order_dir;
		}

		$sortable = $this->get_sortable_columns();
		foreach ( $sortable as $name => $props ) {
			if ( $props[0] == $_REQUEST['orderby'] ) {
				$order_dir = $props[1] ? 'desc' : 'asc';
				if ( ! empty( $_REQUEST['order'] ) && in_array( $_REQUEST['order'], [ 'asc', 'desc' ] ) ) {
					$order_dir = $_REQUEST['order'];
				}

				return ( empty( $props[2] ) ? $_REQUEST['orderby'] : $props[2] ) . ' ' . $order_dir;
			}
		}

		return null;
	}

	/**
	 * Output 'no users' message.
	 *
	 * @since 3.1.0
	 */
	public function no_items() {
		echo tf_auth__( "No " . static::$plural . " found." );
	}

	protected function prepare_filter_values( $values ) {
		return $values;
	}

	protected function get_filter_values() {
		$filter_rules = $this->get_filter_rules();
		$where        = [];
		foreach ( $filter_rules as $field_name => $filter_def ) {
			if ( array_key_exists( $field_name, $_REQUEST ) ) {
				try {
					$where[ $field_name ] = Filter::filterValue( $filter_def, $_REQUEST[ $field_name ] );
				} catch ( Filter\ValueInvalidException $e ) {
					// pass.
				}
			}
		}

		return $where;
	}

	protected function get_filter_fields() {
		return [];
	}

	protected function get_filter_rules() {
		return [];
	}

	/**
	 * Define which columns are hidden
	 *
	 * @return string[]
	 */
	protected function get_hidden_columns() {
		return [];
	}

	// Rendering.

	/**
	 * Display the bulk actions dropdown.
	 *
	 * @param  string  $which  The location of the bulk actions: 'top' or 'bottom'.
	 *                      This is designated as optional for backward compatibility.
	 *
	 * @since 3.1.0
	 *
	 */
	protected function bulk_actions( $which = '' ) {
		if ( $this->_actions === null ) {
			$this->_actions = $this->get_bulk_actions();
			/**
			 * Filters the list table Bulk Actions drop-down.
			 *
			 * The dynamic portion of the hook name, `$this->screen->id`, refers
			 * to the ID of the current screen, usually a string.
			 *
			 * This filter can currently only be used to remove bulk actions.
			 *
			 * @param  string[]  $actions  An array of the available bulk actions.
			 *
			 * @since 3.5.0
			 *
			 */
			$this->_actions = apply_filters( "bulk_actions-{$this->screen->id}", $this->_actions ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			$two            = '';
		} else {
			$two = '2';
		}

		if ( ! $this->_actions ) {
			return;
		}

		echo '<label for="bulk-action-selector-' . esc_attr( $which ) . '" class="screen-reader-text">' . __( 'Select bulk action' ) . '</label>';
		echo '<select name="action' . $two . '" id="bulk-action-selector-' . esc_attr( $which ) . "\">\n";
		echo '<option value="-1">' . __( 'Bulk Actions' ) . "</option>\n";

		foreach ( $this->_actions as $name => $title ) {
			$class = 'edit' === $name ? ' class="hide-if-no-js"' : '';

			echo "\t" . '<option value="' . $name . '"' . $class . '>' . $title . "</option>\n";
		}

		echo "</select>\n"; ?>
        <button type="submit" class="button" name="doaction" value="<?php echo $two ?>"><?php echo tf_auth__( 'Apply' ) ?></button>
		<?php
	}

	protected function get_bulk_action() {
		return $_REQUEST[ 'action' . ( $_REQUEST['doaction'] ?? '' ) ] ?? null;
	}


	/**
	 * Generate the table navigation above or below the table
	 *
	 * @param  string  $which
	 *
	 * @since 3.1.0
	 */
	protected function display_tablenav( $which ) {
		if ( 'top' === $which ) {
			wp_nonce_field( 'bulk-' . $this->_args['plural'] );
		}
		?>
        <div class="tablenav <?php echo esc_attr( $which ); ?>">

			<?php if ( $this->has_items() ) : ?>
                <div class="alignleft actions bulkactions">
					<?php $this->bulk_actions( $which ); ?>
                </div>
			<?php
			endif;
			$this->extra_tablenav( $which );
			if ( $which != 'top' ) {
				$this->pagination( $which );
			}
			?>

            <br class="clear"/>
        </div>
		<?php
	}

	// Column rendering.

	/**
	 * Define what data to show on each column of the table
	 *
	 * @param  array  $item  Data
	 * @param  string  $column_name  - Current column name
	 *
	 * @return Mixed
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'ctime':
			case 'mtime':
				return get_date_from_gmt( $item[ $column_name ], $this->date_time_format );
			case 'user':
				$user = $item['_userdata'] ?? $item['_userdata'] = get_userdata( $item['user_id'] );
				if ( ! $user ) {
					return '';
				}
				$edit_link = esc_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), get_edit_user_link( $user->ID ) ) );

				return '<a href="' . $edit_link . '">' . $user->display_name . '</a>';
			default:
				return $item[ $column_name ] ?? '';
		}
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="item[]" value="%s" />', $item['id'] );
	}
}
