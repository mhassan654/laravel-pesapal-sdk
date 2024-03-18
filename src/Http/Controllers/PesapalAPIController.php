<?php

namespace Mhassan654\Pesapal\Http\Controllers;

use App\Http\Controllers\Controller;
use Mhassan654\Pesapal\Exceptions\PesapalException;
use Mhassan654\Pesapal\Pesapal;

class PesapalAPIController extends Controller
{

    function handleCallback()
    {
        $merchant_reference = request('order_merchant_reference');
        $tracking_id = request('order_transaction_tracking_id');
        $route = config('pesapal.callback_route');
        return redirect()->route(
            $route,
            array('tracking_id' => $tracking_id, 'merchant_reference' => $merchant_reference)
        );
    }

    /**
     * @throws PesapalException
     */
    function handleIPN()
    {
        if (request('OrderNotificationType') && request('OrderMerchantReference') && request('OrderTrackingId')) {
            $notification_type = request('OrderNotificationType');
            $merchant_reference = request('OrderMerchantReference');
            $tracking_id = request('OrderTrackingId');
            (new \Mhassan654\Pesapal\Pesapal)->redirectToIPN($notification_type, $merchant_reference, $tracking_id);
        } else {
            throw new PesapalException("incorrect parameters in request");
        }
    }
    // Test bleeding edge
}
