<?php

namespace App\Http\Controllers;

use App\Actions\Auth\ChangePasswordAction;
use App\Auth\Enums\AuthProviderName;
use App\Auth\Guards\IdApiGuard;
use App\Http\Resources\MessageResource;
use Illuminate\Http\Request;

class ChangePasswordController extends Controller
{
    public function __invoke(AuthProviderName $provider, Request $request): MessageResource
    {
        ChangePasswordAction::run($provider, IdApiGuard::current()->user(), $request->all());

        return MessageResource::make('Пароль изменён.');
    }
}
