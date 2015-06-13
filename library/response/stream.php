<?php namespace Http\Response;

use Framework\Exception;
use Http\Response;

/**
 * Class Stream
 * @package Http\Response
 */
class Stream extends Response {

  /**
   * Rewrite operation attempt
   */
  const EXCEPTION_INVALID_WRITE = 'http#0W';

  /**
   * Write new content to the buffer
   *
   * @param string $content The content
   * @param bool   $append  This MUST be true or an exception thrown
   *
   * @return $this
   * @throws Exception\Strict
   */
  public function write( $content, $append = true ) {

    if( !$append ) throw new Exception\Strict( self::EXCEPTION_INVALID_WRITE );
    else {

      // send the header on the first write
      if( !$this->_sent ) $this->send();

      // check the output stream
      $output_info = is_resource( $this->output ) ? stream_get_meta_data( $this->output ) : null;
      if( !$output_info || strpos( $output_info[ 'mode' ], 'w' ) === false ) {
        throw new Exception\Strict( self::EXCEPTION_INVALID_OUTPUT, [ 'info' => $output_info ] );
      } else {

        // write the content directly to the output
        fwrite( $this->output, $content );
        fflush( $this->output );

        return $this;
      }
    }
  }
}
