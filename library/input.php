<?php namespace Http;

use Framework\Exception;
use Framework\Extension;
use Framework\Helper\Enumerable;
use Framework\Storage;
use Http\Helper\Multipart;
use Http\Helper\StreamInterface;
use Http\Helper\UriInterface;

/**
 * Class Input
 * @package Http
 */
class Input extends Storage {

  /**
   * Mix the 'get' and the 'post' container
   */
  const NAMESPACE_REQUEST = 'request';
  /**
   * Namespace for $_POST superglobal
   */
  const NAMESPACE_BODY = 'body';
  /**
   * Namespace for $_GET superglobal
   */
  const NAMESPACE_URI = 'uri';

  /**
   * Preprocess the input before the construct
   *
   * @param static               $instance
   * @param UriInterface         $uri
   * @param StreamInterface|null $body
   * @param string|null          $format
   *
   * @prevent Uri and body processing
   */
  const EVENT_CREATE = 'input.create';

  /**
   * Parse input stream and uri into structural data
   *
   * @param UriInterface         $uri
   * @param StreamInterface|null $body
   * @param string|null          $format
   */
  public function __construct( $uri, $body = null, $format = null ) {
    parent::__construct( [], static::NAMESPACE_REQUEST );

    // trigger the process event
    $extension = Extension::instance( 'http' );
    $event     = $extension->trigger( self::EVENT_CREATE, [
      'instance' => $this,
      'uri'      => $uri,
      'body'     => $body,
      'format'   => $format
    ] );

    if( !$event->isPrevented() ) {

      // set "public" and "private" data
      $this->set( self::NAMESPACE_URI . ':', $uri->getQuery() );
      $this->set( self::NAMESPACE_BODY . ':', $this->process( $body, $format ) );
    }

    // merge the "public" and "private" storages
    $this->set( 'request:', static::merge( $this->getArray( self::NAMESPACE_URI . ':' ), $this->getArray( self::NAMESPACE_BODY . ':' ) ) );
  }

  /**
   * Process the body into the input's storage
   *
   * @param StreamInterface|null $body
   * @param string|null          $format
   *
   * @return array
   */
  private function process( $body, $format ) {

    $data = [];
    if( $body ) try {

      switch( $format ) {
        // handle multipart messages
        case 'multipart/form-data':

          // temporary data storages
          $raw = [];

          // TODO extract and use the boundary from the content-type

          // process the multipart data into the raw containers (to process the array names later)
          $multipart = new Multipart( $body->getResource() );
          foreach( $multipart->data as $value ) {
            if( isset( $value->meta[ 'content-disposition' ][ 'filename' ] ) ) {

              $tmp = [
                'name'     => $value->meta[ 'content-disposition' ][ 'filename' ],
                'type'     => isset( $value->meta[ 'content-type' ][ 'value' ] ) ? $value->meta[ 'content-type' ][ 'value' ] : '',
                'size'     => null,
                'tmp_name' => null,
                'error'    => UPLOAD_ERR_OK
              ];

              // setup content related values 
              if( is_resource( $value->content ) ) {

                $tmp[ 'tmp_name' ] = stream_get_meta_data( $value->content )[ 'uri' ];
                $tmp[ 'size' ]     = $tmp[ 'tmp_name' ] && is_file( $tmp[ 'tmp_name' ] ) ? filesize( $tmp[ 'tmp_name' ] ) : 0;
              }

              // calculate file errors
              if( empty( $tmp[ 'tmp_name' ] ) ) $tmp[ 'error' ] = UPLOAD_ERR_CANT_WRITE;
              else if( empty( $tmp[ 'size' ] ) ) $tmp[ 'error' ] = UPLOAD_ERR_NO_FILE;
              else ; // FIXME maybe check the file size for overflow

              $raw[] = [
                'name'  => $value->meta[ 'content-disposition' ][ 'name' ],
                'value' => $tmp
              ];

            } else {
              $raw[] = [
                'name'  => $value->meta[ 'content-disposition' ][ 'name' ],
                'value' => $value->content
              ];
            }
          }

          // parse the post names
          if( count( $raw ) ) {

            $query = '';
            foreach( $raw as $key => $value ) {
              $query .= '&' . $value[ 'name' ] . '=' . urlencode( $key );
            }

            $keys = [];
            parse_str( substr( $query, 1 ), $keys );
            array_walk_recursive( $keys, function ( &$key ) use ( $raw ) {
              $key = $raw[ $key ][ 'value' ];
            } );

            $data += $keys;
          }

          break;

        // handle message like a query string
        case 'application/x-www-form-urlencoded':

          $tmp = (string) $body;
          parse_str( $tmp, $data );

          break;

        // handle json messages
        case 'application/json':
        case 'text/json':

          $tmp  = (string) $body;
          $data = Enumerable::fromJson( $tmp, true );

          break;

        // handle xml messages
        case 'application/xml':
        case 'text/xml':

          $tmp  = (string) $body;
          $data = Enumerable::fromXml( $tmp );

          break;
      }

    } catch( \Exception $e ) {
      Exception\Helper::wrap( $e )->log();
    }

    return $data;
  }

  /**
   * Recursive merge of two arrays. This is like the array_merge_recursive() without the strange array-creating thing
   *
   * @param array $destination
   * @param array $source
   *
   * @return array
   */
  private static function merge( array $destination, array $source ) {

    $result = $destination;
    foreach( $source as $key => &$value ) {
      if( !is_array( $value ) || !isset ( $result[ $key ] ) || !is_array( $result[ $key ] ) ) $result[ $key ] = $value;
      else $result[ $key ] = static::merge( $result[ $key ], $value );
    }

    return $result;
  }
  /**
   * Populate an empty input from a request object
   *
   * @param Message\RequestInterface $request
   * @param bool                     $native Add native PHP container values
   *
   * @return static
   */
  public static function instance( Message\RequestInterface $request, $native = false ) {

    $tmp = $request->getHeader( 'content-type' );
    list( $tmp ) = explode( ';', is_array( $tmp ) ? implode( ';', $tmp ) : $tmp );
    $instance = new static( $request->getUri(), $request->getBody(), $tmp );

    // handle $_POST and $_FILES data
    if( $native ) {

      // preprocess native inputs
      $data = empty( $_POST ) ? [] : $_POST;
      if( !empty( $_FILES ) ) {

        /**
         * Recursive container filling helper (for the $_FILES processing)
         *
         * @param array  $container The container that will hold the value
         * @param mixed  $value     The actual value
         * @param string $name      The name of the property
         */
        function helper( &$container, &$value, $name ) {

          if( !is_array( $value ) ) $container[ $name ] = $value;
          else foreach( $value as $i => $v ) {

            if( !isset( $container[ $i ] ) ) $container[ $i ] = [];
            helper( $container[ $i ], $v, $name );
          }
        }

        // walk the files
        foreach( $_FILES as $index => $value ) {
          if( !is_array( $value[ 'name' ] ) ) $data[ $index ] = $value;
          else {

            if( !is_array( $data[ $index ] ) ) $data[ $index ] = [];
            foreach( $value as $property => $container ) {
              helper( $data[ $index ], $container, $property );
            }
          }
        }
      }

      // extend the instance body content
      if( !empty( $data ) ) {

        $data = static::merge( $data, $instance->getArray( static::NAMESPACE_BODY . ':' ) );
        $instance->set( static::NAMESPACE_BODY . ':', $data );
        $instance->set( static::NAMESPACE_REQUEST . ':', static::merge( $instance->getArray( static::NAMESPACE_URI . ':' ), $data ) );
      }
    }

    return $instance;
  }
}
