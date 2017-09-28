<?php 

    require_once "../vendor/autoload.php";

    $app = new Silex\Application();
    $app['debug'] = true;

    require_once "routes.php";


    $app->run();