<?php

namespace App\Actions\Auth;

use App\Auth\AuthStrategyResolver;
use App\Auth\Enums\AuthProviderName;
use App\Auth\Strategies\Contracts\NormalizesInput;
use App\Auth\Strategies\Contracts\ResendsRegistrationCode;
use App\Exceptions\Auth\UnsupportedAuthOperationException;
use App\Notifications\Otp\SmsTemplate;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class ResendRegistrationCodeAction
{
    use AsAction;

    public function __construct(private AuthStrategyResolver $resolver) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(AuthProviderName $provider, array $data): void
    {
        $strategy = $this->resolver->resolve($provider);

        if (! $strategy instanceof ResendsRegistrationCode) {
            throw new UnsupportedAuthOperationException($provider->value);
        }

        if ($strategy instanceof NormalizesInput) {
            $data = $strategy->normalize($data);
        }

        Validator::make($data, $strategy->registrationResendRules())->validate();
        $strategy->resendRegistrationCode($data, SmsTemplate::fromInput($data));
    }
}
