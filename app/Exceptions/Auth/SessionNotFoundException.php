<?php

namespace App\Exceptions\Auth;

/**
 * The requested session doesn't exist, isn't active, or (most likely)
 * belongs to another user — deliberately indistinguishable from "not
 * found" so a session id can't be used to probe other users' sessions.
 */
final class SessionNotFoundException extends AuthException
{
    public function __construct()
    {
        parent::__construct('Session not found.');
    }

    public function errorCode(): AuthErrorCode
    {
        return AuthErrorCode::SessionNotFound;
    }

    public function statusCode(): int
    {
        return 404;
    }
}
