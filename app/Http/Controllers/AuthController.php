<?php

namespace App\Http\Controllers;

use App\Actions\Auth\LoginAction;
use App\Actions\Auth\RefreshTokenAction;
use App\Actions\Auth\RegisterAction;
use App\Actions\Auth\VerifyRegistrationAction;
use App\Auth\Enums\AuthProviderName;
use App\Auth\Guards\IdApiGuard;
use App\Http\Resources\Auth\TokenResource;
use App\Http\Resources\MessageResource;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    /**
     * Register a new identity for the given provider. Returns a JWT right
     * away if nothing needs verifying, otherwise a "check your code" message.
     */
    public function register(AuthProviderName $provider, Request $request): TokenResource|MessageResource
    {
        $tokens = RegisterAction::run($provider, $request->all());

        return $tokens !== null
            ? TokenResource::make($tokens)
            : MessageResource::make('Мы отправили код подтверждения. Подтвердите его, чтобы завершить регистрацию.');
    }

    /**
     * Confirm the code sent after register() and return a JWT.
     */
    public function verifyRegistration(AuthProviderName $provider, Request $request): TokenResource
    {
        return TokenResource::make(VerifyRegistrationAction::run($provider, $request->all()));
    }

    /**
     * Authenticate via the given provider and return a JWT.
     */
    public function login(AuthProviderName $provider, Request $request): TokenResource
    {
        return TokenResource::make(LoginAction::run($provider, $request->all()));
    }

    /**
     * Log the user out (invalidate the token).
     */
    public function logout(): MessageResource
    {
        IdApiGuard::current()->logout();

        return MessageResource::make('Successfully logged out');
    }

    /**
     * Exchange a refresh token for a new access/refresh pair. Unauthenticated
     * on purpose — the access token has usually already expired by the time
     * a client needs this, the refresh token itself is the credential.
     */
    public function refresh(Request $request): TokenResource
    {
        return TokenResource::make(RefreshTokenAction::run($request->all()));
    }
}
