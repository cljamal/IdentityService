<?php

namespace App\Actions\Auth;

use App\Auth\AuthProviderName;
use App\Auth\AuthStrategyResolver;
use App\Auth\Strategies\Contracts\ConfirmsDeletion;
use App\Exceptions\Auth\UnsupportedAuthOperationException;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class RequestAccountDeletionAction
{
    use AsAction;

    public function __construct(private readonly AuthStrategyResolver $resolver) {}

    public function handle(AuthProviderName $provider, User $user): void
    {
        $strategy = $this->resolver->resolve($provider);

        if (! $strategy instanceof ConfirmsDeletion) {
            throw new UnsupportedAuthOperationException($provider->value);
        }

        $strategy->requestDeletion($user);
    }
}
