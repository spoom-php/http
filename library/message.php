<?php namespace Http;

use Http\Helper\StreamInterface;

interface MessageInterface {

  /**
   * @return string
   */
  public function getVersion();
  /**
   * @param string $value
   *
   * @return static
   */
  public function setVersion( $value );

  /**
   * @param string $name
   * @param bool   $join
   *
   * @return
   */
  public function getHeader( $name = null, $join = false );
  /**
   * @param string $name
   * @param mixed  $value
   * @param bool   $append
   *
   * @return static
   */
  public function setHeader( $value, $name = null, $append = false );

  /**
   * @return StreamInterface
   */
  public function getBody();
  /**
   * @param StreamInterface $value
   *
   * @return static
   */
  public function setBody( $value );
}
