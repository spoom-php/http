<?php require '../vendor/autoload.php';

use Spoom\Framework;
use Spoom\Framework\Application;
use Spoom\Framework\File;

// setup the Spoom application 
$spoom = new Application(
  Application::ENVIRONMENT_DEVELOPMENT,
  'en',
  ( $tmp = new File( __DIR__ ) ),
  new Framework\Log( $tmp->get( 'tmp/' ), 'unittest', Application::SEVERITY_DEBUG )
);
