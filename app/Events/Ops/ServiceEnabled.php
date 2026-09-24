<?php

namespace App\Events\Ops;

use App\Models\Client;

final class ServiceEnabled extends OpsBroadcastEvent
{
    public function __construct(public readonly Client $client) {}

    protected function targetClient(): Client
    {
        return $this->client;
    }

    public function broadcastAs(): string
    {
        return 'service.enabled';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'client_id' => $this->client->client_id,
            'enabled_at' => now()->toIso8601String(),
        ];
    }
}
