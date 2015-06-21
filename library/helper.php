<?php namespace Http;

use Framework\Exception;
use Framework\Extension;
use Framework\Page;

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
   *  - request [Request]: The request object
   */
  const EVENT_START = 'start';
  /**
   * Triggers when the HTTP request run. The prevention or exception throw will cancel the request with an error. The handlers MAY return a Response object
   * that will be used to complete the request. Only the first Response object will be used. Arguments:
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
   * @param Request $request The HTTP request object
   *
   * @throws Exception\Strict
   * @throws \Exception
   */
  public static function start( Request $request ) {

    // prevent multiply runs
    if( self::$state != self::STATE_START ) throw new Exception\Strict( self::EXCEPTION_INVALID_STATE, [ 'state' => self::$state ] );
    else {

      // log: debug
      Page::getLog()->debug( 'Start a new request with {method} {url} URL', [
        'url'    => (string) $request->url,
        'method' => strtoupper( $request->method ),
        'data'   => $request->input->convert()
      ] );

      // set the new state value
      self::$state = self::STATE_RUN;

      // trigger the start event
      $extension = Extension::instance( 'http' );
      $event     = $extension->trigger( self::EVENT_START, [ 'request' => $request ] );
      if( $event->prevented || $event->collector->contains() ) {

        throw ( $event->collector->contains() ? $event->collector->get() : new Exception\Strict( self::EXCEPTION_FAIL_START ) );
      }
    }
  }
  /**
   * Handle the HTTP request run by triggers the event and handle the result
   *
   * @param Request $request The HTTP request object
   *
   * @return Response The response for the HTTP request
   * @throws Exception\Strict
   * @throws \Exception
   */
  public static function run( Request $request ) {

    // prevent multiply runs
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
          if( $response instanceof Response ) return $response;
        }

        // return a default response
        $response         = new Response\Buffer( $request );
        $response->status = Response::STATUS_CONTENT_NO;

        return $response;
      }
    }
  }
  /**
   * Send the the HTTP request response
   *
   * @param Response $response The response object for the request
   *
   * @throws Exception\Strict
   */
  public static function stop( Request $request, Response $response ) {

    // prevent multiply runs
    if( self::$state != self::STATE_STOP ) throw new Exception\Strict( self::EXCEPTION_INVALID_STATE, [ 'state' => self::$state ] );
    else {

      // set the new state value
      self::$state = null;

      // trigger the stop event
      $extension = Extension::instance( 'http' );
      $extension->trigger( self::EVENT_STOP, [ 'response' => &$response ] );

      // log: debug
      Page::getLog()->debug( 'Send response for the {method} {url} request', [
        'method'  => strtoupper( $request->method ),
        'url'     => (string) $request->url,

        'status'  => $response->status,
        'message' => $response->message,
        'header'  => $response->header
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
} 
