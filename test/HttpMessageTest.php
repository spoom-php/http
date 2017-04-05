<?php namespace Spoom\Http;

use PHPUnit\Framework\TestCase;
use Spoom\Http\Helper\Uri;
use Spoom\Framework\Helper;

class HttpMessageTest extends TestCase {

  /**
   * @dataProvider providerBasic
   *
   * @param Message $message
   */
  public function testBasic( Message $message ) {

    // test simple body set (just to check the validation)
    $stream = Helper\Stream::instance( fopen( 'php://memory', 'w+b' ) );
    $message->setBody( $stream );
    $this->assertEquals( $stream, $message->getBody() );
    $this->assertNull( $message->setBody( null )->getBody() );

    // check basic header manipulations
    $header = [ 'Content-Type' => 'text/plain', 'length' => 0 ];
    $message->setHeader( $header );
    $this->assertEquals( $header, $message->getHeader() );

    $message->setHeader( gmdate( Message::DATE_FORMAT ), 'Expires' );
    $this->assertEquals( gmdate( Message::DATE_FORMAT ), $message->getHeader( 'expires' ) );
    $this->assertEquals( $header + [ 'Expires' => gmdate( Message::DATE_FORMAT ) ], $message->getHeader() );

    $message->setHeader( null, 'expires' );
    $this->assertEquals( $header, $message->getHeader() );

    $message->setHeader( 'text/html', 'Accept' )
            ->setHeader( [ 'text/plain', 'text/json' ], 'accept', true );
    $this->assertTrue( isset( $message->getHeader()[ 'Accept' ] ) );
    $this->assertEquals( [ 'text/html', 'text/plain', 'text/json' ], $message->getHeader( 'accept' ) );
    $message->setHeader( 'text/json', 'accept' );
    $this->assertEquals( 'text/json', $message->getHeader( 'accept' ) );
  }

  /**
   * @depends testBasic
   */
  public function testRequest() {

    // fill the test object
    $uri     = Uri::instance( 'http://example.com/request/index.php?q=test2' );
    $request = new Message\Request( Uri::instance( 'http://example.com/request/index.php?q=test' ) );
    $request->setHeader( [
      'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
      'Accept-Encoding' => 'gzip, deflate, sdch, br',
      'Cookie'          => 'nick=test; pass=test-pass; PHPSESSID=sjq0mjt36tj8jkgra6hi66jtd2; __utma=82829833.1949765084.1452953918.1474146891.1474481449.88; __utmz=82829833.1461696043.39.2.utmcsr=google|utmccn=(organic)|utmcmd=organic|utmctr=(not%20provided)'
    ] );

    // check standard access and modifications
    $request->setMethod( Message\RequestInterface::METHOD_POST );
    $this->assertEquals( Message\RequestInterface::METHOD_POST, $request->getMethod() );
    $request->setUri( $uri );
    $this->assertEquals( $uri, $request->getUri() );

    // check for cookie access
    $this->assertEquals( 'sjq0mjt36tj8jkgra6hi66jtd2', $request->getCookie( 'PHPSESSID' ) );
    $this->assertEquals( [
      'nick'      => 'test',
      'pass'      => 'test-pass',
      'PHPSESSID' => 'sjq0mjt36tj8jkgra6hi66jtd2',
      '__utma'    => '82829833.1949765084.1452953918.1474146891.1474481449.88',
      '__utmz'    => '82829833.1461696043.39.2.utmcsr=google|utmccn=(organic)|utmcmd=organic|utmctr=(not%20provided)'
    ], $request->getCookie() );

    // TODO test the ->write method after implementation
  }
  /**
   * @depends testBasic
   */
  public function testResponse() {

    $response = new Message\Response();

    // test basic access and modification
    $this->assertEquals( "OK", $response->getReason() );
    $response->setReason( "Test" );
    $this->assertEquals( "Test", $response->getReason() );
    $response->setStatus( Message\ResponseInterface::STATUS_BAD );
    $this->assertEquals( Message\ResponseInterface::STATUS_BAD, $response->getStatus() );

    // test cookie modification
    $date_now  = gmdate( Message::DATE_FORMAT );
    $date_next = gmdate( Message::DATE_FORMAT, time() + 60 * 60 );
    $response->setCookie( 'test', 1, $date_now );
    $this->assertEquals( 'test=1; expires=' . $date_now, $response->getHeader( 'set-cookie' ) );
    $response->setCookie( 'test2', 2, $date_next, [ 'path' => '/' ] );
    $this->assertEquals( [
      'test=1; expires=' . $date_now,
      'test2=2; path=/; expires=' . $date_next
    ], $response->getHeader( 'set-cookie' ) );
    $response->setCookie( 'test', null );
    $this->assertEquals( 'test2=2; path=/; expires=' . $date_next, $response->getHeader( 'set-cookie' ) );

    // TODO test the ->write method after implementation
  }

  // TODO create tests to cover specific responses (file and redirect) 

  /**
   * @return array
   */
  public function providerBasic() {
    return [
      [ new Message\Request( Message\Request::METHOD_GET ) ],
      [ new Message\Response() ]
    ];
  }
}
