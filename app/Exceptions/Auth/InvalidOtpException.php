<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidOtpException extends Exception
{
    public function __construct()
    {
        parent::__construct('Invalid or expired OTP code.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Неверный или истёкший код подтверждения.',
        ], 422);
    }
}
