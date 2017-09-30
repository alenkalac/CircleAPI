<?php 
namespace capi;

use GuzzleHttp\Client;
use OTPHP\TOTP;

class CircleAPI {

    private $client;
    private $email;
    private $password;
    private $label;
    private $secret;
    private $token = null;
    private $userID = null;
    private $accountID = null;

    private static $instance = null;

    /**
    *
    */
    private function __construct($email, $password, $label, $secret) {
        $this->email = $email;
        $this->password = $password;
        $this->label = $label;
        $this->secret = $secret;

        $this->client = new Client([
            'base_uri' => 'https://api.circle.com/',
            'timeout'  => 2.0,
        ]);
    }

    public static function getInstance($email = "", $password = "", $label = "", $secret = "") {
        if(self::$instance == null) {
            self::$instance = new CircleAPI($email, $password, $label, $secret);
        }

        return self::$instance;
    }

    /**
    * returns a token
    */
    public function login($autoMFA = false) {
        $headers = [
            "Content-Type" => "application/json",
            "x-app-id" => "angularjs",
        ];

        $body = json_encode([
            "email" => $this->email, 
            "password" => $this->password
        ]);
        
        $options = ["headers" => $headers, 'verify' => false, 'body' => $body];

        $result = $this->client->request("POST", "/api/v2/customers/0/sessions", $options);

        if($result->getStatusCode() != 200)
            throw new Exception("Failed to log into Circle, Check your email and password");

        $data = json_decode($result->getBody()->getContents());
        $id = $data->response->sessionToken->customerId;
        $token = $data->response->sessionToken->value;

        $this->userID = $id;
        $this->token = $token;

        if($autoMFA) {
            return $this->mfaAuthenticate();
        }

        return $data;
    }

    private function getOTP($label, $secret) {
        $totp = new TOTP($label, $secret);
        return $totp->now();
    }

    public function mfaAuthenticate($trusted = true) {
        $headers = [
            "Content-Type" => "application/json",
            "x-customer-session-token" => $this->token,
            "x-customer-id" => $this->userID,
        ];

        $pin = $this->getOTP($this->label, $this->secret);

        $bodyArray = [
            "action" => "signin", 
            "mfaPin" => $pin,
            "trusted" => $trusted
        ];

        $body = json_encode($bodyArray);
        $options = ["headers" => $headers, 'verify' => false, 'body' => $body];

        $result = $this->client->request("PUT", "/api/v2/customers/{$this->userID}/mfa", $options);

        if($result->getStatusCode() != "200")
            throw new Exception("Failed to authenticate using MultiFactorAuth");

        $data = json_decode($result->getBody()->getContents());

        $this->accountID = $data->response->customer->accounts[0]->id;

        return $data;
    }

    public function logout() {
        $headers = [
            "Content-Type" => "application/json",
            "x-customer-session-token" => $this->token,
            "x-customer-id" => $this->userID,
        ];

        $options = ["headers" => $headers, 'verify' => false];

        $result = $this->client->request("DELETE", "/api/v2/customers/0/sessions", $options);
    }

    public function poll() {
        $headers = [
            "x-customer-session-token" => $this->token,
            "x-customer-id" => $this->userID,
        ];

        $options = ["headers" => $headers, 'verify' => false];

        $result = $this->client->request("GET", "/api/v2/customers/0/sessions", $options);

        $data = json_decode($result->getBody()->getContents());

        return $data;
    }



    //GETTERS/SETTERS
    public function getToken() {
        return $this->token;
    }

    public function getUserID() {
        return $this->userID;
    }

    public function getAccountID() {
        return $this->accountID;
    }



}
?>