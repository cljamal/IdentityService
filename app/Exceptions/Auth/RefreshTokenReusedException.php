<?php

namespace App\Exceptions\Auth;

/**
 * A refresh token was presented after it had already been rotated away —
 * the only way that happens is if it leaked and got used from somewhere
 * else. IdApiGuard::refreshUsingToken() revokes the whole session as soon
 * as this is detected, so this only needs to tell the caller it's gone.
 */
final class RefreshTokenReusedException extends AuthException
{
    public function __construct()
    {
        parent::__construct('Refresh token was already used; session revoked.');
    }

    public function errorCode(): AuthErrorCode
    {
        return AuthErrorCode::RefreshTokenReused;
    }

    public function statusCode(): int
    {
        return 401;
    }
}
