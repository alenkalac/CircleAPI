<?php 

    require_once "../vendor/autoload.php";

    $app = new Silex\Application();
    $app['debug'] = true;

    $app->register(new Silex\Provider\SessionServiceProvider());
    
    $app->register(new Silex\Provider\DoctrineServiceProvider(), array(
        'db.options' 	=> [
            'driver' 	=> 'pdo_mysql',
            'host' 		=> DBHOST,
            'dbname' 	=> DBNAME,
            'user' 		=> DBUSER,
            'password' 	=> DBPASS,
            'charset' 	=> 'utf8',
            'port'		=> '3307'
        ]
    ));

    require_once "routes.php";

    $app->run();