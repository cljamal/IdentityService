<?php

namespace App\Http\Controllers;

use App\Actions\Auth\ConfirmNewIdentifierAction;
use App\Actions\Auth\ConfirmOldIdentifierAction;
use App\Actions\Auth\RequestIdentifierChangeAction;
use App\Auth\AuthProviderName;
use App\Auth\Guards\IdApiGuard;
use App\Http\Resources\MessageResource;
use Illuminate\Http\Request;

class IdentifierChangeController extends Controller
{
    /**
     * Request the change: sends an OTP to the OLD identifier (e.g. current phone).
     */
    public function request(AuthProviderName $provider, Request $request): MessageResource
    {
        RequestIdentifierChangeAction::run($provider, IdApiGuard::current()->user(), $request->all());

        return MessageResource::make('Код отправлен на текущий номер.');
    }

    /**
     * Confirm the OLD identifier's code: sends an OTP to the NEW identifier.
     */
    public function confirmOld(AuthProviderName $provider, Request $request): MessageResource
    {
        ConfirmOldIdentifierAction::run($provider, IdApiGuard::current()->user(), $request->all());

        return MessageResource::make('Код отправлен на новый номер.');
    }

    /**
     * Confirm the NEW identifier's code: the change actually applies.
     */
    public function confirmNew(AuthProviderName $provider, Request $request): MessageResource
    {
        ConfirmNewIdentifierAction::run($provider, IdApiGuard::current()->user(), $request->all());

        return MessageResource::make('Номер изменён.');
    }
}
