<?php

namespace TrueFactor\Helper;

use Closure;
use TrueFactor\Helper\Filter\ValueEmptyException;
use TrueFactor\Helper\Filter\ValueInvalidException;

/**
 * Utility class for validation.
 */
class Filter {

	const REGEX_EMAIL_ONLY = '#^([а-яa-z0-9_\.\-]{1,100})@([\w\d\.\-\_]{1,100})\.([\w\d]{2,100})$#uis';

	/**
	 * @param         $rules
	 * @param  mixed  $value  Value being filtered/validated. Passed to the method as first argument.
	 *
	 * @return mixed
	 * @throws ValueEmptyException
	 * @throws ValueInvalidException
	 */
	static function filterValue( $rules, $value ) {

		foreach ( $rules as $rule ) {
			$value = self::filterValueWithRule( $rule, $value );
		}

		return $value;
	}

	/**
	 * @param  string|array  $rule  The rule definition, should be passed as list of 1 to 3 items:
	 *                               0 - Rule function (callable).
	 *                               1 - Additional parametes passed to rule as second argument. Optional.
	 *                               3 - Error message to display if validation not passed. Optional.
	 * @param  mixed  $value  Value being filtered/validated. Passed to the method as first argument.
	 *
	 * @return mixed
	 * @throws ValueEmptyException
	 * @throws ValueInvalidException
	 * @example Filter::filterValueWithRule(['date_max', 'now', 'Date should not be in future.'])
	 * @example Filter::filterValueWithRule(['in_array', ['Value1', 'Value2'], 'Select value from the list.'])
	 */
	static function filterValueWithRule( $rule, $value ) {
		if ( ! $rule ) {
			return $value;
		}
		if ( ! is_array( $rule ) ) {
			$rule = [ $rule ];
		}
		$class   = get_called_class();
		$method  = array_shift( $rule );
		$params  = array_shift( $rule );
		$message = array_shift( $rule );
		$args    = array_merge( [ $value ], [ $params ] );
		try {
			if ( $method instanceof Closure ) {
				$value = call_user_func_array( $method, $args );
			} elseif ( method_exists( $class, $method ) ) {
				$value = call_user_func_array( [ $class, $method ], $args );
			} else {
				trigger_error( "Invalid validation rule: {$method}",
					E_USER_WARNING );
			}
		} catch ( ValueInvalidException $e ) {
			if ( $message ) {
				$e->setMessage( $message );
			}
			throw $e;
		}

		return $value;
	}

	static function array( $value, $elementRules = [], $preserveKeys = true ) {
		if ( ! is_array( $value ) ) {
			$value = [ $value ];
		}
		if ( $skipInvalid = ( reset( $elementRules ) == 'skip_invalid' ) ) {
			array_shift( $elementRules );
		}
		if ( ! is_array( $elementRules ) ) {
			return $value;
		}
		foreach ( $value as $k => $v ) {
			foreach ( $elementRules as $elementRule ) {
				try {
					$value[ $k ] = static::filterValueWithRule( $elementRule,
						$value[ $k ] );
				} catch ( ValueInvalidException $e ) {
					if ( ! $skipInvalid ) {
						throw $e;
					}
					unset( $value[ $k ] );
					continue 2;
				}
			}
		}

		return $preserveKeys ? $value : array_values( $value );
	}

	static function bool( $value ) {
		return (bool) $value;
	}

	static function email( $value ) {
		$value = trim( strtolower( $value ) );
		if ( ! preg_match( self::REGEX_EMAIL_ONLY, $value ) ) {
			throw new ValueInvalidException( '%s must be valid email address' );
		}

		return $value;
	}

	static function in_array( $value, $array ) {
		if ( ! in_array( $value, $array ) ) {
			throw new ValueInvalidException();
		}

		return $value;
	}

	static function is_array_key( $value, $array ) {
		if ( ! array_key_exists( $value, $array ) ) {
			throw new ValueInvalidException();
		}

		return $value;
	}

	static function int( $value ) {
		if ( empty( $value ) ) {
			$value = 0;
		}

		if ( ! filter_var( $value, FILTER_VALIDATE_INT ) ) {
			throw new ValueInvalidException( '%s must be an integer' );
		}

		return (int) ( $value );
	}

	static function max( $value, $max ) {
		if ( $value > $max ) {
			throw new ValueInvalidException( '%s must not be greater than %s' );
		}

		return $value;
	}

	static function max_length( $value, $length ) {
		if ( mb_strlen( $value ) > $length ) {
			throw new ValueInvalidException( '%s must be at most %s character(s) long' );
		}

		return $value;
	}

	static function min( $value, $min ) {
		if ( $value < $min ) {
			throw new ValueInvalidException( '%s must not be less than %s' );
		}

		return $value;
	}

	/**
	 * @param $value
	 * @param $length
	 *
	 * @return mixed
	 * @throws ValueInvalidException
	 */
	static function min_length( $value, $length ) {
		if ( mb_strlen( $value ) < $length ) {
			throw new ValueInvalidException( '%s must be at least %s character(s) long' );
		}

		return $value;
	}

	/**
	 * Check if value is empty.
	 *
	 * @param $value
	 *
	 * @return mixed The given value itself, if not empty
	 * @throws ValueInvalidException If $value is empty
	 */
	static function not_empty( $value ) {
		if ( empty( $value ) ) {
			throw new ValueInvalidException( '%s should not be empty' );
		}

		return $value;
	}

	/**
	 * If $value is empty, returns null.
	 *
	 * @param $value
	 *
	 * @return null
	 */
	static function null( $value ) {
		return $value ?: null;
	}

	/**
	 * Check if value is empty.
	 *
	 * @param $value
	 *
	 * @return mixed The given value itself, if not empty
	 * @throws ValueInvalidException If $value is empty
	 */
	static function required( $value ) {
		if ( empty( $value ) && ( ! is_string( $value ) || mb_strlen( $value ) == 0 )
		     && ( $value !== 0 )
		) {
			throw new ValueInvalidException( '%s is required' );
		}

		return $value;
	}

	static function skip_empty( $value ) {
		if ( empty( $value ) ) {
			throw new ValueEmptyException();
		}

		return $value;
	}

	static function trim( $value ) {
		$value = trim( $value );

		return $value;
	}
}