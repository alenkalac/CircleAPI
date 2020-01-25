<?php 
namespace capi\controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use GuzzleHttp\Client;
use capi\CircleAPI;
use capi\CapiCurrency;
use OTPHP\TOTP;

class RouteController {

    const MIN_SPENDS = 25;
    const REFERENCE_STARTING_ID = 1450;

    function test(Request $r, Application $app) {
       print_r(password_hash("bbqpizza00", PASSWORD_DEFAULT));
       die();
    }

    function test2(Request $r, Application $app) {
        $capi = $app['capi'];

        $capi->login(true);
        print_r("<pre>");
        print_r($capi->poll());
        die();
    }

    function register(Request $r, Application $app) {

        $err = false;
        $fail = $app->json(["error" => "INVALID_REQUEST"], 401);
        $usernameTaken = $app->json(["error" => "USERNAME_TAKEN"], 401);
        $invalidEmailFormat = $app->json(["error" => "INVALID_EMAIL_FORMAT"], 401);
        
        $email = $r->headers->get("app-user");
        $pass = $r->headers->get("app-pass");

        if(filter_var($email, FILTER_VALIDATE_EMAIL) == false)
           return $invalidEmailFormat;

        if($email == "" || $email == null || empty($email))
            $err = true;
        if($pass == "" || $pass == null || empty($pass))
            $err = true;
        
        if($err == true)
            return $fail;

        //CHECK IF USERNAME EXISTS / EMAIL
        $query = $app['db']->prepare("SELECT * FROM users WHERE email = :USERNAME");
        $query->execute([
            "USERNAME" => $email
        ]);

        $result = $query->rowCount();

        if($result > 0)
            return $usernameTaken;

        $hashed_password = password_hash($pass, PASSWORD_DEFAULT);

        $query = $app['db']->prepare("INSERT INTO users(id, phone, email, password) VALUES (NULL, '', :EMAIL, :PASSWORD)");

        $result =  $query->execute([
            "EMAIL" => $email, 
            "PASSWORD" => $hashed_password,
        ]);

        return $app->json(["error" => "", "result" => $result], 200);
    }

    function login(Request $r, Application $app) {
        $err = false;
        $fail = $app->json(["error" => "INVALID_REQUEST"], 401);
        $wrongCradentials = $app->json(["error" => "WRONG_USERNAME_PASSWORD"], 401);
        $invalidEmailFormat = $app->json(["error" => "INVALID_EMAIL_FORMAT"], 401);

        $email = $r->headers->get("app-user");
        $pass = $r->headers->get("app-pass");

        if(filter_var($email, FILTER_VALIDATE_EMAIL) == false)
            return $invalidEmailFormat;

        if($email == "" || $email == null || empty($email))
            $err = true;
        if($pass == "" || $pass == null || empty($pass))
            $err = true;
        
        if($err == true)
            return $fail;

        $query = $app['db']->prepare("SELECT * FROM users WHERE email = :EMAIL");

        $query->execute([
            "EMAIL" => $email,
        ]);

        $result = $query->fetch(\PDO::FETCH_ASSOC);

        //validate user
        $hashed_password = $result['password'];

        if(password_verify($pass, $hashed_password) == true) {
            $token = bin2hex(random_bytes(16));
            $query = $app['db']->prepare("INSERT INTO auth_token (user_id, token, created_on, expires_on) VALUES(:USERID, :TOKEN, NOW(), NOW() + INTERVAL 1 DAY)");
            $query->execute([
                "USERID" => $result['id'],
                "TOKEN" => $token,
            ]);

            return $app->json(["error" => "", "auth_token" => $token], 200);
           
        }

        return $wrongCradentials;
    }

    function auth_user($r, $app) {
        $auth_token = $r->headers->get("App-Token");

        if($auth_token == "" || $auth_token == null || empty($auth_token) || strlen($auth_token) != 32 )
            return [];

        $query = $app['db']->prepare("SELECT * FROM auth_token 
                                        INNER JOIN users 
                                        ON auth_token.user_id = users.id
                                        INNER JOIN user_details
                                        on users.id = user_details.user_id
                                        WHERE token = :TOKEN 
                                        AND expires_on > NOW()");

        $query->execute([
            "TOKEN" => $auth_token
        ]);

        $count = $query->rowCount();

        if($count == 0)
            return false;

        return $query->fetch(\PDO::FETCH_ASSOC);
    }

    function auth_user_special($r, $app) {
        $user = $this->auth_user($r, $app);
        if(!isset($user['special'])) return false;
        if($user['special'] == 1) return true;
        return false;

    }

    //Normal user can requesta a transaction. 
    function requestTransaction(Request $r, Application $app) {
        $fail = $app->json(["error" => "INVALID_REQUEST"], 401);
        $invalid_token = $app->json(["error" => "INVALID_TOKEN"], 401);
        $invalid_permission = $app->json(["error" => "NOT_ALLOWED"], 401);

        //validate user
        $allowed = $this->auth_user($r, $app);
        if($allowed == false) 
            return $invalid_permission;

        $another = $r->get("tid");
        $value = $r->get("value");

        if($another != null) {
            $query = $app['db']->prepare("SELECT * FROM requests 
                                            INNER JOIN users 
                                            on users.id = requests.user_id 
                                            INNER JOIN user_details
                                            on users.id = user_details.user_id
                                            WHERE transaction_id = :TID 
                                            AND is_request = 1");

            $res = $query->execute([
                "TID" => $another
            ]);


            if($res == false)
                return $invalid_token;


            $result = $query->fetch(\PDO::FETCH_ASSOC); 
        }
        
        $uid = $result['user_id'];
        $email = $result['email'];
        $currency = $result['currency'];
        if($value == null)
            $value = $result['value'];
       

        $total = $this->checkCurrentUserSentValue($uid, $app);

        if($total >= $this::MIN_SPENDS)
            return $app->json(["error" => "MAX_VALUE_REACHED"]);

        //login to circlepay and request a token
        
        $capi = $app['capi'];
        $capi->login(true);
        $data = $capi->requestCash($email, $value, $currency, "Hello There");

        $tid = $data->response->paymentRequests[0];
        $capi->logout();

        //register request in DB
        //TODO: check response code. 

        $query = $app['db']->prepare("INSERT INTO requests (user_id, transaction_id, value, is_request, currency) 
                                        VALUES (:USERID, :TRANSACTIONID, :VALUE, 1, :CURRENCY)");
        $query->execute([
            "USERID" => $uid,
            "TRANSACTIONID" => $tid,
            "CURRENCY" => $currency,
            "VALUE" => $value
        ]);

        //return json response with transactionID
        return $app->json(["error" => "", "transaction" => $tid]);
    }

    private function doValidateToken($r, $app) {
        $capi = $app['capi'];
        $token = $r->get("token", "");
        $userid = $r->get("user_id", "");
        $accountid = $r->get("account_id", "");

        $capi->setToken($token);
        $capi->setUserID($userid);
        $capi->setAccountID($accountid);

        if(empty($token)) {
            $capi->login(true);
        }
        else if($capi->validateToken() == false) {
            $capi->login(true);
        }
        return $capi;
    }

    private function getTransactionInfo($tid, $r, $app) {
        $capi = $this->doValidateToken($r, $app);

        $data = $capi->getTransactionDetails($tid);

        $result = [
            "data" => $data,
            "session" => $capi->getToken(),
        ];

        return $result;
    }

    function session(Request $r, Application $app) {

        if($this->auth_user_special($r, $app) == false) 
            return $app->json([], 401);
        
        $capi = $app['capi'];
        $capi->login(true);

        return $app->json(["token" => $capi->getToken(), "user" => $capi->getUserID(), "account" => $capi->getAccountID()]);
    }

    function processTransaction(Request $r, Application $app) {

        if($this->auth_user_special($r, $app) == false) 
            return $app->json([], 401);

        $tid = $r->get("tid");

        //check database using transactionID
        $query = $app['db']->prepare("SELECT * FROM requests WHERE transaction_id = :ID");
        $query->execute([
            "ID" => $tid,
        ]);

        $result = $query->fetch(\PDO::FETCH_ASSOC);
        $uid = $result['user_id'];

        $count = $query->rowCount();
        if($count == 0) 
            return $app->json(["error" => "INVALID_TID"], 400);

        //parse json returned from API Call
        $data = $this->getTransactionInfo($tid, $r, $app);

        $state = $data['data']->response->activity->activityState;
        $session = $data['session'];

        //if completed, register completed

        //Default return object
        $returnObject = ["data" => $session, "error" => "", "message" => ""];

        if($state == "created") 
            $returnObject["error"] = "pending";
        else if($state == "denied" || $state == "canceled") {
            $returnObject["error"] = $state;
            $this->setRequestAsComplete($tid, $app, true);
        }
        else if($state == "complete") {
            if($result['is_request']) {

                $data = $this->returnTransaction($tid, $r, $app);

                if($this->checkCurrentUserSentValue($uid, $app) < $this::MIN_SPENDS)
                    $returnObject["message"] = "request";
                else
                    $returnObject["message"] = "complete";
            } 
        } 
        else 
            $returnObject["error"] = "INVALID_REQUEST";

        return $app->json($returnObject);
    }

    //NEEDS WORK!
    //TODO: AUTH USER
    private function checkCurrentUserSentValue($uid, $app) {
        $query = $app['db']->prepare("SELECT SUM(value) as total FROM requests WHERE user_id = :UID AND is_request = 1 AND complete = 1");
        $query->execute([
            "UID" => $uid,
        ]);

        $result = $query->fetch(\PDO::FETCH_ASSOC);
        return $result["total"];
    }

    private function setRequestAsComplete($tid, $app, $denied = false) {

        $state = ($denied == true) ? 2 : 1;

        $query = $app['db']->prepare("UPDATE requests SET complete = :STATUS, processed = 1 WHERE transaction_id = :TID");
        $query->execute([
            "STATUS" => $state,
            "TID" => $tid
        ]);
    }

    //check funds also
    //TODO: REFACTOR THIS FUNCTION...
    function returnTransaction($tid, $r, $app) {

        if($this->auth_user_special($r, $app) == false) 
            return $app->json([], 401);

        $query = $app['db']->prepare("SELECT * FROM requests 
                                        WHERE transaction_id = :TID
                                        AND is_request = 1");
        $query->execute([
            "TID" => $tid,
        ]);

        $count = $query->rowCount();
        if($count == 0) 
            return $app->json(["error" => "INVALID_TID", "message" => "INVALID_TID"]);

        $result = $query->fetch(\PDO::FETCH_ASSOC);
        $rid = $result['id'];
        $uid = $result['user_id'];

        //DONE: check returns so no duplicate refunds
        $query = $app['db']->prepare("SELECT * FROM returns WHERE request_id = :RID AND complete = 1");
        $query->execute([
            "RID" => $rid,
        ]);

        $returnsCount = $query->rowCount();
        if($returnsCount > 0) {
            $this->setRequestAsComplete($tid, $app);
            return $app->json(["error" => "INVALID_TID", "message" => "INVALID_TID"]);
        }

        $data = $this->getTransactionInfo($tid, $r, $app);
        $to = $data['data']->response->activity->otherCustomer->raw;

        $value = $data['data']->response->activity->primaryAmount->subunits;
        $value = round($value / 100, 2);
        $currency = $data['data']->response->activity->primaryAmount->currency;

        $refVal = $rid + $this::REFERENCE_STARTING_ID;
        $ref = "#" . $refVal;

        $this->insertReferenceNumberToDB($ref, $rid, $app);

        $sentSoFar = $this->checkCurrentUserSentValue($uid, $app) + $value;

        $min = $this::MIN_SPENDS;

        //issue a refund via API
        $capi = $app['capi'];
        $capi->login(true);
        $data = $capi->sendCash($to, $currency, $value, "Your Reference is: [$ref]\n\r{$sentSoFar}/{$min}");
        $capi->logout();

        $new_tid = $data->response->job->id;
        $complete = ($data->response->job->activityState == "complete") ? 1 : 0;

        //check for PENDING transaction i guess. wrong email / phone number?

        if($complete) {
            $this->setRequestAsComplete($tid, $app);

            $query = $app['db']->prepare("UPDATE returns SET transaction_id = :TID, complete = :COMPLETE WHERE reference = :REF");
            $query->execute([
                "REF" => $ref,
                "TID" => $new_tid,
                "COMPLETE" => $complete
            ]);
        }

        return $data;
    }

    private function insertReferenceNumberToDB($ref, $rid, $app) {
        $query = $app['db']->prepare("INSERT INTO returns (reference, request_id) VALUES (:REF, :RID)");
        $query->execute([
            "REF" => $ref,
            "RID" => $rid
        ]);

        return $query->rowCount();
    }

    function fetchTransactions(Request $r, Application $app) {

        if($this->auth_user_special($r, $app) == false) 
            return $app->json(null, 401);

        $last = $r->get("last_id", 0);

        $query = $app['db']->prepare("SELECT * FROM requests WHERE processed = 0 AND id > :LASTID");
        $query->execute([
            "LASTID" => $last,
        ]);

        $result = $query->fetchAll(\PDO::FETCH_ASSOC);

        return $app->json($result, 200);
    }

    function transactionInfo(Request $r, Application $app) {
        //check database using transactionID

        //parse json returned from API Call

        //
        return $r->get("transactionID");
        
    }
}
