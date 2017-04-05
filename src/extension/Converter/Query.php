<?php namespace Spoom\Http\Converter;

use Spoom\Framework\Helper;
use Spoom\Framework;

/**
 * Class Query
 *
 * TODO handle exceptions
 * TODO check for input restrictions
 * TODO decide how to handle arrays (CGI or PHP way)
 *
 * @package Spoom\Http\Converter
 */
class Query implements Framework\ConverterInterface, Helper\AccessableInterface {
  use Helper\Accessable;
  use Helper\Failable;

  const FORMAT = 'url-query';
  const NAME   = '';

  /**
   * @var QueryMeta
   */
  private $_meta;

  /**
   * @param QueryMeta|int $options
   * @param int           $depth
   * @param bool          $associative
   */
  public function __construct( $options = JSON_PARTIAL_OUTPUT_ON_ERROR, int $depth = 512, bool $associative = false ) {
    $this->_meta = $options instanceof QueryMeta ? $options : new QueryMeta( $options, $depth, $associative );
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

    $result = http_build_query( $content, null, '&', PHP_QUERY_RFC3986 );
    if( !$stream ) return $result;
    else {

      $stream->write( $result );
      return null;
    }
  }
  //
  public function unserialize( $content ) {
    $this->setException();

    // handle stream input
    if( $content instanceof Helper\StreamInterface ) {
      $content = $content->read();
    }

    $result = [];
    parse_str( $content, $result );

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
   */
  public function setMeta( $value ) {
    if( !( $value instanceof QueryMeta ) ) throw new \InvalidArgumentException( 'Meta must be a subclass of ' . QueryMeta::class, $value );
    else $this->_meta = $value;

    return $this;
  }

  //
  public function getFormat(): string {
    return static::FORMAT;
  }
  //
  public function getName(): string {
    return static::NAME;
  }
}
/**
 * Class QueryMeta
 * @package Framework\Converter
 */
class QueryMeta {

  /**
   * Numeric prefix for serialize
   *
   * @var string|null
   */
  public $prefix = null;
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
   * @param null|string $prefix
   */
  public function __construct( int $encoding = PHP_QUERY_RFC3986, string $separator = '&', ?string $prefix = null ) {
    $this->encoding  = $encoding;
    $this->separator = $separator;
    $this->prefix    = $prefix;
  }
}
