<?php

class HttpHelperTest extends PHPUnit_Framework_TestCase {

  private static $directory;

  public static function setUpBeforeClass() {

    static::$directory = _PATH_BASE . 'extension/http/.test/HttpHelperTest/';
    @file_put_contents( static::$directory . 'stream-a.txt', '01234' );
    @file_put_contents( static::$directory . 'stream-r.txt', '01234' );
  }
  public static function tearDownAfterClass() {
    @unlink( static::$directory . 'stream-rw.txt' );
  }

  public function testStream() {

    //
    $rw = \Http\Helper\Stream::instance( fopen( static::$directory . 'stream-rw.txt', 'w+' ) );
    $this->assertEquals( 0, $rw->count() );
    $this->assertTrue( $rw->isReadable() && $rw->isWritable() && $rw->isSeekable() );

    // check basic read/write operations
    $rw->write( '0123--6789' );
    $this->assertEquals( 10, $rw->count() );
    $rw->seek( 4 );
    $this->assertEquals( 4, $rw->getOffset() );
    $rw->write( '45' );
    $this->assertEquals( '0123456789', $rw->read( 0, 0 ) );

    // test write from stream
    $a = \Http\Helper\Stream::instance( fopen( static::$directory . 'stream-a.txt', 'a+' ) );
    $this->assertTrue( $a->isWritable() && $a->isReadable() && $a->isSeekable() );

    $a->write( $rw->seek( 0 ) );
    $this->assertEquals( 15, $a->count() );
    $this->assertEquals( '012340123456789', $a->read( 0, 0 ) );

    // check read only and read to stream
    $r = \Http\Helper\Stream::instance( fopen( static::$directory . 'stream-r.txt', 'r' ) );
    $this->assertTrue( !$r->isWritable() && $r->isReadable() && $r->isSeekable() );

    $this->assertEquals( '01234', $r->read( 0, 0 ) );
    $r->read( 0, 0, $rw->seek( 5 ) );
    $this->assertEquals( '0123401234', $rw->read( 0, 0 ) );
  }
  public function testMultipart() {
    // TODO implement
  }

  /**
   * @dataProvider providerUri
   *
   * @param $uri
   * @param $components
   */
  public function testUri( $uri, $components ) {

    $this->assertEquals( $components, \Http\Helper\Uri::instance( $uri )->component );
    $this->assertEquals( $uri, (string) \Http\Helper\Uri::instance( $uri ) );
  }

  public function providerUri() {
    return [
      [ 'https://test-cloud-pln.pbcs.us1.oraclecloud.com/workspace', [
        'query'  => [],
        'scheme' => 'https',
        'host'   => 'test-cloud-pln.pbcs.us1.oraclecloud.com',
        'path'   => '/workspace'
      ]
      ],
      [
        'http://www.example.com/path?section=17', [
        'query'  => [
          'section' => '17'
        ],
        'scheme' => 'http',
        'host'   => 'www.example.com',
        'path'   => '/path'
      ]
      ], [
        'http://www.example.com:8080/path?section=17', [
          'query'  => [
            'section' => '17'
          ],
          'port'   => 8080,
          'scheme' => 'http',
          'host'   => 'www.example.com',
          'path'   => '/path'
        ]
      ], [ 'ftp://username:password@example.com:9242', [
        'query'    => [],
        'scheme'   => 'ftp',
        'port'     => 9242,
        'user'     => 'username',
        'password' => 'password',
        'host'     => 'example.com'
      ]
      ], [ '//username:password@example.com:9242', [
        'query'    => [],
        'port'     => 9242,
        'user'     => 'username',
        'password' => 'password',
        'host'     => 'example.com'
      ]
      ], [ '//username:password@example.com:9242/?foo=400', [
        'query'    => [
          'foo' => 400
        ],
        'port'     => 9242,
        'user'     => 'username',
        'password' => 'password',
        'host'     => 'example.com',
        'path'     => '/'
      ]
      ]
    ];
  }
}
