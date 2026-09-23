<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OtpThrottledException extends Exception
{
    public function __construct(private readonly int $retryAfter)
    {
        parent::__construct('Too many OTP requests.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Повторная отправка кода будет доступна через '.$this->retryAfter.' сек.',
            'retry_after' => $this->retryAfter,
        ], 429);
    }
}
