<?php

namespace App\Repositories;

use App\Models\AuthSession;
use App\Models\User;
use App\Repositories\Contracts\AuthSessionRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EloquentAuthSessionRepository implements AuthSessionRepositoryInterface
{
    public function record(User $user, string $jti, Carbon $expiresAt, ?string $ip, ?string $userAgent): void
    {
        AuthSession::query()->create([
            'user_id' => $user->id,
            'jti' => $jti,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'last_used_at' => now(),
            'expires_at' => $expiresAt,
        ]);
    }

    public function rotate(string $oldJti, string $newJti, Carbon $expiresAt): bool
    {
        return AuthSession::query()
            ->where('jti', $oldJti)
            ->whereNull('revoked_at')
            ->update([
                'jti' => $newJti,
                'expires_at' => $expiresAt,
                'last_used_at' => now(),
            ]) > 0;
    }

    public function revokeByJti(string $jti): void
    {
        AuthSession::query()->where('jti', $jti)->update(['revoked_at' => now()]);
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
