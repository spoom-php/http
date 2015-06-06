<?php namespace Http;

use Framework\Exception;
use Framework\Extension;
use Framework\Helper\Library;
use Framework\Helper\String;

/**
 * Class Url
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
class Url extends Library {

  /**
   * The query argument can't parsed into an array
   */
  const EXCEPTION_INVALID_QUERY = '';
  /**
   * The port is not a number. Argument:
   *  - port [mixed]: The port that is invalid
   */
  const EXCEPTION_INVALID_PORT = '';
  /**
   * The URI definition can't be parsed into an Url instance
   */
  const EXCEPTION_INVALID_DEFINITION = '';
  /**
   * The Url can't converted into a string. Arguments:
   *  - component [array]: The URI component array that is invalid
   */
  const EXCEPTION_INVALID_URI = '';

  /**
   * Triggers before the url building. Arguments:
   *  - instance [Url]: The Url instance
   *  - &component [array]: The URL's component array
   */
  const EVENT_BUILD = 'url.build';

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

  /**
   * Default port for HTTP scheme
   */
  const PORT_HTTP = 80;
  /**
   * Default port for HTTPS scheme
   */
  const PORT_HTTPS = 443;

  /**
   * Map schemes to their default ports. This port will omitted in the URL string if the URL has the scheme's default port
   *
   * @var int[string]
   */
  protected static $PORT_MAP = [
    'http'  => self::PORT_HTTP,
    'https' => self::PORT_HTTPS
  ];
  /**
   * Helper array that map the components to their string pattern in the URL string
   *
   * @var array[string]string
   */
  protected static $TEMPLATE = [
    self::COMPONENT_SCHEME   => '{scheme}:',
    self::COMPONENT_PASSWORD => '{password}:',
    self::COMPONENT_USER     => '{user}@',
    self::COMPONENT_HOST     => '//{host}',
    self::COMPONENT_PORT     => ':{port}',
    self::COMPONENT_PATH     => '{path}',
    self::COMPONENT_QUERY    => '?{query}',
    self::COMPONENT_FRAGMENT => '#{fragment}'
  ];

  /**
   * Default components that will be in the URL string
   *
   * @var string[]
   */
  protected $allow = [
    self::COMPONENT_SCHEME,
    self::COMPONENT_PASSWORD,
    self::COMPONENT_USER,
    self::COMPONENT_HOST,
    self::COMPONENT_PORT,
    self::COMPONENT_PATH,
    self::COMPONENT_QUERY,
    self::COMPONENT_FRAGMENT
  ];

  /**
   * Storage of the URL components
   *
   * @var mixed[string]
   */
  protected $_component;

  /**
   * @param string|array          $query The query array or string that will parsed into array
   * @param string|null           $path  The path of the URL
   * @param Url|string|array|null $root  The root URL definition. The new instance will be extended with this URL
   */
  public function __construct( $query = [ ], $path = null, $root = null ) {

    // define the simple components
    $this->query = $query;
    $this->path  = $path;

    // parse the root definition if has any and add it's components to the instance
    if( !empty( $root ) ) $this->extend( $root );
  }

  /**
   * @param string $index
   *
   * @return null|string
   */
  public function __get( $index ) {

    switch( $index ) {
      case self::COMPONENT_SCHEME:
      case self::COMPONENT_PASSWORD:
      case self::COMPONENT_USER:
      case self::COMPONENT_HOST:
      case self::COMPONENT_PORT:
      case self::COMPONENT_PATH:
      case self::COMPONENT_QUERY:
      case self::COMPONENT_FRAGMENT:
        return isset( $this->_component[ $index ] ) ? $this->_component[ $index ] : null;
    }

    return parent::__get( $index );
  }
  /**
   * @param string $index
   *
   * @return bool
   */
  public function __isset( $index ) {

    switch( $index ) {
      case self::COMPONENT_SCHEME:
      case self::COMPONENT_PASSWORD:
      case self::COMPONENT_USER:
      case self::COMPONENT_HOST:
      case self::COMPONENT_PORT:
      case self::COMPONENT_PATH:
      case self::COMPONENT_QUERY:
      case self::COMPONENT_FRAGMENT:
        return isset( $this->_component[ $index ] );
    }

    return parent::__isset( $index );
  }
  /**
   * @param $name
   * @param $value
   *
   * @throws Exception\Strict
   */
  public function __set( $name, $value ) {

    switch( $name ) {
      case self::COMPONENT_SCHEME:
      case self::COMPONENT_PASSWORD:
      case self::COMPONENT_USER:
      case self::COMPONENT_HOST:

        if( is_null( $value ) ) unset( $this->_component[ $name ] );
        else $this->_component[ $name ] = (string) $value;

        break;
      case self::COMPONENT_PORT:

        if( is_null( $value ) ) unset( $this->_component[ $name ] );
        else if( !is_numeric( $value ) ) throw new Exception\Strict( self::EXCEPTION_INVALID_PORT, [ 'port' => $value ] );
        else $this->_component[ $name ] = (int) $value;

        break;
      case self::COMPONENT_PATH:

        if( is_null( $value ) ) unset( $this->_component[ $name ] );
        else $this->_component[ $name ] = $value;

        break;
      case self::COMPONENT_QUERY:

        if( is_null( $value ) ) unset( $this->_component[ $name ] );
        else if( is_array( $value ) ) $this->_component[ $name ] = $value;
        else if( !is_string( $value ) ) throw new Exception\Strict( self::EXCEPTION_INVALID_QUERY );
        else {

          $this->_component[ $name ] = [ ];
          parse_str( $value, $this->_component[ $name ] );
        }

        break;
      case self::COMPONENT_FRAGMENT:

        if( is_null( $value ) || trim( $value, ' #' ) == '' ) unset( $this->_component[ $name ] );
        else $this->_component[ $name ] = ltrim( $value, '#' );

        break;
    }
  }

  /**
   * Build with default arguments
   *
   * @return string
   */
  public function __toString() {
    return $this->build();
  }

  /**
   * Build the stored URL components into an URL string
   *
   * @param array $allow The array of component names that will be in the URL string
   *
   * @return string
   * @throws Exception\Strict
   */
  public function build( array $allow = null ) {

    // add the default components
    if( empty( $allow ) ) $allow = $this->allow;

    $component = $this->_component;
    $extension = Extension::instance( 'http' );
    $extension->trigger( self::EVENT_BUILD, [ 'instance' => $this, 'component' => &$component ] );

    // preprocess the components
    foreach( $component as $name => &$value ) {

      if( !in_array( $name, $allow ) ) unset( $component[ $name ] );
      else switch( $name ) {
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
        // check the host if user or scheme is defined
        case self::COMPONENT_USER:
        case self::COMPONENT_SCHEME:

          // cannot build an URL with scheme or user and no host
          if( !isset( $component[ self::COMPONENT_HOST ] ) ) {
            throw new Exception\Strict( self::EXCEPTION_INVALID_URI, [ 'component' => $component ] );
          }

          break;
        // check for a user definition if password id provided
        case self::COMPONENT_PASSWORD:

          // cannot build an URL with password and no user
          if( !isset( $component[ self::COMPONENT_USER ] ) ) {
            throw new Exception\Strict( self::EXCEPTION_INVALID_URI, [ 'component' => $component ] );
          }

          break;
        // convert the path to absolute if there is host definition
        case self::COMPONENT_PATH:

          // there is no relative URL with host definition
          if( isset( $component[ self::COMPONENT_HOST ] ) ) {
            $value = '/' . ltrim( $value, '/' );
          }
      }
    }

    // build the template string based on the exists components
    $string = '';
    foreach( static::$TEMPLATE as $name => $pattern ) {
      if( isset( $component[ $name ] ) ) {
        $string .= $pattern;
      }
    }

    return String::insert( $string, $component );
  }
  /**
   * Extend the components with the ones in the $uri argument
   *
   * @param Url|array|string $uri       The URL definition
   * @param array            $overwrite The component names that will be overwritten not just extended
   *
   * @example  'http/index.php' extended with the 'http://example.com/url' will be http://example.com/url/http/index.php
   *
   * @return $this
   * @throws Exception\Strict
   */
  public function extend( $uri, array $overwrite = [ ] ) {

    // parse the URL
    $uri = self::instance( $uri );

    // handle simple overwrite cases 
    $allow = [ self::COMPONENT_SCHEME, self::COMPONENT_USER, self::COMPONENT_PASSWORD, self::COMPONENT_HOST ];
    foreach( $allow as $component ) if( !isset( $this->{$component} ) || in_array( $component, $overwrite ) ) {
      $this->{$component} = $uri->{$component};
    }

    // handle the special cases where the component can be extended
    $allow = [ self::COMPONENT_PATH, self::COMPONENT_FRAGMENT ];
    foreach( $allow as $component ) if( isset( $uri->{$component} ) ) {
      if( in_array( $component, $overwrite ) ) $this->{$component} = $uri->{$component};
      else $this->{$component} = rtrim( $uri->{$component}, '/' ) . '/' . $this->{$component};
    }

    // handle the super special query case
    if( !empty( $uri->query ) ) {

      if( in_array( self::COMPONENT_QUERY, $overwrite ) ) $this->query = $uri->query;
      else $this->query += $uri->query;
    }

    return $this;
  }

  /**
   * Process the definition into an Url instance
   *
   * @param Url|array|string $definition The string representation of an URL, or an array of URL components
   *
   * @return Url
   * @throws Exception\Strict
   */
  public static function instance( $definition ) {

    if( $definition instanceof Url ) return $definition;
    else if( is_array( $definition ) ) $components = $definition;
    else {

      $components = @parse_url( $definition );
      if( !is_array( $components ) ) throw new Exception\Strict( self::EXCEPTION_INVALID_DEFINITION );
    }

    // setup the basic variables
    $uri       = new Url();
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

    // set components in the new Url instance
    foreach( $components as $name => $value ) {
      $uri->{$translate[ $name ]} = $value;
    }

    return $uri;
  }
}
