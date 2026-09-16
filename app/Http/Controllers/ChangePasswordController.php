<?php

namespace App\Http\Controllers;

use App\Actions\Auth\ChangePasswordAction;
use App\Auth\AuthProviderName;
use App\Http\Resources\MessageResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChangePasswordController extends Controller
{
    public function __invoke(AuthProviderName $provider, Request $request): MessageResource
    {
        ChangePasswordAction::run($provider, Auth::guard('api')->user(), $request->all());

        return MessageResource::make('Пароль изменён.');
    }
}
