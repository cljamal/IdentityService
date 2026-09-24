<?php

namespace App\Auth;

use App\Models\Client;
use LogicException;

/**
 * The client resolved by AuthenticateClient for this request, made
 * available to repositories/strategies via constructor injection instead
 * of threading a Client parameter through every method that needs one.
 * Bound as a singleton — one process handles one request in this app, so
 * "singleton" already means "request-scoped" in practice.
 */
final class CurrentClient
{
    private ?Client $client = null;

    public function set(Client $client): void
    {
        $this->client = $client;
    }

    /**
     * @throws LogicException if no client was resolved for this request —
     *                         a programmer error (AuthenticateClient isn't
     *                         wired on this route), not a client error.
     */
    public function get(): Client
    {
        return $this->client ?? throw new LogicException(
            'No client resolved for this request — is the "client" middleware registered on this route?'
        );
    }

    public function resolved(): ?Client
    {
        return $this->client;
    }
}
