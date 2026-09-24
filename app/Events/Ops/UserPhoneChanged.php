<?php

namespace App\Events\Ops;

use App\Models\Client;
use App\Models\User;

/**
 * Deliberate, not an oversight: old_phone/new_phone go out unmasked (unlike
 * OtpCodeBroadcast's masked contact on the much more broadly-authorized
 * 'otp-deliveries' channel). This channel is scoped to exactly the one
 * client that owns this user's account — data they already hold, since
 * their own Gateway is what proxied the phone-change request in the first
 * place. Revisit this if the channel's authorization model ever widens.
 */
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
