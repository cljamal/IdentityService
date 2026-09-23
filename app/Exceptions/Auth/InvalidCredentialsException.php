<?php

namespace App\Exceptions\Auth;

final class InvalidCredentialsException extends AuthException
{
    public function __construct()
    {
        parent::__construct('Invalid credentials.');
    }

    public function errorCode(): AuthErrorCode
    {
        return AuthErrorCode::InvalidCredentials;
    }

    public function statusCode(): int
    {
        return 401;
    }
}
