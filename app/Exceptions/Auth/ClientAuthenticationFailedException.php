<?php

namespace App\Exceptions\Auth;

/**
 * Missing headers, unknown client_id, wrong secret, and a deactivated
 * client all render identically — telling a caller which one it was would
 * let it enumerate valid client_id values.
 */
final class ClientAuthenticationFailedException extends AuthException
{
    public function __construct()
    {
        parent::__construct('Client authentication failed.');
    }

    public function errorCode(): AuthErrorCode
    {
        return AuthErrorCode::ClientAuthenticationFailed;
    }

    public function statusCode(): int
    {
        return 401;
    }
}
