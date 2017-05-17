<?php namespace Spoom\Http;

use Spoom\Core;
use Spoom\Core\Application;
use Spoom\Http\Message\Response;

/**
 * Class Exception
 *
 * @property int $status HTTP status code
 */
class Exception extends Core\Exception {

  /**
   * HTTP status code
   *
   * @var int
   */
  private $_status;

  /**
   * @inheritDoc
   *
   * @param int $status HTTP status code
   */
  public function __construct( $message, $id, array $context = [], $previous = null, int $status = Response::STATUS_BAD, $severity = Application::SEVERITY_ERROR ) {
    parent::__construct( $message, $id, $context, $previous, $severity );

    $this->_status = $status;
  }
  /**
   * HTTP status code
   *
   * @return int
   */
  public function getStatus(): int {
    return $this->_status;
  }
  /**
   * @param mixed $value
   *
   * @return $this
   */
  public function setStatus( int $value ) {
    $this->_status = $value;

    return $this;
  }
}
