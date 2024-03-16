<?php

namespace Mhassan654\Pesapal;

use Mhassan654\Pesapal\Contracts\PesapalContract;

class Pesapal implements PesapalContract
{
    /**
     * Processes the payment to pesapal
     *
     * @return pesapal_tracking_id
     */

    private $callback_route = '';
}
