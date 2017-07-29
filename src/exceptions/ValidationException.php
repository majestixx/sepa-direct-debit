<?php
namespace DirectDebit\Exceptions;

use Exception;

class ValidationException extends Exception {
  public $field;

  public function __construct($field=null, $message=null, $code=null, $previous=null) {
    $this->field = $field;
    parent::__construct($message,$code,$previous);
  }

  public function getField() {
    return $this->field;
  }
}