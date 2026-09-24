<?php

namespace App\Repositories\Contracts;

use App\Models\AuthSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface AuthSessionRepositoryInterface
{
    public function record(
        User $user,
        string $jti,
        Carbon $expiresAt,
        string $refreshTokenHash,
        Carbon $refreshExpiresAt,
        ?string $ip,
        ?string $userAgent,
    ): void;

    /**
     * Move an existing session onto a new jti and refresh token — a
     * refreshed token is the same logical session, not a new one. The
     * replaced refresh token hash is kept as "previous" for exactly one
     * generation so a replay of it can be recognized as reuse.
     */
    public function rotate(
        AuthSession $session,
        string $newJti,
        Carbon $expiresAt,
        string $newRefreshTokenHash,
        Carbon $newRefreshExpiresAt,
    ): void;

    public function revokeByJti(string $jti): void;

    public function revoke(AuthSession $session): void;

    public function revokeAllForUser(User $user): void;

    /**
     * True only if a row for this jti exists AND is marked revoked — a jti
     * with no row at all (e.g. issued before this feature existed) is not
     * considered revoked.
     */
    public function isRevoked(string $jti): bool;

    /**
     * The session this refresh token currently authorizes — null if the
     * hash is unknown, expired, or the session was revoked.
     */
    public function findActiveByRefreshTokenHash(string $hash): ?AuthSession;

    /**
     * A session whose refresh token was already rotated past this hash. A
     * match means the token was replayed after it had already been used
     * once — a strong signal it leaked — not just an unknown/expired one.
     */
    public function findByPreviousRefreshTokenHash(string $hash): ?AuthSession;

    /**
     * @return Collection<int, AuthSession>
     */
    public function activeForUser(User $user): Collection;

    public function findActiveForUser(User $user, int $id): ?AuthSession;
}
