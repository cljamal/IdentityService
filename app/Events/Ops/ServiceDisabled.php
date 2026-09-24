<?php

namespace App\Events\Ops;

use App\Models\Client;

/**
 * A client-wide "assume everyone's logged out" signal — deliberately not
 * accompanied by a UserSessionRevoked per affected user (see
 * BroadcastServiceDisabled): that would be a thundering herd for a client
 * with many users, and this single event already tells the Gateway
 * everything it needs to know.
 */
final class ServiceDisabled extends OpsBroadcastEvent
{
    public function __construct(public readonly Client $client) {}

    protected function targetClient(): Client
    {
        return $this->client;
    }

    public function broadcastAs(): string
    {
        return 'service.disabled';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'client_id' => $this->client->client_id,
            'disabled_at' => now()->toIso8601String(),
        ];
    }
}
