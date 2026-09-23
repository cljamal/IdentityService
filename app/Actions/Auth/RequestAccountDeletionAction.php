<?php

namespace App\Actions\Auth;

use App\Auth\AuthStrategyResolver;
use App\Auth\Enums\AuthProviderName;
use App\Auth\Strategies\Contracts\ConfirmsDeletion;
use App\Exceptions\Auth\UnsupportedAuthOperationException;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class RequestAccountDeletionAction
{
    use AsAction;

    public function __construct(private AuthStrategyResolver $resolver) {}

    public function handle(AuthProviderName $provider, User $user): void
    {
        $strategy = $this->resolver->resolve($provider);

        if (! $strategy instanceof ConfirmsDeletion) {
            throw new UnsupportedAuthOperationException($provider->value);
        }

        $strategy->requestDeletion($user);
    }
}
