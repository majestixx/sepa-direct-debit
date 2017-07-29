<?php
namespace DirectDebit\Exceptions;

use Exception;

class ExceptionArray extends Exception {
  private $exceptions;

  /**
   * @param Exception[] $exceptions
   */
  public function __construct($exceptions = array()) {
    $message = "";
    foreach($exceptions as $ex){
      $message .= "\n" . $ex->getMessage();
    }

    $this->exceptions = $exceptions;

    parent::__construct($message);
  }

  /**
   * @return Exception[] $exceptions
   */
  public function getExceptions() {
    return $this->exceptions;
  }

  /**
   * @param Exception[] $exceptions
   */
  public function setExceptions($exceptions) {
    $this->exceptions = $exceptions;
  }
}