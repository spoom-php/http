<?php namespace Http\Message;

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
   * @return string
   */
  public function getMethod();
  /**
   * @param string $value
   *
   * @return static
   */
  public function setMethod( $value );

  /**
   * @return UriInterface|null
   */
  public function getUri();
  /**
   * @param UriInterface $value
   *
   * @return static
   */
  public function setUri( $value );

  /**
   * @param string|null $name Name or null for "every" cookie
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
   * @var string
   */
  private $_method = self::METHOD_GET;
  /**
   * @var UriInterface|null
   */
  private $_uri;

  /**
   * Write the message into the input stream
   *
   * @param StreamInterface $stream
   */
  public function write( $stream ) {
    // TODO: Implement write() method.
  }

  /**
   * @return string
   */
  public function getMethod() {
    return $this->_method;
  }
  /**
   * @param string $value
   *
   * @return static
   */
  public function setMethod( $value ) {
    $this->_method = $value;

    return $this;
  }

  /**
   * @return UriInterface|null
   */
  public function getUri() {
    return $this->_uri;
  }
  /**
   * @param UriInterface|string|null $value
   *
   * @return static
   */
  public function setUri( $value ) {
    $this->_uri = $value ? Uri::instance( $value ) : null;

    return $this;
  }

  /**
   * @param string|null $name Name or null for "every" cookie
   *
   * @return mixed|array
   */
  public function getCookie( $name = null ) {

    $cookie = [ ];
    $header = explode( ';', implode( ';', $this->getHeader( 'cookie' ) ) );
    foreach( $header as $h ) {

      list( $tmp, $value ) = explode( '=', ltrim( $h ), 2 );
      $cookie[ $tmp ] = $value;

      if( $tmp == $name ) return $tmp;
    }

    return $name === null ? $cookie : null;
  }
}
