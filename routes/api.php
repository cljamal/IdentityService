<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\IdentifierChangeController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\VerificationCodeController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'api', 'prefix' => 'auth'], function () {
    Route::group(['prefix' => '{provider}'], function () {
        Route::post('otp', VerificationCodeController::class)->middleware('throttle:5,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('register/verify', [AuthController::class, 'verifyRegistration'])->middleware('throttle:10,1');

        Route::post('password/forgot', [PasswordResetController::class, 'request'])->middleware('throttle:5,1');
        Route::post('password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:10,1');
        Route::post('password/change', ChangePasswordController::class)->middleware(['auth:id-api', 'throttle:10,1']);

        Route::post('identifier/change', [IdentifierChangeController::class, 'request'])->middleware(['auth:id-api', 'throttle:5,1']);
        Route::post('identifier/change/confirm-old', [IdentifierChangeController::class, 'confirmOld'])->middleware(['auth:id-api', 'throttle:10,1']);
        Route::post('identifier/change/confirm-new', [IdentifierChangeController::class, 'confirmNew'])->middleware(['auth:id-api', 'throttle:10,1']);

        Route::post('account/delete', [AccountController::class, 'requestDeletion'])->middleware(['auth:id-api', 'throttle:5,1']);
        Route::post('account/delete/confirm', [AccountController::class, 'confirmDeletion'])->middleware(['auth:id-api', 'throttle:10,1']);
    });

    Route::group(['middleware' => 'auth:id-api'], function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);

        Route::get('sessions', [SessionController::class, 'index']);
        Route::delete('sessions/{session}', [SessionController::class, 'destroy']);

        // Дебаг-эндпоинт для разработки: показывает раскодированный JWT
        // текущего запроса. Роут регистрируется всегда — сам контроллер
        // отказывает в проде (см. MeController), т.к. route:cache
        // "заморозил" бы проверку окружения здесь на момент сборки кэша.
        Route::get('me', [MeController::class, 'show']);
    });
});
