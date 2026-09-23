<?php

namespace App\Actions\Auth;

use App\Auth\Enums\AuthProviderName;
use App\Auth\AuthStrategyResolver;
use App\Auth\Guards\IdApiGuard;
use App\Auth\Strategies\Contracts\NormalizesInput;
use App\Auth\Strategies\Contracts\RegistersIdentity;
use App\Exceptions\Auth\AuthProviderDisabledException;
use App\Exceptions\Auth\UnsupportedAuthOperationException;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

class RegisterAction
{
    use AsAction;

    public function __construct(private readonly AuthStrategyResolver $resolver) {}

    /**
     * @param  array<string, mixed>  $data
     * @return string|null The token, or null if the identity still needs
     *                     to be verified before it can be used to log in.
     *
     * @throws AuthProviderDisabledException
     * @throws UnsupportedAuthOperationException
     */
    public function handle(AuthProviderName $provider, array $data): ?string
    {
        $strategy = $this->resolver->resolve($provider);

        if (! $strategy instanceof RegistersIdentity) {
            throw new UnsupportedAuthOperationException($provider->value);
        }

        if ($strategy instanceof NormalizesInput) {
            $data = $strategy->normalize($data);
        }

        Validator::make($data, $strategy->registrationRules())->validate();

        $result = $strategy->register($data);

        if (! $result->verified) {
            return null;
        }

        return IdApiGuard::current()->login($result->user);
    }
}
