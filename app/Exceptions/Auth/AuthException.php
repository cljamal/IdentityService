<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Every client-facing auth exception renders the same shape: a stable
 * code instead of a sentence baked into the response (that also used to
 * mean some messages were English and others Russian, depending which
 * exception fired — a code sidesteps that entirely). Concrete exceptions
 * only need to say which code and which HTTP status apply; override
 * render() directly if a response ever needs to carry more than that
 * (see OtpThrottledException).
 */
abstract class AuthException extends Exception
{
    abstract public function errorCode(): AuthErrorCode;

    abstract public function statusCode(): int;

    public function render(Request $request): JsonResponse
    {
        return response()->json(['code' => $this->errorCode()->value], $this->statusCode());
    }
}
