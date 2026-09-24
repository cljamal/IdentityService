<?php

namespace App\Events\Ops;

use App\Models\Client;

/**
 * Never fired by application logic — only by `php artisan ops:broadcast
 * test`, to prove the channel/event pipeline actually reaches a client
 * without needing to trigger a real app flow. The wire name (`ops.test`)
 * is deliberately unlike any real signal so it can't be mistaken for one.
 */
final class TestBroadcast extends OpsBroadcastEvent
{
    public function __construct(
        public readonly Client $client,
        public readonly string $message,
    ) {}

    protected function targetClient(): Client
    {
        return $this->client;
    }

    public function broadcastAs(): string
    {
        return 'ops.test';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'client_id' => $this->client->client_id,
            'message' => $this->message,
            'sent_at' => now()->toIso8601String(),
        ];
    }
}
