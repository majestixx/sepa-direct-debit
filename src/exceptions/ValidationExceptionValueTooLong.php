<?php
namespace DirectDebit\Exceptions;

class ValidationExceptionValueTooLong extends ValidationException {
  public function __construct($field=null, $message=null, $code=null,$previous=null) {
    parent::__construct($field, $message, $code, $previous);
  }
}