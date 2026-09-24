<?php

namespace Tests\Fakes;

use App\Models\AuthSession;
use App\Models\Client;
use App\Models\User;
use App\Repositories\Contracts\AuthSessionRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Delegates to a real repository for everything except rotate(), which
 * always reports a lost optimistic-concurrency race — exactly what the
 * *losing* side of two concurrent refreshes for the same token sees in
 * production. Used to test IdApiGuard::refreshUsingToken()'s "!$rotated"
 * branch without actually racing two requests against each other.
 */
final class RotationRaceLosingAuthSessionRepository implements AuthSessionRepositoryInterface
{
    public function __construct(private readonly AuthSessionRepositoryInterface $inner) {}

    public function record(
        User $user,
        string $jti,
        Carbon $expiresAt,
        string $refreshTokenHash,
        Carbon $refreshExpiresAt,
        ?string $ip,
        ?string $userAgent,
    ): void {
        $this->inner->record($user, $jti, $expiresAt, $refreshTokenHash, $refreshExpiresAt, $ip, $userAgent);
    }

    public function rotate(
        AuthSession $session,
        string $newJti,
        Carbon $expiresAt,
        string $newRefreshTokenHash,
        Carbon $newRefreshExpiresAt,
        ?string $ip,
        ?string $userAgent,
    ): bool {
        return false;
    }

    public function revokeByJti(string $jti): void
    {
        $this->inner->revokeByJti($jti);
    }

    public function revoke(AuthSession $session): void
    {
        $this->inner->revoke($session);
    }

    public function revokeAllForUser(User $user): void
    {
        $this->inner->revokeAllForUser($user);
    }

    public function revokeAllForClient(Client $client): void
    {
        $this->inner->revokeAllForClient($client);
    }

    public function isRevoked(string $jti): bool
    {
        return $this->inner->isRevoked($jti);
    }

    public function findActiveByRefreshTokenHash(string $hash): ?AuthSession
    {
        return $this->inner->findActiveByRefreshTokenHash($hash);
    }

    public function findByPreviousRefreshTokenHash(string $hash): ?AuthSession
    {
        return $this->inner->findByPreviousRefreshTokenHash($hash);
    }

    public function activeForUser(User $user): Collection
    {
        return $this->inner->activeForUser($user);
    }

    public function findActiveForUser(User $user, int $id): ?AuthSession
    {
        return $this->inner->findActiveForUser($user, $id);
    }
}
