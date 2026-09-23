<?php

namespace App\Http\Controllers;

use App\Actions\Auth\ConfirmAccountDeletionAction;
use App\Actions\Auth\RequestAccountDeletionAction;
use App\Auth\Enums\AuthProviderName;
use App\Auth\Guards\IdApiGuard;
use App\Http\Resources\MessageResource;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * Request account deletion: sends a confirmation code via the given provider's channel.
     */
    public function requestDeletion(AuthProviderName $provider): MessageResource
    {
        RequestAccountDeletionAction::run($provider, IdApiGuard::current()->user());

        return MessageResource::make('Код подтверждения удаления отправлен.');
    }

    /**
     * Confirm the code: the account (and all its linked identities) is deleted.
     */
    public function confirmDeletion(AuthProviderName $provider, Request $request): MessageResource
    {
        ConfirmAccountDeletionAction::run($provider, IdApiGuard::current()->user(), $request->all());

        return MessageResource::make('Аккаунт удалён.');
    }
}
