<?php namespace Http\Response;

use Framework\Exception;
use Http\Response;

/**
 * Class Blank
 * @package Http\Response
 */
class Blank extends Response {

  /**
   * Exception when try to write to the response
   */
  const EXCEPTION_INVALID_WRITE = 'http#16N';

  /**
   * @inheritdoc
   */
  public function send() {

    // add no content flag
    $this->_status = Response::STATUS_CONTENT_NO;

    parent::send();
  }

  /**
   * Prevent body modification
   *
   * @param mixed     $content
   * @param bool|true $append
   *
   * @return $this
   * @throws Exception\Strict
   */
  public function write( $content, $append = true ) {
    throw new Exception\Strict( self::EXCEPTION_INVALID_WRITE );
  }
}
