<?php namespace Spoom\Http;

use Spoom\Core\Storage;
use Spoom\Core;
use Spoom\Http\Message\RequestInterface;
use Spoom\Core\Exception;
use Spoom\Core\Helper;

/**
 * Class Input
 *
 * TODO Add event for the processing
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
   * @param RequestInterface $request
   * @param array            $converters
   *
   * @throws InputExceptionBody Invalid or unknown body format
   */
  public function __construct( RequestInterface $request, array $converters = [] ) {
    parent::__construct( [], static::NAMESPACE_REQUEST );

    // set "public" and "private" data
    $this[ self::NAMESPACE_URI . ':' ] = $request->getUri()->getQuery();
    if( $request->getBody() ) {

      // define body's format
      $format = $request->getHeader( 'content-type' );
      list( $format ) = explode( ';', is_array( $format ) ? implode( ';', $format ) : $format );

      // collect available converters
      $map                = [ 'multipart/form-data' => new Converter\Multipart(), 'application/x-www-form-urlencoded' => new Converter\Query() ];
      $map[ 'text/json' ] = $map[ 'application/json' ] = new Core\Converter\Json();
      $map[ 'text/xml' ]  = $map[ 'application/xml' ] = new Core\Converter\Xml();
      $map                = $converters + $map;

      // process the body
      $converter = $map[ $format ] ?? null;
      if( empty( $converter ) ) throw new InputExceptionBody( $format, array_keys( $map ) );
      else try {
        $this[ self::NAMESPACE_BODY . ':' ] = $converter->unserialize( $request->getBody() );
      } catch( Core\ConverterFailException $e ) {
        throw new InputExceptionBody( $format, array_keys( $map ), $e );
      }
    }

    // merge the "public" and "private" storages
    $this[ self::NAMESPACE_REQUEST . ':' ] = Core\Helper\Collection::merge(
      $this->getArray( self::NAMESPACE_URI . ':' ),
      $this->getArray( self::NAMESPACE_BODY . ':' )
    );
  }
}

/**
 * Unable to process the request's body
 *
 * @package Spoom\Http
 */
class InputExceptionBody extends Exception\Runtime {

  const ID = '0#spoom-http'; // TODO define

  /**
   * @param string          $format Body format
   * @param array           $allow  Available format converters
   * @param \Throwable|null $throwable
   */
  public function __construct( string $format, array $allow = [], ?\Throwable $throwable = null ) {

    $data    = [ 'format' => $format, 'allow' => implode( ',', $allow ) ];
    $message = in_array( $format, $allow ) ? "HTTP request's body is in an unknown format ({format})" : "HTTP request's body can't be processed as '{format}'";

    parent::__construct(
      Helper\Text::apply( $message, $data ),
      static::ID,
      $data,
      $throwable,
      Core\Application::SEVERITY_NOTICE
    );
  }
}
