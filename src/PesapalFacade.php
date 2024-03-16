<?php

namespace Mhassan654\Pesapal;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Mhassan654\Pesapal\Skeleton\SkeletonClass
 */
class PesapalFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'pesapal';
    }
}
