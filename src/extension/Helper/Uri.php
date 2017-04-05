<?php namespace Spoom\Http\Helper;

use Spoom\Framework\Exception;
use Spoom\Http\Extension;
use Spoom\Framework\Helper;
use Spoom\Framework\Helper\Text;

/**
 * Interface UriInterface
 *
 * @see     http://tools.ietf.org/html/rfc3986
 * @package Spoom\Http
 *
 * @property      string $scheme    The scheme. If defined, the host MUST be defined too
 * @property      string $password  The password. If defined, the user MUST be defined too
 * @property      string $user      The user. If defined, the host MUST be defined too
 * @property      string $host      The host without the port
 * @property      int    $port      The port
 * @property      string $path      The path. This can be relative or absolute too
 * @property      array  $query     The query in the array form. This is never null
 * @property      string $fragment  The fragment
 * @property-read array  $component All the components above (only that is defined) in an associative array
 */
interface UriInterface {

  const SEPARATOR_SCHEME   = ':';
  const SEPARATOR_HOST     = '//';
  const SEPARATOR_USER     = '@';
  const SEPARATOR_PASSWORD = ':';
  const SEPARATOR_QUERY    = '?';
  const SEPARATOR_FRAGMENT = '#';

  /**
   * The scheme part name
   */
  const COMPONENT_SCHEME = 'scheme';
  /**
   * The password part name
   */
  const COMPONENT_PASSWORD = 'password';
  /**
   * The user part name
   */
  const COMPONENT_USER = 'user';
  /**
   * The host part name
   */
  const COMPONENT_HOST = 'host';
  /**
   * The port part name
   */
  const COMPONENT_PORT = 'port';
  /**
   * The path part name
   */
  const COMPONENT_PATH = 'path';
  /**
   * The query part name
   */
  const COMPONENT_QUERY = 'query';
  /**
   * The fragment part name
   */
  const COMPONENT_FRAGMENT = 'fragment';

  const SCHEME_HTTP  = 'http';
  const SCHEME_HTTPS = 'https';

  /**
   * Default port for HTTP scheme
   */
  const PORT_HTTP = 80;
  /**
   * Default port for HTTPS scheme
   */
  const PORT_HTTPS = 443;

  /**
   * Build the stored URI components into an URI string
   *
   * @return string Format: [scheme:][//[user[:password]@]host[:port]][/path][?query][#fragment]
   */
  public function __toString();

  /**
   * Merge the components with the ones in the $uri argument
   *
   * @param Uri|array|string $uri       The URI definition
   * @param array            $overwrite The component names that will be overwritten not just extended
   *
   * @example  'http/index.php' merged with the 'http://example.com/url' will be http://example.com/url/http/index.php
   *
   * @return $this
   * @throws \InvalidArgumentException
   */
  public function merge( $uri, array $overwrite = [] );

  /**
   * @return string|null
   */
  public function getScheme():?string;
  /**
   * @param string|null $value
   *
   * @return static
   */
  public function setScheme( ?string $value );

  /**
   * @return string|null
   */
  public function getUser():?string;
  /**
   * @return string|null
   */
  public function getPassword():?string;
  /**
   * note: The raw password definition is deprecated (@see http://tools.ietf.org/html/rfc3986#section-3.2.1)
   *
   * @param string|null $value
   * @param string|null $password
   *
   * @return static
   */
  public function setUser( ?string $value, ?string $password = null );
  /**
   * @return string|null
   */
  public function getHost():?string;
  /**
   * @param string|null $value
   *
   * @return static
   */
  public function setHost( ?string $value );
  /**
   * @return int|null
   */
  public function getPort():?int;
  /**
   * @param int|null $value
   *
   * @return static
   * @throws Exception
   */
  public function setPort( ?int $value );

  /**
   * @return string|null
   */
  public function getPath(): ?string;
  /**
   * @param string|null $value
   *
   * @return static
   */
  public function setPath( ?string $value );

  /**
   * @return array[]|null
   */
  public function getQuery():?array;
  /**
   * @param array|string $value
   *
   * @return static
   * @throws \InvalidArgumentException
   */
  public function setQuery( $value );

  /**
   * @return string|null
   */
  public function getFragment(): ?string;
  /**
   * @param string|null $value
   *
   * @return static
   */
  public function setFragment( ?string $value );

  /**
   * @param array $filter The component names that will be included in the result
   *
   * @return array
   */
  public function getComponent( array $filter = [] ): array;
}
/**
 * Class Uri
 * @package Spoom\Http
 */
class Uri implements Helper\AccessableInterface, UriInterface {
  use Helper\Accessable;

  /**
   * Triggers before the uri building. Arguments:
   *  - instance [Uri]: The Uri instance
   *  - &component [array]: The URI's component array
   */
  const EVENT_BUILD = 'uri.build';

  /**
   * Map schemes to their default ports. This port will omitted in the URI string if the URI has the scheme's default port
   *
   * TODO define the rest known default port
   *
   * @var int[string]
   */
  protected static $PORT_MAP = [
    'http'  => self::PORT_HTTP,
    'https' => self::PORT_HTTPS
  ];
  /**
   * Helper array that map the components to their string pattern in the URI string
   *
   * FIXME add characters from the self::SEPARATOR_* constants
   *
   * @var array[string]string
   */
  protected static $TEMPLATE = [
    self::COMPONENT_SCHEME   => '{scheme}:',
    self::COMPONENT_USER     => '{user}:',
    self::COMPONENT_PASSWORD => '{password}@',
    self::COMPONENT_HOST     => '{host}',
    self::COMPONENT_PORT     => ':{port}',
    self::COMPONENT_PATH     => '{path}',
    self::COMPONENT_QUERY    => '?{query}',
    self::COMPONENT_FRAGMENT => '#{fragment}'
  ];

  /**
   * Storage of the URI components
   *
   * @var mixed[string]
   */
  protected $_component;

  /**
   * @param string|array          $query The query array or string that will parsed into array
   * @param string|null           $path  The path of the URI
   * @param Uri|string|array|null $root  The root URI definition. The new instance will be extended with this URI
   */
  public function __construct( $query = [], $path = null, $root = null ) {

    // define the simple components
    $this->query = $query;
    $this->path  = $path;

    // parse the root definition if has any and add it's components to the instance
    if( !empty( $root ) ) $this->merge( $root );
  }

  //
  public function __toString() {

    $component = $this->getComponent();
    $extension = Extension::instance();
    $extension->trigger( self::EVENT_BUILD, [ 'instance' => $this, 'component' => &$component ] );

    // preprocess the components
    foreach( $component as $name => &$value ) {
      switch( $name ) {
        // convert the array query into a string
        case self::COMPONENT_QUERY:

          if( empty( $value ) ) unset( $component[ $name ] );
          else $value = http_build_query( $value );

          break;
        // skip the port if it's the default of the scheme
        case self::COMPONENT_PORT:

          $scheme = isset( $component[ self::COMPONENT_SCHEME ] ) ? $component[ self::COMPONENT_SCHEME ] : null;
          if( $scheme && isset( static::$PORT_MAP[ $scheme ] ) && static::$PORT_MAP[ $scheme ] == $value ) {
            unset( $component[ $name ] );
          }

          break;
        // check the host if user is defined
        case self::COMPONENT_USER:

          // cannot add user without host
          if( !isset( $component[ self::COMPONENT_HOST ] ) ) {
            unset( $component[ $name ], $component[ self::COMPONENT_PASSWORD ] );
          }

          break;
        // check for a user definition if password id provided
        case self::COMPONENT_PASSWORD:

          // cannot add password without user
          if( !isset( $component[ self::COMPONENT_USER ] ) ) {
            unset( $component[ $name ] );
          }

          break;
        // convert the path to absolute if there is host definition
        case self::COMPONENT_PATH:

          // there is no relative URI with host definition
          if( isset( $component[ self::COMPONENT_HOST ] ) ) {
            $value = '/' . ltrim( $value, '/' );
          }
      }
    }

    // preprocess the template based on the actual values
    $template = static::$TEMPLATE;

    // add double slash before the host (or the user)
    if( !isset( $component[ self::COMPONENT_USER ] ) ) $template[ self::COMPONENT_HOST ] = self::SEPARATOR_HOST . $template[ self::COMPONENT_HOST ];
    else {

      $template[ self::COMPONENT_USER ] = self::SEPARATOR_HOST . $template[ self::COMPONENT_USER ];

      // unset the password part
      if( !isset( $component[ self::COMPONENT_PASSWORD ] ) ) {
        $component[ self::COMPONENT_PASSWORD ] = '';
        $template[ self::COMPONENT_USER ]      = rtrim( $template[ self::COMPONENT_USER ], self::SEPARATOR_PASSWORD );
      }
    }

    // build the template string based on the exists components
    $string = '';
    foreach( $template as $name => $pattern ) {
      if( isset( $component[ $name ] ) ) {
        $string .= $pattern;
      }
    }

    return Text::insert( $string, $component );
  }

  /**
   * Merge the components with the ones in the $uri argument
   *
   * @param Uri|array|string $uri       The URI definition
   * @param array            $overwrite The component names that will be overwritten not just extended
   *
   * @example  'http/index.php' merged with the 'http://example.com/url' will be http://example.com/url/http/index.php
   *
   * @return $this
   * @throws \InvalidArgumentException
   */
  public function merge( $uri, array $overwrite = [] ) {

    // parse the URI
    $uri = static::instance( $uri );

    // handle simple overwrite cases
    $allow = [ static::COMPONENT_SCHEME, static::COMPONENT_USER, static::COMPONENT_PASSWORD, static::COMPONENT_HOST ];
    foreach( $allow as $component ) if( !isset( $this->{$component} ) || in_array( $component, $overwrite ) ) {
      $this->{$component} = $uri->{$component};
    }

    // handle the special cases where the component can be extended
    $allow = [ self::COMPONENT_PATH, self::COMPONENT_FRAGMENT ];
    foreach( $allow as $component ) if( isset( $uri->{$component} ) ) {
      if( in_array( $component, $overwrite ) ) $this->{$component} = $uri->{$component};
      else if( $component != self::COMPONENT_PATH ) $this->{$component} = $uri->{$component} . $this->{$component};
      else {

        // FIXME re-think this extending mechanism (not logical)
        $this->{$component} = rtrim( $uri->{$component}, '/' ) . '/' . $this->{$component};
      }
    }

    // handle the super special query case
    if( !empty( $uri->query ) ) {

      if( in_array( self::COMPONENT_QUERY, $overwrite ) ) $this->query = $uri->query;
      else $this->query += $uri->query;
    }

    return $this;
  }

  //
  public function getScheme(): ?string {
    return isset( $this->_component[ self::COMPONENT_SCHEME ] ) ? $this->_component[ self::COMPONENT_SCHEME ] : null;
  }
  //
  public function setScheme( ?string $value ) {
    if( is_null( $value ) ) unset( $this->_component[ self::COMPONENT_SCHEME ] );
    else $this->_component[ self::COMPONENT_SCHEME ] = (string) $value;

    return $this;
  }

  //
  public function getUser():?string {
    return isset( $this->_component[ self::COMPONENT_USER ] ) ? $this->_component[ self::COMPONENT_USER ] : null;
  }
  //
  public function getPassword():?string {
    return isset( $this->_component[ self::COMPONENT_PASSWORD ] ) ? $this->_component[ self::COMPONENT_PASSWORD ] : null;
  }
  //
  public function setUser( ?string $value, ?string $password = null ) {
    if( is_null( $value ) ) unset( $this->_component[ self::COMPONENT_USER ] );
    else $this->_component[ self::COMPONENT_USER ] = (string) $value;

    if( is_null( $password ) ) unset( $this->_component[ self::COMPONENT_PASSWORD ] );
    else $this->_component[ self::COMPONENT_PASSWORD ] = (string) $password;

    return $this;
  }
  //
  public function getHost():?string {
    return isset( $this->_component[ self::COMPONENT_HOST ] ) ? $this->_component[ self::COMPONENT_HOST ] : null;
  }
  //
  public function setHost( ?string $value ) {
    if( is_null( $value ) ) unset( $this->_component[ self::COMPONENT_HOST ] );
    else $this->_component[ self::COMPONENT_HOST ] = (string) $value;

    return $this;
  }
  //
  public function getPort():?int {
    return isset( $this->_component[ self::COMPONENT_PORT ] ) ? $this->_component[ self::COMPONENT_PORT ] : null;
  }
  //
  public function setPort( ?int $value ) {
    if( is_null( $value ) ) unset( $this->_component[ self::COMPONENT_PORT ] );
    else if( !is_numeric( $value ) ) throw new \InvalidArgumentException( "Port must be numeric, not {$value}" );
    else $this->_component[ self::COMPONENT_PORT ] = (int) $value;

    return $this;
  }

  //
  public function getPath():?string {
    return isset( $this->_component[ self::COMPONENT_PATH ] ) ? $this->_component[ self::COMPONENT_PATH ] : null;
  }
  //
  public function setPath( ?string $value ) {
    if( is_null( $value ) ) unset( $this->_component[ self::COMPONENT_PATH ] );
    else $this->_component[ self::COMPONENT_PATH ] = $value;

    return $this;
  }

  //
  public function getQuery():?array {
    return isset( $this->_component[ self::COMPONENT_QUERY ] ) ? $this->_component[ self::COMPONENT_QUERY ] : null;
  }
  //
  public function setQuery( $value ) {

    if( is_null( $value ) ) unset( $this->_component[ self::COMPONENT_QUERY ] );
    else if( is_array( $value ) ) $this->_component[ self::COMPONENT_QUERY ] = $value;
    else if( !is_string( $value ) ) throw new \InvalidArgumentException( "Query must be an array or a string!" );
    else {

      $this->_component[ self::COMPONENT_QUERY ] = [];
      parse_str( $value, $this->_component[ self::COMPONENT_QUERY ] );
    }

    return $this;
  }

  //
  public function getFragment():?string {
    return isset( $this->_component[ self::COMPONENT_FRAGMENT ] ) ? $this->_component[ self::COMPONENT_FRAGMENT ] : null;
  }
  //
  public function setFragment( ?string $value ) {
    if( is_null( $value ) || trim( $value, ' ' . self::SEPARATOR_FRAGMENT ) == '' ) unset( $this->_component[ self::COMPONENT_FRAGMENT ] );
    else $this->_component[ self::COMPONENT_FRAGMENT ] = ltrim( $value, self::SEPARATOR_FRAGMENT );

    return $this;
  }

  //
  public function getComponent( array $filter = [] ): array {

    if( empty( $filter ) ) return $this->_component;
    else {

      $tmp = [];
      foreach( $filter as $key ) {
        if( isset( $this->_component[ $key ] ) ) {
          $tmp[ $key ] = $this->_component[ $key ];
        }
      }

      return $tmp;
    }
  }

  /**
   * Process the definition into an Uri instance
   *
   * @param UriInterface|array|string $definition The string representation of an URI, or an array of URI components
   *
   * @return UriInterface
   * @throws \InvalidArgumentException Unparsable definition
   */
  public static function instance( $definition ) {

    if( $definition instanceof UriInterface ) return $definition;
    else if( is_array( $definition ) ) $components = $definition;
    else {

      $components = parse_url( $definition );
      if( !is_array( $components ) ) throw new \InvalidArgumentException( "Definition is not a valid URI: {$definition}" );
    }

    // 
    if( isset( $components[ 'pass' ] ) ) {
      $components[ 'password' ] = $components[ 'pass' ];
    }

    // setup the basic variables
    $uri       = new static();
    $translate = [
      'scheme'   => self::COMPONENT_SCHEME,
      'user'     => self::COMPONENT_USER,
      'host'     => self::COMPONENT_HOST,
      'port'     => self::COMPONENT_PORT,
      'path'     => self::COMPONENT_PATH,
      'query'    => self::COMPONENT_QUERY,
      'fragment' => self::COMPONENT_FRAGMENT
    ];

    // set components in the new Uri instance
    foreach( $translate as $name => $property ) {
      if( isset( $components[ $name ] ) ) {
        if( $name == 'user' ) $uri->setUser( $components[ $name ], isset( $components[ 'password' ] ) ? $components[ 'password' ] : null );
        else $uri->{$property} = $components[ $name ];
      }
    }

    return $uri;
  }
}
