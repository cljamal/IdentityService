<?php

namespace App\Exceptions\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OtpThrottledException extends AuthException
{
    public function __construct(private readonly int $retryAfter)
    {
        parent::__construct('Too many OTP requests.');
    }

    public function errorCode(): AuthErrorCode
    {
        return AuthErrorCode::OtpThrottled;
    }

    public function statusCode(): int
    {
        return 429;
    }

    /**
     * Overridden (not just errorCode()/statusCode()) because this is the
     * one response that needs a field beyond the code — retry_after is
     * data a client acts on programmatically, not display text.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => $this->errorCode()->value,
            'retry_after' => $this->retryAfter,
        ], $this->statusCode());
    }
}
