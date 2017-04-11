# Spoom Http
Default HTTP handler for the Spoom Framework. Provide an unified interface (`MessageInterface`) for requests and responses, an `Application` to handle HTTP
calls and other HTTP related helper like: `Helper\UriInterface`, `Converter\Query` and `Converter\Multipart`

## Installation
Install the latest version with

```bash
$ composer require spoom-php/http
```

## Usage
Here is a basic `index.php` file, for HTTP request handling:

```php
<?php 

use Spoom\Http;

// create HTTP application
$spoom = new Http\Application( ... );

// run the application with the default request object
$response = $spoom( $request = Http\Application::getRequest(), function( $input, $request, $uri ) {
  
  // handle stuffs..
  
  // create a response for the request
  return (new Http\Message\Response())->setStatus( Http\Message\Response::STATUS_CONTENT_EMPTY );
});

// send the response as a result for PHP request
Http\Application::response( $response, $request );
```
