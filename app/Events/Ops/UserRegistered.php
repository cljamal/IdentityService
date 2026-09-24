<?php

namespace App\Events\Ops;

use App\Auth\Enums\AuthProviderName;
use App\Models\Client;
use App\Models\User;

final class UserRegistered extends OpsBroadcastEvent
{
    public function __construct(
        public readonly User $user,
        public readonly AuthProviderName $provider,
    ) {}

    protected function targetClient(): Client
    {
        return $this->user->client;
    }

    public function broadcastAs(): string
    {
        return 'user.registered';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_uuid' => $this->user->uuid,
            'client_id' => $this->targetClient()->client_id,
            'provider' => $this->provider->value,
            'registered_at' => now()->toIso8601String(),
        ];
    }
}
