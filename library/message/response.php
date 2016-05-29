<?php namespace Http\Message;

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
   * @return string
   */
  public function getMessage();
  /**
   * @param string $value
   */
  public function setMessage( $value );

  /**
   * @return int
   */
  public function getStatus();
  /**
   * @param int $value
   */
  public function setStatus( $value );
}
