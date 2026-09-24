<?php

namespace App\Events\Ops;

use App\Models\Client;
use App\Models\User;

final class UserPhoneChanged extends OpsBroadcastEvent
{
    public function __construct(
        public readonly User $user,
        public readonly string $oldPhone,
        public readonly string $newPhone,
    ) {}

    protected function targetClient(): Client
    {
        return $this->user->client;
    }

    public function broadcastAs(): string
    {
        return 'user.phone_changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_uuid' => $this->user->uuid,
            'client_id' => $this->targetClient()->client_id,
            'old_phone' => $this->oldPhone,
            'new_phone' => $this->newPhone,
            'changed_at' => now()->toIso8601String(),
        ];
    }
}
