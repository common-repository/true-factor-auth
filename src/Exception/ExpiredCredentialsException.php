<?php


namespace TrueFactor\Exception;


class ExpiredCredentialsException extends \Exception {

	public $message = 'Given credentials are expired';
}