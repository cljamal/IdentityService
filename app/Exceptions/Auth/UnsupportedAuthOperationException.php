<?php

namespace App\Exceptions\Auth;

final class UnsupportedAuthOperationException extends AuthException
{
    public function __construct(string $provider)
    {
        parent::__construct("Provider [{$provider}] does not support this operation.");
    }

    public function errorCode(): AuthErrorCode
    {
        return AuthErrorCode::UnsupportedAuthOperation;
    }

    public function statusCode(): int
    {
        return 400;
    }
}
