<?php

namespace App\Auth\Strategies;

use App\Auth\AuthProviderName;
use App\Auth\Strategies\Contracts\NormalizesInput;
use App\Auth\Strategies\Contracts\ResetsPassword;
use App\Auth\Strategies\Support\CodeBasedPasswordReset;
use App\Auth\Strategies\Support\RegistrationVerifier;
use App\Models\User;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmailPasswordStrategy extends PasswordStrategy implements NormalizesInput, ResetsPassword
{
    public function __construct(
        AuthProviderRepositoryInterface $providers,
        RegistrationVerifier $verification,
        private readonly CodeBasedPasswordReset $reset,
    ) {
        parent::__construct($providers, $verification);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Case-fold the email before it's validated/looked up — otherwise
     * "Foo@Bar.com" and "foo@bar.com" could end up as different identities
     * depending on the DB's collation.
     */
    public function normalize(array $data): array
    {
        if (isset($data['email']) && is_string($data['email'])) {
            $data['email'] = Str::lower(trim($data['email']));
        }

        return $data;
    }

    protected function provider(): AuthProviderName
    {
        return AuthProviderName::EmailPassword;
    }

    protected function identifierField(): string
    {
        return 'email';
    }

    protected function identifierRules(): array
    {
        return [
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('auth_providers', 'identifier')
                    ->where('provider', AuthProviderName::EmailPassword->value),
            ],
        ];
    }

    /**
     * Email always has a channel — the address itself.
     */
    protected function beginVerification(User $user, string $identifier): bool
    {
        $this->verification->send($this->provider(), $identifier, $identifier);

        return false;
    }

    public function passwordResetRequestRules(): array
    {
        return ['email' => ['required', 'email']];
    }

    public function requestPasswordReset(array $data): void
    {
        $email = $data['email'];
        $identity = $this->providers->findByIdentifier($this->provider(), $email);

        // Канал доставки для email-password — сам identifier.
        $this->reset->request($this->provider(), $email, $identity ? $email : null);
    }

    public function passwordResetRules(): array
    {
        return [
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:4'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function resetPassword(array $data): User
    {
        return $this->reset->confirm($this->provider(), $data['email'], $data['code'], $data['password']);
    }
}
