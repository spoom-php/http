<?php namespace Http\Helper;

use Framework\Exception;
use Framework\Extension;
use Framework\Helper\Library;
use Framework\Helper\String;

/**
 * Interface UriInterface
 *
 * @see     http://tools.ietf.org/html/rfc3986
 * @package Http
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
   * @throws Exception\Strict
   */
  public function merge( $uri, array $overwrite = [ ] );

  /**
   * @return string|null
   */
  public function getScheme();
  /**
   * @param string|null $value
   *
   * @return static
   */
  public function setScheme( $value );

  /**
   * @return string|null
   */
  public function getUser();
  /**
   * @return string|null
   */
  public function getPassword();
  /**
   * note: The raw password definition is deprecated (@see http://tools.ietf.org/html/rfc3986#section-3.2.1)
   *
   * @param string|null $value
   * @param string|null $password
   *
   * @return static
   */
  public function setUser( $value, $password = null );
  /**
   * @return string|null
   */
  public function getHost();
  /**
   * @param string|null $value
   *
   * @return static
   */
  public function setHost( $value );
  /**
   * @return int|null
   */
  public function getPort();
  /**
   * @param int|null $value
   *
   * @return static
   * @throws Exception
   */
  public function setPort( $value );

  /**
   * @return string|null
   */
  public function getPath();
  /**
   * @param string|null $value
   *
   * @return static
   */
  public function setPath( $value );

  /**
   * @return array[]|null
   */
  public function getQuery();
  /**
   * @param array|string $value
   *
   * @return static
   * @throws Exception
   */
  public function setQuery( $value );

  /**
   * @return string|null
   */
  public function getFragment();
  /**
   * @param string|null $value
   *
   * @return static
   */
  public function setFragment( $value );

  /**
   * @param array $filter The component names that will be included in the result
   *
   * @return array
   */
  public function getComponent( array $filter = [ ] );
}

/**
 * Class Uri
 * @package Http
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
class Uri extends Library implements UriInterface {

  /**
   * The query argument can't parsed into an array
   */
  const EXCEPTION_INVALID_QUERY = 'http#12W';
  /**
   * The port is not a number. Argument:
   *  - port [mixed]: The port that is invalid
   */
  const EXCEPTION_INVALID_PORT = 'http#13W';
  /**
   * The URI definition can't be parsed into an Uri instance
   */
  const EXCEPTION_INVALID_DEFINITION = 'http#14W';
  /**
   * The Uri can't converted into a string. Arguments:
   *  - component [array]: The URI component array that is invalid
   */
  const EXCEPTION_INVALID_URI = 'http#15W';

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
  public function __construct( $query = [ ], $path = null, $root = null ) {

    // define the simple components
    $this->query = $query;
    $this->path  = $path;

    // parse the root definition if has any and add it's components to the instance
    if( !empty( $root ) ) $this->merge( $root );
  }

  /**
   * Build the stored URI components into an URI string
   *
   * @return string
   * @throws Exception\Strict
   */
  public function __toString() {

    $component = $this->getComponent();
    $extension = Extension::instance( 'http' );
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

          // cannot build an URI with scheme or user and no host
          if( !isset( $component[ self::COMPONENT_HOST ] ) ) {
            throw new Exception\Strict( self::EXCEPTION_INVALID_URI, [ 'component' => $component ] );
          }

          break;
        // check for a user definition if password id provided
        case self::COMPONENT_PASSWORD:

          // cannot build an URI with password and no user
          if( !isset( $component[ self::COMPONENT_USER ] ) ) {
            throw new Exception\Strict( self::EXCEPTION_INVALID_URI, [ 'component' => $component ] );
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
    else $template[ self::COMPONENT_USER ] = self::SEPARATOR_HOST . $template[ self::COMPONENT_USER ];

    // unset the password part
    if( !isset( $component[ self::COMPONENT_PASSWORD ] ) ) {
      $component[ self::COMPONENT_PASSWORD ] = '';
      $template[ self::COMPONENT_USER ]      = rtrim( $template[ self::COMPONENT_USER ], self::SEPARATOR_PASSWORD );
    }

    // build the template string based on the exists components
    $string = '';
    foreach( $template as $name => $pattern ) {
      if( isset( $component[ $name ] ) ) {
        $string .= $pattern;
      }
    }

    return String::insert( $string, $component );
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
   * @throws Exception\Strict
   */
  public function merge( $uri, array $overwrite = [ ] ) {

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

  /**
   * @return string|null
   */
  public function getScheme() {
    return isset( $this->_component[ self::COMPONENT_SCHEME ] ) ? $this->_component[ self::COMPONENT_SCHEME ] : null;
  }
  /**
   * @param string|null $value
   *
   * @return static
   */
  public function setScheme( $value ) {
    if( is_null( $value ) ) unset( $this->_component[ self::COMPONENT_SCHEME ] );
    else $this->_component[ self::COMPONENT_SCHEME ] = (string) $value;

    return $this;
  }

  /**
   * @return string|null
   */
  public function getUser() {
    return isset( $this->_component[ self::COMPONENT_USER ] ) ? $this->_component[ self::COMPONENT_USER ] : null;
  }
  /**
   * @return string|null
   */
  public function getPassword() {
    return isset( $this->_component[ self::COMPONENT_PASSWORD ] ) ? $this->_component[ self::COMPONENT_PASSWORD ] : null;
  }
  /**
   * @param string|null $value
   * @param string|null $password
   *
   * @return static
   */
  public function setUser( $value, $password = null ) {
    if( is_null( $value ) ) unset( $this->_component[ self::COMPONENT_USER ] );
    else $this->_component[ self::COMPONENT_USER ] = (string) $value;

    if( is_null( $password ) ) unset( $this->_component[ self::COMPONENT_PASSWORD ] );
    else $this->_component[ self::COMPONENT_PASSWORD ] = (string) $password;

    return $this;
  }
  /**
   * @return string|null
   */
  public function getHost() {
    return isset( $this->_component[ self::COMPONENT_HOST ] ) ? $this->_component[ self::COMPONENT_HOST ] : null;
  }
  /**
   * @param string|null $value
   *
   * @return static
   */
  public function setHost( $value ) {
    if( is_null( $value ) ) unset( $this->_component[ self::COMPONENT_HOST ] );
    else $this->_component[ self::COMPONENT_HOST ] = (string) $value;

    return $this;
  }
  /**
   * @return int|null
   */
  public function getPort() {
    return isset( $this->_component[ self::COMPONENT_PORT ] ) ? $this->_component[ self::COMPONENT_PORT ] : null;
  }
  /**
   * @param int|null $value
   *
   * @return static
   * @throws Exception
   */
  public function setPort( $value ) {
    if( is_null( $value ) ) unset( $this->_component[ self::COMPONENT_PORT ] );
    else if( !is_numeric( $value ) ) throw new Exception\Strict( self::EXCEPTION_INVALID_PORT, [ 'port' => $value ] );
    else $this->_component[ self::COMPONENT_PORT ] = (int) $value;

    return $this;
  }

  /**
   * @return string|null
   */
  public function getPath() {
    return isset( $this->_component[ self::COMPONENT_PATH ] ) ? $this->_component[ self::COMPONENT_PATH ] : null;
  }
  /**
   * @param string|null $value
   *
   * @return static
   */
  public function setPath( $value ) {
    if( is_null( $value ) ) unset( $this->_component[ self::COMPONENT_PATH ] );
    else $this->_component[ self::COMPONENT_PATH ] = $value;

    return $this;
  }

  /**
   * @return array[]|null
   */
  public function getQuery() {
    return isset( $this->_component[ self::COMPONENT_QUERY ] ) ? $this->_component[ self::COMPONENT_QUERY ] : null;
  }
  /**
   * @param array|string $value
   *
   * @return static
   * @throws Exception
   */
  public function setQuery( $value ) {

    if( is_null( $value ) ) unset( $this->_component[ self::COMPONENT_QUERY ] );
    else if( is_array( $value ) ) $this->_component[ self::COMPONENT_QUERY ] = $value;
    else if( !is_string( $value ) ) throw new Exception\Strict( self::EXCEPTION_INVALID_QUERY );
    else {

      $this->_component[ self::COMPONENT_QUERY ] = [ ];
      parse_str( $value, $this->_component[ self::COMPONENT_QUERY ] );
    }

    return $this;
  }

  /**
   * @return string|null
   */
  public function getFragment() {
    return isset( $this->_component[ self::COMPONENT_FRAGMENT ] ) ? $this->_component[ self::COMPONENT_FRAGMENT ] : null;
  }
  /**
   * @param string|null $value
   *
   * @return static
   */
  public function setFragment( $value ) {
    if( is_null( $value ) || trim( $value, ' ' . self::SEPARATOR_FRAGMENT ) == '' ) unset( $this->_component[ self::COMPONENT_FRAGMENT ] );
    else $this->_component[ self::COMPONENT_FRAGMENT ] = ltrim( $value, self::SEPARATOR_FRAGMENT );

    return $this;
  }

  /**
   * @param array $filter
   *
   * @return array
   */
  public function getComponent( array $filter = [ ] ) {

    if( empty( $filter ) ) return $this->_component;
    else {

      $tmp = [ ];
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
   * @return static
   * @throws Exception\Strict
   */
  public static function instance( $definition ) {

    if( $definition instanceof UriInterface ) return $definition;
    else if( is_array( $definition ) ) $components = $definition;
    else {

      $components = parse_url( $definition );
      if( !is_array( $components ) ) throw new Exception\Strict( self::EXCEPTION_INVALID_DEFINITION );
    }

    // setup the basic variables
    $uri       = new static();
    $translate = [
      'scheme'   => self::COMPONENT_SCHEME,
      'pass'     => self::COMPONENT_PASSWORD,
      'password' => self::COMPONENT_PASSWORD,
      'user'     => self::COMPONENT_USER,
      'host'     => self::COMPONENT_HOST,
      'port'     => self::COMPONENT_PORT,
      'path'     => self::COMPONENT_PATH,
      'query'    => self::COMPONENT_QUERY,
      'fragment' => self::COMPONENT_FRAGMENT
    ];

    // set components in the new Uri instance
    foreach( $components as $name => $value ) {
      $uri->{$translate[ $name ]} = $value;
    }

    return $uri;
  }
}
