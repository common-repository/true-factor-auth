<?php

namespace TrueFactor\Admin;

use TrueFactor\Helper\Html;
use TrueFactor\Orm\AccessRule;
use TrueFactor\View;

class RuleList extends AbstractList {

	static $model_class = AccessRule::class;
	static $screen_name = 'tfa_rules';
	static $singular = 'rule';
	static $plural = 'rules';
	static $order_by = 'title';

	public function get_columns() {
		return [
			'cb'             => tf_auth__( 'cb' ),
			'title'          => tf_auth__( 'Title' ),
			'request_url'    => tf_auth__( 'Url' ),
			'request_method' => tf_auth__( 'Request Type' ),
			'status'         => tf_auth__( 'Status' ),
			'handlers'       => tf_auth__( 'Verification methods' ),
			'is_required'    => tf_auth__( 'Required' ),
			'is_editable'    => tf_auth__( 'User Choice' ),
		];
	}

	/**
	 * Define the sortable columns
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return [
			'title'       => [ 'title', false ],
			'request_url' => [ 'request_url', false ],
		];
	}

	/**
	 * Return an associative array listing all the views that can be used
	 * with this table.
	 *
	 * Provides a list of roles and user count for that role for easy
	 * Filtersing of the user table.
	 *
	 * @return array An array of HTML links, one for each view.
	 * @global string $role
	 *
	 * @since  1.3.8
	 *
	 */
	protected function get_views() {
		$status_links = [
			AccessRule::STATUS_ACTIVE   => 'Active',
			AccessRule::STATUS_DISABLED => 'Disabled',
		];

		$counts_by_status = [];
		foreach ( $status_links as $status_id => $status_name ) {
			$counts_by_status[ $status_id ] = AccessRule::count( [
				'where' => $status_id ? [
					'status' => $status_id,
				] : [],
			] );
		}


		$status = $_REQUEST['status'] ?? AccessRule::STATUS_ACTIVE;

		foreach ( $status_links as $s => $link ) {
			$active_attr        = ( $status == $s ) ? ' class="current" aria-current="page"' : '';
			$url                = add_query_arg( 'status', $s );
			$status_links[ $s ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				$url,
				$active_attr,
				tf_auth__( $status_links[ $s ] ),
				$counts_by_status[ $s ] ?? 0
			);
		}

		return $status_links;
	}

	protected function process_bulk_actions() {

		$action = $this->get_bulk_action();

		if ( ! in_array( $action, [ 'enable', 'disable', 'delete' ] ) ) {
			return;
		}

		if ( empty( $_POST['item'] ) || ! is_array( $_POST['item'] ) ) {
			return;
		}

		$i_data = $_POST['item'];

		$ids = array_filter( $i_data, 'is_numeric' );
		if ( ! $ids ) {
			return;
		}

		$objs = AccessRule::all( [
			'where' => [
				'id' => $ids,
			],
		] );

		if ( ! $objs ) {
			return;
		}

		foreach ( $objs as $obj ) {
			try {
				switch ( $action ) {
					case 'enable':
						$obj->status = AccessRule::STATUS_ACTIVE;
						$obj->save();
						break;
					case 'disable':
						$obj->status = AccessRule::STATUS_DISABLED;
						$obj->save();
						break;
					case 'delete':
						$obj->delete();
						break;
				}
			} catch ( \Exception $e ) {
				View::addNotice( $e->getMessage(), 'error' );
			}
		}

		View::addNotice( tf_auth__( sprintf( '%d action(s) updated', count( $objs ) ) ) );
	}

	protected function get_status_filter() {
		$status = $_REQUEST['status'] ?? AccessRule::STATUS_ACTIVE;
		if ( ! in_array( $status, [ AccessRule::STATUS_ACTIVE, AccessRule::STATUS_DISABLED ] ) ) {
			$status = null;
		}

		return $status;
	}

	function get_filter_rules() {
		return [
			'status' => [ 'skip_empty' ],
		];
	}

	function get_filter_values() {
		$values = parent::get_filter_values();
		if ( empty( $values['status'] ) ) {
			$values['status'] = $this->get_status_filter();
		}

		return $values;
	}

	protected function get_bulk_actions() {
		$actions = apply_filters( 'tfa_user_actions_bulk_actions', [
			'enable'  => tf_auth__( 'Enable' ),
			'disable' => tf_auth__( 'Disable' ),
			'delete'  => tf_auth__( 'Delete' ),
		] );

		return $actions;
	}

	// Column rendering.

	/**
	 * @param  array  $item
	 * @param  string  $column_name
	 *
	 * @return mixed|string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'title':
				return Html::tag( 'a', [ 'href' => menu_page_url( 'tfa-action-edit', false ) . "&id={$item['id']}" ], $item[ $column_name ] );
			case 'status':
				return AccessRule::$statuses[ $item[ $column_name ] ];
			case 'request_method':
				return AccessRule::$request_methods[ $item[ $column_name ] ];
			case 'handlers':
				/** @var AccessRule $obj */
				$obj      = $item['_obj'];
				$handlers = $obj->get_handlers();
				$return   = '';
				foreach ( $handlers as $handler_id => $handler ) {
					$return .= '<span>' . $handler->get_handler_name() . '</span>';
				}

				return $return;
			case 'is_editable':
			case 'is_required':
				return empty( $item[ $column_name ] ) ? tf_auth__( 'No' ) : tf_auth__( 'Yes' );
		}

		return parent::column_default( $item, $column_name );
	}
}
