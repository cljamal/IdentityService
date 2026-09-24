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
        ?string $ip,
        ?string $userAgent,
    ): bool {
        // Conditioned on the hash still being the one we just read: two
        // concurrent refreshes with the same token both pass the earlier
        // SELECT, but only the first UPDATE here can match — the loser
        // gets 0 affected rows instead of silently overwriting the winner.
        return AuthSession::query()
            ->where('id', $session->id)
            ->where('refresh_token_hash', $session->refresh_token_hash)
            ->update([
                'jti' => $newJti,
                'expires_at' => $expiresAt,
                'previous_refresh_token_hash' => $session->refresh_token_hash,
                'refresh_token_hash' => $newRefreshTokenHash,
                'refresh_expires_at' => $newRefreshExpiresAt,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'last_used_at' => now(),
            ]) === 1;
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

        // No row at all means this jti isn't the session's current one
        // any more — either it was rotated away by a refresh (rotate()
        // overwrites jti in place) or it never existed. Either way, a
        // "current" token always has a matching row, so treat a miss the
        // same as an explicit revoke.
        return $session === null || $session->revoked_at !== null;
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
            ->whereNull('revoked_at')
            ->first();
    }

    public function activeForUser(User $user): Collection
    {
        return AuthSession::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            // The session survives on its refresh token, not the access
            // token's own (much shorter) expiry — filtering on expires_at
            // here would drop a perfectly live session off the list an
            // hour after it was last used.
            ->where('refresh_expires_at', '>', now())
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
