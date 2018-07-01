<?php namespace Spoom\Http\Converter;

use Spoom\Core\Application;
use Spoom\Core\Exception;
use Spoom\Core\Helper;
use Spoom\Core\Helper\StreamInterface;
use Spoom\Core;
use Spoom\Core\Helper\Text;

/**
 * Class Multipart
 *
 * FIXME better specification support (http://www.w3.org/Protocols/rfc1341/7_2_Multipart.html) with more abstraction
 * TODO create tests
 *
 * @package Spoom\Http\Helper
 */
class Multipart implements Core\ConverterInterface, Helper\AccessableInterface {
  use Helper\Accessable;

  /**
   * Line separator
   */
  const SEPARATOR_LINE = "\r\n";
  /**
   * Multipart start and end "flag"
   */
  const SEPARATOR_END = "--";
  /**
   * Header content and name separator
   */
  const SEPARATOR_HEAD_CONTENT = ":";
  /**
   * Header content data separator
   */
  const SEPARATOR_HEAD_DATA = ";";
  /**
   * Header content data name and value separator
   */
  const SEPARATOR_HEAD_VALUE = "=";

  /**
   * The input reading chunk length
   */
  const CHUNK = 4096;

  /**
   */
  public function __construct() {

  }

  //
  public function serialize( $content, ?Helper\StreamInterface $stream = null ):?string {

    // TODO implement!
    throw new \LogicException( "Not implemented, yet" );
  }
  //
  public function unserialize( $content ) {
    $raw = [];

    // process the multipart data into associative array
    $data = $this->read( Helper\Stream::instance( $content ) );
    foreach( $data as $value ) {

      // TODO Support access to metadata somehow
      $key         = $value->meta[ 'content-disposition' ][ 'name' ];
      $raw[ $key ] = isset( $value->meta[ 'content-disposition' ][ 'filename' ] ) ? $value->content : (string) $value->content;
    }

    // process sub-arrays (defined by keys)
    if( count( $raw ) ) {

      // build a query string from with the keys
      $query = '';
      foreach( $raw as $key => $value ) {
        $query .= '&' . $key . '=' . urlencode( $key );
      }

      // parse query string and fill it from the raw array 
      $keys = [];
      parse_str( substr( $query, 1 ), $keys );
      array_walk_recursive( $keys, function ( &$key ) use ( $raw ) {
        $key = $raw[ $key ];
      } );

      return $keys;
    }

    return [];
  }

  /**
   * Parse a header field
   *
   * @param string $tmp
   * @param array  $headers
   */
  protected function header( string $tmp, array &$headers ) {

    // process the header into name and content
    list( $name, $content ) = explode( self::SEPARATOR_HEAD_CONTENT, $tmp );

    // process the content' into options
    $options = [];
    $content = explode( self::SEPARATOR_HEAD_DATA, $content );
    foreach( $content as &$part ) {

      // the first part (that has no '=' sign) will be the header' value
      $part = explode( self::SEPARATOR_HEAD_VALUE, trim( $part ) );
      if( count( $part ) < 2 ) $options[ 'value' ] = $part[ 0 ];
      else $options[ $part[ 0 ] ] = trim( $part[ 1 ], '"' );
    }

    $headers[ mb_strtolower( $name ) ] = $options;
  }
  /**
   * @param StreamInterface $input
   *
   * @return array An array of multipart data
   * @throws MultipartExceptionInvalid
   */
  protected function read( StreamInterface $input ): array {
    $result = [];
    $buffer = '';

    // first find the separator string (boundary)
    $boundary = $this->next( $input, self::SEPARATOR_LINE, $buffer );
    if( !empty( $boundary ) ) while( true ) {

      // the first lines (min 1) is the headers of the value
      $headers = [];
      while( true ) {

        // read until we find a line that is not header (empty line)
        $tmp = $this->next( $input, self::SEPARATOR_LINE, $buffer );
        if( $tmp == '' ) break;

        $this->header( $tmp, $headers );
      }

      // save the multipart data
      $value    = $this->next( $input, self::SEPARATOR_LINE . $boundary, $buffer );
      $result[] = MultipartData::instance( [ 'content' => $value, 'meta' => $headers ] );

      // check the multipart data' end
      $last = $this->next( $input, self::SEPARATOR_LINE, $buffer );
      if( $last == self::SEPARATOR_END ) break;

      // check for invalid multipart message
      if( $buffer == '' && $input->isEnd() ) {
        throw new MultipartExceptionInvalid( $input->read( 0, 0 ) );
      }
    }

    return $result;
  }
  /**
   * Read the input stream into a stream or memory until a string
   *
   * @param StreamInterface $input
   * @param string          $stop   The string that will stop the reading
   * @param string          $buffer The remain content from the stream after the $stop (readed from the stream and not used)
   *
   * @return StreamInterface The $content or a string
   */
  private function next( StreamInterface $input, string $stop, string &$buffer ): StreamInterface {

    $stop_size = strlen( $stop );
    $output    = new Helper\Stream( 'php://temp', Helper\Stream::MODE_RW );

    // read until the stop string
    while( ( $position = strpos( $buffer, $stop ) ) === false ) {

      $tmp = $input->read( $stop_size > self::CHUNK ? $stop_size : self::CHUNK );
      if( !$tmp ) break;
      else {

        // remove the "safe" (doesn't include the stop string) string from the buffer into the content for optimalisation
        $output->write( ( $safe = substr( $buffer, 0, -$stop_size ) ) );
        $buffer = substr( $buffer, -$stop_size ) . $tmp;
      }
    }

    // remove the final "safe" (doesn't include the stop string) string from the buffer into the content
    $output->write( substr( $buffer, 0, $position ) );
    $buffer = substr( $buffer, $position + $stop_size );

    return $output->seek( 0 );
  }

  //
  public function getMeta() {
    return null;
  }
  //
  public function setMeta( $value ) {
    return $this;
  }
}
/**
 * Class MultipartData
 */
class MultipartData extends Helper\Wrapper {
  
  /**
   * @var array
   */
  public $meta;
  /**
   * @var StreamInterface
   */
  public $content;

  //
  function __toString() {
    return Text::read( $this->content );
  }
}

/**
 * Invalid multipart message
 *
 * @package Spoom\Http\Helper
 */
class MultipartExceptionInvalid extends Exception\Runtime {

  const ID = 'http#17';

  /**
   * @param string          $message The invalid (raw) message
   * @param null|\Throwable $previous
   */
  public function __construct( string $message, ?\Throwable $previous = null ) {
    parent::__construct( "Invalid multipart message", static::ID, [ 'message' => $message ], $previous, Application::SEVERITY_NOTICE );
  }

}
