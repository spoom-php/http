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
   * @inheritdoc
   */
  public function send() {

    if( !$this->_sent ) {

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

  /**
   * @return Url|string
   */
  public function getUrl() {
    return $this->_url;
  }
  /**
   * @param Url|string $value
   */
  public function setUrl( $value ) {
    $this->_url = $value !== null ? Url::instance( $value ) : null;
  }
}
