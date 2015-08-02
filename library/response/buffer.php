<?php namespace Http\Response;

use Framework\Exception;
use Http\Request;
use Http\Response;

/**
 * Class Buffer
 * @package Http\Response
 */
class Buffer extends Response {

  /**
   * Can't read/write or seek the buffer
   */
  const EXCEPTION_INVALID_BUFFER = 'http#3C';

  /**
   * The content buffer
   *
   * @var resource
   */
  protected $buffer;

  /**
   * @param Request       $request The request object
   * @param resource      $stream  The output stream. Default is the 'php://output'
   * @param resource|null $buffer  The buffer stream. If it's not a resource than a memory stream will be used
   */
  function __construct( Request $request, $stream = null, $buffer = null ) {
    parent::__construct( $request, $stream );

    $this->buffer = is_resource( $buffer ) ? $buffer : fopen( 'php://memory', 'w+' );
  }

  /**
   * Write new content to the buffer
   *
   * @param string $content The content to append
   * @param bool   $append  Clear the content buffer before add this new
   *
   * @return $this
   * @throws Exception\Strict
   */
  public function write( $content, $append = true ) {

    // check the buffer
    $buffer_info = is_resource( $this->buffer ) ? stream_get_meta_data( $this->buffer ) : null;
    if( !$buffer_info || !preg_match( '/(r\+|w\+?|a\+?|x\+?)/i', $buffer_info[ 'mode' ] ) || ( !$append && !$buffer_info[ 'seekable' ] ) ) {

      throw new Exception\Strict( self::EXCEPTION_INVALID_BUFFER, [ 'info' => $buffer_info ] );

    } else {

      if( !$append ) ftruncate( $this->buffer, 0 );
      fwrite( $this->buffer, $content );
    }

    return $this;
  }

  /**
   * @inheritdoc
   */
  public function send() {

    if( !$this->_sent ) {

      // send the response as default (headers)
      parent::send();

      // check the buffer
      $buffer_info = is_resource( $this->buffer ) ? stream_get_meta_data( $this->buffer ) : null;
      if( !$buffer_info || !preg_match( '/(r\+?|w\+|a\+|x\+)/i', $buffer_info[ 'mode' ] ) || !$buffer_info[ 'seekable' ] ) {

        throw new Exception\Strict( self::EXCEPTION_INVALID_BUFFER, [ 'info' => $buffer_info ] );

      } else {

        // rewind the buffer
        rewind( $this->buffer );

        // check the output stream
        $output_info = is_resource( $this->output ) ? stream_get_meta_data( $this->output ) : null;
        if( !$output_info ||
            !preg_match( '/(r\+|w\+?|a\+?|x\+?)/i', $output_info[ 'mode' ] )
        ) throw new Exception\Strict( self::EXCEPTION_INVALID_OUTPUT, [ 'info' => $output_info ] );
        else {

          // copy the buffer to the output
          stream_copy_to_stream( $this->buffer, $this->output );
          fflush( $this->output );

          @fclose( $this->buffer );
        }
      }
    }
  }
}
