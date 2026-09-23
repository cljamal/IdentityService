<?php

namespace App\Actions\Auth;

use App\Auth\Enums\AuthProviderName;
use App\Auth\AuthStrategyResolver;
use App\Auth\Strategies\Contracts\ConfirmsDeletion;
use App\Exceptions\Auth\UnsupportedAuthOperationException;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

class ConfirmAccountDeletionAction
{
    use AsAction;

    public function __construct(private readonly AuthStrategyResolver $resolver) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(AuthProviderName $provider, User $user, array $data): void
    {
        $strategy = $this->resolver->resolve($provider);

        if (! $strategy instanceof ConfirmsDeletion) {
            throw new UnsupportedAuthOperationException($provider->value);
        }

        Validator::make($data, $strategy->confirmDeletionRules())->validate();

        $strategy->confirmDeletion($user, $data);

        DeleteAccountAction::run($user);
    }
}
