<?php

namespace App\Auth;

use Illuminate\Support\Str;

/**
 * An opaque bearer secret, unrelated to the JWT access token — only its
 * SHA-256 hash is ever persisted (see AuthSessionRepositoryInterface). The
 * plaintext exists only for the moment it's generated and returned to the
 * client; nothing server-side keeps it afterwards.
 */
final readonly class RefreshToken
{
    public function __construct(
        public string $plainText,
        public string $hash,
    ) {}

    public static function generate(): self
    {
        $plainText = Str::random(64);

        return new self($plainText, self::hash($plainText));
    }

    public static function hash(string $plainText): string
    {
        return hash('sha256', $plainText);
    }
}
