<?php

namespace TrueFactor\Helper\Filter;

class ValueInvalidException extends \Exception {
	public $message = 'Invalid value';

	function setMessage( $message ) {
		$this->message = $message;
	}
}