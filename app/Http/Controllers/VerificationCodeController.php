<?php

namespace App\Http\Controllers;

use App\Actions\Auth\SendVerificationCodeAction;
use App\Auth\Enums\AuthProviderName;
use App\Http\Resources\MessageResource;
use Illuminate\Http\Request;

final class VerificationCodeController extends Controller
{
    /**
     * Send a verification code (e.g. phone OTP) for the given provider.
     */
    public function __invoke(AuthProviderName $provider, Request $request): MessageResource
    {
        SendVerificationCodeAction::run($provider, $request->all());

        return MessageResource::make('Код отправлен.');
    }
}
