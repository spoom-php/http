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

  /**
   * Get a specific (or all) header value
   *
   * @param string $name Case-insensitive
   *
   * @return array|string|null
   */
  public function getHeader( $name = null );
  /**
   * Set (or extend) a specific or all header. A field can be removed with null value
   *
   * @param mixed       $value
   * @param string|null $name   Case-insensitive
   * @param bool        $append Extend or set the value(s)
   *
   * @return static
   */
  public function setHeader( $value, $name = null, $append = false );
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
   * Header storage. The key is the header field name, the value is the field string (or an array of string) data
   *
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
   * @return array|string|null
   */
  public function getHeader( $name = null ) {

    // find the name (non-casesensitive) in the header
    if( $name ) foreach( $this->_header as $tmp => $_ ) {
      if( mb_strtolower( $name ) == mb_strtolower( $tmp ) ) {
        $name = $tmp;
        break;
      }
    }

    return empty( $name ) ? $this->_header : ( isset( $this->_header[ $name ] ) ? $this->_header[ $name ] : null );
  }
  /**
   * Set (or extend) a specific or all header. A field can be removed with null value
   *
   * @param mixed       $value
   * @param string|null $name   Case-insensitive
   * @param bool        $append Extend or set the value(s)
   *
   * @return static
   */
  public function setHeader( $value, $name = null, $append = false ) {

    if( $name !== null ) return $this->setHeaderField( $name, $value, $append );
    else {

      // clear the current headers of not an append
      if( !$append ) {
        $this->_header = [];
      }

      foreach( $value as $i => $v ) {
        $this->setHeaderField( $i, $v, false );
      }
    }

    return $this;
  }
  /**
   * Set (or extend) directly a header field
   *
   * @param string               $name   The header field's name
   * @param string|string[]|null $value  The header value, or null to remove
   * @param bool                 $append Extend or set the value
   *
   * @return static
   */
  public function setHeaderField( $name, $value = null, $append = false ) {

    // find the name (non-casesensitive) in the header
    $name_old = $name;
    foreach( $this->_header as $tmp => $_ ) {
      if( mb_strtolower( $name ) == mb_strtolower( $tmp ) ) {
        $name_old = $tmp;
        break;
      }
    }

    if( $value === null ) unset( $this->_header[ $name_old ] );
    else {

      // remove the old value to change the name later (if it's a set)
      $tmp = isset( $this->_header[ $name_old ] ) ? $this->_header[ $name_old ] : null;
      unset( $this->_header[ $name_old ] );

      // simple set
      if( !$append || !isset( $tmp ) ) $this->_header[ $name ] = $value;
      else {

        // convert the field storage into an array
        if( !is_array( $tmp ) ) $tmp = [ $tmp ];
        $this->_header[ $name_old ] = array_merge( $tmp, is_array( $value ) ? $value : [ $value ] );
      }
    }

    return $this;
  }
}
