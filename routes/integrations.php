<?php

use App\Http\Controllers\CrmEstimateController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], 'crm/v1/estimates/{deal}', CrmEstimateController::class)
    ->where('deal', '[a-z0-9-]{1,50}:[0-9]{1,19}')
    ->middleware('throttle:60,1,crm-estimates');

Route::get('erp/v1/estimates', \App\Http\Controllers\ErpEstimateController::class)
    ->middleware('throttle:60,1,erp-estimates');
