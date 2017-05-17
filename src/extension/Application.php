<?php namespace Spoom\Http;

use Spoom\Core;
use Spoom\Http;
use Spoom\Core\Storage;
use Spoom\Http\Helper\Uri;
use Spoom\Http\Helper\UriInterface;
use Spoom\Core\Helper\Stream;

/**
 * Class Application
 * @package Spoom\Http
 */
class Application extends Core\Application {

  /**
   * Run the HTTP Application
   *
   * @param callable|null                 $callback
   * @param Message\RequestInterface|null $request
   * @param UriInterface|null             $uri
   * @param Input|null                    $input
   *
   * @return Message\Response
   */
  public function __invoke( Message\RequestInterface $request, ?callable $callback = null, ?UriInterface $uri = null, ?Input $input = null ) {

    $extension = Http\Extension::instance();
    try {

      //
      $uri   = $uri ?? static::getUri( $request );
      $input = $input ?? static::getInput( new Input( $request ) );

      //
      $extension->log->debug( "HTTP request is started for ({id}) '{method} {url}'", [
        'method' => strtoupper( $request->getMethod() ),
        'url'    => (string) $request->getUri(),
        'id'     => $this->id,
        'input'  => $input
      ] );

      // run callback script that handle the main logic and provides the response
      $response = $callback ? $callback( $input, $request, $uri ) : new Message\Response( null, Message\ResponseInterface::STATUS_CONTENT_EMPTY );
      if( !( $response instanceof Message\ResponseInterface ) ) {
        throw new \TypeError( 'HTTP result must be an instance of ' . Message\ResponseInterface::class );
      }

    } catch( \Throwable $e ) {

      //
      Core\Exception::log( $e, Http\Extension::instance()->log );

      // set status message accordingly to the exception's type
      if( $e instanceof Http\Exception ) $status = $e->getStatus();
      else $status = $e instanceof Core\Exception ? Http\Message\ResponseInterface::STATUS_BAD : Http\Message\ResponseInterface::STATUS_INTERNAL;

      $response = new Message\Response( null, $status );
    }

    //
    $extension->log->debug( "HTTP response for the ({id}) '{method} {url}' request is '{reason}' ({status})", [
      'method' => strtoupper( $request->getMethod() ),
      'url'    => (string) $request->getUri(),
      'id'     => $this->getId(),

      'status' => $response->getStatus(),
      'reason' => $response->getReason(),
      'header' => $response->getHeader()
    ] );

    return $response;
  }

  /**
   * Get default request
   *
   * Constructed from the PHP globals
   *
   * @return Message\Request
   */
  public static function getRequest() {
    $storage = new Storage( $_SERVER );

    // build the request uri
    $secure = $storage->getString( 'HTTPS', 'off' ) != 'off' || $storage->getString( 'HTTP_X_FORWARDED_PROTO' ) == Helper\UriInterface::SCHEME_HTTPS;
    $scheme = $storage->getString( 'REQUEST_SCHEME', $secure ? Helper\UriInterface::SCHEME_HTTPS : Helper\UriInterface::SCHEME_HTTP );

    $tmp = $storage->getString( 'HTTP_X_FORWARDED_HOST', $storage->getString( 'HTTP_HOST', $storage->getString( 'SERVER_NAME' ) ) );
    list( $host, $tmp ) = strpos( ':', $tmp ) === false ? [ $tmp, 0 ] : explode( ':', $tmp );
    if( !empty( $tmp ) ) $port = (int) $tmp;
    else if( isset( $storage[ 'HTTP_X_FORWARDED_HOST' ] ) ) $port = $storage->getString( 'HTTP_X_FORWARDED_PROTO' ) == Helper\UriInterface::SCHEME_HTTPS ? Helper\UriInterface::PORT_HTTP : Helper\UriInterface::PORT_HTTPS;
    else $port = $storage->getNumber( 'SERVER_PORT', $secure ? Helper\UriInterface::PORT_HTTP : Helper\UriInterface::PORT_HTTPS );

    // create the request
    $request = new Message\Request( "{$scheme}://{$host}:{$port}" . $storage->getString( 'REQUEST_URI' ) );

    // set the request body and method
    if( $storage->getNumber( 'CONTENT_LENGTH' ) > 0 ) $request->setBody( new Stream( 'php://input', Stream::MODE_READ ) );
    $request->setMethod( mb_strtolower( $storage->getString( 'REQUEST_METHOD', Message\Request::METHOD_GET ) ) );
    $request->setVersion( $storage->getString( 'SERVER_PROTOCOL', MessageInterface::VERSION_HTTP1_1 ) );

    // set headers
    $request->setHeader( $storage->getString( 'CONTENT_TYPE' ), 'content-type' );
    $request->setHeader( $storage->getNumber( 'CONTENT_LENGTH' ), 'content-length' );
    foreach( $_SERVER as $key => $value ) {
      if( strpos( $key, 'HTTP_' ) === 0 ) {
        $key = str_replace( '_', '-', mb_strtolower( substr( $key, strlen( 'HTTP_' ) ) ) );
        $request->setHeader( $value, $key, true );
      }
    }

    return $request;
  }
  /**
   * Get default base Uri
   *
   * Constructed from the request's uri and PHP globals
   *
   * @param Message\RequestInterface $request
   *
   * @return UriInterface
   */
  public static function getUri( Message\RequestInterface $request ) {

    // define the uri base from the request uri
    $uri = Uri::instance( $request->getUri()->getComponent( [
      Helper\UriInterface::COMPONENT_SCHEME,
      Helper\UriInterface::COMPONENT_USER,
      Helper\UriInterface::COMPONENT_PASSWORD,
      Helper\UriInterface::COMPONENT_HOST,
      Helper\UriInterface::COMPONENT_PORT
    ] ) );

    $storage = new Storage( $_SERVER );
    $uri->setPath( rtrim( dirname( $storage->getString( 'SCRIPT_NAME' ) ), '/' ) . '/' );

    return $uri;
  }
  /**
   * Extend input with PHP defaults
   *
   * Input::NAMESPACE_BODY will be extended with $_POST and normalized $_FILES
   *
   * @param Input $input
   */
  public static function getInput( Input $input ) {

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

        if( !is_array( $value ) ) $container[ $name ] = empty( $value[ 'error' ] ) ? new Stream( $value[ 'tmp_name' ], Stream::MODE_RW ) : null;
        else foreach( $value as $i => $v ) {

          if( !isset( $container[ $i ] ) ) $container[ $i ] = [];
          helper( $container[ $i ], $v, $name );
        }
      }

      // walk the files
      foreach( $_FILES as $index => $value ) {
        if( !is_array( $value[ 'name' ] ) ) $data[ $index ] = empty( $value[ 'error' ] ) ? new Stream( $value[ 'tmp_name' ], Stream::MODE_RW ) : null;
        else {

          if( !is_array( $data[ $index ] ) ) $data[ $index ] = [];
          foreach( $value as $property => $container ) {
            helper( $data[ $index ], $container, $property );
          }
        }
      }
    }

    // 
    if( !empty( $data ) ) {

      $instance[ Input::NAMESPACE_BODY . ':' ]    = Core\Helper\Collection::merge( $data, $input->getArray( Input::NAMESPACE_BODY . ':' ) );
      $instance[ Input::NAMESPACE_REQUEST . ':' ] = Core\Helper\Collection::merge(
        $input->getArray( Input::NAMESPACE_URI . ':' ),
        $input->getArray( Input::NAMESPACE_BODY . ':' )
      );
    }
  }

  /**
   * Send response for the PHP request
   *
   * This will setup headers and write the response body to the default PHP output
   *
   * @param Message\ResponseInterface $response
   * @param Message\RequestInterface  $request
   */
  public static function response( Message\ResponseInterface $response, Message\RequestInterface $request ) {

    // setup headers from the response
    if( headers_sent() ) Http\Extension::instance()->log->warning( 'HTTP headers already sent', [ 'response' => $response, 'request' => $request ] );
    else {

      // 
      $header = headers_list();
      foreach( $header as $value ) {
        list( $name, $value ) = explode( ':', $value, 2 );

        $tmp = $response->getHeader( $name );
        if( empty( $tmp ) ) {
          $response->setHeader( ltrim( $value ), $name );
        }
      }

      // reset headers and set the status line
      header_remove();
      header( $response->getVersion() . " {$response->getStatus()} {$response->getReason()}", true, $response->getStatus() );

      // 
      $header = $response->getHeader();
      foreach( $header as $name => $value ) {

        $value = is_array( $value ) ? $value : [ $value ];
        foreach( $value as $data ) {
          header( $name . ": {$data}" );
        }
      }
    }

    // send the response body to the output 
    if( $response->getBody() ) {
      $output = new Stream( 'php://output', Stream::MODE_WA );
      $output->write( $response->getBody() );
    }
  }
} 
