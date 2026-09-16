<?php

namespace App\Actions\Auth;

use App\Auth\AuthProviderName;
use App\Auth\AuthStrategyResolver;
use App\Auth\Strategies\Contracts\ChangesPassword;
use App\Exceptions\Auth\UnsupportedAuthOperationException;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

class ChangePasswordAction
{
    use AsAction;

    public function __construct(private readonly AuthStrategyResolver $resolver)
    {
    }

    public function handle(AuthProviderName $provider, User $user, array $data): void
    {
        $strategy = $this->resolver->resolve($provider);

        if (! $strategy instanceof ChangesPassword) {
            throw new UnsupportedAuthOperationException($provider->value);
        }

        Validator::make($data, $strategy->changePasswordRules())->validate();

        $strategy->changePassword($user, $data);
    }
}
