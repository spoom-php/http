<?php namespace Http\Message;

use Framework\Exception;
use Http\Helper\StreamInterface;
use Http\Message;
use Http\MessageInterface;

/**
 * Interface ResponseInterface
 * @package Http\Message
 */
interface ResponseInterface extends MessageInterface {

  /**
   * Standard response for successful HTTP requests. The actual response will depend on
   * the request method used. In a GET request, the response will contain an entity
   * corresponding to the requested resource. In a POST request the response will contain
   * an entity describing or containing the result of the action.
   */
  const STATUS_OK = 200;
  /**
   * The request has been fulfilled and resulted in a new resource being created.
   */
  const STATUS_CREATED = 201;
  /**
   * The request has been accepted for processing, but the processing has not been completed.
   * The request might or might not eventually be acted upon, as it might be disallowed when
   * processing actually takes place.
   */
  const STATUS_ACCEPTED = 202;
  /**
   * The server successfully processed the request, but is not returning any content.
   * Usually used as a response to a successful delete request.
   */
  const STATUS_CONTENT_EMPTY = 204;
  /**
   * The server has fulfilled the request and the user agent SHOULD reset the document view which caused the request to be sent
   */
  const STATUS_CONTENT_RESET = 205;
  /**
   * The server has fulfilled the partial GET request for the resource
   */
  const STATUS_CONTENT_PARTIAL = 206;

  /**
   * The requested resource has been assigned a new permanent URI and any future references to this resource SHOULD use one of the returned URIs
   */
  const STATUS_PERMANENTLY = 301;
  /**
   * The requested resource resides temporarily under a different URI
   */
  const STATUS_FOUND = 302;
  /**
   * The response to the request can be found under a different URI and SHOULD be retrieved using a GET method on that resource
   */
  const STATUS_OTHER = 303;
  /**
   * If the client has performed a conditional GET request and access is allowed, but the document has not been modified, the server SHOULD respond with this
   * status code
   */
  const STATUS_UNMODIFIED = 304;

  /**
   * The request cannot be fulfilled due to bad syntax.
   */
  const STATUS_BAD = 400;
  /**
   * Similar to 403 Forbidden, but specifically for use when authentication is required and
   * has failed or has not yet been provided. The response must include a WWW-Authenticate
   * header field containing a challenge applicable to the requested resource.
   */
  const STATUS_UNAUTHORIZED = 401;
  /**
   * The request was a valid request, but the server is refusing to respond to it. Unlike
   * a 401 Unauthorized response, authenticating will make no difference.
   */
  const STATUS_FORBIDDEN = 403;
  /**
   * The requested resource could not be found but may be available again in the future.
   * Subsequent requests by the client are permissible.
   */
  const STATUS_MISSING = 404;
  /**
   * A request was made of a resource using a request method not supported by that resource;
   * for example, using GET on a form which requires data to be presented via POST, or using
   * PUT on a read-only resource.
   */
  const STATUS_UNSUPPORTED = 405;
  /**
   * The resource identified by the request is only capable of generating response entities which have content characteristics not acceptable according to the
   * accept headers sent in the request
   */
  const STATUS_UNACCEPTABLE = 406;
  /**
   * The client did not produce a request within the time that the server was prepared to wait
   */
  const STATUS_TIMEOUT = 408;
  /**
   * The request could not be completed due to a conflict with the current state of the resource
   */
  const STATUS_CONFLICT = 409;
  /**
   * The requested resource is no longer available at the server and no forwarding address is known. This condition is expected to be considered permanent
   */
  const STATUS_GONE = 410;

  /**
   * A generic error message, given when an unexpected condition was encountered and no more
   * specific message is suitable.
   */
  const STATUS_INTERNAL = 500;
  /**
   * The server either does not recognize the request method, or it lacks the ability to
   * fulfil the request. Usually this implies future availability (e.g., a new feature of
   * a web-service API).
   */
  const STATUS_UNIMPLEMENTED = 501;
  /**
   * The server is currently unavailable (because it is overloaded or down for maintenance).
   * Generally, this is a temporary state.
   */
  const STATUS_UNAVAILABLE = 503;

  /**
   * Get the default reason message for the status code, or the stored one
   *
   * @return string
   */
  public function getReason();
  /**
   * Set the reason message
   *
   * @param string|null $value Direct message or null for reset to default
   *
   * @return static
   */
  public function setReason( $value );

  /**
   * Get the status code
   *
   * @return int
   */
  public function getStatus();
  /**
   * Set the status code
   *
   * @param int $value Zero means default
   *
   * @return static
   */
  public function setStatus( $value );

  /**
   * Set, remove (or edit) a cookie for the response
   *
   * @param string     $name
   * @param mixed|null $value  The (new) value, or null for "remove"
   * @param string|int $expire The (new) expire time in s or datetime string
   * @param array      $option Other options for the cookie (path, domain, ..)
   *
   * @return static
   */
  public function setCookie( $name, $value = null, $expire = 0, $option = [] );
}
/**
 * Class Response
 * @package Http\Message
 */
class Response extends Message implements ResponseInterface {

  /**
   * Invalid status code
   *
   * @param mixed $value The wrong input
   */
  const EXCEPTION_INVALID_STATUS = 'http#0E';

  /**
   * The status codes default reason phrases
   *
   * TODO change this to const after PHP7
   *
   * @var string[]
   */
  protected static $REASON = [
    self::STATUS_OK              => 'OK',
    self::STATUS_CREATED         => 'Created',
    self::STATUS_ACCEPTED        => 'Accepted',
    self::STATUS_CONTENT_EMPTY   => 'No Content',
    self::STATUS_CONTENT_RESET   => 'Reset Content',
    self::STATUS_CONTENT_PARTIAL => 'Partial Content',

    self::STATUS_PERMANENTLY => 'Moved Permanently',
    self::STATUS_FOUND       => 'Found',
    self::STATUS_OTHER       => 'See Other',
    self::STATUS_UNMODIFIED  => 'Not Modified',

    self::STATUS_BAD          => 'Bad Request',
    self::STATUS_UNAUTHORIZED => 'Unauthorized',
    self::STATUS_FORBIDDEN    => 'Forbidden',
    self::STATUS_MISSING      => 'Not Found',
    self::STATUS_UNSUPPORTED  => 'Method Not Allowed',
    self::STATUS_UNACCEPTABLE => 'Not Acceptable',
    self::STATUS_TIMEOUT      => 'Request Time-out',
    self::STATUS_CONFLICT     => 'Conflict',
    self::STATUS_GONE         => 'Gone',

    self::STATUS_INTERNAL      => 'Internal Server Error',
    self::STATUS_UNIMPLEMENTED => 'Not Implemented',
    self::STATUS_UNAVAILABLE   => 'Service Unavailable'
  ];

  /**
   * @var string|null
   */
  private $_reason = null;
  /**
   * @var int
   */
  private $_status = 0;

  /**
   * @param int                  $status
   * @param array                $header
   * @param StreamInterface|null $body
   */
  public function __construct( $status = self::STATUS_OK, array $header = [], $body = null ) {

    $this->setBody( $body );
    $this->setHeader( $header );

    $this->setStatus( $status );
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
   * Get the default reason message for the status code, or the stored one
   *
   * @return string
   */
  public function getReason() {
    return empty( $this->_reason ) && isset( static::$REASON[ $this->_status ] ) ? static::$REASON[ $this->_status ] : $this->_reason;
  }
  /**
   * Set the reason message
   *
   * @param string|null $value Direct message or null for reset to default
   *
   * @return static
   */
  public function setReason( $value ) {
    $this->_reason = !empty( $value ) ? (string) $value : null;
    return $this;
  }

  /**
   * Get the status code
   *
   * @return int
   */
  public function getStatus() {
    return $this->_status;
  }
  /**
   * Set the status code
   *
   * @param int $value Zero means default
   *
   * @return static
   * @throws Exception\Strict
   */
  public function setStatus( $value ) {

    if( $value < 0 ) throw new Exception\Strict( static::EXCEPTION_INVALID_STATUS, [ 'value' => $value ] );
    else {

      $this->_status = (int) $value;
      return $this;
    }
  }

  /**
   * Set, remove (or edit) a cookie(s) for the response
   *
   * note: To set a "remove" cookie use it like ->setCookie( '..', 1, 1 )
   *
   * @param string     $name
   * @param mixed|null $value  The (new) value, or null for remove
   * @param string|int $expire The (new) expire time in s or datetime string
   * @param array      $option Other options for the cookie (path, domain, ..)
   *
   * @return static
   */
  public function setCookie( $name, $value = null, $expire = 0, $option = [] ) {

    // search and remove the previous cookie
    $header_list = $this->getHeader( 'set-cookie' );
    $header_list = is_array( $header_list ) ? $header_list : ( empty( $header_list ) ? [] : [ $header_list ] );
    foreach( $header_list as $i => $header ) {

      list( $tmp ) = explode( '=', ltrim( explode( ';', trim( $header ), 2 )[ 0 ] ), 2 );
      if( $tmp == $name ) unset( $header_list[ $i ] );
    }

    $header_list = array_values( $header_list );
    if( $value !== null ) {
      // create the cookie's data than build it
      $cookie = [ $name => $value === null ? '' : $value ] + $option;
      if( !empty( $expire ) ) $cookie[ 'expires' ] = gmdate( MessageInterface::DATE_FORMAT, is_numeric( $expire ) ? $expire : strtotime( $expire ) );

      $string = '';
      foreach( $cookie as $key => $data ) {

        $string .= ( empty( $string ) ? '' : '; ' );
        $string .= $key . ( $data === true ? '' : ( '=' . $data ) );
      }

      $header_list[] = $string;
    }

    // change the header field
    $this->setHeader( count( $header_list ) > 1 ? $header_list : ( !empty( $header_list ) ? $header_list[ 0 ] : null ), 'set-cookie' );
    return $this;
  }
}
