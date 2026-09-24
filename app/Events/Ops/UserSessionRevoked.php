<?php

namespace App\Events\Ops;

use App\Auth\Enums\SessionRevocationReason;
use App\Models\Client;
use App\Models\User;

final class UserSessionRevoked extends OpsBroadcastEvent
{
    public function __construct(
        public readonly User $user,
        public readonly SessionRevocationReason $reason,
    ) {}

    protected function targetClient(): Client
    {
        return $this->user->client;
    }

    public function broadcastAs(): string
    {
        return 'user.session_revoked';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_uuid' => $this->user->uuid,
            'client_id' => $this->targetClient()->client_id,
            'reason' => $this->reason->value,
            'revoked_at' => now()->toIso8601String(),
        ];
    }
}
