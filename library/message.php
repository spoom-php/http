<?php namespace Http;

use Framework\Exception;
use Framework\Helper\Library;
use Http\Helper\StreamInterface;

/**
 * Interface MessageInterface
 * @package Http
 */
interface MessageInterface {

  const VERSION_HTTP1   = 'HTTP/1';
  const VERSION_HTTP1_1 = 'HTTP/1.1';
  const VERSION_HTTP2   = 'HTTP/2';

  /**
   * Standard HTTP date format
   */
  const DATE_FORMAT = 'D, d M Y H:i:s T';

  /**
   * Write the message into the input stream
   *
   * @param StreamInterface $stream
   */
  public function write( $stream );

  /**
   * Get the message's protocol version
   *
   * @return string
   */
  public function getVersion();
  /**
   * Set the message's protocol version
   *
   * @param string $value
   *
   * @return static
   */
  public function setVersion( $value );

  /**
   * Get a specific (or all) header value
   *
   * @param string $name Case-insensitive
   *
   * @return array|array[string]
   */
  public function getHeader( $name = null );
  /**
   * Set (or extend) a specific or all header
   *
   * @param mixed       $value
   * @param string|null $name   Case-insensitive
   * @param bool        $append Extend or set the value(s)
   *
   * @return static
   */
  public function setHeader( $value, $name = null, $append = false );

  /**
   * Get the message's body
   *
   * @return StreamInterface|null
   */
  public function getBody();
  /**
   * Set (or clear) the message's body (stream)
   *
   * @param StreamInterface|null $value
   *
   * @return static
   */
  public function setBody( $value );
}
/**
 * Class Message
 * @package Http
 */
abstract class Message extends Library implements MessageInterface {

  const EXCEPTION_INVALID_BODY    = 'http#0E';
  const EXCEPTION_INVALID_VERSION = 'http#0E';

  /**
   * @var string
   */
  private $_version = self::VERSION_HTTP1_1;
  /**
   * @var StreamInterface|null
   */
  private $_body;

  /**
   * @var array[string]
   */
  private $_header = [];

  /**
   * Get the message's protocol version
   *
   * @return string
   */
  public function getVersion() {
    return $this->_version;
  }
  /**
   * Set the message's protocol version
   *
   * @param string $value
   *
   * @return static
   * @throws Exception\Strict
   */
  public function setVersion( $value ) {

    if( empty( $value ) ) throw new Exception\Strict( static::EXCEPTION_INVALID_VERSION );
    else {

      $this->_version = (string) $value;
      return $this;
    }
  }

  /**
   * Get the message's body
   *
   * @return StreamInterface|null
   */
  public function getBody() {
    return $this->_body;
  }
  /**
   * Set the message's body (stream)
   *
   * @param StreamInterface|null $value
   *
   * @return static
   * @throws Exception\Strict
   */
  public function setBody( $value ) {

    if( $value !== null && !( $value instanceof StreamInterface ) ) throw new Exception\Strict( static::EXCEPTION_INVALID_BODY );
    else {

      $this->_body = $value;
      return $this;
    }
  }

  /**
   * Get a specific (or all) header value
   *
   * @param string $name Case-insensitive
   *
   * @return array|array[string]
   */
  public function getHeader( $name = null ) {

    $name = mb_strtolower( $name );
    return empty( $name ) ? $this->_header : ( isset( $this->_header[ $name ] ) ? $this->_header[ $name ] : [] );
  }
  /**
   * Set (or extend) a specific or all header
   *
   * @param mixed       $value
   * @param string|null $name   Case-insensitive
   * @param bool        $append Extend or set the value(s)
   *
   * @return static
   */
  public function setHeader( $value, $name = null, $append = false ) {

    // force value to array
    if( empty( $value ) ) $value = [];
    else $value = (array) $value;

    // create the header if empty
    $name = mb_strtolower( $name );
    if( !empty( $name ) && !isset( $this->_header[ $name ] ) ) {
      $this->_header[ $name ] = [];
    }

    $tmp = &( empty( $name ) ? $this->_header : $this->_header[ $name ] );
    if( $append ) $tmp = array_merge( $tmp, $value );
    else {

      // TODO implement true remove

      $tmp = $value;
    }

    return $this;
  }
}
