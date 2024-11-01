<?php

namespace TrueFactor\Helper;

use Exception;
use wpdb;

/**
 * Class DbMigration
 * Provides handy methods for database migrations for Wordpress plugins.
 *
 * @package TrueFactor
 * @author k7g isalmin@gmail.com
 */
class DbMigration {

	static $floatTypes = [ 'decimal', 'float', 'double' ];
	static $integerTypes = [ 'tinyint', 'smallint', 'int', 'mediumint', 'bigint' ];
	static $stringTypes = [ 'varchar', 'char', 'text', 'mediumtext', 'longtext' ];

	/**
	 * Add and/or remove columns.
	 *
	 * @param  string  $tableName
	 * @param  array  $colsToAdd  Associative array where keys are column names and values are column definitions:
	 * [column_name => [type, length, type_ext, default, nullable, extra]]
	 * 0 - type: Field type, e.g MEDIUMINT or LONGTEXT.
	 * 1 - length: For integer and string types, defines field length; For floating point types - length and precision; For enumerable - list of values.
	 * 2 - type_ext: For numeric types indicates that value is unsigned. For other types it is not supported (but when entered, will be added to generated SQL after the length).
	 * 3 - default: Default value, e.g ''. For non-numeric field types default values will be escaped.
	 * 4 - nullable: If true, then field will be declared as NULLABLE
	 * 5 - extra: Any additional statement, e.g 'AUTO_INCREMENT' or 'ON UPDATE CURRENT_TIMESTAMP'
	 * @param  array  $colsToRemove
	 * @param  array  $colsToChange
	 *
	 * @return void
	 * @example
	 * ```php
	 * [
	 *  'id' => ['mediumint', null, 1, null, null, 'AUTO_INCREMENT'],
	 *  'name' => ['char', 2, null, ''],
	 *  'inv' => ['varchar', 2, 'INVALID', 'default_value', true, 'AGAIN_INVALID']
	 * ]
	 * ```
	 *
	 */
	static function alterTable( $tableName, $colsToAdd, $colsToRemove = [], $colsToChange = [] ) {
		/** @var wpdb $wpdb */
		global $wpdb;

		$colsSqls = [];
		foreach ( $colsToRemove as $colName ) {
			$colsSqls[] = "DROP COLUMN `$colName`";
		}
		foreach ( $colsToAdd as $colName => $def ) {
			if ( is_array( $def ) ) {
				$def = self::generateColumnDefSql( $def );
			}
			if ( is_int( $colName ) ) {
				$colsSqls[] = $def;
			} else {
				$colsSqls[] = "ADD `$colName` $def";
			}
		}
		foreach ( $colsToChange as $colName => $def ) {
			if ( is_array( $def ) ) {
				$def = self::generateColumnDefSql( $def );
			}
			$colsSqls[] = "CHANGE `$colName` `$colName` $def";
		}

		if ( ! $colsSqls ) {
			// Nothing to do.
			return;
		}

		$wpdb->query( "ALTER TABLE `{$tableName}` " . join( ',', $colsSqls ) );
	}

	/**
	 * Remove columns.
	 *
	 * @param  string  $tableName
	 * @param  string[]  $cols  Names of columns to remove
	 */
	static function dropColumns( $tableName, $cols ) {
		self::alterTable( $tableName, [], $cols );
	}

	/**
	 * @param $name
	 * @param  array  $cols  Columns definition. See $colsToAdd parameter of the DbMigration::alterTable method.
	 * @param  bool  $force
	 *
	 * @return bool
	 */
	static function createTable( $name, $cols, $force = false ) {
		/** @var wpdb $wpdb */
		global $wpdb;

		$collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';

		if ( self::tableExists( $name ) ) {
			if ( ! $force ) {
				return false;
			}
			$wpdb->query( "DROP TABLE {$name}" );
		}

		$colsSqls = [];
		foreach ( $cols as $colName => $def ) {
			if ( is_int( $colName ) ) {
				$colsSqls[] = $def;
			} else {
				if ( is_array( $def ) ) {
					$def = self::generateColumnDefSql( $def );
				}
				$colsSqls[] = "`{$colName}` {$def}";
			}
		}

		$wpdb->query( "CREATE TABLE {$name} (" . join( ',', $colsSqls ) . ") ENGINE=InnoDB {$collate}" );

		return true;
	}

	static function createIndex( $table, $fields, $unique = false, $name = null ) {
		/** @var wpdb $wpdb */
		global $wpdb;

		$tableNameEscaped = "`$table`";
		if ( ! is_array( $fields ) ) {
			$fields = [ $fields ];
		}
		if ( ! $name ) {
			$name = join( '_', array_map( function ( $v ) {
				return preg_replace( '/[^a-zA-Z0-9]/', '_', $v );
			}, $fields ) );
		}
		$exists = $wpdb->query( "SHOW INDEX FROM {$table} WHERE KEY_NAME = '{$name}'" );
		if ( $exists ) {
			return true;
		}
		$query = "ALTER TABLE {$tableNameEscaped} ADD " . ( $unique ? 'UNIQUE' : 'INDEX' ) . " {$name} (" . join( ',', $fields ) . ")";
		$wpdb->query( $query );

		return true;
	}

	static function dropTable( $name ) {
		/** @var wpdb $wpdb */
		global $wpdb;

		if ( ! is_string( $name ) || ! preg_match( '/^\w[\w\d\_]*$/', $name ) ) {
			throw new Exception( 'Invalid table name: ' . json_encode( $name ) );
		}
		$wpdb->query( "DROP TABLE IF EXISTS `{$name}`" );
	}

	static function getActualSchema( $table ) {
		/** @var wpdb $wpdb */
		global $wpdb;

		if ( ! self::tableExists( $table ) ) {
			return [];
		}
		$res  = $wpdb->get_results( "DESCRIBE {$table}", ARRAY_A );
		$cols = [];
		foreach ( $res as $col ) {
			$typeInfo  = preg_split( '/[\s\(\)]+/', $col['Type'] );
			$superType = array_shift( $typeInfo );
			$typeInfo  = array_filter( $typeInfo, 'mb_strlen' );
			if ( $typeInfo ) {
				if ( $superType == 'set' || $superType == 'enum' ) {
					$typeInfo[0] = array_map( function ( $v ) {
						return trim( $v, "'" );
					}, explode( ',', $typeInfo[0] ) );
				}
			}
			$cols[ $col['Field'] ] = [
				$superType,
				$typeInfo[0] ?? null, // Length or values
				$typeInfo[1] ?? null, // Unsigned
				$col['Default'],
				$col['Null'] === 'YES' ?: null,
				join( ' ', array_filter( [
					trim( str_replace( 'DEFAULT_GENERATED', '', strtoupper( $col['Extra'] ) ) ) ?: null, // AUTO_INCREMENT or ON UPDATE statement
					( $col['Key'] == 'PRI' ) ? 'PRIMARY KEY' : null,
				] ) ) ?: null,
			];

			if ( preg_match( '/^\d+$/', $cols[ $col['Field'] ][1] ) ) {
				$cols[ $col['Field'] ][1] = intval( $cols[ $col['Field'] ][1] );
			}
			if ( $cols[ $col['Field'] ][2] == 'unsigned' ) {
				// For shortening we use 1 instead of 'unsigned'.
				$cols[ $col['Field'] ][2] = 1;
			}
			// Omit meaningless data.
			for ( $i = 5; $i > 0; -- $i ) {
				if ( $cols[ $col['Field'] ][ $i ] !== null ) {
					break;
				}
				unset( $cols[ $col['Field'] ][ $i ] );
			}
		}

		return $cols;
	}

	/**
	 * Updates table structure according to provided definition.
	 *
	 * @param  string  $table
	 * @param  array  $cols  Columns definition. See $colsToAdd parameter of the DbMigration::alterTable method.
	 * @param  bool  $doRemove  Whether to remove fields not listed in $cols or not.
	 *
	 * @return bool|void
	 */
	static function updateSchema( $table, $cols, $doRemove = false ) {
		$actualCols = @self::getActualSchema( $table );
		if ( ! $actualCols ) {
			return self::createTable( $table, $cols );
		}
		$colsToAdd    = array_diff_key( $cols, $actualCols );
		$colsToRemove = $doRemove ? array_keys( array_diff_key( $actualCols, $cols ) ) : [];
		$colsToChange = array_udiff_assoc( array_diff_key( $cols, $colsToAdd ), $actualCols, function ( $a1, $a2 ) {
			if ( in_array( $a1[0], self::$integerTypes ) && $a1[1] == null ) {
				// Ignore default field length.
				$a2[1] = null;
			}

			return count( array_diff_assoc( $a1, $a2 ) ) == 0 ? 0 : 1;
		} );

		// Remove "PRIMARY KEY" to avoid "Duplicate primary key" error.
		foreach ( $colsToChange as $colName => &$colDef ) {
			if ( ! empty( $colDef[5] ) ) {
				if ( mb_strpos( $colDef[5], 'PRIMARY KEY' ) !== false && mb_strpos( $actualCols[ $colName ][5], 'PRIMARY KEY' ) !== false ) {
					$colDef[5] = trim( str_replace( 'PRIMARY KEY', '', $colDef[5] ) );
				}
			}
		}

		return self::alterTable( $table, $colsToAdd, $colsToRemove, $colsToChange );
	}

	/**
	 * Build column definition SQL from given parameters.
	 *
	 * @param  array  $def  Column definition arguments. Should be provided as simple array: [type, length, type_ext, default, nullable, extra]:
	 *                      0 - type: Field type, e.g MEDIUMINT or LONGTEXT.
	 *                      1 - length: For integer and string types, defines field length; For floating point types - length and precision; For enumerable - list of values.
	 *                      2 - type_ext: For numeric types indicates that value is unsigned. For other types it is not supported (but when entered, will be added to generated SQL after the length).
	 *                      3 - default: Default value, e.g ''. For non-numeric field types default values will be escaped.
	 *                      4 - nullable: If true, then field will be declared as NULLABLE
	 *                      5 - extra: Any additional statement, e.g 'AUTO_INCREMENT' or 'ON UPDATE CURRENT_TIMESTAMP'
	 *
	 * @return string SQL query particle, e.g: CHAR(4) NOT NULL DEFAULT ''
	 */
	static function generateColumnDefSql( $def ) {
		/** @var wpdb */
		global $wpdb;

		$version = $wpdb->db_version();

		$type = $def[0];
		if ( $type == 'json' && version_compare( $version, '5.7' ) == - 1 ) {
			$type = 'longtext';
		}
		$length   = $def[1] ?? null;
		$typeExt  = $def[2] ?? null;
		$default  = $def[3] ?? null;
		$nullable = $def[4] ?? false;

		$isText  = in_array( $type, self::$stringTypes );
		$isInt   = ! $isText && in_array( $type, self::$integerTypes );
		$isFloat = ! $isText && ! $isInt && in_array( $type, self::$floatTypes );

		if ( $typeExt ) {
			if ( $isInt || $isFloat ) {
				$typeExt = 'unsigned';
			} else {
				// We don't know other type definition extension yet.
				$typeExt = null;
			}
		}

		$defSql = $type;
		if ( $length ) {
			if ( $isText || $isInt ) {
				if ( is_int( $length ) ) {
					$defSql .= '(' . intval( $length ) . ')';
				}

				if ( $isInt ) {
					if ( $default !== null ) {
						$default = floatval( $default );
					}
				}
			} elseif ( $isFloat ) {
				if ( preg_match( '/\d+,\d+/', $length ) ) {
					$defSql .= '(' . $length . ')';
				}

				if ( $default !== null ) {
					$default = floatval( $default );
				}
			} elseif ( $type == 'enum' || $type == 'set' ) {
				$defSql .= join( ',', array_map( function ( $v ) {
					self::escape( $v );
				}, $length ) );
			}
		}

		if ( $typeExt ) {
			$defSql .= ' ' . $typeExt;
		}

		if ( ! $nullable ) {
			$defSql .= ' NOT NULL';
		}

		if ( $default !== null ) {
			if ( ! $isInt && $default != 'CURRENT_TIMESTAMP' ) {
				$default = self::escape( $default );
			}
			$defSql .= ' DEFAULT ' . $default;
		}

		if ( ! empty( $def[5] ) ) {
			$defSql .= ' ' . $def[5];
		}

		return $defSql;
	}

	static function tableExists( $tableName ) {
		/** @var wpdb */
		global $wpdb;

		return $wpdb->get_results( "SHOW TABLES LIKE '{$tableName}'", ARRAY_A );
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
}