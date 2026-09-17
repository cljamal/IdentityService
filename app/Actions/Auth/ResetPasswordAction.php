<?php

namespace App\Actions\Auth;

use App\Auth\AuthProviderName;
use App\Auth\AuthStrategyResolver;
use App\Auth\Strategies\Contracts\ResetsPassword;
use App\Exceptions\Auth\UnsupportedAuthOperationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

class ResetPasswordAction
{
    use AsAction;

    public function __construct(private readonly AuthStrategyResolver $resolver)
    {
    }

    public function handle(AuthProviderName $provider, array $data): string
    {
        $strategy = $this->resolver->resolve($provider);

        if (! $strategy instanceof ResetsPassword) {
            throw new UnsupportedAuthOperationException($provider->value);
        }

        Validator::make($data, $strategy->passwordResetRules())->validate();

        $user = $strategy->resetPassword($data);

        return Auth::guard('id-api')->login($user);
    }
}
