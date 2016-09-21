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

      $this->set( self::NAMESPACE_URI . ':', $uri->getQuery() );

      // FIXME handle $_POST data if the body is "empty"

      // choose processor based on the body format
      if( $body ) try {

        $data = [];
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

        $this->set( self::NAMESPACE_BODY . ':', $data );

      } catch( \Exception $e ) {

        // log any input parse exception
        Exception\Helper::wrap( $e )->log();
      }
    }

    // merge the "public" and "private" storages
    $this->set( 'request:', $this->getArray( self::NAMESPACE_URI . ':' ) + $this->getArray( self::NAMESPACE_URI . ':' ) );
  }

  /**
   * Populate an empty input from a request object
   *
   * @param Message\RequestInterface $request
   *
   * @return static
   */
  public static function instance( Message\RequestInterface $request ) {
    $tmp = $request->getHeader( 'content-type' );
    list( $tmp ) = explode( ';', is_array( $tmp ) ? implode( ';', $tmp ) : $tmp );
    return new static( $request->getUri(), $request->getBody(), $tmp );
  }
}
