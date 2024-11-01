<?php

namespace TrueFactor\Exception;

class SmsBlockedException extends \Exception {

	protected $message = 'SMS send blocked';
}