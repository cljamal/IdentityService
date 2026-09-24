<?php

namespace App\Repositories;

use App\Models\AuthSession;
use App\Models\User;
use App\Repositories\Contracts\AuthSessionRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EloquentAuthSessionRepository implements AuthSessionRepositoryInterface
{
    public function record(
        User $user,
        string $jti,
        Carbon $expiresAt,
        string $refreshTokenHash,
        Carbon $refreshExpiresAt,
        ?string $ip,
        ?string $userAgent,
    ): void {
        AuthSession::query()->create([
            'user_id' => $user->id,
            'jti' => $jti,
            'refresh_token_hash' => $refreshTokenHash,
            'refresh_expires_at' => $refreshExpiresAt,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'last_used_at' => now(),
            'expires_at' => $expiresAt,
        ]);
    }

    public function rotate(
        AuthSession $session,
        string $newJti,
        Carbon $expiresAt,
        string $newRefreshTokenHash,
        Carbon $newRefreshExpiresAt,
    ): void {
        $session->update([
            'jti' => $newJti,
            'expires_at' => $expiresAt,
            'previous_refresh_token_hash' => $session->refresh_token_hash,
            'refresh_token_hash' => $newRefreshTokenHash,
            'refresh_expires_at' => $newRefreshExpiresAt,
            'last_used_at' => now(),
        ]);
    }

    public function revokeByJti(string $jti): void
    {
        AuthSession::query()->where('jti', $jti)->update(['revoked_at' => now()]);
    }

    public function revoke(AuthSession $session): void
    {
        $session->update(['revoked_at' => now()]);
    }

    public function revokeAllForUser(User $user): void
    {
        AuthSession::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function isRevoked(string $jti): bool
    {
        $session = AuthSession::query()->where('jti', $jti)->first();

        return $session !== null && $session->revoked_at !== null;
    }

    public function findActiveByRefreshTokenHash(string $hash): ?AuthSession
    {
        return AuthSession::query()
            ->where('refresh_token_hash', $hash)
            ->whereNull('revoked_at')
            ->where('refresh_expires_at', '>', now())
            ->first();
    }

    public function findByPreviousRefreshTokenHash(string $hash): ?AuthSession
    {
        return AuthSession::query()
            ->where('previous_refresh_token_hash', $hash)
            ->first();
    }

    public function activeForUser(User $user): Collection
    {
        return AuthSession::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('last_used_at')
            ->get();
    }

    public function findActiveForUser(User $user, int $id): ?AuthSession
    {
        return AuthSession::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->whereNull('revoked_at')
            ->first();
    }
}
