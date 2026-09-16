<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\VerificationCodeController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'api', 'prefix' => 'auth'], function () {

    Route::group(['prefix' => '{provider}'], function () {
        Route::post('otp', VerificationCodeController::class)->middleware('throttle:5,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    });

    Route::group(['middleware' => 'auth:api'], function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });

});
