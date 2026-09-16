<?php

namespace App\Auth\Strategies;

use App\Auth\AuthProviderName;
use App\Auth\Strategies\Contracts\ResetsPassword;
use App\Auth\Strategies\Support\CodeBasedPasswordReset;
use App\Models\User;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use Illuminate\Validation\Rule;

class EmailPasswordStrategy extends PasswordStrategy implements ResetsPassword
{
    public function __construct(
        AuthProviderRepositoryInterface $providers,
        private readonly CodeBasedPasswordReset $reset,
    ) {
        parent::__construct($providers);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
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
