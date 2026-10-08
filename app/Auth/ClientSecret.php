<?php

namespace App\Auth;

use Illuminate\Support\Str;

/**
 * The secret half of a client's credentials (see AuthenticateClient) —
 * structurally identical to RefreshToken: only the SHA-256 hash is ever
 * persisted, the plaintext is shown once when generated or rotated.
 */
final readonly class ClientSecret
{
    public function __construct(
        public string $plainText,
        public string $hash,
    ) {}

    public static function generate(): self
    {
        $plainText = Str::random(48);

        return new self($plainText, self::hash($plainText));
    }

    public static function hash(string $plainText): string
    {
        return hash('sha256', $plainText);
    }
}
