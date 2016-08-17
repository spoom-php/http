<?php namespace Http\Helper;

use Framework\Exception;
use Framework\Helper\Library;

/**
 * Interface StreamInterface
 * @package Http\Helper
 */
interface StreamInterface extends \Countable {

  /**
   * Convert the stream into a string, begin from the current cursor
   *
   * @return string
   */
  public function __toString();

  /**
   * Write to the stream
   *
   * @param string|StreamInterface $content The content to write
   * @param int|null               $offset  Offset in the stream where to write. Default (===null) is the current cursor
   *
   * @return $this
   * @throws Exception\Strict
   */
  public function write( $content, $offset = null );
  /**
   * Read from the stream
   *
   * @param int      $length The maximum byte to read
   * @param int|null $offset Offset in the stream to read from. Default (===null) is the current cursor
   *
   * @return string
   * @throws Exception\Strict
   */
  public function read( $length, $offset = null );

  /**
   * Move the internal cursor within the stream
   *
   * @param int $offset The new cursor position
   *
   * @return $this
   * @throws Exception\Strict
   */
  public function seek( $offset = 0 );

  /**
   * Get the internal stream resource
   *
   * @return resource|null
   */
  public function getResource();
  /**
   * Get the internal cursor position
   *
   * @return int
   */
  public function getOffset();
  /**
   * Get the raw metadata of the stream
   *
   * @param string|null $key Get a specific metadata instead of an array of them
   *
   * @return array|mixed
   */
  public function getMeta( $key = null );

  /**
   * Write to the stream is allowed
   *
   * @return boolean
   */
  public function isWritable();
  /**
   * Read from the stream is allowed
   *
   * @return boolean
   */
  public function isReadable();
  /**
   * Seek the stream is allowed
   *
   * @return boolean
   */
  public function isSeekable();
}
/**
 * Class Stream
 * @package Http\Helper
 */
class Stream extends Library implements StreamInterface {

  const EXCEPTION_INVALID_OPERATION = 'http#0E';
  const EXCEPTION_INVALID_OFFSET    = 'http#0E';
  const EXCEPTION_INVALID_RESOURCE  = 'http#0E';

  /**
   * @var resource
   */
  private $_resource;

  /**
   * @param resource $resource
   *
   * @throws Exception\Strict
   */
  public function __construct( $resource ) {
    if( !is_resource( $resource ) ) throw new Exception\Strict( static::EXCEPTION_INVALID_RESOURCE );
    else {

      $this->_resource = $resource;
    }
  }

  /**
   * Write to the stream
   *
   * @param string|StreamInterface $content The content to write
   * @param int|null               $offset  Offset in the stream where to write. Default (===null) is the current cursor
   *
   * @return $this
   * @throws Exception\Strict
   */
  public function write( $content, $offset = null ) {

    if( !$this->isWritable() ) throw new Exception\Strict( static::EXCEPTION_INVALID_OPERATION, [ 'meta' => $this->getMeta() ] );
    else {

      // seek to a position if given
      if( $offset !== null ) {
        if( !$this->isSeekable() ) throw new Exception\Strict( static::EXCEPTION_INVALID_OPERATION, [ 'meta' => $this->getMeta() ] );
        else $this->seek( $offset );
      }

      // write the content
      if( $content instanceof StreamInterface ) stream_copy_to_stream( $content->getResource(), $this->_resource );
      else fwrite( $this->_resource, $content );

      return $this;
    }
  }
  /**
   * Read from the stream
   *
   * @param int      $length The maximum byte to read
   * @param int|null $offset Offset in the stream to read from. Default (===null) is the current cursor
   *
   * @return string
   * @throws Exception\Strict
   */
  public function read( $length, $offset = null ) {

    if( !$this->isReadable() ) throw new Exception\Strict( static::EXCEPTION_INVALID_OPERATION, [ 'meta' => $this->getMeta() ] );
    else {

      // seek to a position if given
      if( $offset !== null ) {
        if( !$this->isSeekable() ) throw new Exception\Strict( static::EXCEPTION_INVALID_OPERATION, [ 'meta' => $this->getMeta() ] );
        else $this->seek( $offset );
      }

      // read the content
      return fread( $this->_resource, $length );
    }
  }

  /**
   * Move the internal cursor within the stream
   *
   * @param int $offset The new cursor position
   *
   * @return $this
   * @throws Exception\Strict
   */
  public function seek( $offset = 0 ) {
    if( !$this->isSeekable() ) throw new Exception\Strict( static::EXCEPTION_INVALID_OPERATION, [ 'meta' => $this->getMeta() ] );
    else if( $offset < 0 ) throw new Exception\Strict( static::EXCEPTION_INVALID_OFFSET );
    else fseek( $this->_resource, $offset );

    return $this;
  }

  /**
   * Count elements of an object
   * @link  http://php.net/manual/en/countable.count.php
   * @return int The custom count as an integer.
   * </p>
   * <p>
   * The return value is cast to an integer.
   * @since 5.1.0
   */
  public function count() {
    return $this->_resource ? fstat( $this->_resource )[ 'size' ] : 0;
  }

  /**
   * Get the internal stream resource
   *
   * @return resource|null
   */
  public function getResource() {
    return $this->_resource;
  }
  /**
   * Get the internal cursor position
   *
   * @return int
   */
  public function getOffset() {
    return $this->_resource ? ftell( $this->_resource ) : 0;
  }
  /**
   * Get the raw metadata of the stream
   *
   * @param string|null $key Get a specific metadata instead of an array of them
   *
   * @return array|mixed
   */
  public function getMeta( $key = null ) {

    $tmp = $this->_resource ? stream_get_meta_data( $this->_resource ) : null;
    if( empty( $tmp ) || empty( $key ) ) return $tmp;
    else return !empty( $tmp[ $key ] ) ? $tmp[ $key ] : null;
  }

  /**
   * Write to the stream is allowed
   *
   * @return boolean
   */
  public function isWritable() {
    $tmp = $this->getMeta( 'mode' );
    return $tmp && preg_match( '/(r\+|w\+?|a\+?|x\+?)/i', $tmp );
  }
  /**
   * Read from the stream is allowed
   *
   * @return boolean
   */
  public function isReadable() {
    $tmp = $this->getMeta( 'mode' );
    return $tmp && preg_match( '/(r\+?|w\+|a\+|x\+)/i', $tmp );
  }
  /**
   * Seek the stream is allowed
   *
   * @return boolean
   */
  public function isSeekable() {
    return (bool) $this->getMeta( 'seekable' );
  }

  /**
   * @param $value
   *
   * @return static
   * @throws Exception\Strict
   */
  public static function instance( $value ) {
    return $value instanceof StreamInterface ? $value : new static( $value );
  }
}
