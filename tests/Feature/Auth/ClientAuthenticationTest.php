<?php

namespace Tests\Feature\Auth;

use App\Models\Client;
use Database\Factories\ClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_headers_are_rejected(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'CLIENT_AUTHENTICATION_FAILED');
    }

    public function test_unknown_client_id_is_rejected(): void
    {
        $this->withHeaders([
            'X-Client-Id' => 'does-not-exist',
            'X-Client-Secret' => 'whatever',
        ])->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'CLIENT_AUTHENTICATION_FAILED');
    }

    public function test_wrong_secret_is_rejected(): void
    {
        $client = Client::factory()->create();

        $this->withHeaders([
            'X-Client-Id' => $client->client_id,
            'X-Client-Secret' => 'wrong-secret',
        ])->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'CLIENT_AUTHENTICATION_FAILED');
    }

    public function test_inactive_client_is_rejected(): void
    {
        $client = Client::factory()->inactive()->create();

        $this->withHeaders([
            'X-Client-Id' => $client->client_id,
            'X-Client-Secret' => ClientFactory::PLAIN_SECRET,
        ])->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'CLIENT_AUTHENTICATION_FAILED');
    }

    public function test_valid_credentials_pass_client_authentication(): void
    {
        $client = Client::factory()->create();

        // Client auth passes, so this fails the end-user auth check
        // instead — a plain AuthenticationException renders with no "code"
        // field at all, unlike our AuthException family.
        $this->withHeaders([
            'X-Client-Id' => $client->client_id,
            'X-Client-Secret' => ClientFactory::PLAIN_SECRET,
        ])->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonMissingPath('code');
    }
}
