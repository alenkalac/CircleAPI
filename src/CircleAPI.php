<?php
/**
* @author Alen Kalac
* This is an API Designed to work with Circle Pay using their website and all the
* API Calls that are publically visible through the use of Chrome/Firefox.
*/


namespace capi;

use GuzzleHttp\Client;
use OTPHP\TOTP;

class CapiCurrency {
    const USD = "USD";
    const GBP = "GBP";
    const EUR = "EUR";
    const BTC = "BTC";
}

/**
 * Undocumented class
 */
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
     * Constructor that sets everything up
     * 
     * @param string $email
     *      Email that is used for signing into Circle Pay.
     * @param string $password
     *      Password associated with the Circle Pay Account
     * @param string $label
     *      Label that can be aquired from the Circle Pay's Website by going to setting and enabling 2FA 
     *      and scanning the QR Code with a QR Reader application
     * @param string $secret
     *      Secret that can be aquired from the Circle Pay's Website by going to setting and enabling 2FA 
     *      and scanning the QR Code with a QR Reader application
     */
    private function __construct($email, $password, $label, $secret) {
        $this->email = $email;
        $this->password = $password;
        $this->label = $label;
        $this->secret = $secret;

        $this->client = new Client([
            'base_uri' => 'https://api.circle.com/',
            'timeout'  => 10.0,
        ]);
    }

    /**
     * Singleton function to get an instance, should be called only once with full details 
     * and any future calls can ignore all parameters. 
     *
     * @param string $email
     *      Email that is used for signing into Circle Pay.
     * @param string $password
     *      Password associated with the Circle Pay Account
     * @param string $label
     *      Label that can be aquired from the Circle Pay's Website by going to setting and enabling 2FA 
     *      and scanning the QR Code with a QR Reader application
     * @param string $secret
     *      Secret that can be aquired from the Circle Pay's Website by going to setting and enabling 2FA 
     *      and scanning the QR Code with a QR Reader application
     * @return CircleAPI 
     */
    public static function getInstance($email = "", $password = "", $label = "", $secret = "") {
        if(self::$instance == null) {
            self::$instance = new CircleAPI($email, $password, $label, $secret);
        }

        return self::$instance;
    }

    /**
     * Undocumented function
     *
     * @param boolean $autoMFA
     *      <strong>True</strong> to automatically login and authenticate using 2FA <br>
     *      <strong>False (default)</strong> to only log in and you will have to call mfaAuthenticate() manually
     *      to authenticate the login
     * @return array 
     *      Data that is returned by the server
     */
    public function login($autoMFA = false) {
        $body = json_encode([
            "email" => $this->email, 
            "password" => $this->password
        ]);
        
        $options = ["headers" => $this->getHeaders("POST", false), 'verify' => false, 'body' => $body];

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

    /**
     * Generates 2FA code that is used to authenticate at the login stage.
     *
     * @param string $label
     *      Label that can be aquired from the Circle Pay's Website by going to setting and enabling 2FA 
     *      and scanning the QR Code with a QR Reader application
     * @param string $secret
     *      Secret that can be aquired from the Circle Pay's Website by going to setting and enabling 2FA 
     *      and scanning the QR Code with a QR Reader application
     * @return string 
     *      2FA key code that changes every 30 seconds
     */
    private function getOTP($label, $secret) {
        $totp = new TOTP($label, $secret);
        return $totp->now();
    }

    /**
     * Generate 2FA key and logs into the account with a token that is now valid for use
     * for any other transactions
     *
     * @param boolean $trusted (optional)
     *      Haven't really seen any reason to change this to false
     * @return array
     *      Returns the server response body.
     */
    public function mfaAuthenticate($trusted = true) {
        $pin = $this->getOTP($this->label, $this->secret);

        $bodyArray = [
            "action" => "signin", 
            "mfaPin" => $pin,
            "trusted" => $trusted
        ];

        $body = json_encode($bodyArray);
        $options = ["headers" => $this->getHeaders("PUT"), 'verify' => false, 'body' => $body];

        $result = $this->client->request("PUT", "/api/v2/customers/{$this->userID}/mfa", $options);

        if($result->getStatusCode() != "200")
            throw new Exception("Failed to authenticate using MultiFactorAuth");

        $data = json_decode($result->getBody()->getContents());

        $this->accountID = $data->response->customer->accounts[0]->id;

        return $data;
    }

    /**
     * Kills the session token and makes it invalid
     *
     * @return void
     */
    public function logout() {
        $options = ["headers" => $this->getHeaders("DELETE"), 'verify' => false];

        $result = $this->client->request("DELETE", "/api/v2/customers/0/sessions", $options);
    }

    /**
     * Polls the account, all account info can be gathered from the returned array
     *
     * @return array
     *      Returns an array of all the account information. 
     */
    public function poll() {
        $options = ["headers" => $this->getHeaders("GET"), 'verify' => false];

        $result = $this->client->request("GET", "/api/v2/customers/{$this->getUserID()}?polling=true", $options);

        $data = json_decode($result->getBody()->getContents());

        return $data;
    }

    private function getRecipientType($to) {
        if(filter_var($to, FILTER_VALIDATE_EMAIL))
            $type = "email";
        else 
            $type = "phone";
        return $type;
    }

    /**
     * Undocumented function
     *
     * @param [type] $to
     * @param [type] $amount
     * @param [type] $currancy
     * @param [type] $message
     * @return void
     */
    public function requestCash($to, $amount, $currancy, $message) {
        $url = "/api/v4/customers/{$this->getUserID()}/accounts/{$this->getAccountID()}/requests";

        $bodyArray = [
            "paymentRequest" => [
                "recipientType" => $this->getRecipientType($to),
                "recipientValue" => $to,
                "amount" => $amount * 100,
                "amountCurrency" => strtoupper($currancy),
                "message" => $message

            ]
        ];

        $body = json_encode($bodyArray);
        $options = ["headers" => $this->getHeaders("POST"), 'verify' => false, 'body' => $body];

        $result = $this->client->request("POST", $url, $options);

        if($result->getStatusCode() != "200")
            throw new Exception("Failed to authenticate using MultiFactorAuth");

        $data = json_decode($result->getBody()->getContents());

        return $data;
        
    }

    public function sendCash($to, $fromCurrency, $toCurrency, $amount, $message = "") {
        $url = "/api/v4/customers/{$this->getUserID()}/accounts/{$this->getAccountID()}/spends";

        $exchangeData = $this->convertCurrency($fromCurrency, $toCurrency, $amount);

        $rate = $exchangeData->response->quote->rate;
        $amount = $exchangeData->response->quote->amount;
        $dAmount = $exchangeData->response->quote->determinedAmount;
        
        $bodyArray = [
            "spend" => [
                "message" => $message, 
                "quote" => [
                    "fromCurrency" => $fromCurrency,
                    "toCurrency" => $toCurrency,
                    "rate" => $rate,
                    "amount" => $amount,
                    "amountCurrency" => $toCurrency,
                    "determinedAmountCurrency" => $fromCurrency,
                    "determinedAmount" => $dAmount, 
                    "ttl" => 300000,
                ],
                "recipientValue" => $to,
                "recipientType" => $this->getRecipientType($to)
            ]
        ];

        $body = json_encode($bodyArray);

        $options = ["headers" => $this->getHeaders("POST"), 'verify' => false, 'body' => $body];
        
        $result = $this->client->request("POST", $url, $options);

        $data = json_decode($result->getBody()->getContents());

        //TODO: save transaction to database
        
        return $data;
    }

    public function convertCurrency($from, $to, $amount) {
        $amount = $amount * 100;

        $url = "/api/v4/customers/{$this->getUserID()}/quote/{$from}/{$to}/{$to}/{$amount}";
        $options = ["headers" => $this->getHeaders("GET"), 'verify' => false];
        $result = $this->client->request("GET", $url, $options);
        $data = json_decode($result->getBody()->getContents());
        
        return $data;
    }

    private function getHeaders($method, $withToken = true) {
        $header = [ 
            "x-app-id" => "angularjs",
        ];
        if(strtoupper($method) != "GET") {
            $header["Content-Type"] = "application/json";
        }
        if($withToken) {
            $header["x-customer-session-token"] = $this->token;
            $header["x-customer-id"] = $this->userID;
        }

        return $header;
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