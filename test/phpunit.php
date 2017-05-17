<?php require __DIR__ . '/../vendor/autoload.php';

use Spoom\Core;
use Spoom\Core\Application;
use Spoom\Core\File;

// setup the Spoom application 
$spoom = new Application(
  Application::ENVIRONMENT_DEVELOPMENT,
  'en',
  ( $tmp = new File( __DIR__ ) ),
  new Core\Log( $tmp->get( 'tmp/' ), 'unittest', Application::SEVERITY_DEBUG )
);
