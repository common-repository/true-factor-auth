<?php

namespace TrueFactor\Helper;

class FormRenderer {

	protected $form;

	static $classByType = [
		'text'  => 'regular-text',
		'email' => 'regular-text',
	];

	function __construct( Form $form ) {
		$this->form = $form;
	}

	function render() {
		return Html::tag( 'form', $this->form->getAttributes(),
			$this->renderElements() );
	}

	function renderElements() {
		$chunks = [];
		foreach ( $this->form->getFields() as $fieldName => $fieldConfig ) {
			$chunks[] = $this->renderElement( $fieldName );
		}

		return join( "\n", $chunks );
	}

	/**
	 * @param         $name
	 * @param         $value
	 * @param  array  $options
	 * Possible options:
	 * - label: Label text. If set to false, no label will be displayed. Otherwise, if empty, label will be generated from field name.
	 * - labelToPlaceholder: Show label as placeholder.
	 * - implodeArrayValue: If given value is array, implode it using given option value as glue.
	 *
	 * @return string
	 */
	function renderElement( $name, $value = null, $options = [] ) {
		if ( $value === null ) {
			$value = $this->form->getInputValue( $name );
		}
		$fieldOptions = $this->form->getField( $name );
		if ( $fieldOptions ) {
			$options = array_replace( $fieldOptions, $options );
		}
		$type = $options['type'] ?? $options[0] ?? 'text';

		if ( ! empty( self::$classByType[ $type ] ) ) {
			$options['class'] = ( $options['class'] ?? '' ) . ' ' . self::$classByType[ $type ];
		}

		if ( $type == 'hidden' ) {
			return '<tr style="display: none"><td colspan="2">' . Html::hidden( $name, $value ) . '</td></tr>';
		}

		$label = $this->form->getFieldLabel( $name );
		$hint  = $options['_hint'] ?? null;

		// Remove special option keys.
		// The rest of options will be either processed and removed on further steps or considered as html attributes.
		Arr::unsetKeys( $options, [ 'label' ] );

		$labelHtml = Html::tag( 'label', [], $label );
		switch ( $type ) {
			case 'checkbox':
				$inputHtml = Html::checkbox( $name, $value, $options );
				if ( substr( $name, - 2 ) != '[]' ) {
					$inputHtml = Html::hidden( $name, 0 ) . $inputHtml;
				}
				break;
			case 'radio':
				$inputHtml = Html::radio( $name, null, $value, $options );
				break;
			default:
				$inputHtml = Html::input( $name, $value, $options );
				break;
		}

		$inputHtml .= $this->renderFieldErrors( $name, $options );

		if ( $hint ) {
			if ( is_a( $hint, \Closure::class ) ) {
				$hint = $hint();
			}
			$inputHtml .= '<div class="description">' . $hint . '</div>';
		}

		return '<tr><th scope="row">' . $labelHtml . '</th><td>' . $inputHtml . '</td></tr>';
	}

	function renderFieldErrors( $name, $options = [] ) {
		$errors = $this->form->getErrorMessages( $name );

		return Html::inputErrors( $errors, $name, $options );
	}
}