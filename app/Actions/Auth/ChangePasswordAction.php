<?php

namespace App\Actions\Auth;

use App\Auth\Enums\AuthProviderName;
use App\Auth\AuthStrategyResolver;
use App\Auth\Strategies\Contracts\ChangesPassword;
use App\Exceptions\Auth\AuthProviderDisabledException;
use App\Exceptions\Auth\UnsupportedAuthOperationException;
use App\Models\User;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class ChangePasswordAction
{
    use AsAction;

    public function __construct(private AuthStrategyResolver $resolver) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws UnsupportedAuthOperationException
     * @throws AuthProviderDisabledException
     * @throws BindingResolutionException
     */
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
