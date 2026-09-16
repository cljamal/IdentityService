<?php

namespace App\Auth\Strategies;

use App\Auth\AuthProviderName;
use Illuminate\Validation\Rule;

class EmailPasswordStrategy extends PasswordStrategy
{
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
}
