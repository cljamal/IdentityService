<?php

namespace App\Exceptions\Auth;

final class IdentityNotVerifiedException extends AuthException
{
    public function __construct(string $provider)
    {
        parent::__construct("Identity for provider [{$provider}] is not verified yet.");
    }

    public function errorCode(): AuthErrorCode
    {
        return AuthErrorCode::IdentityNotVerified;
    }

    public function statusCode(): int
    {
        return 403;
    }
}
