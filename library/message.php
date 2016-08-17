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
   * Get a specific header value or get all header
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
   * @return StreamInterface
   */
  public function getBody();
  /**
   * @param StreamInterface $value
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

  const EXCEPTION_INVALID_BODY = 'http#0E';

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
  private $_header = [ ];

  /**
   * @param StreamInterface|null $body
   * @param array                $header
   * @param string               $version
   */
  public function __construct( $body = null, array $header = [ ], $version = self::VERSION_HTTP1_1 ) {

    $this->_body    = $body;
    $this->_header  = $header;
    $this->_version = $version;
  }

  /**
   * @return string
   */
  public function getVersion() {
    return $this->_version;
  }
  /**
   * @param string $value
   *
   * @return static
   */
  public function setVersion( $value ) {

    $this->_version = (string) $value;
    return $this;
  }

  /**
   * @return StreamInterface|null
   */
  public function getBody() {
    return $this->_body;
  }
  /**
   * @param StreamInterface|null $value
   *
   * @return static
   * @throws Exception\Strict
   */
  public function setBody( $value ) {

    if( !( $value instanceof StreamInterface ) || $value !== null ) throw new Exception\Strict( static::EXCEPTION_INVALID_BODY );
    else {

      $this->_body = $value;
      return $this;
    }
  }

  /**
   * @param string $name
   *
   * @return array|array[string]
   */
  public function getHeader( $name = null ) {

    $name = mb_strtolower( $name );
    return empty( $name ) ? $this->_header : ( isset( $this->_header[ $name ] ) ? $this->_header[ $name ] : [ ] );
  }
  /**
   * @param string $name
   * @param mixed  $value
   * @param bool   $append
   *
   * @return static
   */
  public function setHeader( $value, $name = null, $append = false ) {

    // force value to array
    if( empty( $value ) ) $value = [ ];
    else $value = (array) $value;

    // create the header if empty
    $name = mb_strtolower( $name );
    if( !empty( $name ) && !isset( $this->_header[ $name ] ) ) {
      $this->_header[ $name ] = [ ];
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
