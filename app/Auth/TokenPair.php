<?php

namespace App\Auth;

/**
 * What every login/register/reset/refresh flow ultimately produces: the
 * short-lived JWT clients authenticate with, and the opaque refresh token
 * they exchange for a new pair once it expires.
 */
final readonly class TokenPair
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
    ) {}
}
