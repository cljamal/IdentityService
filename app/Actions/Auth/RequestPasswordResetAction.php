<?php

namespace App\Actions\Auth;

use App\Auth\AuthStrategyResolver;
use App\Auth\Enums\AuthProviderName;
use App\Auth\Strategies\Contracts\NormalizesInput;
use App\Auth\Strategies\Contracts\ResetsPassword;
use App\Exceptions\Auth\UnsupportedAuthOperationException;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class RequestPasswordResetAction
{
    use AsAction;

    public function __construct(private AuthStrategyResolver $resolver) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(AuthProviderName $provider, array $data): void
    {
        $strategy = $this->resolver->resolve($provider);

        if (! $strategy instanceof ResetsPassword) {
            throw new UnsupportedAuthOperationException($provider->value);
        }

        if ($strategy instanceof NormalizesInput) {
            $data = $strategy->normalize($data);
        }

        Validator::make($data, $strategy->passwordResetRequestRules())->validate();

        $strategy->requestPasswordReset($data);
    }
}
