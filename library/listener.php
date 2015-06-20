<?php namespace Http;

use Framework\Exception;
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

  /**
   * The request data object for the HTTP
   *
   * @var Request
   */
  private $request;
  /**
   * The response objet for the HTTP request
   *
   * @var Response
   */
  private $response;
  /**
   * The stored exception
   *
   * @var Exception
   */
  private $exception;

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
  protected function frameworkPageStart() {

    $this->request = new Request();
    try {

      Helper::start( $this->request );

    } catch( \Exception $e ) {
      $this->exception = $e;
    }
  }
  /**
   * Run the HTTP request handlers for a Response object
   */
  protected function frameworkPageRun() {

    if( !$this->exception ) try {

      $this->response = Helper::run( $this->request );

    } catch( \Exception $e ) {
      $this->exception = $e;
    }
  }
  /**
   * Send the Response object data to complete the HTTP request
   *
   * @throws Exception\Strict
   */
  protected function frameworkPageStop() {

    // setup default response if needed
    if( !$this->response ) {
      $this->response = new Response\Buffer( $this->request );
    }

    // setup default http status if needed
    if( $this->exception && empty( $this->response->status ) ) {
      $this->response->status = $this->exception instanceof Exception\Runtime ? Response::STATUS_BAD : Response::STATUS_INTERNAL;
    }

    Helper::stop( $this->response );
  }
}
