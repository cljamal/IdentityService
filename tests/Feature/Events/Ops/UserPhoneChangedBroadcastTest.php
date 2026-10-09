<?php

namespace Tests\Feature\Events\Ops;

use App\Events\Ops\UserPhoneChanged;
use App\Models\User;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\ActsAsClient;
use Tests\Fakes\FakeOtpRepository;
use Tests\TestCase;

class UserPhoneChangedBroadcastTest extends TestCase
{
    use ActsAsClient, RefreshDatabase;

    public function test_confirming_a_phone_change_dispatches_user_phone_changed(): void
    {
        $this->app->singleton(OtpRepositoryInterface::class, FakeOtpRepository::class);
        /** @var FakeOtpRepository $otp */
        $otp = $this->app->make(OtpRepositoryInterface::class);

        $this->postJson('/api/auth/phone-otp/otp', ['phone' => '998901234567'])->assertOk();
        $loginResponse = $this->postJson('/api/auth/phone-otp/login', [
            'phone' => '998901234567',
            'code' => $otp->peek("{$this->defaultClient->id}:998901234567"),
        ])->assertOk();

        $user = User::query()->firstOrFail();
        $accessToken = $loginResponse->json('data.access_token');

        $this->withToken($accessToken)
            ->postJson('/api/auth/phone-otp/identifier/change', ['new_phone' => '998911111111'])
            ->assertOk();

        $oldCode = $otp->peekLatest("phone-otp-change-old:{$user->id}:");

        $this->withToken($accessToken)
            ->postJson('/api/auth/phone-otp/identifier/change/confirm-old', ['code' => $oldCode])
            ->assertOk();

        $newCode = $otp->peekLatest("phone-otp-change-new:{$user->id}:");

        Event::fake([UserPhoneChanged::class]);

        $this->withToken($accessToken)
            ->postJson('/api/auth/phone-otp/identifier/change/confirm-new', ['code' => $newCode])
            ->assertOk();

        Event::assertDispatched(UserPhoneChanged::class, function ($e) use ($user) {
            return $e->user->is($user)
                && $e->oldPhone === '998901234567'
                && $e->newPhone === '998911111111';
        });
    }
}
