<?php

namespace Tests\Feature\Auth;

use App\Models\Client;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Database\Factories\ClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeOtpRepository;
use Tests\TestCase;

class OtpLoginConsumptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->singleton(OtpRepositoryInterface::class, FakeOtpRepository::class);
    }

    public function test_phone_login_code_cannot_create_a_second_session_or_user(): void
    {
        $client = Client::factory()->create();
        $headers = [
            'X-Client-Id' => $client->client_id,
            'X-Client-Secret' => ClientFactory::PLAIN_SECRET,
        ];
        $phone = '998901234567';

        $this->withHeaders($headers)->postJson('/api/auth/phone-otp/otp', ['phone' => $phone])->assertOk();
        /** @var FakeOtpRepository $otp */
        $otp = $this->app->make(OtpRepositoryInterface::class);
        $code = $otp->peek("{$client->id}:{$phone}");

        $this->withHeaders($headers)->postJson('/api/auth/phone-otp/login', [
            'phone' => $phone,
            'code' => $code,
        ])->assertOk();

        $this->withHeaders($headers)->postJson('/api/auth/phone-otp/login', [
            'phone' => $phone,
            'code' => $code,
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_OTP');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('auth_sessions', 1);
    }
}
