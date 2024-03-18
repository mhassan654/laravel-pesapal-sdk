<?php

namespace Mhassan654\Pesapal;

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
//        $billing_object = (object)[
//            "email_address" => "john.doe@example.com",
//            "phone_number" => null,
//            "country_code" => "",
//            "first_name" => "John",
//            "middle_name" => "",
//            "last_name" => "Doe",
//            "line_1" => "",
//            "line_2" => "",
//            "city" => "",
//            "state" => "",
//            "postal_code" => null,
//            "zip_code" => null
//        ];
//
//        $defaults = [ // the defaults will be overidden if set in $params
//            'id' => $this->random_reference(),
//            'amount' => '1',
//            'description' => 'sample description',
//            'callback_url' => config('pesapal.callback_route'),
//            "notification_id" => "fe078e53-78da-4a83-aa89-e7ded5c456e6",
//            "branch"=> "Store Name - HQ",
//            'billing_address' => $billing_object
//        ];
//
//        $callback_url = url('/') . '/pesapal-callback';
//
//
//        if (!array_key_exists('currency', $params) && config('pesapal.currency') != null) {
//            $params['currency'] = config('pesapal.currency');
//        }
//
//        $params = array_merge($defaults, $params);

//        if (!config('pesapal.callback_route')) {
//            throw new PesapalException("callback route not provided");
//        } else {
//            if (!Route::has(config('pesapal.callback_route'))) {
//                throw new PesapalException("callback route does not exist");
//            }
//        }

//        dd( json_encode([$params]));

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

//        dd($response);
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

        $statusrequestAPI = $this->api_link('Transactions/GetTransactionStatus');

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
            $payment = new $class();
            $payment->$method($response);
//            $payment->$method($OrderTrackingId, $status, $payment_method, $OrderMerchantReference);

//            $controller = config('pesapal.ipn_controller');
//
//            if ($controller) {
//                // Separate namespace, controller name, and method
//                $parts = explode('@', $controller);
//                $namespace = $parts[0];
//                $method = $parts[1];
//
//                // Leverage dependency injection or call the method based on your preference
//                $paymentController = app($namespace);
//                $paymentController->$method($response);
////                $paymentController->$method($OrderTrackingId, $status, $payment_method, $OrderMerchantReference);
//            } else {
//                // Handle the case where no controller is defined
//                throw new PesapalException('Pesapal IPN controller not defined in configuration.');
//            }

            if ($status != "PENDING") {
                $resp = "OrderNotificationType=$OrderNotificationType&OrderTrackingId=$OrderTrackingId&OrderMerchantReference=$OrderMerchantReference";
                ob_start();
                echo $resp;
                ob_flush();
                exit;
            }
        }
    }


    /**
     * https://cybqa.pesapal.com/pesapalv3/api/Transactions/GetTransactionStatus?orderTrackingId=xxxxxxxxxxxx
     * https://pay.pesapal.com/v3/api/Transactions/GetTransactionStatus?orderTrackingId=xxxxxxxxxxxx
     * @param $order_tracking_id
     *
     * @return bool|string
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
