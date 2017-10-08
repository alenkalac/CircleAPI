<?php 
    function path($className, $functionName) {
		return "capi\controller\\$className::$functionName";
    }
    
    $app->get("/", function(){
        return "Home Page";
    });

    $app->post("/circle/login", path("RouteController","circleLogin"));

    $app->post("login", path("RouteController","login"));

    $app->post("/register", path("RouteController", "register"));

    $app->get("/requests/last", path("RouteController", "getLastIndex"));

    $app->get("/request/transaction/{value}", path("RouteController", "requestTransaction"));

    $app->post("/request/transaction", path("RouteController", "requestTransaction"));

    $app->post("/transaction", path("RouteController", "processTransaction"));
    
    $app->post("/fetch/requests", path("RouteController", "fetchTransactions"));

    $app->post("/test", path("RouteController", "test"));

    $app->get("/test2", path("RouteController", "test2"));

    $app->get("/session", path("RouteController", "session"));
