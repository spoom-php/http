<?php namespace Http\Helper;

use Framework\Exception;
use Framework\Helper\Library;

/**
 * Class Multipart
 * @package Http\Helper
 *
 * @property-read MultipartData[] $data
 */
class Multipart extends Library {

  /**
   * @var resource[]
   */
  private static $resource = [ ];

  /**
   * Invalid end of the multipart input
   */
  const EXCEPTION_MISSING_END = 'http#17W';

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
   * The input stream
   *
   * @var resource
   */
  private $stream;

  /**
   * The parsed data
   *
   * @var MultipartData[]
   */
  private $_data;

  /**
   *
   * FIXME better specification support (http://www.w3.org/Protocols/rfc1341/7_2_Multipart.html) with more abstraction
   *
   * @param $input
   *
   * @throws Exception
   */
  public function __construct( $input ) {

    $this->stream = $input;
    $this->_data  = [ ];

    $buffer = '';

    // first find the separator string (boundary)
    $boundary = $this->read( self::SEPARATOR_LINE, $buffer );
    if( !empty( $boundary ) ) while( true ) {

      // the first lines (min 1) is the headers of the value
      $headers = [ ];
      while( true ) {

        // read until we find a line that is not header (empty line)
        $tmp = $this->read( self::SEPARATOR_LINE, $buffer );
        if( $tmp == '' ) break;

        $this->header( $tmp, $headers );
      }

      // read the last "line" which will be the content. The files is readed to a temp file instead of the memory
      if( !isset( $headers[ 'content-disposition' ][ 'filename' ] ) ) $value = $this->read( self::SEPARATOR_LINE . $boundary, $buffer );
      else {

        $value = $this->read( self::SEPARATOR_LINE . $boundary, $buffer, tmpfile() );
        if( is_resource( $value ) ) self::$resource[] = $value;
      }

      // save the multipart data
      $this->_data[] = new MultipartData( $value, $headers );

      // check the multipart data' end
      $last = $this->read( self::SEPARATOR_LINE, $buffer );
      if( $last == self::SEPARATOR_END ) break;

      // check for invalid multipart message
      if( $buffer == '' && feof( $this->stream ) ) {
        throw new Exception\System( self::EXCEPTION_MISSING_END );
      }
    }
  }

  /**
   * Parse a header field
   *
   * @param string $tmp
   * @param array  $headers
   */
  private function header( $tmp, &$headers ) {

    // process the header into name and content
    list( $name, $content ) = explode( self::SEPARATOR_HEAD_CONTENT, $tmp );

    // process the content' into options
    $options = [ ];
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
   * @param string        $stop    The string that will stop the reading
   * @param string        $buffer  The remain content from the stream after the $stop (readed from the stream and not used)
   * @param resource|null $content A stream to write the content. If null the result will be a string
   *
   * @return resource|string The $content or a string
   */
  private function read( $stop, &$buffer, $content = null ) {

    $stop_size = strlen( $stop );
    $string    = false;
    if( $content === null ) {
      $string  = true;
      $content = fopen( 'php://memory', 'w+' );
    }

    // read until the stop string
    while( ( $position = strpos( $buffer, $stop ) ) === false ) {

      $tmp = fread( $this->stream, $stop_size > self::CHUNK ? $stop_size : self::CHUNK );
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

  /**
   * @return MultipartData[]
   */
  public function getData() {
    return $this->_data;
  }
}
/**
 * Class MultipartData
 * @package Http\Helper
 *
 * @property-read array           $meta
 * @property-read string|resource $content
 */
class MultipartData extends Library {

  /**
   * @var array
   */
  private $_meta;
  /**
   * @var string|resource
   */
  private $_content;

  /**
   * MultipartData constructor.
   *
   * @param string|resource $content
   * @param array           $meta
   */
  public function __construct( $content, $meta ) {

    $this->_meta    = $meta;
    $this->_content = $content;
  }

  /**
   * @return array
   */
  public function getMeta() {
    return $this->_meta;
  }
  /**
   * @return string|resource
   */
  public function getContent() {
    return $this->_content;
  }
}
