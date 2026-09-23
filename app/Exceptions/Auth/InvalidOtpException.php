<?php

namespace App\Exceptions\Auth;

final class InvalidOtpException extends AuthException
{
    public function __construct()
    {
        parent::__construct('Invalid or expired OTP code.');
    }

    public function errorCode(): AuthErrorCode
    {
        return AuthErrorCode::InvalidOtp;
    }

    public function statusCode(): int
    {
        return 422;
    }
}
