# Spoom Framework
Spoom is a collection of cooperative libraries (extensions), which you can use to "build" a framework that suits your needs.

## About the Http
Default HTTP handler for the Spoom Framework. Provide an unified interface (`MessageInterface`) for requests and responses, an `Application` to handle HTTP
calls and other HTTP related helper like: `Helper\UriInterface`, `Converter\Query` and `Converter\Multipart`

## Installation
Install the latest version with

```bash
$ composer require spoom-php/http
```

## Usage
Here is a 'hello world' example, for HTTP request handling:

```php
<?php require __DIR__ . '/vendor/autoload.php';

use Spoom\Http;
use Spoom\Http\Message;
use Spoom\Core\Helper\Stream;
use Spoom\Core\File;

// create HTTP application
$spoom = new Http\Application(
  
  // used environment's name
  Http\Application::ENVIRONMENT_DEVELOPMENT,
                                        
  // default localization
  'en',
  
  // root directory of the application
  new File( __DIR__ )
);

// run the application with the default request object
$request = Http\Application::getRequest();
$response = $spoom( $request, function( $input, $request, $uri ) {
  
  // handle stuffs..
  
  // create response body and write a nice message to it
  $stream = new Stream( 'php://memory', Stream::MODE_RW );
  $stream->write( '<h1>Hello World!</h1>' );
  
  // create a response for the request
  return new Message\Response( $stream->seek( 0 ) );
});

// send the response as a result for PHP request
Http\Application::response( $response, $request );
```

## License
The Spoom Framework is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).
