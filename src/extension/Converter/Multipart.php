<?php namespace Spoom\Http\Converter;

use Spoom\Framework\Application;
use Spoom\Framework\Exception;
use Spoom\Framework\Helper;
use Spoom\Framework\Helper\StreamInterface;
use Spoom\Framework;

/**
 * Class Multipart
 *
 * FIXME better specification support (http://www.w3.org/Protocols/rfc1341/7_2_Multipart.html) with more abstraction
 *
 * @package Spoom\Http\Helper
 */
class Multipart implements Framework\ConverterInterface, Helper\AccessableInterface {
  use Helper\Accessable;
  use Helper\Failable;

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
   * @var resource[]
   */
  private static $resource = [];

  /**
   *
   *
   *
   * @throws Exception
   */
  public function __construct() {

  }

  //
  public function serialize( $content, ?Helper\StreamInterface $stream = null ):?string {
    $this->setException();

    // TODO implement!
    $this->setException( new \LogicException( "Not implemented, yet" ) );
    return null;
  }
  //
  public function unserialize( $content ) {
    $this->setException();

    $raw = $result = [];

    // process the multipart data into the raw containers (to process the array names later)
    $data = $this->read( Helper\Stream::instance( $content ) );
    foreach( $data as $value ) {
      if( isset( $value->meta[ 'content-disposition' ][ 'filename' ] ) ) {

        $tmp = [
          'name'     => $value->meta[ 'content-disposition' ][ 'filename' ],
          'type'     => isset( $value->meta[ 'content-type' ][ 'value' ] ) ? $value->meta[ 'content-type' ][ 'value' ] : '',
          'size'     => null,
          'tmp_name' => null,
          'error'    => UPLOAD_ERR_OK
        ];

        // setup content related values 
        if( is_resource( $value->content ) ) {

          $tmp[ 'tmp_name' ] = stream_get_meta_data( $value->content )[ 'uri' ];
          $tmp[ 'size' ]     = $tmp[ 'tmp_name' ] && is_file( $tmp[ 'tmp_name' ] ) ? filesize( $tmp[ 'tmp_name' ] ) : 0;
        }

        // calculate file errors
        if( empty( $tmp[ 'tmp_name' ] ) ) $tmp[ 'error' ] = UPLOAD_ERR_CANT_WRITE;
        else if( empty( $tmp[ 'size' ] ) ) $tmp[ 'error' ] = UPLOAD_ERR_NO_FILE;
        else ; // FIXME maybe check the file size for overflow

        $raw[] = [
          'name'  => $value->meta[ 'content-disposition' ][ 'name' ],
          'value' => $tmp
        ];

      } else {
        $raw[] = [
          'name'  => $value->meta[ 'content-disposition' ][ 'name' ],
          'value' => $value->content
        ];
      }
    }

    // parse the post names
    if( count( $raw ) ) {

      $query = '';
      foreach( $raw as $key => $value ) {
        $query .= '&' . $value[ 'name' ] . '=' . urlencode( $key );
      }

      $keys = [];
      parse_str( substr( $query, 1 ), $keys );
      array_walk_recursive( $keys, function ( &$key ) use ( $raw ) {
        $key = $raw[ $key ][ 'value' ];
      } );

      $result += $keys;
    }

    return $result;
  }

  public function read( StreamInterface $input ): array {
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

      // read the last "line" which will be the content. The files is readed to a temp file instead of the memory
      if( !isset( $headers[ 'content-disposition' ][ 'filename' ] ) ) $value = $this->next( $input, self::SEPARATOR_LINE . $boundary, $buffer );
      else {

        $value = $this->next( $input, self::SEPARATOR_LINE . $boundary, $buffer, tmpfile() );
        if( is_resource( $value ) ) self::$resource[] = $value;
      }

      // save the multipart data
      $result[] = new MultipartData( $value, $headers );

      // check the multipart data' end
      $last = $this->next( $input, self::SEPARATOR_LINE, $buffer );
      if( $last == self::SEPARATOR_END ) break;

      // check for invalid multipart message
      if( $buffer == '' && feof( $input->getResource() ) ) {
        throw new MultipartExceptionInvalid( $input->read( 0, 0 ) );
      }
    }

    return $result;
  }

  /**
   * Parse a header field
   *
   * @param string $tmp
   * @param array  $headers
   */
  private function header( string $tmp, array &$headers ) {

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
   * Read the input stream into a stream or memory until a string
   *
   * @param StreamInterface $stream
   * @param string          $stop    The string that will stop the reading
   * @param string          $buffer  The remain content from the stream after the $stop (readed from the stream and not used)
   * @param resource|null   $content A stream to write the content. If null the result will be a string
   *
   * @return resource|string The $content or a string
   */
  private function next( StreamInterface $stream, string $stop, string &$buffer, $content = null ) {

    $stop_size = strlen( $stop );
    $string    = false;
    if( $content === null ) {
      $string  = true;
      $content = fopen( 'php://memory', 'w+' );
    }

    // read until the stop string
    while( ( $position = strpos( $buffer, $stop ) ) === false ) {

      $tmp = $stream->read( $stop_size > self::CHUNK ? $stop_size : self::CHUNK );
      if( !$tmp ) break;
      else {

        // remove the "safe" (doesn't include the stop string) string from the buffer into the content for optimalisation
        $safe = substr( $buffer, 0, -$stop_size );
        if( is_resource( $content ) ) fwrite( $content, $safe );
        $buffer = substr( $buffer, -$stop_size ) . $tmp;
      }
    }

    // remove the final "safe" (doesn't include the stop string) string from the buffer into the content
    if( is_resource( $content ) ) fwrite( $content, substr( $buffer, 0, $position ) );
    $buffer = substr( $buffer, $position + $stop_size );

    if( is_resource( $content ) ) rewind( $content );
    return $string ? stream_get_contents( $content ) : $content;
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
 * @package Spoom\Http\Helper
 *
 * @property-read array           $meta
 * @property-read string|resource $content
 */
class MultipartData implements Helper\AccessableInterface {
  use Helper\Accessable;

  /**
   * @var array
   */
  private $_meta;
  /**
   * @var string|resource
   */
  private $_content;

  /**
   * @param string|resource $content
   * @param array           $meta
   */
  public function __construct( $content, array $meta ) {

    $this->_meta    = $meta;
    $this->_content = $content;
  }

  /**
   * @return array
   */
  public function getMeta(): array {
    return $this->_meta;
  }
  /**
   * @return string|resource
   */
  public function getContent() {
    return $this->_content;
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
