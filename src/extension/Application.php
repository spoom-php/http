<?php namespace Spoom\Http;

use Spoom\Framework;
use Spoom\Http;
use Spoom\Framework\Storage;
use Spoom\Http\Helper\Uri;
use Spoom\Http\Helper\UriInterface;
use Spoom\Framework\Helper\Stream;

/**
 * Class Manager
 * @package Spoom\Http
 *
 * @property-read string                         $id
 * @property-read Input|null                     $input
 * @property-read UriInterface|null              $uri
 * @property      Message\RequestInterface|null  $request
 * @property      Message\ResponseInterface|null $response
 */
class Application extends Framework\Application {

  public function __invoke( ?callable $callback = null, ?Message\RequestInterface $request = null, ?UriInterface $base = null, ?Input $input = null ) {

    $extension = Http\Extension::instance();
    try {

      //
      $request = $request ?? static::request();
      $base    = $base ?? static::base( $request );
      $input   = $input ?? Input::instance( $request, true );

      // log: debug
      $extension->log->debug( 'HTTP request is started for ({id}) \'{method} {url}\'', [
        'method' => strtoupper( $request->getMethod() ),
        'url'    => (string) $request->getUri(),
        'id'     => $this->id,
        'input'  => $input
      ] );

      // run callback script that handle the main logic and provides the response
      $response = $callback ? $callback( $input, $request, $base ) : new Message\Response( Message\ResponseInterface::STATUS_CONTENT_EMPTY );
      if( !( $response instanceof Message\ResponseInterface ) ) {
        throw new \TypeError( 'HTTP result must be an instance of ' . Message\ResponseInterface::class );
      }

    } catch( \Throwable $e ) {

      // TODO log the exception

      $response = new Message\Response();

      // TODO set status message accordingly to the exception's type
    }

    $extension->log->debug( 'HTTP response for the ({id}) \'{method} {url}\' request is \'{reason}\' ({status})', [
      'method' => $request ? strtoupper( $request->getMethod() ) : null,
      'url'    => $request ? (string) $request->getUri() : null,
      'id'     => $request ? $this->getId() : null,

      'status' => $response->getStatus(),
      'reason' => $response->getReason(),
      'header' => $response->getHeader()
    ] );

    return $response;
  }

  public static function request() {
    $storage = new Storage( $_SERVER );

    // build the request uri
    $secure = $storage->getString( 'HTTPS', 'off' ) != 'off' || $storage->getString( 'HTTP_X_FORWARDED_PROTO' ) == 'https';
    $scheme = $storage->getString( 'REQUEST_SCHEME', $secure ? Helper\UriInterface::SCHEME_HTTPS : Helper\UriInterface::SCHEME_HTTP );

    $tmp = $storage->getString( 'HTTP_X_FORWARDED_HOST', $storage->getString( 'HTTP_HOST', $storage->getString( 'SERVER_NAME' ) ) );
    list( $host, $tmp ) = strpos( ':', $tmp ) === false ? [ $tmp, 0 ] : explode( ':', $tmp );
    $port = !empty( $tmp ) ? (int) $tmp : $storage->getNumber( 'SERVER_PORT', $secure ? Helper\UriInterface::PORT_HTTP : Helper\UriInterface::PORT_HTTPS );

    // create the request
    $request = new Message\Request( "{$scheme}://{$host}:{$port}" . $storage->getString( 'REQUEST_URI' ) );

    // set the request body and method
    $request->setBody( new Stream( 'php://input', Stream::MODE_READ ) );
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
  public static function base( Message\RequestInterface $request ) {

    // define the uri base from the request uri
    $uri = Uri::instance( $request->getUri()->getComponent( [
      Helper\UriInterface::COMPONENT_SCHEME,
      Helper\UriInterface::COMPONENT_HOST,
      Helper\UriInterface::COMPONENT_PORT
    ] ) );

    $storage = new Storage( $_SERVER );
    $uri->setPath( rtrim( dirname( $storage->getString( 'SCRIPT_NAME' ) ), '/' ) . '/' );

    return $uri;
  }
  public static function response( Message\ResponseInterface $response ) {

    // send the manager's response to the "output"
    if( headers_sent() ) ; // TODO log this as a warning
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
