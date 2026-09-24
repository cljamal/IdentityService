<?php

namespace App\Repositories\Contracts;

use App\Models\AuthSession;
use App\Models\Client;
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
     *
     * Only applies if $session's refresh token hash is still what the
     * caller read it as — returns false instead of overwriting a hash
     * some other, concurrent rotation already moved on from.
     */
    public function rotate(
        AuthSession $session,
        string $newJti,
        Carbon $expiresAt,
        string $newRefreshTokenHash,
        Carbon $newRefreshExpiresAt,
        ?string $ip,
        ?string $userAgent,
    ): bool;

    public function revokeByJti(string $jti): void;

    public function revoke(AuthSession $session): void;

    public function revokeAllForUser(User $user): void;

    /**
     * Revokes every non-revoked session belonging to any user under this
     * client — used when a client is deactivated (see ClientDeactivated),
     * so `revoked_at` reflects reality instead of sessions sitting there
     * looking active just because nothing naturally expired them.
     */
    public function revokeAllForClient(Client $client): void;

    /**
     * True if this jti isn't a session's current one any more — either
     * the row was explicitly revoked, or (since a refresh rotates jti in
     * place) it has since moved on and this jti is a stale, pre-rotation
     * token that must not keep working.
     */
    public function isRevoked(string $jti): bool;

    /**
     * The session this refresh token currently authorizes — null if the
     * hash is unknown, expired, or the session was revoked.
     */
    public function findActiveByRefreshTokenHash(string $hash): ?AuthSession;

    /**
     * A non-revoked session whose refresh token was already rotated past
     * this hash. A match means the token was replayed after it had
     * already been used once — a strong signal it leaked — not just an
     * unknown/expired one.
     */
    public function findByPreviousRefreshTokenHash(string $hash): ?AuthSession;

    /**
     * @return Collection<int, AuthSession>
     */
    public function activeForUser(User $user): Collection;

    public function findActiveForUser(User $user, int $id): ?AuthSession;
}
