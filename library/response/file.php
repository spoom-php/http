<?php namespace Http\Response;

use Framework\Exception;
use Http\Response;

/**
 * Class File
 * @package Http\Response
 *
 * @property string  $path     The full path to the file
 * @property string  $name     The output file name with extesion (populated from the path if empty)
 * @property boolean $download Force the file download
 */
class File extends Buffer {

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
   * Chucks size for the file reading
   *
   * @var int
   */
  protected $chunk = 4096;

  /**
   * The full path to the file
   *
   * @var string
   */
  protected $_path;
  /**
   * The name of the file
   *
   * @var string
   */
  protected $_name;
  /**
   * Force the download or not
   *
   * @var boolean
   */
  protected $_download;

  /**
   * @param string $name
   * @param mixed  $value
   */
  function __set( $name, $value ) {

    switch( $name ) {
      case 'path':
        $this->_path = $value !== null ? (string) $value : null;
        break;
      case 'name':
        $this->_name = $value !== null ? (string) $value : null;
        break;
      case 'download':
        $this->_download = (bool) $value;
        break;
      default:
        parent::__set( $name, $value );
    }
  }

  /**
   * @inheritdoc
   */
  public function send() {

    if( !$this->_sent ) {

      if( !is_file( $this->_path ) ) throw new Exception\Strict( self::EXCEPTION_MISSING_FILE, [ 'file' => $this->_path ] );
      else if( !is_readable( $this->_path ) ) throw new Exception\Strict( self::EXCEPTION_INVALID_FILE, [ 'file' => $this->_path ] );
      else {

        // setup file specific headers
        if( !$this->_header->exist( 'content-transfer-encoding' ) ) $this->_header->set( 'content-transfer-encoding', 'binary' );
        if( !$this->_header->exist( 'content-length' ) ) $this->_header->set( 'content-length', filesize( $this->_path ) );
        if( !$this->_header->exist( 'content-disposition' ) ) {

          $name = empty( $this->_name ) ? pathinfo( $this->_path, PATHINFO_BASENAME ) : $this->_name;
          $this->_header->set( 'content-disposition', ( $this->_download ? 'attachment' : 'inline' ) . '; filename="' . $name . '"' );
        }

        // open and setup the file resource as the buffer
        $tmp = @fopen( $this->_path, 'rb' );
        if( !is_resource( $tmp ) ) throw new Exception\Strict( self::EXCEPTION_FAIL_FILE, [ 'file' => $this->_path ] );
        else {

          $this->buffer = $tmp;
          parent::send();
        }
      }
    }
  }
}
