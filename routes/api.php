<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BroadcastingClientAuthController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\IdentifierChangeController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\VerificationCodeController;
use Illuminate\Support\Facades\Route;

// Client-secret-authenticated counterpart to the framework's own
// POST /api/broadcasting/auth (registered by withBroadcasting() in
// bootstrap/app.php, behind auth:id-api). Lets a client's Gateway
// subscribe to its own private WS channel (see routes/channels.php)
// using the same X-Client-Id/X-Client-Secret it uses everywhere else.
Route::post('broadcasting/client-auth', BroadcastingClientAuthController::class)
    ->middleware(['api', 'client']);

Route::group(['middleware' => ['api', 'client'], 'prefix' => 'auth'], function () {
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

    // Не требует auth:id-api — access-токен к этому моменту обычно уже
    // истёк, аутентификатор здесь сам refresh-токен из тела запроса.
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:10,1');

    Route::group(['middleware' => 'auth:id-api'], function () {
        Route::post('logout', [AuthController::class, 'logout']);

        Route::get('sessions', [SessionController::class, 'index']);
        Route::delete('sessions/{session}', [SessionController::class, 'destroy']);

        // Дебаг-эндпоинт для разработки: показывает раскодированный JWT
        // текущего запроса. Роут регистрируется всегда — сам контроллер
        // отказывает в проде (см. MeController), т.к. route:cache
        // "заморозил" бы проверку окружения здесь на момент сборки кэша.
        Route::get('me', [MeController::class, 'show']);
    });
});
