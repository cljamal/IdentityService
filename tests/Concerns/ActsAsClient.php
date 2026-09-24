<?php

namespace Tests\Concerns;

use App\Auth\CurrentClient;
use App\Models\Client;
use Database\Factories\ClientFactory;

/**
 * Every /api/auth/* route now requires client credentials. This creates one
 * Client, sets it as the default request headers (for HTTP-level test
 * calls) and seeds CurrentClient directly (for tests that call a
 * repository/guard method without going through the middleware at all).
 *
 * Must be declared AFTER RefreshDatabase in the test class's `use` list —
 * Laravel calls setUp<Trait>() methods in that order, and the `clients`
 * table needs to exist first.
 */
trait ActsAsClient
{
    protected Client $defaultClient;

    protected function setUpActsAsClient(): void
    {
        $this->defaultClient = Client::factory()->create();

        $this->withHeaders([
            'X-Client-Id' => $this->defaultClient->client_id,
            'X-Client-Secret' => ClientFactory::PLAIN_SECRET,
        ]);

        $this->app->make(CurrentClient::class)->set($this->defaultClient);
    }
}
