<?php

require_once(dirname(__FILE__) . DS . "verisign_name_studio_response.php");

class VerisignNameStudio {
    const API_URL = "https://sugapi.verisign-grs.com/ns-api/2.0/";

    private $api_key;
    private $last_request = ['url' => null, 'params' => null];

    public function __construct($api_key) {
        $this->setApiKey($api_key);  
    }

    public function setApiKey($api_key) {
        $this->api_key = $api_key;
    }

    public function getLastRequest() {
        return $this->last_request;
    }

    public function suggest($name, array $userParams = []) {
        // Merge user options with defaults
        $params = array_replace_recursive([
          'name'        => $name,
          'tlds'        => "com,net",
          'lang'        => "eng",  // eng/spa/ita/jpn/tur/chi/ger/por/fre/kor/vie/dut
          'use-numbers' => true,
          'use-idns'    => true,
          'use-dashes'  => "auto", // true/false/"auto"
          'max-length'  => 63,
          'max-results' => 20,
          'ip-address'  => null,   // Optional
          'lat-lng'     => null,   // Optional
          'include-registered' => false,
          'include-suggestion-type'  => false,
          'sensitive-content-filter' => false,
        ], $userParams);
        
        // Perform API request
        return $this->apiRequest("/suggest", $params);
    }
    
    public function supportedTlds() {
        return $this->apiRequest("/supported-tlds");
    }

    public function apiRequest($method, array $params = [], $type = 'GET') {
        // Send request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        // Set authentication details
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-NAMESUGGESTION-APIKEY: ' . $this->api_key
        ]);

        // Build GET request
        if ($type == 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

            if (!empty($params)) {
                $get = http_build_query($params);
            }
        }

        // Build POST request
        if ($type == 'POST') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POST, true);

            if (!empty($params)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            }
        }

        // Execute request
        $url = self::API_URL . trim($method, '/');

        $this->last_request = [
            'url' => $url,
            'params' => $params
        ];

        curl_setopt($ch, CURLOPT_URL, $url . (isset($get) ? '?' . $get : null));
        $response = curl_exec($ch);
        curl_close($ch);

        return new VerisignNameStudioResponse($response);
    }
}