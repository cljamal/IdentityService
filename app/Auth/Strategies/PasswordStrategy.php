<?php

namespace App\Auth\Strategies;

use App\Auth\AuthProviderName;
use App\Auth\Strategies\Contracts\AuthStrategy;
use App\Auth\Strategies\Contracts\ChangesPassword;
use App\Auth\Strategies\Contracts\RegistersIdentity;
use App\Auth\Strategies\Support\RegistrationResult;
use App\Auth\Strategies\Support\RegistrationVerifier;
use App\Exceptions\Auth\IdentityNotVerifiedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Models\User;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use Illuminate\Support\Facades\Hash;

/**
 * Shared logic for identifier+password providers. Unlike PhoneOtpStrategy,
 * a password never proves ownership of the identifier by itself, so the
 * identity must go through an explicit register() step — authenticate()
 * only ever verifies, it never auto-creates.
 */
abstract class PasswordStrategy implements AuthStrategy, RegistersIdentity, ChangesPassword
{
    /**
     * A valid, arbitrary bcrypt hash used only to keep authenticate()'s
     * timing constant when no identity is found — Hash::check()'s cost
     * depends on the hash's cost factor, not its content.
     */
    private const DUMMY_HASH = '$2y$12$CwTycUXWue0Thq9StjUM0uJ8Ffx5DZOG.iP4XCTnhSEHIZQ0BEqiG';

    public function __construct(
        protected readonly AuthProviderRepositoryInterface $providers,
        protected readonly RegistrationVerifier $verification,
    ) {
    }

    abstract protected function provider(): AuthProviderName;

    abstract protected function identifierField(): string;

    /**
     * Rules for the identifier alone, including uniqueness — used during
     * registration. rules() (login) intentionally skips the uniqueness
     * check since the identity is expected to already exist there.
     */
    abstract protected function identifierRules(): array;

    public function authenticate(array $data): User
    {
        $identity = $this->providers->findByIdentifier($this->provider(), $data[$this->identifierField()]);

        // Всегда проверяем хэш (даже фиктивный), чтобы отсутствие identity
        // не отличалось по времени ответа от неверного пароля — иначе это
        // канал для энумерации существующих email/username.
        $hash = $identity?->meta['password'] ?? self::DUMMY_HASH;
        $valid = Hash::check($data['password'], $hash);

        if (! $identity || ! $valid) {
            throw new InvalidCredentialsException();
        }

        if (! $identity->verified_at) {
            throw new IdentityNotVerifiedException($this->provider()->value);
        }

        return $identity->userOrFail();
    }

    public function registrationRules(): array
    {
        return [
            ...$this->identifierRules(),
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function register(array $data): RegistrationResult
    {
        $identifier = $data[$this->identifierField()];

        $user = $this->providers->createUserWithIdentity(
            $this->provider(),
            $identifier,
            ['password' => Hash::make($data['password'])],
        );

        $verified = $this->beginVerification($user, $identifier);

        return new RegistrationResult($user, $verified);
    }

    /**
     * Kick off proving ownership of the identifier right after registration.
     * Default: no delivery channel exists for this provider, so there's
     * nothing to prove — auto-verify immediately.
     *
     * @return bool Whether the identity ended up verified immediately.
     */
    protected function beginVerification(User $user, string $identifier): bool
    {
        $this->providers->markVerified($this->provider(), $user);

        return true;
    }

    public function registrationVerificationRules(): array
    {
        return [
            $this->identifierField() => ['required', 'string'],
            'code' => ['required', 'digits:4'],
        ];
    }

    public function verifyRegistration(array $data): User
    {
        return $this->verification->confirm($this->provider(), $data[$this->identifierField()], $data['code']);
    }

    public function changePasswordRules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function changePassword(User $user, array $data): void
    {
        $identity = $this->providers->findByUser($this->provider(), $user);

        if (! $identity || ! Hash::check($data['current_password'], $identity->meta['password'] ?? '')) {
            throw new InvalidCredentialsException();
        }

        $this->providers->updateSecret($identity, ['password' => Hash::make($data['password'])]);
    }
}
