<?php namespace Http;

use Framework\Helper\Library;
use Framework\Helper\String;
use Http\Helper\UriInterface;
use Http\Message;

/**
 * Class Manager
 * @package Http
 *
 * @property-read string                         $id
 * @property-read      Input|null                $input
 * @property-read      UriInterface|null         $uri
 * @property      Message\RequestInterface|null  $request
 * @property      Message\ResponseInterface|null $response
 */
class Manager extends Library {

  /**
   * @var static
   */
  protected static $instance;

  /**
   * Unique identifier of the request
   *
   * @var string
   */
  private $_id;

  /**
   * Base url for the request. This should be an absolute uri for the \Framework::PATH_BASE
   *
   * @var UriInterface
   */
  private $_uri;

  /**
   * @var Input
   */
  private $_input;
  /**
   * @var Message\RequestInterface
   */
  private $_request;
  /**
   * @var Message\ResponseInterface
   */
  private $_response;

  /**
   * @param string|null $id
   */
  public function __construct( $id = null ) {
    $this->_id = String::unique( 8, '', false );
  }

  /**
   * @return string
   */
  public function getId() {
    return $this->_id;
  }

  /**
   * @return UriInterface
   */
  public function getUri() {
    return $this->_uri;
  }
  /**
   * @return Input
   */
  public function getInput() {
    return $this->_input;
  }

  /**
   * @return Message\RequestInterface
   */
  public function getRequest() {
    return $this->_request;
  }
  /**
   * @param Message\RequestInterface $value
   * @param Helper\UriInterface|null $uri The request's base uri
   *
   * @return $this
   */
  public function setRequest( $value, $uri = null ) {

    $this->_request = $value;
    $this->_uri     = $uri ?: $this->_request->getUri();
    $this->_input   = Input::instance( $this->_request );

    return $this;
  }
  /**
   * @return Message\ResponseInterface
   */
  public function getResponse() {
    return $this->_response;
  }
  /**
   * @param Message\ResponseInterface $value
   *
   * @return $this
   */
  public function setResponse( $value ) {
    $this->_response = $value;
    return $this;
  }

  /**
   * @return static
   */
  public static function instance() {
    return static::$instance ?: ( static::$instance = new static() );
  }
} 
