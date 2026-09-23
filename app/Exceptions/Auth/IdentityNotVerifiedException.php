<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IdentityNotVerifiedException extends Exception
{
    public function __construct(string $provider)
    {
        parent::__construct("Identity for provider [{$provider}] is not verified yet.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Подтвердите владение аккаунтом перед входом.'], 403);
    }
}
