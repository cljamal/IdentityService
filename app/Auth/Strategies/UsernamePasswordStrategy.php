<?php

namespace App\Auth\Strategies;

use App\Auth\AuthProviderName;
use Illuminate\Validation\Rule;

class UsernamePasswordStrategy extends PasswordStrategy
{
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
}
