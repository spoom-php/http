<?php namespace Http;

use Framework\Helper\Library;
use Http\Helper\StreamInterface;

/**
 * Interface MessageInterface
 * @package Http
 */
interface MessageInterface {

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

  public function setCookie( $name, $value = null, $expire = 0, $option = [ ] );
  public function getCookie( $name = null );
}

/**
 * Class Message
 * @package Http
 */
abstract class Message extends Library implements MessageInterface {

  /**
   * @var string
   */
  private $_version;
  /**
   * @var StreamInterface
   */
  private $_body;

  /**
   * @var array[string]
   */
  private $_header;

  /**
   * @return string
   */
  public function getVersion() {
    return $this->_version;
  }
  /**
   * @param string $version
   *
   * @return static
   */
  public function setVersion( $version ) {
    $this->_version = $version;

    return $this;
  }

  /**
   * @return StreamInterface
   */
  public function getBody() {
    return $this->_body;
  }
  /**
   * @param StreamInterface $body
   *
   * @return static
   */
  public function setBody( $body ) {

    // TODO create a stream
    $this->_body = $body;

    return $this;
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

  public function setCookie( $name, $value = null, $expire = 0, $option = [ ] ) {
    // TODO: Implement setCookie() method.
  }
  public function getCookie( $name = null ) {
    // TODO: Implement getCookie() method.
  }
}
