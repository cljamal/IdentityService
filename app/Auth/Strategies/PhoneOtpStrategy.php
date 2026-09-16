<?php

namespace App\Auth\Strategies;

use App\Auth\AuthProviderName;
use App\Auth\Strategies\Contracts\AuthStrategy;
use App\Auth\Strategies\Contracts\IssuesVerificationCode;
use App\Exceptions\Auth\InvalidOtpException;
use App\Exceptions\Auth\OtpThrottledException;
use App\Models\User;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Illuminate\Support\Facades\Log;

class PhoneOtpStrategy implements AuthStrategy, IssuesVerificationCode
{
    private const PHONE_RULE = ['required', 'string', 'regex:/^\+?[1-9]\d{7,14}$/'];

    public function __construct(
        private readonly OtpRepositoryInterface $otp,
        private readonly AuthProviderRepositoryInterface $providers,
    ) {
    }

    public function codeRules(): array
    {
        return ['phone' => self::PHONE_RULE];
    }

    public function sendCode(array $data): void
    {
        $phone = $data['phone'];

        if (! $this->otp->canBeRequested($phone)) {
            throw new OtpThrottledException($this->otp->secondsUntilNextRequest($phone));
        }

        $code = $this->generateCode();

        $this->otp->put($phone, $code);

        $this->dispatch($phone, $code);
    }

    public function rules(): array
    {
        return [
            'phone' => self::PHONE_RULE,
            'code' => ['required', 'digits:4'],
        ];
    }

    public function authenticate(array $data): User
    {
        $phone = $data['phone'];
        $code = $data['code'];

        $actual = $this->otp->get($phone);

        if ($actual === null || ! hash_equals($actual, $code)) {
            throw new InvalidOtpException();
        }

        $this->otp->forget($phone);

        return $this->providers->firstOrCreateUser(AuthProviderName::PhoneOtp, $phone);
    }

    private function generateCode(): string
    {
        if (app()->environment('local')) {
            return '1111';
        }

        return (string) random_int(1000, 9999);
    }

    private function dispatch(string $phone, string $code): void
    {
        // TODO: подключить реальный SMS-шлюз вместо лога.
        Log::info("OTP for {$phone}: {$code}");
    }
}
