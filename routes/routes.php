<?php

use Mhassan654\Pesapal\Http\Controllers\PesapalAPIController;

Route::get('pesapal-callback', [PesapalAPIController::class,'handleCallback']);
Route::get('pesapal-ipn', [PesapalAPIController::class,'handleIPN']);

