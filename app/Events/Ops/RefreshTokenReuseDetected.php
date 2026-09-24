<?php

namespace App\Events\Ops;

use App\Models\Client;
use App\Models\User;

/**
 * A refresh token was replayed after it had already been rotated away —
 * the strongest signal this service has that a token leaked. Deliberately
 * its own event, not a UserSessionRevoked reason: the correct reaction on
 * the client's side ("treat this as a possible compromise") is different
 * from a routine logout, and the two are never fired for the same incident.
 */
final class RefreshTokenReuseDetected extends OpsBroadcastEvent
{
    public function __construct(
        public readonly User $user,
        public readonly ?string $ip,
        public readonly ?string $userAgent,
    ) {}

    protected function targetClient(): Client
    {
        return $this->user->client;
    }

    public function broadcastAs(): string
    {
        return 'user.refresh_token_reuse_detected';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_uuid' => $this->user->uuid,
            'client_id' => $this->targetClient()->client_id,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'detected_at' => now()->toIso8601String(),
        ];
    }
}
