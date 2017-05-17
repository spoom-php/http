<?php namespace Spoom\Http;

use Spoom\Core\Helper;
use Spoom\Core\Helper\AccessableInterface;
use Spoom\Core\Helper\StreamInterface;

/**
 * Interface MessageInterface
 * @package Spoom\Http
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
  public function write( StreamInterface $stream );

  /**
   * Get the message's protocol version
   *
   * @return string
   */
  public function getVersion(): string;
  /**
   * Set the message's protocol version
   *
   * @param string $value
   *
   * @return static
   */
  public function setVersion( string $value );

  /**
   * Get the message's body
   *
   * @return StreamInterface|null
   */
  public function getBody(): ?StreamInterface;
  /**
   * Set (or clear) the message's body (stream)
   *
   * @param StreamInterface|null $value
   *
   * @return static
   */
  public function setBody( ?StreamInterface $value );

  /**
   * Get a specific (or all) header value
   *
   * @param string|null $name Case-insensitive
   *
   * @return array|string|null
   */
  public function getHeader( ?string $name = null );
  /**
   * Set (or extend) a specific or all header. A field can be removed with null value
   *
   * @param mixed       $value
   * @param string|null $name   Case-insensitive
   * @param bool        $append Extend or set the value(s)
   *
   * @return static
   */
  public function setHeader( $value, ?string $name = null, bool $append = false );
}
/**
 * Class Message
 * @package Spoom\Http
 */
abstract class Message implements AccessableInterface, MessageInterface {
  use Helper\Accessable;

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

  //
  public function getVersion(): string {
    return $this->_version;
  }
  //
  public function setVersion( string $value ) {

    if( empty( $value ) ) throw new \InvalidArgumentException( "Protocol version can't be empty" );
    else {

      $this->_version = (string) $value;
      return $this;
    }
  }

  //
  public function getBody(): ?StreamInterface {
    return $this->_body;
  }
  //
  public function setBody( ?StreamInterface $value ) {

    $this->_body = $value;
    return $this;
  }

  //
  public function getHeader( ?string $name = null ) {

    // find the name (non-casesensitive) in the header
    if( $name ) foreach( $this->_header as $tmp => $_ ) {
      if( mb_strtolower( $name ) == mb_strtolower( $tmp ) ) {
        $name = $tmp;
        break;
      }
    }

    return empty( $name ) ? $this->_header : ( isset( $this->_header[ $name ] ) ? $this->_header[ $name ] : null );
  }
  //
  public function setHeader( $value, ?string $name = null, bool $append = false ) {

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
  public function setHeaderField( string $name, $value = null, bool $append = false ) {

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
