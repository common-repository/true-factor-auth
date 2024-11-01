<?php

namespace TrueFactor\Helper;

use TrueFactor\Helper\Filter\ValueEmptyException;
use TrueFactor\Helper\Filter\ValueInvalidException;

/**
 * This class is responsible for building forms, user input validation and form rendering.
 *
 * @package AkWallet
 */
class Form {

	/** @var array Html attributes of the form */
	protected $attributes = [];

	/** @var array Validated and filtered input data. */
	protected $cleanedData = [];

	/** @var array Current fields' values. */
	protected $data = [];

	/** @var array Validation errors */
	protected $errors = [];

	/**
	 * @var array Fields definitions for rendering
	 * @example
	 * [
	 *   'field_name' => [
	 *     'type' => 'select',
	 *     'options' => [1 => 'First option', 2 => 'Second option']
	 *     '_rules' => [<optional validation rules>]
	 *   ]
	 * ]
	 */
	protected $fields = [];

	/** @var string */
	protected $filterClass = Filter::class;

	/**
	 * @var array List of fields which should not be included in query
	 * @see Form::getQueryData()
	 */
	protected $hideFieldsInQuery = [];

	/** @var array Initial form values */
	protected $initialData = [];

	/** @var array Unfiltered input */
	protected $inputData = [];

	/** @var array Validation rules */
	protected $rules = [];

	/**
	 * @param  array  $data  Initial values
	 */
	function __construct( $data = [] ) {
		$this->data        = $data;
		$this->initialData = $data;
	}

	function getAttributes() {
		return $this->attributes;
	}

	function setAttributes( $attributes ) {
		$this->attributes = $attributes;

		return $this;
	}

	function setMethod( $method ) {
		$this->attributes['method'] = $method;
	}

	function setAction( $url ) {
		$this->attributes['action'] = $url;
	}

	function getFields() {
		return $this->fields;
	}

	/**
	 * Returns field definition, if exists.
	 *
	 * @param $fieldName
	 *
	 * @return array
	 */
	function getField( $fieldName ) {
		return $this->fields[ $fieldName ] ?? null;
	}

	function addField( $name, $definition, $rules = null ) {
		$this->fields[ $name ] = $definition;
		if ( $rules === null && array_key_exists( '_rules', $definition ) ) {
			$rules = $definition['_rules'];
			unset( $definition['_rules'] );
		}
		if ( $rules !== null ) {
			$this->setFieldRules( $name, $rules );
		}
	}

	function setFields( $fields ) {
		foreach ( $fields as $fieldName => $definition ) {
			$this->addField( $fieldName, $definition );
		}
	}

	function getRules() {
		return $this->rules;
	}

	/**
	 * Replace validation rules.
	 *
	 * @param $rules
	 *
	 * @return $this
	 */
	function setRules( $rules ) {
		$this->rules = $rules;

		return $this;
	}

	/**
	 * Replace validation rules on a field.
	 *
	 * @param $fieldName
	 * @param $rules
	 *
	 * @return $this
	 */
	function setFieldRules( $fieldName, $rules ) {
		$this->rules[ $fieldName ] = $rules;

		return $this;
	}

	/**
	 * Validate and set fields' values.
	 *
	 * @param  array  $data  Key-value pairs for field values.
	 * @param  bool  $skipRequired  Do not throw if required field is missing.
	 *
	 * @return $this
	 * @throws ValueInvalidException
	 */
	function setInputData( $data, $skipRequired = false ) {
		$this->filter( $data, $skipRequired );

		if ( ! count( $this->errors ) ) {
			$this->setSafeData( $this->cleanedData );
		} else {
			throw new ValueInvalidException( 'Validation failed' );
		}

		return $this;
	}

	/**
	 * Returns value.
	 *
	 * @param  null  $key
	 *
	 * @return array|mixed|string|null
	 */
	function getInputValue( $key = null ) {
		if ( ! $key ) {
			return array_replace( $this->data, $this->inputData );
		}
		$value = $this->inputData[ $key ] ?? $this->get( $key );

		return $value;
	}

	/**
	 * Apply validation ad filtering rules on given values and save them.
	 *
	 * @param  array  $data
	 * @param  bool  $partial
	 *
	 * @return bool
	 */
	function filter( array $data, $partial = false ) {
		$this->errors    = [];
		$this->inputData = $data;

		foreach ( $this->getRules() as $n => $f ) {
			if ( $this->filterField( $data, $n, $f, $partial ) ) {
				$this->cleanedData[ $n ] = $data[ $n ];
			}
		}

		return empty( $this->errors );
	}

	/**
	 * Returns true if field is valid or false otherwise. If data can not be updated, validation error will be added.
	 *
	 * @param  array  $data  Fields' values array
	 * @param  string  $fieldName
	 * @param  array  $fieldRules
	 * @param  bool  $ignoreRequired
	 *
	 * @return bool
	 */
	function filterField( &$data, $fieldName, $fieldRules, $ignoreRequired = false ) {
		/** @var Filter $filterClass */
		$filterClass = $this->filterClass;

		// If no rules defined for given field, and value not provided, then just skip.
		if ( ! $fieldRules ) {
			if ( ! array_key_exists( $fieldName, $data ) ) {
				return false;
			}
		}

		try {
			foreach ( $fieldRules as $ruleDef ) {
				if ( ! is_array( $ruleDef ) ) {
					$ruleDef = [ $ruleDef ];
				}
				if ( $ruleDef[0] == 'readonly' ) {
					return false;
				}
				if ( $ruleDef[0] == 'skip_empty' ) {
					if ( empty( $data[ $fieldName ] ) ) {
						return false;
					}
					continue;
				}
				if ( ! array_key_exists( $fieldName, $data ) ) {
					if ( $ruleDef[0] == 'required' && ! $ignoreRequired ) {
						throw new ValueEmptyException( '%s is required' );
					}

					return false;
				}
				try {
					$data[ $fieldName ] = $filterClass::filterValueWithRule( $ruleDef, $data[ $fieldName ] );
				} catch ( ValueEmptyException $e ) {
					unset( $data[ $fieldName ] );
					continue;
				}
			}
		} catch ( ValueInvalidException $e ) {
			$errorMessage     = $e->getMessage();
			$errorMessageArgs = [];
			if ( is_array( $ruleDef ) ) {
				if ( ! empty( $ruleDef[2] ) ) {
					$errorMessage = $ruleDef[2];
				}
				$errorMessageArgs = $ruleDef[1] ?? [];
			}
			$this->addError( $fieldName, $errorMessage, $errorMessageArgs );
		}

		return empty( $this->errors[ $fieldName ] );
	}

	function addError( $field, $error, $args = [] ) {
		if ( empty( $this->errors[ $field ] ) ) {
			$this->errors[ $field ] = [];
		}
		$this->errors[ $field ][] = [ $error, $args ];
	}

	function getErrors( $field = null ) {
		if ( $field ) {
			return empty( $this->errors[ $field ] ) ? [] : $this->errors[ $field ];
		} else {
			return $this->errors;
		}
	}

	function getErrorMessages( $field = null ) {
		$messages = [];
		if ( $field ) {
			if ( empty( $this->errors[ $field ] ) ) {
				return [];
			}
			foreach ( $this->errors[ $field ] as $e ) {
				$messages[] = $this->buildErrorMessage( $e, $field );
			}
		} else {
			foreach ( array_keys( $this->errors ) as $field ) {
				$messages[ $field ] = $this->getErrorMessages( $field );
			}
		}

		return $messages;
	}

	function getChangedData() {
		$changed = [];
		foreach ( $this->getRules() as $field => $rules ) {
			if ( in_array( 'virtual', $rules )
			     || ! array_key_exists( $field, $this->data )
			     || ( isset( $this->initialData[ $field ] )
			          && $this->data[ $field ] == $this->initialData[ $field ] )
			) {
				continue;
			}
			$changed[ $field ] = $this->data[ $field ];
		}

		return $changed;
	}

	function getData() {
		return $this->data;
	}

	function getQueryData() {
		$queryData = [];
		foreach ( $this->getRules() as $field => $rules ) {
			if ( in_array( $field, $this->hideFieldsInQuery ) ) {
				continue;
			}
			if ( ! empty( $this->data[ $field ] ) ) {
				$queryData[ $field ] = $this->getInputValue( $field );
			}
		}

		return $queryData;
	}

	function getQuery() {
		return http_build_query( $this->getQueryData() );
	}

	function get( $key ) {
		if ( isset( $this->data[ $key ] ) ) {
			return $this->data[ $key ];
		}

		return null;
	}

	function set( $key, $value ) {
		$this->data[ $key ] = $value;
	}

	/**
	 *
	 * @param  array  $data
	 *
	 * @return $this
	 */
	function setSafeData( $data ) {
		$this->data = array_merge( $this->data, $data );

		return $this;
	}

	protected $renderer;

	function getRenderer() {
		return $this->renderer ?: $this->renderer = new FormRenderer( $this );
	}

	function render() {
		return $this->getRenderer()->render();
	}

	/**
	 * Generate human-friendly error message from given array.
	 *
	 * @param  array  $error  May look as follows:
	 *                         ```php
	 *                         ['max', [1000]]
	 *                         ```
	 * @param  string  $fieldName
	 *
	 * @return array|string
	 */
	function buildErrorMessage( $error, $fieldName = '' ) {
		if ( is_array( $error ) ) {
			$message = array_shift( $error );
			array_unshift( $error, $this->getFieldLabel( $fieldName ) );

			return Str::vsprintf( $message, $error );
		}

		return (string) $error;
	}

	function getFieldLabel( $fieldName ) {
		return $this->fields[ $fieldName ]['label'] ?? Str::toTitle( $fieldName );
	}
}