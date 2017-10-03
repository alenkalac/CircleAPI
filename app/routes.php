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

    $app->get("/request/transaction", path("RouteController", "requestTransaction"));

    $app->get("/transaction/{tid}", path("RouteController", "processTransaction"));
    
    $app->get("/fetch/requests", path("RouteController", "fetchTransactions"));

    $app->get("/test", path("RouteController", "test"));
