<?php namespace Http;

use Framework\Exception;
use Framework\Helper\Library;
use Framework\Page;
use Framework\Storage;

/**
 * Class Response
 * @package Http
 *
 * @property-read Storage\Single $header Header data storage
 * @property-read boolean        $sent   The response is already sent or not
 * @property      int            status  The status code of the response
 * @property      int            message The status message if the response
 */
abstract class Response extends Library {

  /**
   * The headers already sent, cannot setup the headers
   */
  const EXCEPTION_FAIL_SEND = 'http#1W';
  /**
   * Can't write the output stream
   */
  const EXCEPTION_INVALID_OUTPUT = 'http#2C';

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
  const STATUS_CONTENT_NO = 204;
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
   * If the client has performed a conditional GET request and access is allowed, but the document has not been modified, the server SHOULD respond with this status code
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
   * The resource identified by the request is only capable of generating response entities which have content characteristics not acceptable according to the accept headers sent in the request
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
   * The status codes default reason phrases
   *
   * @var string[]
   */
  protected static $REASON = [
    self::STATUS_OK              => 'OK',
    self::STATUS_CREATED         => 'Created',
    self::STATUS_ACCEPTED        => 'Accepted',
    self::STATUS_CONTENT_NO      => 'No Content',
    self::STATUS_CONTENT_RESET   => 'Reset Content',
    self::STATUS_CONTENT_PARTIAL => 'Partial Content',

    self::STATUS_PERMANENTLY     => 'Moved Permanently',
    self::STATUS_FOUND           => 'Found',
    self::STATUS_OTHER           => 'See Other',
    self::STATUS_UNMODIFIED      => 'Not Modified',

    self::STATUS_BAD             => 'Bad Request',
    self::STATUS_UNAUTHORIZED    => 'Unauthorized',
    self::STATUS_FORBIDDEN       => 'Forbidden',
    self::STATUS_MISSING         => 'Not Found',
    self::STATUS_UNSUPPORTED     => 'Method Not Allowed',
    self::STATUS_UNACCEPTABLE    => 'Not Acceptable',
    self::STATUS_TIMEOUT         => 'Request Time-out',
    self::STATUS_CONFLICT        => 'Conflict',
    self::STATUS_GONE            => 'Gone',

    self::STATUS_INTERNAL        => 'Internal Server Error',
    self::STATUS_UNIMPLEMENTED   => 'Not Implemented',
    self::STATUS_UNAVAILABLE     => 'Service Unavailable'
  ];

  /**
   * The HTTP request object
   *
   * @var Request
   */
  protected $request;
  /**
   * The output stream
   *
   * @var resource
   */
  protected $output;

  /**
   * The response status code
   *
   * @var int
   */
  protected $_status;
  /**
   * The request status message
   *
   * @var string
   */
  protected $_message;
  /**
   * Flag for the response output state
   *
   * @var bool
   */
  protected $_sent = false;

  /**
   * Storage for the header fields
   *
   * @var Storage\Single
   */
  protected $_header;

  /**
   * @param Request  $request The HTTP request representation
   * @param resource $stream  The output stream
   */
  function __construct( Request $request, $stream ) {

    $this->_header = new Storage\Single();
    $this->request = $request;
    $this->output  = $stream;
  }

  /**
   * @param string $name
   * @param mixed  $value
   */
  function __set( $name, $value ) {

    switch( $name ) {
      case 'status':
        $this->_status = $value !== null ? (int) $value : null;
        break;
      case 'message':
        $this->_message = $value !== null ? (string) $value : null;
        break;
    }
  }

  /**
   * Send the response (with the header) to the stored output stream
   */
  public function send() {

    if( !$this->_sent ) {

      // log: debug
      Page::getLog()->debug( 'Send response for the {method} {url} request', [
        'method' => strtoupper( $this->request->method ),
        'url'    => (string) $this->request->url
      ] );

      $this->_sent = true;
      if( headers_sent() ) throw new Exception\Strict( self::EXCEPTION_FAIL_SEND );
      else {

        // apply default values for the status and reason phrase
        if( empty( $this->_status ) ) $this->_status = static::STATUS_OK;
        if( empty( $this->_message ) ) $this->_message = isset( static::$REASON[ $this->_status ] ) ? static::$REASON[ $this->_status ] : '';

        // process the actual header list
        $header = headers_list();
        foreach( $header as $value ) {
          list( $name, $value ) = explode( ':', $value, 2 );

          if( !$this->_header->exist( $name ) ) {
            $this->_header->set( $name, $value );
          }
        }

        // reset headers and set the status line
        header_remove();
        header( $this->request->input->getString( 'meta:server.protocol', 'HTTP/1.1' ) . " {$this->_status} {$this->_message}", true, $this->_status );

        // add every other header definition
        $header = $this->_header->getArray( '' );
        foreach( $header as $name => $value ) {

          $name = ucwords( $name );
          header( "{$name}: {$value}" );
        }

        // log: debug
        Page::getLog()->debug( 'Response headers', [ 'data' => $header ] );
      }
    }
  }
  /**
   * Write content to the response body
   *
   * @param mixed $content The content
   * @param bool  $append  Append or rewrite the exists content
   *
   * @return $this
   */
  abstract public function write( $content, $append = true );
}
