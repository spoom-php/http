<?php namespace Http\Response;

use Framework\Exception;
use Http\Response;
use Http\Url;

/**
 * Class Redirect
 * @package Http\Response
 */
class Redirect extends Buffer {

  /**
   * The URL is not valid
   */
  const EXCEPTION_INVALID_URL = 'http#4C';

  /**
   * The URL to redirect on send
   *
   * @var string|Url
   */
  protected $_url;

  /**
   * @param string $name
   * @param mixed  $value
   */
  function __set( $name, $value ) {

    switch( $name ) {
      case 'url':
        $this->_url = $value !== null ? Url::instance( $value ) : null;
        break;
      default:
        parent::__set( $name, $value );
    }
  }

  /**
   * @inheritdoc
   */
  public function send() {

    if( !$this->_url ) throw new Exception\Strict( self::EXCEPTION_INVALID_URL );
    else {

      // setup the redirect location
      $this->_header->set( 'location', (string) $this->_url );

      // setup the default 'See Other' status code if there is no other
      if( empty( $this->_status ) ) $this->_status = static::STATUS_OTHER;

      // send the response like normal
      parent::send();
    }
  }
}
