<?php
/**
* @author Alen Kalac
* This is an API Designed to work with Circle Pay using their website and all the
* API Calls that are publically visible through the use of Chrome/Firefox.
*/


namespace capi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use OTPHP\TOTP;

class CapiCurrency {
    const USD = "USD";
    const GBP = "GBP";
    const EUR = "EUR";
    const BTC = "BTC";

    public static function getCurrencySign($currency) {
        if($currancy == \capi\CapiCurrency::USD) return "$";
        else if($currancy == \capi\CapiCurrency::EUR) return "€";
        else if($currancy == \capi\CapiCurrency::GBP) return "£";
    }
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
    private $currency = "USD"; //default

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

        $accountCurrency = $data->response->customer->baseCurrencyCode;
        $this->setCurrency($accountCurrency);

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
     * Sets the currancy to work with, this is account specific and can only be changed from the settings 
     * page on circle.com using the converter.
     *
     * @param string $currency default USD, available options USD/EUR/BTC
     * @return void
     */
    public function setCurrency($currency) {
        $this->currency = $currency;
    }

    /**
     * Getter for currency
     *
     * @return string
     */
    public function getCurrency() {
        return $this->currency;
    }

    /**
     * Polls the account, all account info can be gathered from the returned array
     *
     * @return array
     *      Returns an array of all the account information. 
     */
    public function poll() {
        $options = ["headers" => $this->getHeaders("GET"), 'verify' => false];

        try {
            $result = $this->client->request("GET", "/api/v2/customers/{$this->getUserID()}?polling=true", $options);

            $data = json_decode($result->getBody()->getContents());

            return $data;
        }catch(ClientException $ex) {
            return false;
        }
        
    }

    public function validateToken() {
        $data = $this->poll();
        if($data == false) return false;
        return $data->response->status->code == 0;
    }

    /**
     * Figures out the recipient type, phone or email
     *
     * @param string $to
     *      A user you want to send/request money to/from.
     * @return string email or phone depending on the input
     */
    private function getRecipientType($to) {
        if(filter_var($to, FILTER_VALIDATE_EMAIL))
            $type = "email";
        else 
            $type = "phone";
        return $type;
    }

    public function getAvailableBalance() {
        $data = $this->poll();

        $balance = $data->response->customer->accounts[0]->availableBalance;
        $balance = $balance / 100;
        $balance = round($balance, 2);

        return $balance;
    }

    /**
     * Request money from anyone using their phone number or email address. 
     *
     * @param string $from
     *      From whom to request money from. eg: +353xxxxxxxx or someone@something.com
     * @param double $amount
     *      Float value amount to request, eg: 1.20
     * @param string $currancy
     *      Currancy to request, USD/EUR/BTC
     * @param string $message
     *      Message to send with the request
     * @return array
     */
    public function requestCash($from, $amount, $currancy, $message = "") {

        $amount = round($amount, 2);
        $amount = $amount * 100;
        $url = "/api/v4/customers/{$this->getUserID()}/accounts/{$this->getAccountID()}/requests";

        $bodyArray = [
            "paymentRequest" => [
                "recipientType" => $this->getRecipientType($from),
                "recipientValue" => $from,
                "amount" => $amount,
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

    /**
     * Send Cash from your account to another user using a phone number or email address of the recipient
     *
     * @param string $to
     * @param string $toCurrency
     * @param float $amount
     * @param string $message
     * @return array response
     */
    public function sendCash($to, $toCurrency, $amount, $message = "") {
        $url = "/api/v4/customers/{$this->getUserID()}/accounts/{$this->getAccountID()}/spends";

        $amount = $this->formatMoney($amount);

        $exchangeData = $this->convertCurrency($this->getCurrency(), $toCurrency, $amount);

        $rate = $exchangeData->response->quote->rate;
        $amount = $exchangeData->response->quote->amount;
        $dAmount = $exchangeData->response->quote->determinedAmount;

        $pin = $this->getOTP($this->label, $this->secret);
        
        $bodyArray = [
            "mfaPin" => $pin,
            "spend" => [
                "message" => $message, 
                "quote" => [
                    "fromCurrency" => $this->getCurrency(),
                    "toCurrency" => $toCurrency,
                    "rate" => $rate,
                    "amount" => $amount,
                    "amountCurrency" => $toCurrency,
                    "determinedAmountCurrency" => $this->getCurrency(),
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

        return $data;
    }

    /**
     * Gets the transaction details
     *
     * @param string $transactionID
     * @return array
     */
    public function getTransactionDetails($transactionID) {
        $url = "/api/v4/customers/{$this->getUserID()}/activities/{$transactionID}";

        $options = $options = ["headers" => $this->getHeaders("GET"), 'verify' => false];

        $result = $this->client->request("GET", $url, $options);
        $data = json_decode($result->getBody()->getContents());
        
        return $data;
    }

    /**
     * Converts an amount between two currencies
     *
     * @param string $from 
     *      From currency, USD/EUR/BTC
     * @param string $to
     *      To currency, USD/EUR/BTC
     * @param float $amount
     *      Amount to convert
     * @return array response
     */
    public function convertCurrency($from, $to, $amount) {
        $amount = $amount * 100;

        $url = "/api/v4/customers/{$this->getUserID()}/quote/{$from}/{$to}/{$to}/{$amount}";
        $options = ["headers" => $this->getHeaders("GET"), 'verify' => false];
        $result = $this->client->request("GET", $url, $options);
        $data = json_decode($result->getBody()->getContents());
        
        return $data;
    }

    /**
     * Reusable helper function that returns the header for this to work. 
     * 
     * @param string $method
     * @param boolean $withToken
     *      default set to true, it will provide the login token,
     *      if set to false it will leave out the access token, only used for login step.
     * @return array
     */
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

    /**
     * Undocumented function
     *
     * @param [type] $amount
     * @return void
     */
    private function formatMoney($amount) {
        $amount = round($amount, 2);
        $amount * 100; //Circle uses cents as value, $2.50 = 250c

        return $amount;
    }

    public function setToken($token) {
        $this->token = $token;
    }

    public function setAccountID($accountId) {
        $this->accountID = $accountId;
    }

    public function setUserID($userid) {
        $this->userID = $userid;
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