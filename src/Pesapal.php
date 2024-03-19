<?php

namespace Mhassan654\Pesapal;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mhassan654\Pesapal\Contracts\PesapalContract;
use Mhassan654\Pesapal\Exceptions\PesapalException;
use Mhassan654\Pesapal\OAuth\OAuthToken;
use Mockery\Exception;
use Random\RandomException;

class Pesapal implements PesapalContract
{
    /**
     * Processes the payment to pesapal
     *
     * @return pesapal_tracking_id
     */

    private $callback_route = '';

    /**
     * Sandbox/Demo URL: https://cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest
     * Production/Live URL: https://pay.pesapal.com/v3/api/Transactions/SubmitOrderRequest
     * @param $params
     *
     * @return string
     * @throws PesapalException|RandomException
     */
    public function makePayment($params)
    {
        $token = self::getToken();
        $submit_order_link = $this->api_link('Transactions/SubmitOrderRequest');

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $submit_order_link,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode( // Encode data as associative array
                $params
            ),
            CURLOPT_HTTPHEADER => array(
                self::ACCEPT,
                self::CONTENT_TYPE,
                "Authorization: Bearer $token",  // Include bearer token in header
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    /**
     * @param $OrderNotificationType
     * @param $OrderMerchantReference
     * @param $OrderTrackingId
     * @throws PesapalException
     */
    function redirectToIPN($OrderNotificationType, $OrderMerchantReference, $OrderTrackingId)
    {
        $token =self::getToken();

        $statusrequestAPI = $this->api_link("Transactions/GetTransactionStatus?orderTrackingId=$OrderTrackingId");

        if ($OrderNotificationType == "IPNCHANGE" && $OrderTrackingId != '') {
            //get transaction status
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $statusrequestAPI,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    self::ACCEPT,
                    self::CONTENT_TYPE,
                    "Authorization: Bearer $token",  // Include bearer token in header
                ),
            ));

            $response = curl_exec($curl);

            $decoded_response = json_decode($response);
            $payment_method = $decoded_response->payment_method;
            $status = $decoded_response->status;
            curl_close($curl);

            if ($status == 'PENDING') {
                sleep(60);
                $this->redirectToIPN($OrderNotificationType, $OrderMerchantReference, $OrderTrackingId);
            }

            //UPDATE YOUR DB TABLE WITH NEW STATUS FOR TRANSACTION WITH $OrderTrackingId
            $separator = explode('@', config('pesapal.ipn_controller'));
            $controller = $separator[0];
            $method = $separator[1];
            $class = '\App\Http\Controllers\\' . $controller;
            $payment = new $class($this);
            $payment->$method($OrderTrackingId, $status, $payment_method, $OrderMerchantReference);

            if ($status != "PENDING") {
                $resp = "OrderNotificationType=$OrderNotificationType&OrderTrackingId=$OrderTrackingId&OrderMerchantReference=$OrderMerchantReference";
                ob_start();
                echo $resp;
                ob_flush();
                exit;
            }
            return $payment;
        }
        throw new PesapalException("something went wrong");
    }


    /**
     * https://cybqa.pesapal.com/pesapalv3/api/Transactions/GetTransactionStatus?orderTrackingId=xxxxxxxxxxxx
     * https://pay.pesapal.com/v3/api/Transactions/GetTransactionStatus?orderTrackingId=xxxxxxxxxxxx
     * @param $order_tracking_id
     *
     * @return bool|string
     * @throws PesapalException
     */
    public function getTransactionStatus($order_tracking_id)
    {
        $token = self::getToken();

        $statusrequestAPI = $this->api_link("Transactions/GetTransactionStatus?orderTrackingId=$order_tracking_id");

        //get transaction status
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $statusrequestAPI,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                self::ACCEPT,
                self::CONTENT_TYPE,
                "Authorization: Bearer $token",  // Include bearer token in header
            ),
        ));

        $status = curl_exec($curl);
        curl_close($curl);
        return $status;
    }

    /**
     * https://cybqa.pesapal.com/pesapalv3/api/Transactions/GetTransactionStatus?orderTrackingId=xxxxxxxxxxxx
     * https://pay.pesapal.com/v3/api/Transactions/GetTransactionStatus?orderTrackingId=xxxxxxxxxxxx
     * @param $url
     * @param string $type
     * @return bool|string
     * @throws PesapalException
     */
    public function registerIPN($url, string $type="GET")
    {
        $token = self::getToken();

        $statusrequestAPI = $this->api_link("URLSetup/RegisterIPN");

        //get transaction status
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $statusrequestAPI,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode([ // Encode data as associative array
                "url" => $url,
                "ipn_notification_type" =>$type
            ]),
            CURLOPT_HTTPHEADER => array(
                self::ACCEPT,
                self::CONTENT_TYPE,
                "Authorization: Bearer $token",  // Include bearer token in header
            ),
        ));

        $status = curl_exec($curl);
        curl_close($curl);
        return $status;
    }

    function getRegisterIPNlist()
    {
        $token = self::getToken();

        $list_link = $this->api_link("URLSetup/GetIpnList");

        //get transaction status
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $list_link,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',

            CURLOPT_HTTPHEADER => array(
                self::ACCEPT,
                self::CONTENT_TYPE,
                "Authorization: Bearer $token",  // Include bearer token in header
            ),
        ));

        $status = curl_exec($curl);
        curl_close($curl);
        return $status;
    }


    /**
     * @param string $prefix
     * @param int $length
     *
     * @return string
     * @throws RandomException
     */
    public function random_reference(string $prefix = 'PESAPAL', int $length = 15): string
    {
        $keyspace = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $str = '';

        $max = mb_strlen($keyspace, '8bit') - 1;

        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }

        return $prefix . $str;
    }

    /**
     * Get API path
     * @param null $path
     * @return string
     */
    public function api_link($path = null): string
    {
        $live = 'https://cybqa.pesapal.com/pesapalv3/api/';
        $demo = 'https://pay.pesapal.com/v3/api/';
        return config('pesapal.live') ? $demo : $live . $path;
    }


    /**
     * @return string
     * @throws PesapalException
     */
    private static function getToken(): string
    {
        $consumer_key = config('pesapal.consumer_key');
        $consumer_secret = config('pesapal.consumer_secret');

        if (!$consumer_key && !$consumer_secret) {
            throw new PesapalException("Consumer key and secrete keys are required");
        } else {
            $init_token = new OAuthToken($consumer_key, $consumer_secret);
           return (string)$init_token;
        }

    }
}
