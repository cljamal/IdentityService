<?php

namespace App\Events\Ops;

use App\Models\Client;
use App\Models\User;

/**
 * Distinct from UserSessionRevoked on purpose: this identity no longer
 * exists at all, not just "needs to re-authenticate" — a client's correct
 * reaction is to purge cached user data, not just drop a token. Fired
 * alone; DeleteAccountAction does not also fire UserSessionRevoked for the
 * same action.
 */
final class AccountDeleted extends OpsBroadcastEvent
{
    public function __construct(public readonly User $user) {}

    protected function targetClient(): Client
    {
        return $this->user->client;
    }

    public function broadcastAs(): string
    {
        return 'user.account_deleted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_uuid' => $this->user->uuid,
            'client_id' => $this->targetClient()->client_id,
            'deleted_at' => now()->toIso8601String(),
        ];
    }
}
