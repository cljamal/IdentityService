<?php

namespace Tests\Feature\Auth;

use App\Models\Client;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Database\Factories\ClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeOtpRepository;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_limit_follows_target_username_with_bearer_tokens_and_isolated_by_client_and_operation(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();
        $loginPath = '/api/auth/username-password/login';
        $tokens = [];
        foreach (['rate_limit_owner_one', 'rate_limit_owner_two'] as $username) {
            $tokens[] = $this->withHeaders($this->headersFor($clientA))
                ->postJson('/api/auth/username-password/register', [
                    'username' => $username,
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                ])
                ->assertOk()
                ->json('data.access_token');
        }
        $otp = new FakeOtpRepository;
        $this->app->instance(OtpRepositoryInterface::class, $otp);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->withHeaders($this->headersFor($clientA))
                ->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($attempt + 1)])
                ->withToken($tokens[$attempt % count($tokens)])
                ->postJson($loginPath, ['username' => 'Rate_Limited_User', 'password' => 'password123'])
                ->assertUnauthorized();
        }

        $this->withHeaders([...$this->headersFor($clientA), 'Authorization' => ''])
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.8'])
            ->postJson($loginPath, ['username' => 'Rate_Limited_User', 'password' => 'password123'])
            ->assertStatus(429);

        $this->withHeaders($this->headersFor($clientB))
            ->postJson($loginPath, ['username' => 'Rate_Limited_User', 'password' => 'password123'])
            ->assertUnauthorized();

        $this->withHeaders($this->headersFor($clientA))
            ->postJson('/api/auth/username-password/register', [
                'username' => 'Rate_Limited_User',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertOk();
    }

    public function test_email_login_limit_uses_the_strategy_case_folded_email(): void
    {
        $client = Client::factory()->create();
        $path = '/api/auth/email-password/login';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $email = $attempt % 2 === 0 ? 'Target@Example.com' : ' target@example.COM ';
            $this->withHeaders($this->headersFor($client))
                ->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($attempt + 20)])
                ->postJson($path, ['email' => $email, 'password' => 'password123'])
                ->assertUnauthorized();
        }

        $this->withHeaders($this->headersFor($client))
            ->postJson($path, ['email' => 'TARGET@example.com', 'password' => 'password123'])
            ->assertStatus(429);
    }

    public function test_phone_otp_limit_uses_the_strategy_digits_only_phone(): void
    {
        $client = Client::factory()->create();
        $this->app->instance(OtpRepositoryInterface::class, new FakeOtpRepository);
        $path = '/api/auth/phone-otp/login';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $phone = $attempt % 2 === 0 ? '+998 90 123 45 67' : '998(90)123-45-67';
            $this->withHeaders($this->headersFor($client))
                ->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($attempt + 40)])
                ->postJson($path, ['phone' => $phone, 'code' => '1234'])
                ->assertUnprocessable();
        }

        $this->withHeaders($this->headersFor($client))
            ->postJson($path, ['phone' => '998901234567', 'code' => '1234'])
            ->assertStatus(429);
    }

    /** @return array<string, string> */
    private function headersFor(Client $client): array
    {
        return [
            'X-Client-Id' => $client->client_id,
            'X-Client-Secret' => ClientFactory::PLAIN_SECRET,
        ];
    }
}
