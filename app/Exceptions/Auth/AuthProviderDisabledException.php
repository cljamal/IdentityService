<?php

namespace App\Exceptions\Auth;

final class AuthProviderDisabledException extends AuthException
{
    public function __construct(string $provider)
    {
        parent::__construct("Provider [{$provider}] is currently disabled.");
    }

    public function errorCode(): AuthErrorCode
    {
        return AuthErrorCode::AuthProviderDisabled;
    }

    public function statusCode(): int
    {
        return 404;
    }
}
