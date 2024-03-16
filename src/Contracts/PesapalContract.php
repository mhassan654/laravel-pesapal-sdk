<?php
/**
 * Created by PhpStorm.
 * User: mxgel
 * Date: 11/15/16
 * Time: 4:47 AM
 */

namespace Mhassan654\Pesapal\Contracts;


/**
 * Interface PesapalContract
 * @package Mhassan654\Pesapal\Contracts
 */
interface PesapalContract
{
    /**
     * Pending payment
     */
    const PESAPAL_STATUS_PENDING = 'pending';

    /**
     * Failed payment
     */
    const PESAPAL_STATUS_FAILED = 'failed';

    /**
     * Successfully completed payment
     */
    const PESAPAL_STATUS_COMPLETED = 'completed';

    const ACCEPT = 'Accept: application/json';
    const CONTENT_TYPE = 'Content-Type: application/json';
}
