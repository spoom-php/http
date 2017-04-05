<?php namespace Spoom\Http;

use Spoom\Framework\Storage;
use Spoom\Framework\Helper\StreamInterface;
use Spoom\Http\Helper\UriInterface;
use Spoom\Framework;

/**
 * Class Input
 *
 * TODO add ability to override or extend converters without event
 * TODO construct from `Message\RequestInterface`
 *
 * @package Spoom\Http
 */
class Input extends Storage {

  /**
   * Mix the 'get' and the 'post' container
   */
  const NAMESPACE_REQUEST = 'request';
  /**
   * Namespace for $_POST superglobal
   */
  const NAMESPACE_BODY = 'body';
  /**
   * Namespace for $_GET superglobal
   */
  const NAMESPACE_URI = 'uri';

  /**
   * Preprocess the input before the construct
   *
   * @param static               $instance
   * @param UriInterface         $uri
   * @param StreamInterface|null $body
   * @param string|null          $format
   *
   * @prevent Uri and body processing
   */
  const EVENT_CREATE = 'input.create';

  /**
   * @var Framework\ConverterInterface[]
   */
  private $_converter_map;

  /**
   * Parse input stream and uri into structural data
   *
   * @param UriInterface         $uri
   * @param StreamInterface|null $body
   * @param string|null          $format
   */
  public function __construct( $uri, $body = null, $format = null ) {
    parent::__construct( [], static::NAMESPACE_REQUEST );

    $this->_converter_map = [
      'application/json'                  => $tmp = new Framework\Converter\Json(),
      'text/json'                         => $tmp,
      'application/xml'                   => $tmp = new Framework\Converter\Xml(),
      'text/xml'                          => $tmp,
      'multipart/form-data'               => new Converter\Multipart(),
      'application/x-www-form-urlencoded' => new Converter\Query()
    ];

    // trigger the process event
    $extension = Extension::instance();
    $event     = $extension->trigger( self::EVENT_CREATE, [
      'instance' => $this,
      'uri'      => $uri,
      'body'     => $body,
      'format'   => $format
    ] );

    if( !$event->isPrevented() ) {

      // set "public" and "private" data
      $this[ self::NAMESPACE_URI . ':' ]  = $uri->getQuery();
      $this[ self::NAMESPACE_BODY . ':' ] = $this->process( $body, $format );
    }

    // merge the "public" and "private" storages
    $this[ self::NAMESPACE_REQUEST . ':' ] = static::merge( $this->getArray( self::NAMESPACE_URI . ':' ), $this->getArray( self::NAMESPACE_BODY . ':' ) );
  }

  /**
   * Process the body into the input's storage
   *
   * @param StreamInterface|null $body
   * @param string|null          $format
   *
   * @return array
   */
  private function process( $body, $format ) {

    if( !$body ) return [];
    else {

      $converter = $this->_converter_map[ $format ];
      if( empty( $converter ) ) throw new \RuntimeException(); // TODO exception
      else {

        $tmp = $converter->unserialize( $body );
        if( $converter->getException() ) throw new \RuntimeException(); // TODO exception
        else return $tmp;
      }
    }
  }

  /**
   * Recursive merge of two arrays. This is like the array_merge_recursive() without the strange array-creating thing
   *
   * @param array $destination
   * @param array $source
   *
   * @return array
   */
  private static function merge( array $destination, array $source ) {

    $result = $destination;
    foreach( $source as $key => &$value ) {
      if( !is_array( $value ) || !isset ( $result[ $key ] ) || !is_array( $result[ $key ] ) ) $result[ $key ] = $value;
      else $result[ $key ] = static::merge( $result[ $key ], $value );
    }

    return $result;
  }
  /**
   * Populate an empty input from a request object
   *
   * @param Message\RequestInterface $request
   * @param bool                     $native Add native PHP container values
   *
   * @return static
   */
  public static function instance( $request, $native = false ) {

    $tmp = $request->getHeader( 'content-type' );
    list( $tmp ) = explode( ';', is_array( $tmp ) ? implode( ';', $tmp ) : $tmp );
    $instance = new static( $request->getUri(), $request->getBody(), $tmp );

    // handle $_POST and $_FILES data
    if( $native ) {

      // preprocess native inputs
      $data = empty( $_POST ) ? [] : $_POST;
      if( !empty( $_FILES ) ) {

        /**
         * Recursive container filling helper (for the $_FILES processing)
         *
         * @param array  $container The container that will hold the value
         * @param mixed  $value     The actual value
         * @param string $name      The name of the property
         */
        function helper( &$container, &$value, $name ) {

          if( !is_array( $value ) ) $container[ $name ] = $value;
          else foreach( $value as $i => $v ) {

            if( !isset( $container[ $i ] ) ) $container[ $i ] = [];
            helper( $container[ $i ], $v, $name );
          }
        }

        // walk the files
        foreach( $_FILES as $index => $value ) {
          if( !is_array( $value[ 'name' ] ) ) $data[ $index ] = $value;
          else {

            if( !is_array( $data[ $index ] ) ) $data[ $index ] = [];
            foreach( $value as $property => $container ) {
              helper( $data[ $index ], $container, $property );
            }
          }
        }
      }

      // extend the instance body content
      if( !empty( $data ) ) {

        $data                                        = static::merge( $data, $instance->getArray( static::NAMESPACE_BODY . ':' ) );
        $instance[ static::NAMESPACE_BODY . ':' ]    = $data;
        $instance[ static::NAMESPACE_REQUEST . ':' ] = static::merge( $instance->getArray( static::NAMESPACE_URI . ':' ), $data );
      }
    }

    return $instance;
  }
}
