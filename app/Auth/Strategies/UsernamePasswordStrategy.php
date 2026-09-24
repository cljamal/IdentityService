<?php

namespace App\Auth\Strategies;

use App\Auth\CurrentClient;
use App\Auth\Enums\AuthProviderName;
use App\Auth\Rescue\RescueContactResolver;
use App\Auth\Strategies\Contracts\ResetsPassword;
use App\Auth\Strategies\Support\CodeBasedPasswordReset;
use App\Auth\Strategies\Support\RegistrationVerifier;
use App\Exceptions\Auth\InvalidOtpException;
use App\Models\User;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\IdentityChangeLogRepositoryInterface;

final readonly class UsernamePasswordStrategy extends PasswordStrategy implements ResetsPassword
{
    public function __construct(
        AuthProviderRepositoryInterface $providers,
        RegistrationVerifier $verification,
        IdentityChangeLogRepositoryInterface $history,
        CurrentClient $currentClient,
        private CodeBasedPasswordReset $reset,
        private RescueContactResolver $rescue,
    ) {
        parent::__construct($providers, $verification, $history, $currentClient);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    protected function provider(): AuthProviderName
    {
        return AuthProviderName::UsernamePassword;
    }

    protected function identifierField(): string
    {
        return 'username';
    }

    /**
     * @return array<string, mixed>
     */
    protected function identifierRules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255', 'alpha_dash', $this->uniqueIdentifierRule()],
        ];
    }

    /**
     * "username" has no channel of its own — only send a code if we can
     * resolve a rescue contact for the freshly created user; otherwise
     * there's nothing to prove ownership of, so auto-verify.
     */
    protected function beginVerification(User $user, string $identifier): bool
    {
        $destination = $this->rescue->resolve($user);

        if (! $destination) {
            $this->providers->markVerified($this->provider(), $user);

            return true;
        }

        $this->verification->send($this->provider(), $identifier, $destination);

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function passwordResetRequestRules(): array
    {
        return ['username' => ['required', 'string']];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function requestPasswordReset(array $data): void
    {
        $username = $data['username'];
        $identity = $this->providers->findByIdentifier($this->provider(), $username);
        // Orphaned identity (user удалён) должна выглядеть так же, как
        // "не найдено" — иначе TypeError/500 сам стал бы каналом энумерации.
        // Без рескью-контакта (не связан ни один другой провайдер и не
        // настроена rescue-таблица) код всё равно создаётся и сохраняется
        // ниже — просто никому не будет отправлен: то же самое разделение
        // "existence vs delivery", что и у email-password.
        $destination = $identity?->user ? $this->rescue->resolve($identity->user) : null;

        $this->reset->request($this->provider(), $username, $destination);
    }

    /**
     * @return array<string, mixed>
     */
    public function passwordResetRules(): array
    {
        return [
            'username' => ['required', 'string'],
            'code' => ['required', 'digits:4'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidOtpException
     */
    public function resetPassword(array $data): User
    {
        return $this->reset->confirm($this->provider(), $data['username'], $data['code'], $data['password']);
    }
}
