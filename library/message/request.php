<?php namespace Http\Message;

use Framework\Exception;
use Http\Helper\StreamInterface;
use Http\Helper\Uri;
use Http\Message;
use Http\MessageInterface;
use Http\Helper\UriInterface;

/**
 * Interface RequestInterface
 * @package Http\Message
 */
interface RequestInterface extends MessageInterface {

  /**
   * Requests a representation of the specified resource. Requests using GET should only retrieve data and should have no other effect
   */
  const METHOD_GET = 'get';
  /**
   * This method can be used for obtaining metainformation about the entity implied by the request without transferring the entity-body itself
   */
  const METHOD_HEAD = 'head';
  /**
   * Allows the client to see what is being received at the other end of the request chain and use that data for testing or diagnostic information
   */
  const METHOD_TRACE = 'trace';
  /**
   * This method allows the client to determine the options and/or requirements associated with a resource, or the capabilities of a server, without implying a
   * resource action or initiating a resource retrieval
   */
  const METHOD_OPTIONS = 'options';
  /**
   * The method is used to request that the origin server accept the entity enclosed in the request as a new subordinate of the resource identified by the
   * Request-URI in the Request-Line
   */
  const METHOD_POST = 'post';
  /**
   * The method requests that the enclosed entity be stored under the supplied Request-URI. If the Request-URI refers to an already existing resource, the
   * enclosed entity SHOULD be considered as a modified version of the one residing on the origin server. If the Request-URI does not point to an existing
   * resource, and that URI is capable of being defined as a new resource by the requesting user agent, the origin server can create the resource with that URI
   */
  const METHOD_PUT = 'put';
  /**
   * Applies partial modifications to a resource
   */
  const METHOD_PATCH = 'patch';
  /**
   * Deletes the specified resource
   */
  const METHOD_DELETE = 'delete';

  /**
   * Get the request method
   *
   * @return string
   */
  public function getMethod();
  /**
   * Set the request method, cannot be empty
   *
   * @param string $value
   *
   * @return static
   * @throws Exception\Strict
   */
  public function setMethod( $value );

  /**
   * Get the request Uri
   *
   * @return UriInterface
   */
  public function getUri();
  /**
   * Set the request Uri, cannot be empty
   *
   * @param UriInterface|string $value
   *
   * @return static
   * @throws Exception\Strict
   */
  public function setUri( $value );

  /**
   * Get a (or all) cookie(s) in the request
   *
   * @param string|null $name Name or null for all cookie
   *
   * @return mixed|array
   */
  public function getCookie( $name = null );
}
/**
 * Class Request
 * @package Http\Message
 */
class Request extends Message implements RequestInterface {

  /**
   * Try to set an invalid (empty) method
   */
  const EXCEPTION_INVALID_METHOD = 'http#0E';
  /**
   * Try to set an invalid (empty) uri
   */
  const EXCEPTION_INVALID_URI = 'http#0E';

  /**
   * @var string
   */
  private $_method = self::METHOD_GET;
  /**
   * @var UriInterface
   */
  private $_uri;

  /**
   * @param UriInterface|string  $uri
   * @param string               $method
   * @param StreamInterface|null $body
   * @param array                $header
   */
  public function __construct( $uri, $method = self::METHOD_GET, $body = null, array $header = [] ) {

    $this->setBody( $body );
    $this->setHeader( $header );

    $this->setUri( $uri );
    $this->setMethod( $method );
  }

  /**
   * Write the message into the input stream
   *
   * @param StreamInterface $stream
   */
  public function write( $stream ) {
    // TODO: Implement write() method.
  }

  /**
   * Get the request method
   *
   * @return string
   */
  public function getMethod() {
    return $this->_method;
  }
  /**
   * Set the request method, cannot be empty
   *
   * @param string $value
   *
   * @return static
   * @throws Exception\Strict
   */
  public function setMethod( $value ) {

    if( empty( $value ) ) throw new Exception\Strict( static::EXCEPTION_INVALID_METHOD );
    else {

      $this->_method = $value;
      return $this;
    }
  }

  /**
   * Get the request Uri
   *
   * @return UriInterface
   */
  public function getUri() {
    return $this->_uri;
  }
  /**
   * Set the request Uri, cannot be empty
   *
   * @param UriInterface|string $value
   *
   * @return static
   * @throws Exception\Strict
   */
  public function setUri( $value ) {

    if( empty( $value ) ) throw new Exception\Strict( static::EXCEPTION_INVALID_URI );
    else {

      $this->_uri = Uri::instance( $value );
      return $this;
    }
  }

  /**
   * Get a (or all) cookie(s) in the request
   *
   * @param string|null $name Name or null for all cookie
   *
   * @return mixed|array
   */
  public function getCookie( $name = null ) {

    $cookie = [];
    $tmp    = $this->getHeader( 'cookie' );
    $header = explode( ';', implode( ';', is_array( $tmp ) ? $tmp : [ $tmp ] ) );
    foreach( $header as $h ) {

      list( $tmp, $value ) = explode( '=', ltrim( $h ), 2 );

      if( empty( $name ) ) $cookie[ $tmp ] = $value;
      else if( $name == $tmp ) return $value;
    }

    return $name === null ? $cookie : null;
  }
}
