<?php

namespace App\Http\Controllers;

use App\Actions\Auth\RequestPasswordResetAction;
use App\Actions\Auth\ResetPasswordAction;
use App\Auth\Enums\AuthProviderName;
use App\Http\Resources\Auth\TokenResource;
use App\Http\Resources\MessageResource;
use Illuminate\Http\Request;

class PasswordResetController extends Controller
{
    /**
     * Request a password reset code for the given provider.
     */
    public function request(AuthProviderName $provider, Request $request): MessageResource
    {
        RequestPasswordResetAction::run($provider, $request->all());

        return MessageResource::make('Если такой аккаунт существует, код отправлен.');
    }

    /**
     * Confirm the reset code and set a new password.
     */
    public function reset(AuthProviderName $provider, Request $request): TokenResource
    {
        $token = ResetPasswordAction::run($provider, $request->all());

        return TokenResource::make($token);
    }
}
