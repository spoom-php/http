<?php namespace Spoom\Http\Message\Response;

use Spoom\Core\Helper\StreamInterface;
use Spoom\Http\Helper\Uri;
use Spoom\Http\Helper\UriInterface;
use Spoom\Http\Message;

/**
 * Class Redirect
 * @package Spoom\Http\Message\Response
 *
 * @property UriInterface|null $uri
 */
class Redirect extends Message\Response {

  /**
   * The URL to redirect on send
   *
   * @var UriInterface|null
   */
  protected $_uri;

  //
  public function __construct( ?StreamInterface $body = null, int $status = self::STATUS_OTHER, array $header = [] ) {
    parent::__construct( $body, $status, $header );
  }

  /**
   * @return UriInterface|null
   */
  public function getUri(): ?UriInterface {
    return $this->_uri;
  }
  /**
   * @param UriInterface|string|null $value
   */
  public function setUri( $value ) {

    $this->_uri = $value !== null ? Uri::instance( $value ) : null;
    $this->setHeader( $this->_uri, 'location' );
  }
}
