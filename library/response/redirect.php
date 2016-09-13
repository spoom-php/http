<?php namespace Http\Response;

use Http\Helper\Uri;
use Http\Message;

/**
 * Class Redirect
 * @package Http\Response
 */
class Redirect extends Message\Response {

  /**
   * The URL is not valid
   */
  const EXCEPTION_INVALID_URL = 'http#4C';

  /**
   * The URL to redirect on send
   *
   * @var string|Uri
   */
  protected $_url;

  /**
   * @inheritDoc
   */
  public function __construct( $status = self::STATUS_OTHER, array $header = [], $body = null ) {
    parent::__construct( $status, $header, $body );
  }

  /**
   * @return Uri|string
   */
  public function getUrl() {
    return $this->_url;
  }
  /**
   * @param Uri|string $value
   */
  public function setUrl( $value ) {

    $this->_url = $value !== null ? Uri::instance( $value ) : null;
    $this->setHeader( $this->_url, 'location' );
  }
}
