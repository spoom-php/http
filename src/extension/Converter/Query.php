<?php namespace Spoom\Http\Converter;

use Spoom\Core\Helper;
use Spoom\Core;

/**
 * Class Query
 *
 * TODO create tests
 *
 * @package Spoom\Http\Converter
 */
class Query implements Core\ConverterInterface, Helper\AccessableInterface {
  use Helper\Accessable;
  use Helper\Failable;

  /**
   * @var QueryMeta
   */
  private $_meta;

  /**
   * @param QueryMeta|int $encoding
   * @param string        $separator
   */
  public function __construct( $encoding = PHP_QUERY_RFC3986, string $separator = '&' ) {
    $this->_meta = $encoding instanceof QueryMeta ? $encoding : new QueryMeta( $encoding, $separator );
  }
  /**
   *
   */
  public function __clone() {
    $this->_meta = clone $this->_meta;
  }

  //
  public function serialize( $content, ?Helper\StreamInterface $stream = null ):?string {
    $this->setException();

    // this converter can only serialize collections
    if( !Helper\Collection::is( $content ) ) $this->setException( new \InvalidArgumentException( 'Content must be a valid collection' ) );
    else {

      $result = http_build_query( $content, null, $this->getMeta()->separator, $this->getMeta()->encoding );
      if( !$stream ) return $result;
      else $stream->write( $result );
    }

    return null;
  }
  //
  public function unserialize( $content ) {
    $this->setException();

    // handle stream input
    if( $content instanceof Helper\StreamInterface ) {
      $content = $content->read();
    }

    $result  = [];
    $content = Helper\Text::read( $content, null );
    if( $content === null ) $this->setException( new \InvalidArgumentException( 'Content must be a valid text' ) );
    else parse_str( $content, $result );

    return $result;
  }

  /**
   * @return QueryMeta
   */
  public function getMeta() {
    return $this->_meta;
  }
  /**
   * @param QueryMeta $value
   *
   * @return $this
   * @throws \InvalidArgumentException
   */
  public function setMeta( $value ) {
    if( !( $value instanceof QueryMeta ) ) throw new \InvalidArgumentException( 'Meta must be a subclass of ' . QueryMeta::class, $value );
    else $this->_meta = $value;

    return $this;
  }
}
/**
 * Class QueryMeta
 * @package Core\Converter
 */
class QueryMeta {

  /**
   * Encoding for serialization
   *
   * @var int
   */
  public $encoding = PHP_QUERY_RFC3986;
  /**
   * Separator character
   *
   * @var string
   */
  public $separator = '&';

  /**
   * @param int         $encoding
   * @param string      $separator
   */
  public function __construct( int $encoding = PHP_QUERY_RFC3986, string $separator = '&' ) {
    $this->encoding  = $encoding;
    $this->separator = $separator;
  }
}
