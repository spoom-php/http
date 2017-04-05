<?php namespace Spoom\Http;

use PHPUnit\Framework\TestCase;
use Spoom\Http\Helper;

class HttpHelperTest extends TestCase {

  /**
   * @dataProvider providerUri
   *
   * @param $uri
   * @param $components
   */
  public function testUri( $uri, $components ) {

    $this->assertEquals( $components, Helper\Uri::instance( $uri )->component );
    $this->assertEquals( $uri, (string) Helper\Uri::instance( $uri ) );
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
