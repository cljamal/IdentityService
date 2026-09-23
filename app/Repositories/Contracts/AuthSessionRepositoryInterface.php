<?php

namespace App\Repositories\Contracts;

use App\Models\AuthSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface AuthSessionRepositoryInterface
{
    public function record(User $user, string $jti, Carbon $expiresAt, ?string $ip, ?string $userAgent): void;

    /**
     * Move an existing, non-revoked row from its old jti to a new one (a
     * refreshed token is the same logical session, not a new one). Returns
     * false if no matching row was found, so the caller can fall back to
     * record().
     */
    public function rotate(string $oldJti, string $newJti, Carbon $expiresAt): bool;

    public function revokeByJti(string $jti): void;

    public function revokeAllForUser(User $user): void;

    /**
     * True only if a row for this jti exists AND is marked revoked — a jti
     * with no row at all (e.g. issued before this feature existed) is not
     * considered revoked.
     */
    public function isRevoked(string $jti): bool;

    /**
     * @return Collection<int, AuthSession>
     */
    public function activeForUser(User $user): Collection;

    public function findActiveForUser(User $user, int $id): ?AuthSession;
}
