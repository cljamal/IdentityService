<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\VerificationCodeController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'api', 'prefix' => 'auth'], function () {

    Route::group(['prefix' => '{provider}'], function () {
        Route::post('otp', VerificationCodeController::class)->middleware('throttle:5,1');
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

        Route::post('password/forgot', [PasswordResetController::class, 'request'])->middleware('throttle:5,1');
        Route::post('password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:10,1');
        Route::post('password/change', ChangePasswordController::class)->middleware(['auth:id-api', 'throttle:10,1']);
    });

    Route::group(['middleware' => 'auth:id-api'], function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });

});
