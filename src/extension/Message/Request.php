<?php namespace Spoom\Http\Message;

use Spoom\Framework\Helper\StreamInterface;
use Spoom\Http\Helper\Uri;
use Spoom\Http\Message;
use Spoom\Http\MessageInterface;
use Spoom\Http\Helper\UriInterface;

/**
 * Interface RequestInterface
 * @package Spoom\Http\Message
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
  public function getMethod(): string;
  /**
   * Set the request method, cannot be empty
   *
   * @param string $value
   *
   * @return static
   * @throws \InvalidArgumentException
   */
  public function setMethod( string $value );

  /**
   * Get the request Uri
   *
   * @return UriInterface
   */
  public function getUri(): UriInterface;
  /**
   * Set the request Uri, cannot be empty
   *
   * @param UriInterface|string $value
   *
   * @return static
   * @throws \InvalidArgumentException
   */
  public function setUri( $value );

  /**
   * Get a (or all) cookie(s) in the request
   *
   * @param string|null $name Name or null for all cookie
   *
   * @return mixed|array
   */
  public function getCookie( ?string $name = null );
}
/**
 * Class Request
 * @package Spoom\Http\Message
 */
class Request extends Message implements RequestInterface {

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
  public function __construct( $uri, string $method = self::METHOD_GET, ?StreamInterface $body = null, array $header = [] ) {

    $this->setBody( $body );
    $this->setHeader( $header );

    $this->setUri( $uri );
    $this->setMethod( $method );
  }

  //
  public function write( StreamInterface $stream ) {
    // TODO: Implement write() method.
  }

  //
  public function getMethod(): string {
    return $this->_method;
  }
  //
  public function setMethod( string $value ) {

    if( empty( $value ) ) throw new \InvalidArgumentException( "Method can't be empty" );
    else {

      $this->_method = $value;
      return $this;
    }
  }

  //
  public function getUri(): UriInterface {
    return $this->_uri;
  }
  //
  public function setUri( $value ) {

    if( empty( $value ) ) throw new \InvalidArgumentException( "URI can't be empty" );
    else {

      $this->_uri = Uri::instance( $value );
      return $this;
    }
  }

  //
  public function getCookie( ?string $name = null ) {

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
