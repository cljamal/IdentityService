<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\DeleteAccountAction;
use App\Auth\Enums\AuthProviderName;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsClient;
use Tests\Fakes\FakeOtpRepository;
use Tests\TestCase;

class PasswordResetIdentityBindingTest extends TestCase
{
    use ActsAsClient, RefreshDatabase;

    private FakeOtpRepository $otp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->otp = new FakeOtpRepository;
        $this->app->instance(OtpRepositoryInterface::class, $this->otp);
    }

    public function test_a_reset_already_in_progress_cannot_mutate_a_recreated_identity(): void
    {
        $providers = $this->app->make(AuthProviderRepositoryInterface::class);
        $otp = new class extends FakeOtpRepository
        {
            public ?Closure $duringConsume = null;

            /**
             * @template TResult
             *
             * @param  Closure(): TResult  $operation
             * @return TResult
             */
            public function consume(string $subject, string $code, Closure $operation): mixed
            {
                return parent::consume($subject, $code, function () use ($operation): mixed {
                    if ($this->duringConsume !== null) {
                        $duringConsume = $this->duringConsume;
                        $this->duringConsume = null;
                        $duringConsume();
                    }

                    return $operation();
                });
            }
        };
        $this->app->instance(OtpRepositoryInterface::class, $otp);

        $identifier = 'inflight@example.com';
        $provider = AuthProviderName::EmailPassword;
        $userA = $providers->createUserWithIdentity(
            $provider,
            $identifier,
            ['password' => Hash::make('account-a-password')],
            verified: true,
        );
        $identityA = $providers->findByIdentifier($provider, $identifier);
        $this->assertNotNull($identityA);

        $this->postJson('/api/auth/email-password/password/forgot', ['email' => $identifier])->assertOk();
        $oldCode = $otp->peek("{$this->defaultClient->id}:email-password-reset:{$identityA->id}:{$identifier}");
        $this->assertNotNull($oldCode);

        $userB = null;
        $otp->duringConsume = function () use ($providers, $userA, &$userB, $provider, $identifier): void {
            DeleteAccountAction::run($userA);
            $userB = $providers->createUserWithIdentity(
                $provider,
                $identifier,
                ['password' => Hash::make('account-b-password')],
                verified: true,
            );
        };

        $this->postJson('/api/auth/email-password/password/reset', [
            'email' => $identifier,
            'code' => $oldCode,
            'password' => 'attacker-password',
            'password_confirmation' => 'attacker-password',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_OTP')
            ->assertJsonMissingPath('data.access_token');

        $this->assertNotNull($userB);
        $identityB = $providers->findByIdentifier($provider, $identifier);
        $this->assertNotNull($identityB);
        $this->assertTrue(Hash::check('account-b-password', $identityB->meta['password']));
    }

    #[DataProvider('reusableIdentifiers')]
    public function test_a_reset_code_cannot_reset_a_recreated_identity(
        AuthProviderName $provider,
        string $field,
        string $identifier,
    ): void {
        /** @var AuthProviderRepositoryInterface $providers */
        $providers = $this->app->make(AuthProviderRepositoryInterface::class);
        $userA = $providers->createUserWithIdentity(
            $provider,
            $identifier,
            ['password' => Hash::make('account-a-password')],
            verified: true,
        );
        $identityA = $providers->findByIdentifier($provider, $identifier);
        $this->assertNotNull($identityA);

        $forgotRoute = "/api/auth/{$provider->value}/password/forgot";
        $resetRoute = "/api/auth/{$provider->value}/password/reset";
        $this->postJson($forgotRoute, [$field => $identifier])->assertOk();

        /** @var FakeOtpRepository $otp */
        $otp = $this->app->make(OtpRepositoryInterface::class);
        $oldCode = $otp->peek("{$this->defaultClient->id}:{$provider->value}-reset:{$identityA->id}:{$identifier}");
        $this->assertNotNull($oldCode);

        DeleteAccountAction::run($userA);

        $userB = $providers->createUserWithIdentity(
            $provider,
            $identifier,
            ['password' => Hash::make('account-b-password')],
            verified: true,
        );
        $identityB = $providers->findByIdentifier($provider, $identifier);
        $this->assertNotNull($identityB);
        $this->assertNotSame($identityA->id, $identityB->id);

        $this->postJson($resetRoute, [
            $field => $identifier,
            'code' => $oldCode,
            'password' => 'attacker-password',
            'password_confirmation' => 'attacker-password',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_OTP')
            ->assertJsonMissingPath('data.access_token');

        $identityB->refresh();
        $this->assertTrue(Hash::check('account-b-password', $identityB->meta['password']));

        $this->postJson($forgotRoute, [$field => $identifier])->assertOk();
        $newCode = $otp->peek("{$this->defaultClient->id}:{$provider->value}-reset:{$identityB->id}:{$identifier}");
        $this->assertNotNull($newCode);

        $resetResponse = $this->postJson($resetRoute, [
            $field => $identifier,
            'code' => $newCode,
            'password' => 'account-b-new-password',
            'password_confirmation' => 'account-b-new-password',
        ])->assertOk();
        $this->assertNotEmpty($resetResponse->json('data.access_token'));

        $identityB->refresh();
        $this->assertTrue(Hash::check('account-b-new-password', $identityB->meta['password']));
    }

    /**
     * @return array<string, array{AuthProviderName, string, string}>
     */
    public static function reusableIdentifiers(): array
    {
        return [
            'email' => [AuthProviderName::EmailPassword, 'email', 'reused@example.com'],
            'username' => [AuthProviderName::UsernamePassword, 'username', 'reused_username'],
        ];
    }
}
