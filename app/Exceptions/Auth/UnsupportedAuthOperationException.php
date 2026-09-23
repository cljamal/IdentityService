<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UnsupportedAuthOperationException extends Exception
{
    public function __construct(string $provider)
    {
        parent::__construct("Provider [{$provider}] does not support this operation.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 400);
    }
}
