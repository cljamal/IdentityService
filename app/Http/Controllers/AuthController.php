<?php

namespace App\Http\Controllers;

use App\Actions\Auth\LoginAction;
use App\Auth\AuthProviderName;
use App\Http\Resources\Auth\TokenResource;
use App\Http\Resources\MessageResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Authenticate via the given provider and return a JWT.
     */
    public function login(AuthProviderName $provider, Request $request): TokenResource
    {
        $token = LoginAction::run($provider, $request->all());

        return TokenResource::make($token);
    }

    /**
     * Log the user out (invalidate the token).
     */
    public function logout(): MessageResource
    {
        Auth::guard('api')->logout();

        return MessageResource::make('Successfully logged out');
    }

    /**
     * Refresh a token.
     */
    public function refresh(): TokenResource
    {
        return TokenResource::make(Auth::guard('api')->refresh());
    }
}
