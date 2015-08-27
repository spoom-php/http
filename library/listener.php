<?php namespace Http;

use Framework\Exception;
use Framework\Extension;
use Framework\Helper\Feasible;
use Framework\Helper\FeasibleInterface;

/**
 * Class Listener
 * @package Page\Document
 */
class Listener implements FeasibleInterface {
  use Feasible {
    execute as executeFeasible;
  }

  private $extension;
  /**
   * The request data object for the HTTP
   *
   * @var Request
   */
  private $request;
  /**
   * The response objet for the HTTP request
   *
   * @var ResponseInterface
   */
  private $response;
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
  }

  /**
   * @inheritdoc
   *
   * @return mixed
   */
  public function execute( $name, $arguments = null ) {

    // execute only if the request is http
    return Helper::isHttp() ? $this->executeFeasible( $name, $arguments ) : null;
  }

  /**
   * Init the HTTP request (if this is a http request)
   */
  protected function frameworkRequestStart() {

    try {

      $this->request = Helper::start();

      // log: debug
      $this->extension->log->debug( 'HTTP request is started for \'{method} {url}\' ({id})', [
        'method' => strtoupper( $this->request->getMethod() ),
        'url'    => (string) $this->request->url,
        'id'     => $this->request->id,

        'input'  => $this->request->input->getSource()
      ] );

    } catch( \Exception $e ) {
      $this->exception = Exception\Helper::wrap( $e )->log( [ ], $this->extension->log );
    }
  }
  /**
   * Run the HTTP request handlers for a Response object
   */
  protected function frameworkRequestRun() {

    if( !$this->exception ) try {

      $this->response = Helper::run( $this->request );

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

    // setup default response if needed
    if( !$this->response ) {

      // log: info
      $this->extension->log->info( 'HTTP response is not defined, blank response is used' );

      $this->response = new Response\Blank( $this->request );
    }

    // setup default http status if needed
    if( $this->exception && !$this->response->getStatus() ) {
      $this->response->setStatus( $this->exception instanceof Exception\Runtime ? Response::STATUS_BAD : Response::STATUS_INTERNAL );
    }

    Helper::stop( $this->response, $this->request );
  }
}
