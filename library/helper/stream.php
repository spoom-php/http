<?php namespace Http\Helper;

/**
 * Interface StreamInterface
 * @package Http\Helper
 */
interface StreamInterface extends \Countable {

  public function __toString();

  public function write( $content );
  public function read( $length, $offset = null );

  public function seek( $offset = 0 );

  public function getContent( $offset = null );
  public function getCursor();
  public function getMeta();

  public function isWritable();
  public function isReadable();
  public function isSeekable();
}
