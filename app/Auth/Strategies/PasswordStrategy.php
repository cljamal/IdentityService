<?php

namespace App\Auth\Strategies;

use App\Auth\AuthProviderName;
use App\Auth\Strategies\Contracts\AuthStrategy;
use App\Auth\Strategies\Contracts\ChangesPassword;
use App\Auth\Strategies\Contracts\RegistersIdentity;
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

    public function __construct(protected readonly AuthProviderRepositoryInterface $providers)
    {
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

        return $identity->userOrFail();
    }

    public function registrationRules(): array
    {
        return [
            ...$this->identifierRules(),
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function register(array $data): User
    {
        return $this->providers->createUserWithIdentity(
            $this->provider(),
            $data[$this->identifierField()],
            ['password' => Hash::make($data['password'])],
        );
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
