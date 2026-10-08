<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\RenderOtpSmsAction;
use App\Auth\Enums\AuthProviderName;
use App\Auth\Guards\IdApiGuard;
use App\Auth\Rescue\RescueContactResolver;
use App\Events\Notifications\OtpCodeBroadcast;
use App\Events\Notifications\OtpCodeIssued;
use App\Models\AuthProvider;
use App\Models\User;
use App\Notifications\Otp\EmailNotifier;
use App\Notifications\Otp\OtpChannel;
use App\Notifications\Otp\OtpDestination;
use App\Notifications\Otp\OtpPurpose;
use App\Notifications\Otp\SmsNotifier;
use App\Notifications\Otp\SmsTemplate;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsClient;
use Tests\Fakes\FakeOtpRepository;
use Tests\TestCase;

class OtpSmsTemplateTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsClient;

    private ?MockInterface $smsMock = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.algo' => 'HS256',
            'jwt.secret' => str_repeat('sms-template-test-key-', 4),
        ]);
        $this->app->singleton(OtpRepositoryInterface::class, FakeOtpRepository::class);
        Event::fake([OtpCodeBroadcast::class]);
    }

    public function test_rendering_is_literal_and_cannot_replace_the_server_code_recursively(): void
    {
        $sms = SmsTemplate::fromInput(['sms' => [
            'template' => '{{service_name}}: {{code}}',
            'data' => ['service_name' => '{{code}}'],
        ]]);

        $this->assertSame('{{code}}: 4821', RenderOtpSmsAction::run('4821', $sms));
        $this->assertSame('Ваш код подтверждения: 4821', RenderOtpSmsAction::run('4821'));
    }

    public function test_phone_login_uses_custom_text_but_broadcasts_no_template_or_code(): void
    {
        $this->expectSms('998901234567', '/^Привет из сервиса! Код [0-9]{4}$/', OtpPurpose::Login);

        $this->postJson('/api/auth/phone-otp/otp', [
            'phone' => '998901234567',
            'sms' => ['template' => 'Привет из {{service_name}}! Код {{code}}', 'data' => ['service_name' => 'сервиса']],
        ])->assertOk();

        Event::assertDispatched(OtpCodeBroadcast::class, function (OtpCodeBroadcast $event): bool {
            $payload = $event->broadcastWith();
            $this->assertSame('********4567', $payload['contact']);
            $this->assertSame(['channel', 'purpose', 'contact', 'issued_at'], array_keys($payload));

            return true;
        });
    }

    public function test_omitting_sms_keeps_the_default_message(): void
    {
        $this->expectSms('998901234567', '/^Ваш код подтверждения: [0-9]{4}$/', OtpPurpose::Login);

        $this->postJson('/api/auth/phone-otp/otp', ['phone' => '998901234567'])->assertOk();
    }

    public function test_username_registration_can_deliver_its_template_to_a_rescue_phone(): void
    {
        $this->app->bind(RescueContactResolver::class, fn () => new class implements RescueContactResolver
        {
            public function resolve(User $user): OtpDestination
            {
                return new OtpDestination(OtpChannel::Phone, '998901234567');
            }
        });
        $this->expectSms('998901234567', '/^Регистрация [0-9]{4}$/', OtpPurpose::Registration);

        $this->postJson('/api/auth/username-password/register', [
            'username' => 'test_user',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'sms' => ['template' => 'Регистрация {{code}}'],
        ])->assertOk()->assertJsonMissingPath('data.access_token');
    }

    public function test_username_reset_delivers_custom_text_to_a_verified_linked_phone(): void
    {
        $user = $this->phoneUser();
        AuthProvider::query()->create([
            'user_id' => $user->id,
            'client_id' => $this->defaultClient->id,
            'provider' => AuthProviderName::UsernamePassword->value,
            'identifier' => 'test_user',
            'meta' => ['password' => Hash::make('password123')],
            'verified_at' => now(),
        ]);
        $this->expectSms('998901234567', '/^Сброс [0-9]{4}$/', OtpPurpose::PasswordReset);

        $this->postJson('/api/auth/username-password/password/forgot', [
            'username' => 'test_user', 'sms' => ['template' => 'Сброс {{code}}'],
        ])->assertOk();
    }

    public function test_phone_change_accepts_different_templates_for_the_two_destinations(): void
    {
        $user = $this->phoneUser();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);
        $this->expectSms('998901234567', '/^Старый номер [0-9]{4}$/', OtpPurpose::IdentifierChangeOld);
        $this->expectSms('998907654321', '/^Новый номер [0-9]{4}$/', OtpPurpose::IdentifierChangeNew);

        $this->withToken($tokens->accessToken)->postJson('/api/auth/phone-otp/identifier/change', [
            'new_phone' => '998907654321', 'sms' => ['template' => 'Старый номер {{code}}'],
        ])->assertOk();

        /** @var FakeOtpRepository $otp */
        $otp = $this->app->make(OtpRepositoryInterface::class);
        $this->postJson('/api/auth/phone-otp/identifier/change/confirm-old', [
            'code' => $otp->peek("phone-otp-change-old:{$user->id}"),
            'sms' => ['template' => 'Новый номер {{code}}'],
        ])->assertOk();
    }

    public function test_invalid_new_phone_template_keeps_the_old_code_and_omitting_sms_uses_the_default(): void
    {
        $user = $this->phoneUser();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);
        $this->expectSms('998901234567', '/^Старый номер [0-9]{4}$/', OtpPurpose::IdentifierChangeOld);
        $this->expectSms('998907654321', '/^Ваш код подтверждения: [0-9]{4}$/', OtpPurpose::IdentifierChangeNew);

        $this->withToken($tokens->accessToken)->postJson('/api/auth/phone-otp/identifier/change', [
            'new_phone' => '998907654321', 'sms' => ['template' => 'Старый номер {{code}}'],
        ])->assertOk();

        /** @var FakeOtpRepository $otp */
        $otp = $this->app->make(OtpRepositoryInterface::class);
        $oldCode = $otp->peek("phone-otp-change-old:{$user->id}");

        $this->postJson('/api/auth/phone-otp/identifier/change/confirm-old', [
            'code' => $oldCode, 'sms' => ['template' => 'Нет подстановки кода'],
        ])->assertUnprocessable()->assertJsonValidationErrors('sms.template');
        $this->assertSame($oldCode, $otp->peek("phone-otp-change-old:{$user->id}"));

        $this->postJson('/api/auth/phone-otp/identifier/change/confirm-old', ['code' => $oldCode])->assertOk();
    }

    public function test_account_deletion_accepts_a_template_in_the_request_body(): void
    {
        $user = $this->phoneUser();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);
        $this->expectSms('998901234567', '/^Удаление [0-9]{4}$/', OtpPurpose::AccountDeletion);

        $this->withToken($tokens->accessToken)->postJson('/api/auth/phone-otp/account/delete', [
            'sms' => ['template' => 'Удаление {{code}}'],
        ])->assertOk();
    }

    public function test_email_delivery_still_receives_the_code_and_not_the_sms_text(): void
    {
        $this->mock(EmailNotifier::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notify')->once()->with('user@example.com', \Mockery::pattern('/^[0-9]{4}$/'), OtpPurpose::Registration);
        });

        $this->postJson('/api/auth/email-password/register', [
            'email' => 'user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'sms' => ['template' => 'SMS only {{code}}'],
        ])->assertOk();
    }

    #[DataProvider('invalidSms')]
    public function test_invalid_sms_is_rejected_before_otp_is_stored(mixed $sms): void
    {
        Event::fake([OtpCodeIssued::class]);

        $this->postJson('/api/auth/phone-otp/otp', ['phone' => '998901234567', 'sms' => $sms])
            ->assertUnprocessable()->assertJsonStructure(['errors']);

        /** @var FakeOtpRepository $otp */
        $otp = $this->app->make(OtpRepositoryInterface::class);
        $this->assertNull($otp->peek("{$this->defaultClient->id}:998901234567"));
        $this->assertTrue($otp->canBeRequested("{$this->defaultClient->id}:998901234567"));
        Event::assertNotDispatched(OtpCodeIssued::class);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidSms(): array
    {
        return [
            'null' => [null],
            'empty object' => [[]],
            'string' => ['text'],
            'missing code' => [['template' => 'Hello']],
            'missing variable' => [['template' => '{{service_name}} {{code}}']],
            'reserved code' => [['template' => '{{code}}', 'data' => ['code' => '9999']]],
            'nested value' => [['template' => '{{code}}', 'data' => ['name' => ['nested']]]],
            'expression' => [['template' => '{{code}} {{ config("app.key") }}']],
            'unknown option' => [['template' => '{{code}}', 'sender' => 'custom']],
        ];
    }

    #[DataProvider('issuanceRoutes')]
    public function test_invalid_template_is_rejected_at_every_issuance_boundary(string $path): void
    {
        $user = $this->phoneUser();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);
        Event::fake([OtpCodeIssued::class]);

        $this->withToken($tokens->accessToken)->postJson($path, [
            'phone' => '998901234567', 'new_phone' => '998907654321',
            'email' => 'user@example.com', 'username' => 'test_user',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'code' => '1234', 'sms' => ['template' => 'No code'],
        ])->assertUnprocessable()->assertJsonValidationErrors('sms.template');

        $this->assertDatabaseCount('users', 1);
        Event::assertNotDispatched(OtpCodeIssued::class);
    }

    /** @return array<string, array{string}> */
    public static function issuanceRoutes(): array
    {
        return [
            'email registration' => ['/api/auth/email-password/register'],
            'username registration' => ['/api/auth/username-password/register'],
            'email recovery' => ['/api/auth/email-password/password/forgot'],
            'username recovery' => ['/api/auth/username-password/password/forgot'],
            'old phone' => ['/api/auth/phone-otp/identifier/change'],
            'new phone' => ['/api/auth/phone-otp/identifier/change/confirm-old'],
            'phone deletion' => ['/api/auth/phone-otp/account/delete'],
            'email deletion' => ['/api/auth/email-password/account/delete'],
        ];
    }

    private function phoneUser(): User
    {
        $user = User::factory()->for($this->defaultClient)->create();
        AuthProvider::query()->create([
            'user_id' => $user->id,
            'client_id' => $this->defaultClient->id,
            'provider' => AuthProviderName::PhoneOtp->value,
            'identifier' => '998901234567',
            'verified_at' => now(),
        ]);

        return $user;
    }

    private function expectSms(string $phone, string $pattern, OtpPurpose $purpose): void
    {
        $this->smsMock ??= $this->mock(SmsNotifier::class);
        $this->smsMock->shouldReceive('notify')->once()->with($phone, \Mockery::pattern($pattern), $purpose);
    }
}
