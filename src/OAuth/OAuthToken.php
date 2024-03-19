<?php

/**
 * Created by PhpStorm.
 * User: mxgel
 * Date: 11/14/16
 * Time: 2:26 AM
 */

namespace Mhassan654\Pesapal\OAuth;


use Mhassan654\Pesapal\Pesapal;

/**
 * Class OAuthToken
 *
 * @package Mhassan654\Pesapal\OAuth
 */
class OAuthToken
{
    // access tokens and request tokens
    /**
     * @var
     */
    public $key;
    /**
     * @var
     */
    public $secret;

    /**
     * @param string $key - the token
     * @param string $secret - the token secret
     */
   public function __construct(string $key, string $secret)
    {
        $this->key = $key;
        $this->secret = $secret;
    }

    /**
     * generates the basic string serialization of a token that a server
     * would respond to request_token and access_token calls with
     */
   private function requestAuthToken(): string
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => (new Pesapal())->api_link("Auth/RequestToken"),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode([ // Encode data as associative array
                "consumer_key" => $this->key,
                "consumer_secret" =>$this->secret
            ]),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept: application/json',
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;
    }

    /**
     * @return string
     */
   public function __toString()
    {
        $response = $this->requestAuthToken();
        $data = json_decode($response, true);  // Decode JSON to associative array

        if (is_null($data['error']) && $data['status'] == "200"){
            return  $data['token'];
        }
        return $data['error'];
    }
}
