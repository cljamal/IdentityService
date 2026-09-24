<?php

namespace Tests\Feature\Auth;

use App\Models\AuthProvider;
use App\Models\Client;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Database\Factories\ClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fakes\FakeOtpRepository;
use Tests\TestCase;

/**
 * The core guarantee of the multi-tenant redesign: two clients sharing one
 * instance/database never see each other's identities, even when the same
 * identifier, access token, refresh token, or OTP code is involved.
 */
class ClientIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_identifier_registers_independently_under_different_clients(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();

        $this->registerUsernamePassword($clientA, 'shared_user')->assertOk();
        $this->registerUsernamePassword($clientB, 'shared_user')->assertOk();

        $identities = AuthProvider::query()->where('identifier', 'shared_user')->get();

        $this->assertCount(2, $identities);
        $this->assertNotSame($identities[0]->user_id, $identities[1]->user_id);
        $this->assertNotSame($identities[0]->client_id, $identities[1]->client_id);
    }

    public function test_login_fails_under_a_different_client_than_the_one_registered_with(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();

        $this->registerUsernamePassword($clientA, 'only_in_a')->assertOk();

        $this->withHeaders($this->headersFor($clientB))
            ->postJson('/api/auth/username-password/login', [
                'username' => 'only_in_a',
                'password' => 'password123',
            ])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    public function test_an_access_token_minted_under_one_client_is_rejected_under_another(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();

        $accessToken = $this->registerUsernamePassword($clientA, 'cross_client_access')
            ->assertOk()
            ->json('data.access_token');

        $this->withHeaders($this->headersFor($clientB))
            ->withToken($accessToken)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_a_refresh_token_minted_under_one_client_is_rejected_under_another(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();

        $refreshToken = $this->registerUsernamePassword($clientA, 'cross_client_refresh')
            ->assertOk()
            ->json('data.refresh_token');

        $this->withHeaders($this->headersFor($clientB))
            ->postJson('/api/auth/refresh', ['refresh_token' => $refreshToken])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'INVALID_REFRESH_TOKEN');
    }

    public function test_an_otp_code_requested_under_one_client_does_not_verify_under_another(): void
    {
        $this->app->singleton(OtpRepositoryInterface::class, FakeOtpRepository::class);

        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();

        $this->withHeaders($this->headersFor($clientA))
            ->postJson('/api/auth/phone-otp/otp', ['phone' => '998901234567'])
            ->assertOk();

        /** @var FakeOtpRepository $otp */
        $otp = $this->app->make(OtpRepositoryInterface::class);
        $code = $otp->peek("{$clientA->id}:998901234567");

        $this->withHeaders($this->headersFor($clientB))
            ->postJson('/api/auth/phone-otp/login', ['phone' => '998901234567', 'code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_OTP');
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(Client $client): array
    {
        return [
            'X-Client-Id' => $client->client_id,
            'X-Client-Secret' => ClientFactory::PLAIN_SECRET,
        ];
    }

    /**
     * @return TestResponse<Response>
     */
    private function registerUsernamePassword(Client $client, string $username): TestResponse
    {
        return $this->withHeaders($this->headersFor($client))
            ->postJson('/api/auth/username-password/register', [
                'username' => $username,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
    }
}
