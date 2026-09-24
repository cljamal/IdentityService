<?php

namespace App\Broadcasting;

use App\Auth\CurrentClient;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Authorization callback for the `client.{clientId}` channel (see
 * routes/channels.php) — a named class instead of an inline closure so it's
 * directly unit-testable without needing to exercise Laravel's broadcasting
 * transport machinery at all.
 */
final readonly class ClientChannelAuthorizer
{
    public function __construct(private CurrentClient $currentClient) {}

    /**
     * Laravel's own convention for a channel class given to Broadcast::channel()
     * by class name — it resolves the class via the container and calls
     * join(), not __invoke() (see Broadcaster::normalizeChannelHandlerToCallable()).
     *
     * $user is always null here in practice: this channel is only ever
     * reached via POST /api/broadcasting/client-auth, which runs the
     * "client" middleware instead of auth:id-api. Authorization goes
     * through CurrentClient (populated by AuthenticateClient from the
     * caller's own X-Client-Id/X-Client-Secret) instead.
     */
    public function join(?Authenticatable $user, string $clientId): bool
    {
        $client = $this->currentClient->resolved();

        return $client !== null && $client->client_id === $clientId;
    }
}
