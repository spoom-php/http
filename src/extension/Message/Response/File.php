<?php namespace Spoom\Http\Message\Response;

use Spoom\Framework\FileInterface;
use Spoom\Framework\Helper\StreamInterface;
use Spoom\Http\Message;

/**
 * Class File
 * @package Spoom\Http\Message\Response
 *
 * @property FileInterface $file     The file
 * @property string|null   $name     The output file name with extesion (populated from the path if empty)
 * @property bool          $download Force the file download
 */
class File extends Message\Response {

  /**
   * The full path to the file
   *
   * @var FileInterface
   */
  private $_file;
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
  public function __construct( int $status = self::STATUS_OK, array $header = [], ?StreamInterface $body = null ) {
    parent::__construct( $status, $header + [ 'binary' => 'content-transfer-encoding' ], $body );
  }

  /**
   * @return bool
   */
  public function isDownload(): bool {
    return $this->_download;
  }
  /**
   * @param bool $value
   */
  public function setDownload( bool $value ) {
    $this->_download = $value;

    $file = !empty( $this->_name ) ? ( '; filename="' . $this->_name . '"' ) : '';
    $this->setHeader( ( $this->_download ? 'attachment' : 'inline' ) . $file, 'content-disposition' );
  }

  /**
   * @return string|null
   */
  public function getName():?string {
    return $this->_name;
  }
  /**
   * @param string|null $value
   */
  public function setName( ?string $value ) {
    $this->_name = $value !== null ? (string) $value : null;
    $this->setDownload( $this->_download );
  }
  /**
   * @return FileInterface
   */
  public function getFile() {
    return $this->_file;
  }
  /**
   * @param FileInterface|null $value The file
   * @param bool               $name  Set name from the file
   */
  public function setFile( ?FileInterface $value, bool $name = true ) {

    if( $value === null ) {

      $this->setBody( null );
      $this->setHeader( null, 'content-length' );

      if( $name ) $this->setName( null );

    } else {

      $this->setBody( $value->stream() );
      $this->_file = $value;

      // setup file specific headers
      $meta = $value->getMeta( [ FileInterface::META_SIZE, FileInterface::META_MIME ] );
      $this->setHeader( $meta[ FileInterface::META_SIZE ], 'content-length' );
      if( !empty( $meta[ FileInterface::META_MIME ] ) ) $this->setHeader( $meta[ FileInterface::META_MIME ], 'content-type' );
      if( $name ) $this->setName( pathinfo( $value->getPath(), PATHINFO_BASENAME ) );
    }
  }
}
