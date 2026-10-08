<?php

use EscolaLms\Invoices\Http\Controllers\InvoicesApiController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api/order-invoices'], function () {
    Route::get('/{id}', [InvoicesApiController::class, 'read']);
});
