<?php

namespace TrueFactor\Helper;

/**
 * Class Html
 *
 * @package S
 */
class Html {

	static $inputClass = '';
	static $inputErrorsClass = 'inp-errs';
	static $inputErrorClass = 'inp-err';
	static $selectClass = 'sel';
	static $textareaClass = 'large-text';

	// Basic HTML elements and form controls.

	static function checkbox( $name, $checked = false, $extra = null ) {
		if ( is_array( $name ) ) {
			$attrs = $name;
		} else {
			$attrs = [ 'name' => $name ];
		}
		$attrs['type'] = 'checkbox';
		if ( $checked ) {
			$attrs['checked'] = 'checked';
		}

		return self::input( $attrs, null, $extra );
	}

	/**
	 * Generates html string for hidden input.
	 *
	 * @param  string|array  $name  Name of the input or array of attributes.
	 * @param  string|null  $value  Value of the input.
	 *
	 * @return string
	 */
	static function hidden( $name, $value = null ) {
		return self::input( $name, $value, [ 'type' => 'hidden' ] );
	}

	/**
	 * Build html input tag.
	 *
	 * @param  string|mixed  $name  Name of input or array of input attributes. If array is given, other arguments are ignored.
	 * @param  string  $value  Value of the input.
	 * @param  array|string|null  $extra  Array of attributes to override. If string given, it's assumed as class attribute.
	 *
	 * @return string
	 */
	static function input( $name, $value = '', $extra = null ) {
		if ( is_array( $name ) ) {
			$attrs = $name;
			if ( array_key_exists( 'value', $attrs ) ) {
				$value = $attrs['value'];
			}
		} else {
			$attrs = [ 'name' => $name ];
		}
		if ( empty( $attrs['type'] ) ) {
			$attrs['type'] = 'text';
		}
		if ( is_string( $extra ) ) {
			$extra = [ 'class' => $extra ];
		}
		if ( $extra ) {
			$attrs = array_replace( $attrs, $extra );
		}
		if ( $attrs['type'] == 'checkbox' ) {
			if ( $value ) {
				$attrs['checked'] = "checked";
			}
		} else {
			$attrs['value'] = $value;
		}
		if ( array_key_exists( 'required', $attrs ) ) {
			if ( ! $attrs['required'] ) {
				unset( $attrs['required'] );
			} else {
				$attrs['required'] = 'required';
			}
		}
		$classes = empty( $attrs['class'] ) ? []
			: array_filter( explode( ' ', $attrs['class'] ) );
		if ( $attrs['type'] == 'select' ) {
			array_unshift( $classes, static::$selectClass );
			$attrs['class'] = join( ' ', $classes );
			if ( empty( $extra['options'] ) ) {
				$extra['options'] = [];
			}
			if ( $extra['options'] instanceof \Closure ) {
				$extra['options'] = $extra['options']();
			}
			Arr::unsetKeys( $attrs, [ 'options', 'value', 'type' ] );
			if ( ! empty( $attrs['multiple'] ) ) {
				if ( ! empty( $attrs['name'] ) ) {
					$attrs['name'] = Str::appended( $attrs['name'], '[]' );
				}
			}

			return self::tag( 'select', $attrs,
				self::options( $extra['options'], $value, ! empty( $attrs['required'] ) )
			);
		}
		if ( $attrs['type'] == 'textarea' ) {
			array_unshift( $classes, static::$textareaClass );
			$attrs['class'] = join( ' ', $classes );
			Arr::unsetKeys( $attrs, [ 'type', 'value' ] );
			$content = htmlspecialchars_decode( $value );
			if ( ! empty( $extra['isHtml'] ) ) {
				unset( $extra['isHtml'] );
				$prependWithNl = [
					"<p",
					"<div",
					"<table",
					"<td",
					"<tr",
				];
				$content       = str_replace( $prependWithNl, array_map( function ( $v ) {
					return "\n$v";
				}, $prependWithNl ), $content );
			}
			$content = str_replace( [
				"&#39;",
				"&quot;",
			], [
				"'",
				'"',
			], htmlspecialchars( $content ) );

			return self::tag( 'textarea', $attrs, $content );
		}
		array_unshift( $classes, self::$inputClass );
		$classes[]      = $attrs['type'];
		$attrs['class'] = join( ' ', $classes );
		if ( empty( $attrs['id'] ) && ! empty( $attrs['name'] ) ) {
			if ( ! strpos( $attrs['name'], '[]' ) ) {
				$attrs['id'] = Str::slugify( 'input_' . $attrs['name'], '_' );
			}
		}

		return self::tag( 'input', $attrs, null, true );
	}

	/**
	 * @param        $errors
	 * @param  null  $inputKey
	 *
	 * @param  null  $options
	 *
	 * @return string HTML code to display errors.
	 */
	static function inputErrors( $errors, $inputKey = null, $options = null ) {
		if ( ! $errors ) {
			return '';
		}
		$html = '<div class="' . ( $options['wrapperClass'] ??
		                           self::$inputErrorsClass ) . '" ' . ( $inputKey ? 'data-input-key="'
		                                                                            . $inputKey . '"' : '' ) . '>';
		foreach ( $errors as $e ) {
			$html .= '<div class="' . ( $options['wrapperClass'] ??
			                            self::$inputErrorClass ) . '">' . $e . '</div>';
		}
		$html .= '</div>';

		return $html;
	}

	static function label( $for, $text ) {
		return self::tag( 'label', [ 'for' => Str::slugify( $for ) ], $text );
	}

	/**
	 * Builds options for select element
	 *
	 * @param  array  $options
	 * @param  mixed  $selected
	 * @param  bool  $required  If set to true, then option with value=$emptyOptionKey will be disabled.
	 * @param  bool|string|integer  $emptyOptionValue  Value for empty option. If set to false, empty option will not be added.
	 * @param  bool|string  $emptyOptionLabel  Label for empty option. If false, option will not be added.
	 *
	 * @return string
	 */
	static function options(
		$options,
		$selected = [],
		$required = false,
		$emptyOptionValue = '',
		$emptyOptionLabel = false
	) {
		$html = '';
		if ( ! is_array( $selected ) ) {
			$selected = [ $selected ];
		}
		$selected = array_map( function ( $v ) {
			if ( $v === false ) {
				return '0';
			} elseif ( $v === true ) {
				return '1';
			} elseif ( $v === null ) {
				return '';
			}

			return (string) $v;
		}, $selected );
		if ( $emptyOptionValue !== false ) {
			if ( ! array_key_exists( $emptyOptionValue, $options )
			     && $emptyOptionLabel !== false
			) {
				$options = array_replace( [
					$emptyOptionValue => $emptyOptionLabel,
				], $options );
			}
		}
		foreach ( $options as $k => $v ) {
			if ( is_array( $v ) ) {
				$html .= self::tag( 'optgroup', [ 'label' => $k ],
					self::options( $v, $selected ) );
			} else {
				$optionAttrs = [
					'value' => $k,
				];
				if ( in_array( (string) $k, $selected, true ) ) {
					$optionAttrs['selected'] = 'selected';
				}
				if ( $required && $emptyOptionValue === $k ) {
					$optionAttrs['disabled'] = 'disabled';
				}
				$html .= self::tag( 'option', $optionAttrs, $v );
			}
		}

		return $html;
	}

	static function radio(
		$name,
		$value = null,
		$checked = false,
		array $extra = null
	) {
		if ( ! is_array( $name ) ) {
			$name = [ 'name' => $name ];
		}
		$name['type'] = 'radio';
		if ( $checked ) {
			$name['checked'] = 'checked';
		}
		if ( ! empty( $extra['value'] ) ) {
			$value = $extra['value'];
		}
		if ( empty( $extra['id'] ) && empty( $name['id'] ) ) {
			$name['id'] = Str::slugify( 'radio_' . $name['name'] . '_' . $value, '_' );
		}

		return self::input( $name, $value, $extra );
	}

	/**
	 * Returns HTML code for custom tag with given attributes.
	 *
	 * @param          $name
	 * @param          $attrs
	 * @param  string|boolean  $innerHtml  If false, no closing tag will be added.
	 * @param  bool  $shortTag
	 *
	 * @return string
	 */
	static function tag( $name, $attrs, $innerHtml = '', $shortTag = false ) {
		$name = trim( mb_strtolower( $name ), '<>' );

		foreach ( $attrs as $key => $option ) {
			if ( substr( $key, 0, 1 ) == '_' ) {
				unset( $attrs[ $key ] );
			}
		}

		return '<' . $name . ' ' . self::attributes( $attrs )
		       . ( $innerHtml === false
				? '>'
				:
				( $innerHtml
					? ( '>' . $innerHtml . '</' . $name . '>' )
					: ( $shortTag
						? '/>'
						: '></' . $name . '>'
					)
				) );
	}

	static function textarea( $name, $value, $extra = [] ) {
		if ( is_array( $name ) ) {
			$attrs = $name;
		} else {
			$attrs = [ 'name' => $name ];
		}
		if ( is_string( $extra ) ) {
			$extra = [ 'class' => $extra ];
		}
		if ( $extra ) {
			$attrs = array_replace( $attrs, $extra );
		}
		$classes = empty( $attrs['class'] ) ? []
			: array_filter( explode( ' ', $attrs['class'] ) );
		array_unshift( $classes, self::$textareaClass );
		$attrs['class'] = join( ' ', $classes );
		if ( empty( $attrs['id'] ) && ! empty( $attrs['name'] ) ) {
			if ( ! strpos( $attrs['name'], '[]' ) ) {
				$attrs['id'] = Str::slugify( 'textarea-' . $attrs['name'], '_' );
			}
		}

		return self::tag( 'textarea', $attrs,
			htmlspecialchars( htmlspecialchars_decode( $value ) ) );
	}

	// Html parts.

	static function attributes( $attrs ) {
		$chunks = [];
		foreach ( $attrs as $key => $val ) {
			if ( ! preg_match( '/^\w[\w\d]*(\-[\w\d]+)*$/', $key ) ) {
				trigger_error( "Invalid attribute name: {$key}" );
				continue;
			}
			$chunks[] = $key . '="' . self::escapeValue( $val ) . '"';
		}

		return implode( ' ', $chunks );
	}

	static function classes( $classes ) {
		if ( ! is_array( $classes ) ) {
			$classes = explode( ' ', $classes );
		}

		return join( ' ', array_filter( array_unique( $classes ) ) );
	}

	static function escapeValue( $str ) {
		if ( ! is_scalar( $str ) && $str !== null ) {
			trigger_error( "Non-scalar value provided: " . json_encode( $str ), E_USER_WARNING );

			return '';
		}

		return htmlspecialchars( htmlspecialchars_decode( $str ) );
	}

}
