<?php namespace Http;

use Framework\Extension;
use Framework\Helper\Enumerable;
use Framework\Helper\Library;
use Framework\Helper\String;
use Framework\Storage;

/**
 * Class Request
 * @package Http
 *
 * @property-read string       $id       The unique id of the request (random, 32 length)
 * @property-read Url          $url      The url of the request
 * @property-read Url          $url_base The url with the root path
 * @property-read RequestInput $input    The input data with the servers' meta
 * @property-read string       $method   The HTTP request method
 */
class Request extends Library {

  /**
   * Triggered BEFORE the body parser of the request. Can prevent the native parsing. Arguments:
   *  - &body [resource]: The body content stream (it is the 'php://input' so it can read only once! Replace it with an other stream if you read this out)
   *  - meta [Storage]: The request metadata
   *  - &data [array]: The request' final data storage
   */
  const EVENT_BODY = 'request.body';

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
   * Unique string of the request mostly for logging purpose
   *
   * @var string
   */
  private $_id;

  /**
   * The request url representation
   *
   * @var Url
   */
  protected $_url;
  /**
   * The site url base (the url that is point to the site root directory)
   *
   * @var Url
   */
  protected $_url_base;

  /**
   * The request method name
   *
   * @var string
   */
  protected $_method;

  /**
   * The request input's storage
   *
   * @var RequestInput
   */
  protected $_input;

  /**
   * @param  array    $data The input data storage (using the RequestInput::NAMESPACE_* namespaces)
   * @param  resource $body The body stream
   * @param Storage   $meta The request metadata
   *
   * @throws \Framework\Exception\Strict
   */
  public function __construct( $data, $body, Storage $meta ) {

    // process the request body
    $this->process( $body, $meta, $data );
    $data[ RequestInput::NAMESPACE_META ] = $meta->getSource();

    // define simple properties
    $this->_input  = new RequestInput( $data );
    $this->_method = mb_strtolower( $this->_input->getString( 'meta:request.method', 'get' ) );

    // define the current url object
    $scheme     = $this->_input->getString( 'meta:url.scheme', 'http' );
    $host       = $this->_input->getString( 'meta:url.host', null );
    $port       = $this->_input->getNumber( 'meta:url.port', $scheme == 'http' ? Url::PORT_HTTP : Url::PORT_HTTPS );
    $this->_url = Url::instance( "{$scheme}://{$host}:{$port}" . $this->_input->getString( 'meta:url.route' ) );

    // define the web server base url
    $this->_url_base       = new Url( [ ], null, $this->_url->build( [ Url::COMPONENT_SCHEME, Url::COMPONENT_HOST, Url::COMPONENT_PORT ] ) );
    $this->_url_base->path = rtrim( dirname( $this->_input->getString( 'meta:request.path' ) ), '/' ) . '/';

    // generate unique request identifier
    $this->_id = String::unique( 32, "{$this->_method} {$this->_url}", false, 'md5' );
  }

  /**
   * Process the request body into data fields
   *
   * @param resource $body The body stream
   * @param Storage  $meta The request' meta data
   * @param array    $data The request' data
   */
  private function process( $body, Storage $meta, &$data ) {

    // trigger the process event
    $extension = Extension::instance( 'http' );
    $event     = $extension->trigger( self::EVENT_BODY, [
      'body' => &$body,
      'meta' => $meta,
      'data' => &$data
    ] );

    if( !$event->isPrevented() && is_resource( $body ) ) {

      // choose processor based on the body format
      $format = $meta->getString( 'body.format' );
      switch( $format ) {

        // handle multipart messages
        case 'multipart/form-data':

          // temporary data storages
          $raw_post = [ ];
          $raw_file = [ ];

          // process the multipart data into the raw containers (to process the array names later)
          $multipart = Helper::fromMultipart( $body );
          foreach( $multipart as $value ) {
            if( isset( $value->meta[ 'content-disposition' ][ 'filename' ] ) ) {

              $tmp    = [
                'name'     => $value->meta[ 'content-disposition' ][ 'filename' ],
                'type'     => isset( $value->meta[ 'content-type' ][ 'value' ] ) ? $value->meta[ 'content-type' ][ 'value' ] : '',
                'size'     => null,
                'tmp_name' => null,
                'error'    => 0,
                'stream'   => null
              ];

              // setup content related values 
              if( is_resource( $value->content ) ) {
                
                $tmp[ 'stream' ]   = $value->content;
                $tmp[ 'tmp_name' ] = stream_get_meta_data( $tmp[ 'stream' ] )[ 'uri' ];
                $tmp[ 'size' ]     = $tmp[ 'tmp_name' ] && is_file( $tmp[ 'tmp_name' ] ) ? filesize( $tmp[ 'tmp_name' ] ) : 0;
              }

              // calculate file errors
              if( empty( $tmp[ 'tmp_name' ] ) ) $tmp[ 'error' ] = UPLOAD_ERR_CANT_WRITE;
              else if( empty( $tmp[ 'size' ] ) ) $tmp[ 'error' ] = UPLOAD_ERR_NO_FILE;
              else ; // FIXME maybe check the file size for overflow

              $raw_file[] = [
                'name'  => $value->meta[ 'content-disposition' ][ 'name' ],
                'value' => $tmp
              ];

            } else {
              $raw_post[] = [
                'name'  => $value->meta[ 'content-disposition' ][ 'name' ],
                'value' => $value->content
              ];
            }
          }

          // parse the post names
          if( count( $raw_post ) ) {

            $query = '';
            foreach( $raw_post as $key => $value ) {
              $query .= '&' . $value[ 'name' ] . '=' . urlencode( $key );
            }

            $keys = [ ];
            parse_str( substr( $query, 1 ), $keys );
            array_walk_recursive( $keys, function ( &$key ) use ( $raw_post ) {
              $key = $raw_post[ $key ][ 'value' ];
            } );
            $data[ RequestInput::NAMESPACE_POST ] += $keys;
          }
          // parse the file names
          if( count( $raw_file ) ) {

            $query = '';
            foreach( $raw_file as $key => $value ) {
              $query .= '&' . $value[ 'name' ] . '=' . urlencode( $key );
            }

            $keys = [ ];
            parse_str( substr( $query, 1 ), $keys );
            array_walk_recursive( $keys, function ( &$key ) use ( $raw_file ) {
              $key = $raw_file[ $key ][ 'value' ];
            } );
            $data[ RequestInput::NAMESPACE_FILE ] += $keys;
          }

          break;

        // handle message like a query string
        case 'application/x-www-form-urlencoded':

          $tmp = stream_get_contents( $body );
          parse_str( $tmp, $data[ RequestInput::NAMESPACE_POST ] );

          break;

        // handle json messages
        case 'application/json':
        case 'text/json':

          $tmp                                  = stream_get_contents( $body );
          $data[ RequestInput::NAMESPACE_POST ] = Enumerable::fromJson( $tmp, true ) + $data[ RequestInput::NAMESPACE_POST ];

          break;

        // handle xml messages
        case 'application/xml':
        case 'text/xml':

          $tmp                                  = stream_get_contents( $body );
          $data[ RequestInput::NAMESPACE_POST ] = Enumerable::fromXml( $tmp ) + $data[ RequestInput::NAMESPACE_POST ];

          break;
      }
    }
  }

  /**
   * @return string
   */
  public function getId() {
    return $this->_id;
  }
  /**
   * @return Url
   */
  public function getUrl() {
    return $this->_url;
  }
  /**
   * @return Url
   */
  public function getUrlBase() {
    return $this->_url_base;
  }
  /**
   * @return string
   */
  public function getMethod() {
    return $this->_method;
  }
  /**
   * @return RequestInput
   */
  public function getInput() {
    return $this->_input;
  }
}
/**
 * Class RequestInput
 * @package Http
 */
class RequestInput extends Storage {

  /**
   * Mix the 'get' and the 'post' container
   */
  const NAMESPACE_REQUEST = 'request';
  /**
   * Namespace for $_POST superglobal
   */
  const NAMESPACE_POST = 'post';
  /**
   * Namespace for the processed $_FILES superglobal
   */
  const NAMESPACE_FILE = 'file';
  /**
   * Namespace for $_GET superglobal
   */
  const NAMESPACE_GET = 'get';
  /**
   * Namespace for $_COOKIE superglobal
   */
  const NAMESPACE_COOKIE = 'cookie';
  /**
   * Namespace for the processed $_SERVER superglobal. Every superglobal index turn into lowercase and exploded by the '_' characters into 2 pieces.
   * The second piece can be accessed with dot notation. In the second piece the remaining '_' characters replaced with '-'
   */
  const NAMESPACE_META = 'meta';

  /**
   * @param array $data The input storage initial data
   */
  public function __construct( $data ) {

    $data[ self::NAMESPACE_REQUEST ] = $data[ self::NAMESPACE_POST ] + $data[ self::NAMESPACE_GET ];
    parent::__construct( $data, self::NAMESPACE_REQUEST, Storage::CACHE_NONE );
  }
}
