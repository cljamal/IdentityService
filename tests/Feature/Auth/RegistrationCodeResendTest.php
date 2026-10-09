<?php

namespace Tests\Feature\Auth;

use App\Models\AuthProvider;
use App\Models\Client;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Database\Factories\ClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeOtpRepository;
use Tests\TestCase;

class RegistrationCodeResendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->singleton(OtpRepositoryInterface::class, FakeOtpRepository::class);
    }

    public function test_resend_reuses_unverified_account_and_invalidates_the_previous_code(): void
    {
        $client = Client::factory()->create();
        $headers = $this->headersFor($client);
        $email = 'pending@example.com';

        $this->withHeaders($headers)->postJson('/api/auth/email-password/register', [
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertOk();

        $identity = AuthProvider::query()->where('identifier', $email)->firstOrFail();
        $passwordHash = $identity->meta['password'];
        /** @var FakeOtpRepository $otp */
        $otp = $this->app->make(OtpRepositoryInterface::class);
        $subject = "{$client->id}:email-password-verify:{$email}";
        $oldCode = $otp->peek($subject);
        $oldVersion = $otp->peekVersion($subject);

        $this->travel(60)->seconds();
        $this->withHeaders($headers)->postJson('/api/auth/email-password/register/resend', ['email' => $email])
            ->assertOk();

        $identity->refresh();
        $this->assertSame($passwordHash, $identity->meta['password']);
        $this->assertNull($identity->verified_at);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('auth_sessions', 0);

        $newCode = $otp->peek($subject);
        $this->assertGreaterThan($oldVersion, $otp->peekVersion($subject));

        if ($oldCode !== $newCode) {
            $this->withHeaders($headers)->postJson('/api/auth/email-password/register/verify', [
                'email' => $email,
                'code' => $oldCode,
            ])->assertStatus(422)->assertJsonPath('code', 'INVALID_OTP');
        }

        $this->withHeaders($headers)->postJson('/api/auth/email-password/register/verify', [
            'email' => $email,
            'code' => $newCode,
        ])->assertOk();

        $this->withHeaders($headers)->postJson('/api/auth/email-password/register/verify', [
            'email' => $email,
            'code' => $newCode,
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_OTP');
    }

    public function test_expired_registration_code_can_be_resent(): void
    {
        $client = Client::factory()->create();
        $headers = $this->headersFor($client);
        $email = 'expired@example.com';

        $this->withHeaders($headers)->postJson('/api/auth/email-password/register', [
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertOk();

        $this->travel(90)->seconds();
        $this->withHeaders($headers)->postJson('/api/auth/email-password/register/resend', ['email' => $email])
            ->assertOk();
    }

    public function test_resend_obeys_cooldown_and_rejects_other_clients_and_verified_identities(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();
        $email = 'isolated@example.com';
        $this->withHeaders($this->headersFor($clientA))->postJson('/api/auth/email-password/register', [
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertOk();

        $this->withHeaders($this->headersFor($clientA))
            ->postJson('/api/auth/email-password/register/resend', ['email' => $email])
            ->assertStatus(429)->assertJsonPath('code', 'OTP_THROTTLED');

        $this->withHeaders($this->headersFor($clientB))
            ->postJson('/api/auth/email-password/register/resend', ['email' => $email])
            ->assertStatus(422)->assertJsonPath('code', 'INVALID_OTP');

        $this->travel(60)->seconds();
        /** @var FakeOtpRepository $otp */
        $otp = $this->app->make(OtpRepositoryInterface::class);
        $code = $otp->peek("{$clientA->id}:email-password-verify:{$email}");
        $this->withHeaders($this->headersFor($clientA))->postJson('/api/auth/email-password/register/verify', [
            'email' => $email,
            'code' => $code,
        ])->assertOk();

        $this->withHeaders($this->headersFor($clientA))
            ->postJson('/api/auth/email-password/register/resend', ['email' => $email])
            ->assertStatus(422)->assertJsonPath('code', 'INVALID_OTP');
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
