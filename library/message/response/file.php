<?php namespace Http\Message\Response;

use Framework\Exception;
use Http\Helper\Stream;
use Http\Message;

/**
 * Class File
 * @package Http\Message\Response
 *
 * @property string  $path     The full path to the file
 * @property string  $name     The output file name with extesion (populated from the path if empty)
 * @property boolean $download Force the file download
 */
class File extends Message\Response {

  /**
   * The file is not exists
   */
  const EXCEPTION_MISSING_FILE = 'http#5C';
  /**
   * The file is not readable
   */
  const EXCEPTION_INVALID_FILE = 'http#6C';
  /**
   * Can't open the file for reading
   */
  const EXCEPTION_FAIL_FILE = 'http#7C';

  /**
   * The full path to the file
   *
   * @var string
   */
  private $_path;
  /**
   * The name of the file
   *
   * @var string
   */
  private $_name;
  /**
   * Force the download or not
   *
   * @var boolean
   */
  private $_download;

  /**
   * @inheritDoc
   */
  public function __construct( $status = self::STATUS_OK, array $header = [], $body = null ) {
    parent::__construct( $status, $header + [ 'binary' => 'content-transfer-encoding' ], $body );
  }

  /**
   * @return boolean
   */
  public function isDownload() {
    return $this->_download;
  }
  /**
   * @param boolean $value
   */
  public function setDownload( $value ) {
    $this->_download = (bool) $value;

    $file = !empty( $this->_name ) ? ( '; filename="' . $this->_name . '"' ) : '';
    $this->setHeader( ( $this->_download ? 'attachment' : 'inline' ) . $file, 'content-disposition' );
  }

  /**
   * @return string
   */
  public function getName() {
    return $this->_name;
  }
  /**
   * @param string $value
   */
  public function setName( $value ) {
    $this->_name = $value !== null ? (string) $value : null;
    $this->setDownload( $this->_download );
  }
  /**
   * @return string
   */
  public function getPath() {
    return $this->_path;
  }
  /**
   * @param string $value The file path
   * @param bool   $name  Set name from the file
   *
   * @throws Exception\Strict
   */
  public function setPath( $value, $name = true ) {

    if( $value === null ) {

      $this->setBody( null );
      $this->setHeader( null, 'content-length' );

      if( $name ) $this->setName( null );

    } else {

      $value = (string) $value;
      if( !is_file( $value ) ) throw new Exception\Strict( self::EXCEPTION_MISSING_FILE, [ 'file' => $value ] );
      else if( !is_readable( $value ) ) throw new Exception\Strict( self::EXCEPTION_INVALID_FILE, [ 'file' => $value ] );
      else {

        // open and setup the file resource as the buffer
        $tmp = @fopen( $value, 'rb' );
        if( !is_resource( $tmp ) ) throw new Exception\Strict( self::EXCEPTION_FAIL_FILE, [ 'file' => $value ] );
        else {

          $this->setBody( Stream::instance( $tmp ) );
          $this->_path = $value;

          // setup file specific headers
          $this->setHeader( filesize( $value ), 'content-length' );
          $tmp = mime_content_type( $value );
          if( !empty( $tmp ) ) $this->setHeader( $tmp, 'content-type' );
          if( $name ) $this->setName( pathinfo( $value, PATHINFO_BASENAME ) );
        }
      }
    }
  }
}
