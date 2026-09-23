<?php

namespace App\Exceptions\Auth;

/**
 * The authenticated user has no identity for this provider to change
 * (e.g. they logged in via email-password and never linked a phone).
 */
final class NoLinkedIdentityException extends AuthException
{
    public function __construct(string $provider)
    {
        parent::__construct("No {$provider} identity linked to this account.");
    }

    public function errorCode(): AuthErrorCode
    {
        return AuthErrorCode::NoLinkedIdentity;
    }

    public function statusCode(): int
    {
        return 400;
    }
}
