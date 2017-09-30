<?php 

    require_once "../vendor/autoload.php";
    require_once "config.php";

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

    $app["capi"] = function() use ($app) {
        $query = $app['db']->prepare("SELECT * FROM settings");
        $query->execute();
        $result = $query->fetch(\PDO::FETCH_ASSOC);

        return capi\CircleAPI::getInstance(
            $result['capi_email'], 
            $result['capi_password'], 
            $result["secret_label"], 
            $result['secret_key']
        );
    };


    require_once "routes.php";

    $app->run();