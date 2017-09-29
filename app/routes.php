<?php 
    use GuzzleHttp\Client;
    use GuzzleHttp\Psr7\Request;
    use GuzzleHttp\Cookie\FileCookieJar;

    function path($className, $functionName) {
		return "capi\controller\\$className::$functionName";
    }
    
    $app->get("/", function(){
        return "Home Page";
    });

    $app->get("/login", path("RouteController","login"));

    $app->get("/requests/last", path("RouteController", "getLastIndex"));

    $app->get("/transaction/{transactionID}", path("RouteController", "transactionInfo"));
