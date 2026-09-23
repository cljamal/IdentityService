<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InvalidCredentialsException extends Exception
{
    public function __construct()
    {
        parent::__construct('Invalid credentials.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Неверный логин или пароль.'], 401);
    }
}
