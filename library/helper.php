<?php namespace Http;

use Framework;
use Framework\Exception;
use Framework\Extension;

/**
 * Class Helper
 * @package Http
 */
abstract class Helper {

  /**
   * Failed to start the request due to an exception or event prevention
   */
  const EXCEPTION_FAIL_START = 'http#8C';
  /**
   * Failed to run the request (collect a response)
   */
  const EXCEPTION_FAIL_RUN = 'http#9C';
  /**
   * Can't execute the state
   */
  const EXCEPTION_INVALID_STATE = 'http#10W';

  /**
   * Triggers after the HTTP request starts. The prevention or exception throw will cancel the request with an error. Arguments:
   *  - &data [array]: The request data object
   *  - &meta [array]: The request metadata object
   *  - &body [resource]: The request body stream
   */
  const EVENT_START = 'start';
  /**
   * Triggers when the HTTP request run. The prevention or exception throw will cancel the request with an error. The handlers MAY return a ResponseInterface
   * object that will be used to complete the request. Only the first ResponseInteface object will be used. Arguments:
   *  - request [Request]: The request object
   */
  const EVENT_RUN = 'run';
  /**
   * Triggers before the HTTP request stop and the response send. Arguments:
   *  - &response [Response]: The response object to send
   */
  const EVENT_STOP = 'stop';

  /**
   * Request is ready to start
   */
  const STATE_START = 'start';
  /**
   * Request is ready to run
   */
  const STATE_RUN = 'run';
  /**
   * Request is ready to stop
   */
  const STATE_STOP = 'stop';

  /**
   * Holds the request state
   *
   * @var string
   */
  private static $state = self::STATE_START;

  /**
   * Handle the HTTP request start. It can be called only once
   *
   * @return Request
   * @throws Exception
   */
  public static function start() {

    // prevent multiple runs
    if( self::$state != self::STATE_START ) throw new Exception\Strict( self::EXCEPTION_INVALID_STATE, [ 'state' => self::$state ] );
    else {

      // set the new state value
      self::$state = self::STATE_RUN;

      // collect request variables
      $meta = self::getMeta();
      $data = self::getData();
      $body = fopen( 'php://input', 'r' );

      // log: debug
      Framework\Request::getLog()->debug( 'Start a new request', [
        'data' => $data,
        'meta' => $meta
      ] );

      // trigger the start event
      $extension = Extension::instance( 'http' );
      $event     = $extension->trigger( self::EVENT_START, [ 'data' => &$data, 'meta' => &$meta, 'body' => &$body ] );
      if( $event->prevented || $event->collector->contains() ) {

        throw ( $event->collector->contains() ? $event->collector->get() : new Exception\Strict( self::EXCEPTION_FAIL_START ) );
      } else {

        // search for the request object in the results
        foreach( $event->result as $request ) {
          if( $request instanceof Request ) return $request;
        }

        // return a default request
        return new Request( $data, $body, $meta );
      }
    }
  }
  /**
   * Handle the HTTP request run by triggers the event and handle the result
   *
   * @param Request $request The HTTP request object
   *
   * @return ResponseInterface The response for the HTTP request
   * @throws Exception\Strict
   * @throws \Exception
   */
  public static function run( Request $request ) {

    // prevent multiple runs
    if( self::$state != self::STATE_RUN ) throw new Exception\Strict( self::EXCEPTION_INVALID_STATE, [ 'state' => self::$state ] );
    else {

      // set the new state value
      self::$state = self::STATE_STOP;

      // trigger the run event
      $extension = Extension::instance( 'http' );
      $event     = $extension->trigger( self::EVENT_RUN, [ 'request' => $request ] );
      if( $event->prevented || $event->collector->contains() ) {

        throw ( $event->collector->contains() ? $event->collector->get() : new Exception\Strict( self::EXCEPTION_FAIL_RUN ) );

      } else {

        // search for the response object in the results
        foreach( $event->result as $response ) {
          if( $response instanceof ResponseInterface ) return $response;
        }

        // return a default response
        return new Response\Blank( $request );
      }
    }
  }
  /**
   * Send the the HTTP request response
   *
   * @param ResponseInterface $response The response object for the request
   * @param Request           $request
   *
   * @throws Exception\Strict
   */
  public static function stop( ResponseInterface $response, Request $request = null ) {

    // prevent multiple runs
    if( self::$state != self::STATE_STOP ) throw new Exception\Strict( self::EXCEPTION_INVALID_STATE, [ 'state' => self::$state ] );
    else {

      // set the new state value
      self::$state = null;

      // trigger the stop event
      $extension = Extension::instance( 'http' );
      $extension->trigger( self::EVENT_STOP, [ 'response' => &$response ] );

      // log: debug
      Framework\Request::getLog()->debug( 'Send response for the {method} {url} request', [
        'method'  => $request ? strtoupper( $request->method ) : null,
        'url'     => $request ? (string) $request->url : null,

        'status'  => $response->getStatus(),
        'message' => $response->getMessage(),
        'header'  => $response->getHeader()
      ] );

      // send the response
      $response->send();
    }
  }

  /**
   * Detect if the request made with HTTP protocol
   *
   * @return bool
   */
  public static function isHttp() {

    // TODO test this definition in other environments

    $extension = Extension::instance( 'http' );
    return in_array( PHP_SAPI, $extension->option( 'default:sapi!array', [ ] ) ) ||
           ( isset( $_SERVER[ 'SERVER_PROTOCOL' ] ) && strpos( 'http', strtolower( $_SERVER[ 'SERVER_PROTOCOL' ] ) ) === 0 ) ||
           isset( $_SERVER[ 'REQUEST_METHOD' ] );
  }

  /**
   * Get the request' parsed metadata in a storage
   *
   * @return Framework\Storage
   */
  public static function getMeta() {

    // process request' metadata 
    $meta = [ ];
    foreach( $_SERVER as $name => $value ) {

      $tmp = explode( '_', mb_strtolower( $name ), 2 );
      if( count( $tmp ) == 1 ) $meta[ $tmp[ 0 ] ] = $value;
      else {

        $tmp[ 1 ] = str_replace( '_', '-', $tmp[ 1 ] );
        if( !isset( $meta[ $tmp[ 0 ] ] ) ) {
          $meta[ $tmp[ 0 ] ] = [ ];
        }
        $meta[ $tmp[ 0 ] ][ $tmp[ 1 ] ] = $value;

      }
    }

    // normalize the meta input
    $meta = new Framework\Storage( [ 'raw' => $meta ] );
    $meta->set( 'url.scheme', $meta->getString( 'raw.request.scheme', $meta->getString( 'https', 'off' ) != 'off' ? 'https' : 'http' ) )
         ->set( 'url.host', $meta->getString( 'raw.server.name', $meta->getString( 'raw.http.host', null ) ) )
         ->set( 'url.port', $meta->getNumber( 'raw.server.port', $meta->getString( 'url.scheme' ) == 'http' ? Url::PORT_HTTP : Url::PORT_HTTPS ) )
         ->set( 'url.route', $meta->getString( 'raw.request.uri' ) )
         ->set( 'body.format', explode( ';', $meta->getString( 'raw.content.type' ) )[ 0 ] )
         ->set( 'body.length', $meta->getNumber( 'raw.content.length' ) )
         ->set( 'request.path', rtrim( dirname( $meta->getString( 'raw.script.name' ) ), '/' ) . '/' )
         ->set( 'request.method', mb_strtolower( $meta->getString( 'raw.request.method', 'get' ) ) );

    return $meta;
  }
  /**
   * Get the request parsed data
   *
   * @return array
   */
  public static function getData() {

    // set basic data
    $data                                   = [ ];
    $data[ RequestInput::NAMESPACE_COOKIE ] = empty( $_COOKIE ) ? [ ] : $_COOKIE;
    $data[ RequestInput::NAMESPACE_GET ]    = empty( $_GET ) ? [ ] : $_GET;
    $data[ RequestInput::NAMESPACE_POST ]   = empty( $_POST ) ? [ ] : $_POST;

    // FIXME  process file data
    $data[ RequestInput::NAMESPACE_FILE ] = [ ];
    if( !empty( $_FILES ) ) {

      function fileProcessor( &$container, &$value, $name ) {

        if( !is_array( $value ) ) $container[ $name ] = $value;
        else foreach( $value as $i => $v ) {

          if( !isset( $container[ $i ] ) ) $container[ $i ] = [ ];
          fileProcessor( $container[ $i ], $v, $name );
        }
      }

      foreach( $_FILES as $index => $value ) {

        if( !is_array( $value[ 'name' ] ) ) $data[ RequestInput::NAMESPACE_FILE ][ $index ] = $value;
        else {

          $data[ RequestInput::NAMESPACE_FILE ][ $index ] = [ ];
          foreach( $value as $property => $container ) {
            fileProcessor( $data[ RequestInput::NAMESPACE_FILE ][ $index ], $container, $property );
          }
        }
      }
    } 

    return $data;
  }

  /**
   * @param $input
   *
   * @return array
   */
  public static function fromMultipart( $input ) {

    /**
     * Read a stream into a stream or memory until a string
     *
     * @param resource      $stream  A stream to read from
     * @param string        $stop    The string that will stop the reading
     * @param string        $buffer  The remain content from the stream after the $stop (readed from the stream and not used)
     * @param resource|null $content A stream to write the content. If null the result will be a string
     *
     * @return resource|string The $content or a string
     */
    function read( $stream, $stop, &$buffer, $content = null ) {

      $stop_size = strlen( $stop );
      $string    = false;
      if( !$content ) {
        $string  = true;
        $content = fopen( 'php://memory', 'w+' );
      }

      // read until the stop string
      while( ( $position = strpos( $buffer, $stop ) ) === false ) {

        $tmp = fread( $stream, $stop_size > 4096 ? $stop_size : 4096 );
        if( !$tmp ) break;
        else {

          // remove the "safe" (doesn't include the stop string) string from the buffer into the content for optimalisation
          $safe = substr( $buffer, 0, -$stop_size );
          fwrite( $content, $safe );
          $buffer = substr( $buffer, -$stop_size ) . $tmp;
        }
      }

      // remove the final "safe" (doesn't include the stop string) string from the buffer into the content
      fwrite( $content, substr( $buffer, 0, $position ) );
      $buffer = substr( $buffer, $position + $stop_size );

      rewind( $content );
      return $string ? stream_get_contents( $content ) : $content;
    }

    $multipart = [ ];
    $buffer    = '';

    // first find the separator string (boundary)
    $boundary = rtrim( read( $input, "\n", $buffer ), "\r" );
    if( !empty( $boundary ) ) while( true ) {

      // the first lines (min 1) is the headers of the value
      $headers = [ ];
      while( true ) {

        // read until we find a line that is not header (empty line)
        $tmp = rtrim( read( $input, "\n", $buffer ), "\r" );
        if( trim( $tmp ) == '' ) break;

        // process the header into name and content
        list( $name, $content ) = explode( ':', $tmp );

        // process the content' into options
        $options = [ ];
        $content = explode( ';', $content );
        foreach( $content as &$part ) {

          // the first part (that has no '=' sign) will be the header' value
          $part = explode( '=', trim( $part ) );
          if( count( $part ) < 2 ) $options[ 'value' ] = $part[ 0 ];
          else $options[ $part[ 0 ] ] = trim( $part[ 1 ], '"' );
        }

        $headers[ mb_strtolower( $name ) ] = $options;
      }

      // read the last "line" which will be the content. The files is readed to a temp file instead of the memory
      if( isset( $headers[ 'content-disposition' ][ 'filename' ] ) ) $value = read( $input, $boundary, $buffer, tmpfile() );
      else $value = rtrim( read( $input, $boundary, $buffer ), "\r" );

      // save the multipart data
      $multipart[] = (object) [
        'meta'    => $headers,
        'content' => $value
      ];

      // check the multipart data' end
      $last = rtrim( read( $input, "\n", $buffer ), "\r" );
      if( $last == '--' ) break;
    }

    return $multipart;
  }
} 
