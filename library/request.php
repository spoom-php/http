<?php namespace Http;

use Framework\Helper\Library;
use Framework\Page;
use Framework\Storage\Multi;

/**
 * Class Request
 * @package Http
 *
 * @property-read Url          $url
 * @property-read Url          $url_base
 * @property-read RequestInput $input
 * @property-read string       method
 */
class Request extends Library {

  /**
   * Requests a representation of the specified resource. Requests using GET should only retrieve data and should have no other effect
   */
  const METHOD_GET = 'get';
  /**
   * Asks for the response identical to the one that would correspond to a GET request, but without the response body
   */
  const METHOD_HEAD    = 'head';
  const METHOD_TRACE   = 'trace';
  const METHOD_OPTIONS = 'options';
  const METHOD_POST    = 'post';
  const METHOD_PUT     = 'put';
  /**
   * Applies partial modifications to a resource
   */
  const METHOD_PATCH = 'patch';
  /**
   * Deletes the specified resource
   */
  const METHOD_DELETE  = 'delete';
  const METHOD_CONNECT = 'connect';

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
   * @throws \Framework\Exception\Strict
   */
  public function __construct() {

    // define simple properties 
    $this->_input  = new RequestInput();
    $this->_method = mb_strtolower( $this->_input->getString( 'meta:request.method', 'get' ) );

    // define the current url object
    $scheme     = $this->_input->getString( 'meta:request.scheme', $this->_input->getString( 'https', 'off' ) != 'off' ? 'https' : 'http' );
    $host = $this->_input->getString( 'meta:server.name', $this->_input->getString( 'header:http.host', null ) );
    $port       = $this->_input->getNumber( 'meta:server.port', $scheme == 'http' ? Url::PORT_HTTP : Url::PORT_HTTPS );
    $this->_url = Url::instance( "{$scheme}://{$host}:{$port}" . $this->_input->getString( 'meta:request.uri' ) );

    // define the web server base url
    $this->_url_base       = new Url( [ ], null, $this->_url->build( [ Url::COMPONENT_SCHEME, Url::COMPONENT_HOST, Url::COMPONENT_PORT ] ) );
    $this->_url_base->path = rtrim( dirname( $this->_input->getString( 'meta:script.name' ) ), '/' ) . '/';

    // log: debug
    Page::getLog()->debug( 'Start a new request with {method} {url} URL', [ 'url' => (string) $this->_url, 'method' => strtoupper( $this->_method ) ] );
  }
}
/**
 * Class RequestInput
 * @package Http
 */
class RequestInput extends Multi {

  /**
   * Namespace for $_REQUEST superglobal
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
   * Namespace for the http request headers. All index is in lowercase
   */
  const NAMESPACE_HEADER = 'header';

  /**
   * Internal request variable storage
   *
   * @var array
   */
  protected static $storage = null;

  // TODO add 'body' property for the request body access

  /**
   * @param string    $namespace
   * @param int|mixed $caching
   */
  public function __construct( $namespace = self::NAMESPACE_REQUEST, $caching = Multi::CACHE_NONE ) {
    parent::__construct( $namespace, null, $caching );

    self::read();
    foreach( static::$storage as $index => $value ) {

      $this->addr( $value, $index );
      unset( $value );
    }
  }

  /**
   * Create, remove, update a cookie
   *
   * TODO implement setcookie with an event ( secure the cookie, or else )
   *
   * @param string      $index
   * @param mixed       $value
   * @param int|null    $expire
   * @param string|null $url
   */
  public function setCookie( $index, $value, $expire = null, $url = null ) {
    setcookie( $index, $value, $expire, $url );
  }

  /**
   * Read the PHP superglobals into the internal storage
   */
  private static function read() {

    if( static::$storage === null ) {

      static::$storage = [
        self::NAMESPACE_REQUEST => $_REQUEST,
        self::NAMESPACE_POST    => $_POST,
        self::NAMESPACE_FILE    => [ ],
        self::NAMESPACE_GET     => $_GET,
        self::NAMESPACE_COOKIE  => $_COOKIE,
        self::NAMESPACE_META    => [ ],
        self::NAMESPACE_HEADER => [ ]
      ];

      // process files into a more logical format :)
      foreach( $_FILES as $name => $value ) {

        if( !is_array( $value[ 'name' ] ) ) static::$storage[ self::NAMESPACE_FILE ][ $name ] = $value;
        else foreach( $value[ 'name' ] as $i => $tmp ) {

          static::$storage[ self::NAMESPACE_FILE ][ $i ] = [ ];
          foreach( $value as $name2 => $value2 ) static::$storage[ self::NAMESPACE_FILE ][ $i ][ $name2 ] = $value2;
        }
      }

      // process the server variables 
      foreach( $_SERVER as $name => $value ) {

        $tmp = explode( '_', mb_strtolower( $name ), 2 );
        if( count( $tmp ) == 1 ) static::$storage[ self::NAMESPACE_META ][ $tmp[ 0 ] ] = $value;
        else {

          $tmp[ 1 ] = str_replace( '_', '-', $tmp[ 1 ] );
          if( !isset( static::$storage[ self::NAMESPACE_META ][ $tmp[ 0 ] ] ) ) {
            static::$storage[ self::NAMESPACE_META ][ $tmp[ 0 ] ] = [ ];
          }
          static::$storage[ self::NAMESPACE_META ][ $tmp[ 0 ] ][ $tmp[ 1 ] ] = $value;

          // The 'HTTP_' variables goes to the header too
          if( $tmp[ 0 ] == 'http' ) {
            static::$storage[ self::NAMESPACE_HEADER ][ $tmp[ 1 ] ] = $value;
          }
        }
      }

      // TODO parse request body if the content type is urlencoded (or form data?)

      // log: debug
      Page::getLog()->debug( 'Superglobals is successfuly parsed into the internal storage', static::$storage, '\Http\Request->read' );
    }
  }
}
