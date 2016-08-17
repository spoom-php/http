<?php namespace Http;

use Framework\Exception;
use Framework\Extension;
use Framework\Helper\Feasible;
use Framework\Helper\FeasibleInterface;
use Framework\Storage;
use Http\Helper\Stream;
use Http\Helper\Uri;
use Http\Message\Request;

/**
 * Class Listener
 * @package Page\Document
 */
class Listener implements FeasibleInterface {
  use Feasible {
    execute as executeFeasible;
  }

  const EXCEPTION_FAIL_START = 'http#0E';
  const EXCEPTION_FAIL_SEND  = 'http#0E';

  const EVENT_START = 'start';
  const EVENT_RUN   = 'run';
  const EVENT_STOP  = 'stop';

  /**
   * @var Extension
   */
  private $extension;
  /**
   * @var Storage
   */
  private $storage;
  /**
   * @var bool
   */
  private $enable;
  /**
   * The stored exception
   *
   * @var Exception
   */
  private $exception;

  /**
   * @inheritDoc
   */
  function __construct() {

    $this->extension = Extension::instance( 'http' );

    $this->storage = new Storage( $_SERVER );
    // TODO test this definition in other environments
    $this->enable = stripos( 'http', $this->storage->getString( 'SERVER_PROTOCOL' ) ) === 0 ||
      $this->storage->exist( 'REQUEST_METHOD' ) ||
      in_array( PHP_SAPI, $this->extension->option( 'default:sapi!array', [ ] ) );
  }

  /**
   * @inheritdoc. Executes only if the request is http
   *
   * @return mixed
   */
  public function execute( $name, $arguments = null ) {
    return $this->enable ? $this->executeFeasible( $name, $arguments ) : null;
  }

  /**
   * Init the HTTP request (if this is a http request)
   */
  protected function frameworkRequestStart() {

    try {

      /** @var Manager $manager */
      $manager = Manager::instance();

      if( !$manager->getRequest() ) {
        $request = new Message\Request();

        // set the request body and method
        $request->setBody( Stream::instance( fopen( 'php://input', 'r' ) ) );
        $request->setMethod( mb_strtolower( $this->storage->getString( 'REQUEST_METHOD', Message\Request::METHOD_GET ) ) );
        $request->setVersion( $this->storage->getString( 'SERVER_PROTOCOL', 'HTTP1/1' ) ); // FIXME extract to const and decide the 'HTTP' part's fate

        // set headers
        foreach( $_SERVER as $key => $value ) {
          if( strpos( $key, 'HTTP_' ) === 0 ) {
            $key = str_replace( '_', '-', mb_strtolower( substr( $key, strlen( 'HTTP_' ) ) ) );
            $request->setHeader( $value, $key, true );
          }
        }

        // set the request uri
        $secure = $this->storage->getString( 'HTTPS', 'off' ) != 'off' || $this->storage->getString( 'HTTP_X_FORWARDED_PROTO' ) == 'https';
        $scheme = $this->storage->getString( 'REQUEST_SCHEME', $secure ? Helper\UriInterface::SCHEME_HTTPS : Helper\UriInterface::SCHEME_HTTP );

        $tmp = $this->storage->getString( 'HTTP_X_FORWARDED_HOST', $this->storage->getString( 'HTTP_HOST', $this->storage->getString( 'SERVER_NAME' ) ) );
        list( $host, $tmp ) = explode( ':', $tmp );

        $port = !empty( $tmp ) ? (int) $tmp : $this->storage->getNumber( 'SERVER_PORT', $secure ? Helper\UriInterface::PORT_HTTP : Helper\UriInterface::PORT_HTTPS );
        $request->setUri( "{$scheme}://{$host}:{$port}" . $this->storage->getString( 'REQUEST_URI' ) );

        // define the uri base from the request uri
        $uri       = Uri::instance( $request->getUri()->getComponent( [
          Helper\UriInterface::COMPONENT_SCHEME,
          Helper\UriInterface::COMPONENT_HOST,
          Helper\UriInterface::COMPONENT_PORT
        ] ) );
        $uri->path = rtrim( dirname( $this->storage->getString( 'SCRIPT_NAME' ) ), '/' ) . '/';

        // set the default request object
        $manager->setRequest( $request, $uri );
      }

      // trigger the http start event
      $event = $this->extension->trigger( self::EVENT_START );
      if( $event->collector->contains() ) throw $event->collector->get();
      else if( $event->prevented ) throw new Exception\Strict( self::EXCEPTION_FAIL_START );
      else {

        // log: debug
        $this->extension->log->debug( 'HTTP request is started for ({id}) \'{method} {url}\'', [
          'method' => strtoupper( $manager->request->getMethod() ),
          'url'    => (string) $manager->request->getUri(),
          'id'     => $manager->id,
          'input'  => $manager->input
        ] );
      }

    } catch( \Exception $e ) {
      $this->exception = Exception\Helper::wrap( $e )->log( [ ], $this->extension->log );
    }
  }
  /**
   * Run the HTTP request handlers for a Response object
   */
  protected function frameworkRequestRun() {

    // trigger http run event if there was no exception on start
    if( !$this->exception ) try {

      $event = $this->extension->trigger( self::EVENT_RUN );
      if( $event->collector->contains() ) throw $event->collector->get();

    } catch( \Exception $e ) {
      $this->exception = Exception\Helper::wrap( $e )->log( [ ], $this->extension->log );
    }

    $manager = Manager::instance();
    if( !$manager->getResponse() ) try {

      // log: info
      $this->extension->log->info( 'HTTP response is not defined, blank response is used' );

      // setup the default response
      $response = new Message\Response();
      $response->setStatus( Message\ResponseInterface::STATUS_CONTENT_EMPTY );
      if( $this->exception ) {

        // set status message accordingly to the exception's type
        $exception = Exception\Helper::wrap( $this->exception );
        $response->setStatus( $exception instanceof Exception\Runtime ? Message\ResponseInterface::STATUS_BAD : Message\ResponseInterface::STATUS_INTERNAL );
      }

    } catch( \Exception $e ) {
      $this->exception = Exception\Helper::wrap( $e )->log( [ ], $this->extension->log );
    }
  }
  /**
   * Send the Response object data to complete the HTTP request
   *
   * @throws Exception\Strict
   */
  protected function frameworkRequestStop() {

    // trigger http stop
    $this->extension->trigger( self::EVENT_STOP );

    // send the manager's response to the "output"
    if( headers_sent() ) throw new Exception\Strict( self::EXCEPTION_FAIL_SEND );
    else {

      $manager  = Manager::instance();
      $response = $manager->getResponse();

      // 
      $header = headers_list();
      foreach( $header as $value ) {
        list( $name, $value ) = explode( ':', $value, 2 );

        if( !count( $response->getHeader( $name ) ) ) {
          $response->setHeader( $value, $name );
        }
      }

      // reset headers and set the status line
      header_remove();
      header( $response->getVersion() . " {$response->getStatus()} {$response->getReason()}", true, $response->getStatus() );

      // 
      $header = $response->getHeader();
      foreach( $header as $name => $value ) {
        header( ucwords( $name ) . ": {$value}" );
      }

      // send the response body to the output 
      if( $response->getBody() ) {
        $output = Stream::instance( fopen( 'php://output', 'w' ) );
        $output->write( $response->getBody() );
      }

      $request = $manager->getRequest();
      $this->extension->log->debug( "HTTP response for " . ( $request ? 'the ({id}) \'{method} {url}\'' : 'an unknown' ) . " request is '{reason}' ({status})", [
        'method' => $request ? strtoupper( $request->getMethod() ) : null,
        'url'    => $request ? (string) $request->getUri() : null,
        'id'     => $request ? $manager->getId() : null,

        'status' => $response->getStatus(),
        'reason' => $response->getReason(),
        'header' => $response->getHeader()
      ] );
    }
  }
}
