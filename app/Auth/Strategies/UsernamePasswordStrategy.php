<?php

namespace App\Auth\Strategies;

use App\Auth\AuthProviderName;
use App\Auth\Rescue\RescueContactResolver;
use App\Auth\Strategies\Contracts\ResetsPassword;
use App\Auth\Strategies\Support\CodeBasedPasswordReset;
use App\Auth\Strategies\Support\RegistrationVerifier;
use App\Exceptions\Auth\UnsupportedAuthOperationException;
use App\Models\User;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use Illuminate\Validation\Rule;

class UsernamePasswordStrategy extends PasswordStrategy implements ResetsPassword
{
    public function __construct(
        AuthProviderRepositoryInterface $providers,
        RegistrationVerifier $verification,
        private readonly CodeBasedPasswordReset $reset,
        private readonly RescueContactResolver $rescue,
    ) {
        parent::__construct($providers, $verification);
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
            'username' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('auth_providers', 'identifier')
                    ->where('provider', AuthProviderName::UsernamePassword->value),
            ],
        ];
    }

    /**
     * "username" has no channel of its own — only send a code if we can
     * resolve a rescue contact for the freshly created user; otherwise
     * there's nothing to prove ownership of, so auto-verify.
     */
    protected function beginVerification(User $user, string $identifier): bool
    {
        $contact = $this->rescue->resolve($user);

        if (! $contact) {
            $this->providers->markVerified($this->provider(), $user);

            return true;
        }

        $this->verification->send($this->provider(), $identifier, $contact);

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
        $this->guardRescueEnabled();

        $username = $data['username'];
        $identity = $this->providers->findByIdentifier($this->provider(), $username);
        // Orphaned identity (user удалён) должна выглядеть так же, как
        // "не найдено" — иначе TypeError/500 сам стал бы каналом энумерации.
        $contact = $identity?->user ? $this->rescue->resolve($identity->user) : null;

        $this->reset->request($this->provider(), $username, $contact);
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
     */
    public function resetPassword(array $data): User
    {
        $this->guardRescueEnabled();

        return $this->reset->confirm($this->provider(), $data['username'], $data['code'], $data['password']);
    }

    /**
     * "username" has no delivery channel of its own — only proceed if a
     * rescue contact table is actually configured (see config/identity.php).
     */
    private function guardRescueEnabled(): void
    {
        if (blank(config('identity.username_password_rescue.table'))) {
            throw new UnsupportedAuthOperationException($this->provider()->value);
        }
    }
}
