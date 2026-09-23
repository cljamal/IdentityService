<?php

namespace App\Actions\Auth;

use App\Auth\AuthProviderName;
use App\Auth\AuthStrategyResolver;
use App\Auth\Strategies\Contracts\ChangesIdentifier;
use App\Auth\Strategies\Contracts\NormalizesInput;
use App\Exceptions\Auth\UnsupportedAuthOperationException;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

class RequestIdentifierChangeAction
{
    use AsAction;

    public function __construct(private readonly AuthStrategyResolver $resolver) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(AuthProviderName $provider, User $user, array $data): void
    {
        $strategy = $this->resolver->resolve($provider);

        if (! $strategy instanceof ChangesIdentifier) {
            throw new UnsupportedAuthOperationException($provider->value);
        }

        if ($strategy instanceof NormalizesInput) {
            $data = $strategy->normalize($data);
        }

        Validator::make($data, $strategy->changeRules($user))->validate();

        $strategy->requestChange($user, $data);
    }
}
