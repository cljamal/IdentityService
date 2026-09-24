<?php

namespace App\Exceptions\Auth;

final class InvalidRefreshTokenException extends AuthException
{
    public function __construct()
    {
        parent::__construct('Invalid or expired refresh token.');
    }

    public function errorCode(): AuthErrorCode
    {
        return AuthErrorCode::InvalidRefreshToken;
    }

    public function statusCode(): int
    {
        return 401;
    }
}
