<?php

namespace K7g\WpOrm;

use wpdb;

/**
 * This class provides handy methods for database operations.
 *
 * @package AkWallet
 */
class Orm implements \ArrayAccess {

	/** @var string Table name prefix. Added after Wordpress prefix (usually "wp_"). If you do not want additional prefix, leave it empty. */
	static $prefix = '';

	/** @var string Table name. If empty, table name will be suggested from the class name. */
	static $table;

	/** @var string Primary key name */
	static $pk = 'id';

	/**
	 * @var array Table definition. Keys are column names, values are column definitions:
	 * [column_name => [type, length, type_ext, default, nullable, extra]]
	 * 0 - type: Field type, e.g MEDIUMINT or LONGTEXT.
	 * 1 - length: For integer and string types, defines field length; For floating point types - length and precision; For enumerable - list of values.
	 * 2 - type_ext: For numeric types indicates that value is unsigned. For other types it is not supported (but when entered, will be added to generated SQL after the length).
	 * 3 - default: Default value, e.g ''. For non-numeric field types default values will be escaped.
	 * 4 - nullable: If true, then field will be declared as NULLABLE
	 * 5 - extra: Any additional statement, e.g 'AUTO_INCREMENT' or 'ON UPDATE CURRENT_TIMESTAMP'
	 * @example
	 * ```php
	 * [
	 *  'id' => ['mediumint', null, 1, null, null, 'AUTO_INCREMENT'],
	 *  'name' => ['char', 2, null, ''],
	 *  'inv' => ['varchar', 2, 'INVALID', 'default_value', true, 'AGAIN_INVALID']
	 * ]
	 * ```
	 * with given definition, selfCreate method will produce the following SQL:
	 * CREATE TABLE <table> id mediumint unsigned not null AUTO_INCREMENT, name char(2) not null default '', inv varchar(2) INVALID DEFAULT 'default_value' AGAIN_INVALID
	 *
	 */
	static $cols = [];

	static function addDbPrefix( $tableName ) {
		/** @var wpdb */
		global $wpdb;

		return $wpdb->base_prefix . $tableName;
	}

	static function getTableName() {
		if ( static::$table ) {
			$table = static::$table;
		} else {
			$parts = explode( '\\', get_called_class() );
			$table = strtolower( array_pop( $parts ) );
		}

		return self::addDbPrefix( static::$prefix . $table );
	}

	/**
	 * @param $sql
	 *
	 * @return $this|null
	 */
	static function one( $sql ) {
		if ( ! is_string( $sql ) ) {
			$sql = static::buildSelect( $sql );
			$all = static::all( $sql );
		}

		return empty( $all[0] ) ? null : $all[0];
	}

	/**
	 * @param $id
	 *
	 * @return $this|null
	 */
	static function oneById( $id ) {
		return static::one( [
			'where' => [
				static::$pk => $id,
			],
		] );
	}

	static function allIndexed( $sql = [], $options = [] ) {
		$options['index_by'] = static::$pk;

		return static::all( $sql, $options );
	}

	/**
	 * @param array $sql
	 *
	 * @param array $options
	 *
	 * @return $this[]
	 */
	static function all( $sql = [], $options = [] ) {
		$results = self::select( $sql, $options );

		$list = [];
		if ( ! empty( $options['index_by'] ) ) {
			foreach ( $results as $key => $result ) {
				$list[ $key ] = new static( $result, false );
			}
		} else {
			foreach ( $results as $result ) {
				$list[] = new static( $result, false );
			}
		}

		return $list;
	}

	static function count( $sql = [] ) {

		if ( ! is_string( $sql ) ) {
			if ( empty( $sql['columns'] ) ) {
				$sql['columns'] = 'COUNT(*)';
			}
		}

		return static::cell( $sql );
	}

	static function cell( $sql = [] ) {
		/** @var wpdb */
		global $wpdb;

		if ( ! is_string( $sql ) ) {
			$sql = static::compileSelect( self::buildSelect( $sql ) );
		}

		return $wpdb->get_var( $sql );
	}

	static function col( $sql = [] ) {
		/** @var wpdb */
		global $wpdb;

		if ( ! is_string( $sql ) ) {
			$sql = static::compileSelect( self::buildSelect( $sql ) );
		}

		return $wpdb->get_col( $sql );
	}

	static function pairs( $keyField, $valueField, $sql = [] ) {
		if ( is_array( $sql ) ) {
			if ( empty( $sql['columns'] ) ) {
				$sql['columns'] = "{$keyField}, {$valueField}";
			}
		}
		$results = static::select( $sql );
		$pairs   = [];
		foreach ( $results as $item ) {
			$pairs[ $item[ $keyField ] ] = $item[ $valueField ];
		}

		return $pairs;
	}

	static function select( $sql, $options = [] ) {
		/** @var wpdb */
		global $wpdb;

		if ( ! is_string( $sql ) ) {
			$sql = static::buildSelect( $sql );
			$sql = static::compileSelect( $sql );
		}

		$results = $wpdb->get_results( $sql, ARRAY_A );
		if ( empty( $options['index_by'] ) ) {
			return $results;
		}
		$list = [];
		foreach ( $results as $row ) {
			$list[ $row[ $options['index_by'] ] ] = $row;
		}

		return $list;
	}

	static function query( $sql ) {
		/** @var wpdb */
		global $wpdb;

		return $wpdb->query( $sql );
	}

	static function lastQuery() {
		/** @var wpdb */
		global $wpdb;

		return $wpdb->last_query;
	}

	static function lastError() {
		/** @var wpdb */
		global $wpdb;

		return $wpdb->last_error;
	}

	static function startTransaction() {
		self::query( 'START TRANSACTION' );
	}

	static function commitTransaction() {
		self::query( 'COMMIT' );
	}

	static function rollbackTransaction() {
		self::query( 'ROLLBACK' );
	}

	static function escape( $str ) {
		/** @var wpdb $wpdb */
		global $wpdb;

		if ( is_bool( $str ) ) {
			$str = ( $str === false ) ? 0 : 1;
		} elseif ( is_null( $str ) ) {
			$str = 'NULL';
		}

		return "'" . $wpdb->remove_placeholder_escape( $wpdb->_real_escape( $str ) ) . "'";
	}

	/**
	 * Builds SQL query from given params.
	 * Does not perform any security checks.
	 *
	 * @param $sql
	 *
	 * @return mixed|string
	 */
	static function buildSelect( $sql = [] ) {

		if ( is_string( $sql ) ) {
			return $sql;
		}

		$sql = array_replace( [
			'from' => static::getTableName(),
		], $sql );

		return $sql;
	}

	static function compileSelect( $sqlParts ) {
		$columns = '*';
		if ( ! empty( $sqlParts['columns'] ) ) {
			if ( is_string( $sqlParts['columns'] ) ) {
				$columns = $sqlParts['columns'];
			} elseif ( is_array( $sqlParts['columns'] ) ) {
				$columns = [];
				foreach ( $sqlParts['columns'] as $tableAlias => $tableColumns ) {
					if ( is_string( $tableColumns ) ) {
						$columns[] = $tableColumns;
					} elseif ( is_array( $tableColumns ) ) {
						if ( is_int( $tableAlias ) ) {
							array_push( $columns, ...$tableColumns );
						} else {
							foreach ( $tableColumns as $colAlias => $colName ) {
								$columns[] = "{$tableAlias}.{$colName}" . ( ! is_int( $colAlias ) ? " AS {$colAlias}" : '' );
							}
						}
					}
				}
				$columns = join( ',', $columns );
			}
		}

		$sql = "SELECT {$columns} FROM " . $sqlParts['from'];

		if ( ! empty( $sqlParts['join'] ) ) {
			foreach ( $sqlParts['join'] as $joinAlias => $joinDef ) {
				$joinAlias = is_int( $joinAlias ) ? '' : "AS {$joinAlias}";
				$joinMode  = mb_strtoupper( empty( $joinDef[2] ) ? '' : $joinDef[2] );
				$sql       .= " {$joinMode} JOIN {$joinDef[0]} {$joinAlias} ON {$joinDef[1]}";
			}
		}

		if ( ! empty( $sqlParts['where'] ) ) {
			$where = [];
			foreach ( $sqlParts['where'] as $k => $v ) {
				if ( is_int( $k ) ) {
					// Condition is given as raw string, e.g `"LENGTH(field_name) < 10"`
					$where[] = $v;
				} elseif ( is_string( $k ) ) {
					// Condition is given as key-value pair, e.g `"field_name" => "field_value"`
					$k        = trim( $k );
					$operator = '=';
					foreach ( [ '=', '>', '<', 'LIKE', 'REGEXP' ] as $o ) {
						if ( mb_strtoupper( mb_substr( $k, - strlen( $o ) ) ) == $o ) {
							$operator = '';
							break;
						}
					}
					if ( is_array( $v ) ) {
						$where[] = $k . ' IN (' . implode( ',', array_map( function ( $v ) {
								return self::escape( $v );
							}, $v ) ) . ')';
					} else {
						if ( $v === null ) {
							$where[] = "{$k} IS NULL";
						} else {
							$where[] = $k . $operator . self::escape( $v );
						}
					}
				}
			}
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		if ( ! empty( $sqlParts['group_by'] ) ) {
			$sql .= ' GROUP BY ' . $sqlParts['group_by'];
		}

		if ( ! empty( $sqlParts['order_by'] ) ) {
			$sql .= ' ORDER BY ' . $sqlParts['order_by'];
		}

		if ( ! empty( $sqlParts['limit'] ) ) {
			$sql .= ' LIMIT ';

			if ( ! empty( $sqlParts['offset'] ) ) {
				$sql .= $sqlParts['offset'] . ', ';
			}

			$sql .= $sqlParts['limit'];
		}

		return $sql;
	}

	// Object-level methods.

	protected $_initialData = [];

	function pk() {
		return $this->{static::$pk};
	}

	protected $_exists = false;

	function exists() {
		return $this->_exists;
	}

	/**
	 * Orm constructor.
	 *
	 * @param array|\stdClass $data
	 * @param bool $isNew
	 */
	function __construct( $data = [], $isNew = true ) {
		if ( ! is_array( $data ) ) {
			$data = get_object_vars( $data );
		}
		if ( ! $isNew ) {
			$this->_initialData = $data;
			$this->_exists      = true;
		}
		$this->setData( $data );
	}

	function save() {

		$data = $this->getSaveableData();
		if ( ! $data ) {
			return true;
		}

		if ( $this->_exists ) {
			$result = self::update( $data, [ static::$pk => $this->pk() ] );
		} else {
			$result              = static::insert( $data );
			$this->{static::$pk} = $result;
			$this->_exists       = true;
		}

		if ( $result ) {
			$this->reload();
		}

		return $result;
	}

	static function insert( $data ) {
		/** @var wpdb */
		global $wpdb;

		if ( array_key_exists( 'ctime', static::$cols ) ) {
			$data['ctime'] = date( 'c' );
		}

		$result = $wpdb->insert( static::getTableName(), $data );
		if ( ! $result ) {
			trigger_error( print_r( $data, true ), E_USER_WARNING );
			throw new \Exception( 'Insertion failed' );
		}

		return $wpdb->insert_id;
	}

	static function update( $data, $where ) {
		/** @var wpdb */
		global $wpdb;

		if ( ! $data ) {
			return true;
		}

		if ( array_key_exists( 'mtime', static::$cols ) ) {
			$data['mtime'] = date( 'c' );
		}

		return $wpdb->update( static::getTableName(), $data, $where );
	}

	function getChangedData() {
		return array_diff_assoc( $this->toArray(), $this->_initialData );
	}

	function getSaveableData() {
		$saveable = [];
		$data     = $this->getData();
		foreach ( static::$cols as $col => $def ) {
			if ( ! array_key_exists( $col, $data ) ) {
				continue;
			}
			if ( array_key_exists( $col, $this->_initialData ) ) {
				if ( $data[ $col ] == $this->_initialData[ $col ] ) {
					// If field value not changed, skip it.
					continue;
				}
			}
			$type     = $def[0];
			$nullable = empty( $def[4] ) ? false : $def[4];
			if ( empty( $data[ $col ] ) ) {
				$saveable[ $col ] = $nullable ? null : '';
				continue;
			}
			switch ( $type ) {
				case 'json':
					$saveable[ $col ] = json_encode( $data[ $col ] );
					break;
				case 'tinyint':
				case 'smallint':
				case 'int':
				case 'mediumint':
				case 'bigint':
					$saveable[ $col ] = (int) ( $data[ $col ] );
					break;
				case 'set':
					$saveable[ $col ] = is_array( $data[ $col ] ) ? implode( ',', $data[ $col ] ) : $data[ $col ];
					break;
				case 'decimal':
				case 'float':
					$saveable[ $col ] = (float) $data[ $col ];
					break;
				case 'char':
				case 'varchar':
				case 'datetime':
				case 'timestamp':
				case 'enum':
				case 'mediumtext':
				case 'text':
				case 'longtext':
				default:
					$saveable[ $col ] = $data[ $col ];
			}
		}

		return $saveable;
	}

	function getData() {
		return array_intersect_key( $this->toArray(), static::$cols );
	}

	function toArray() {
		return get_object_vars( $this );
	}

	function offsetExists( $offset ) {
		return property_exists( $this, $offset );
	}

	function offsetGet( $offset ) {
		return $this->$offset;
	}

	function offsetSet( $offset, $value ) {
		$this->$offset = $value;
	}

	function offsetUnset( $offset ) {
		$this->$offset = null;
	}

	function setData( $data ) {
		foreach ( $data as $k => $v ) {
			if ( ! empty( static::$cols[ $k ] ) && static::$cols[ $k ][0] == 'json' && is_string( $v ) ) {
				$v = @json_decode( $v, true );
			}
			$this->$k = $v;
		}
	}

	function reload() {
		if ( ! $this->pk() ) {
			trigger_error( 'Unable to reload object without primary key', E_USER_WARNING );

			return;
		}

		$data = static::oneById( $this->pk() );
		$this->setData( $data->toArray() );
	}

	function delete() {
		if ( ! $this->pk() || ! is_scalar( $this->pk() ) ) {
			throw new \Exception( 'Deletion failed: invalid ID' );
		}

		return self::query( "DELETE FROM " . static::getTableName() . " WHERE " . static::$pk . "=" . self::escape( $this->pk() ) );
	}
}