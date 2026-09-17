<?php

namespace App\Auth\Strategies;

use App\Auth\AuthProviderName;
use App\Auth\Rules\AllowedPhoneCountry;
use App\Auth\Strategies\Concerns\GeneratesVerificationCode;
use App\Auth\Strategies\Contracts\AuthStrategy;
use App\Auth\Strategies\Contracts\IssuesVerificationCode;
use App\Auth\Strategies\Contracts\NormalizesInput;
use App\Exceptions\Auth\InvalidOtpException;
use App\Exceptions\Auth\OtpThrottledException;
use App\Models\User;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Illuminate\Support\Facades\Log;

class PhoneOtpStrategy implements AuthStrategy, IssuesVerificationCode, NormalizesInput
{
    use GeneratesVerificationCode;

    public function __construct(
        private readonly OtpRepositoryInterface $otp,
        private readonly AuthProviderRepositoryInterface $providers,
    ) {}

    /**
     * Flatten to digits-only (998 90 012-34-56 / +998 (90) 012 34 56 /
     * 998(90)0123456 → 998900123456) before anything else touches it —
     * validation, storage and Redis keys all assume this shape.
     */
    public function normalize(array $data): array
    {
        if (isset($data['phone']) && is_string($data['phone'])) {
            $data['phone'] = preg_replace('/\D+/', '', $data['phone']);
        }

        return $data;
    }

    public function codeRules(): array
    {
        return ['phone' => $this->phoneRules()];
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
            'phone' => $this->phoneRules(),
            'code' => ['required', 'digits:4'],
        ];
    }

    public function authenticate(array $data): User
    {
        $phone = $data['phone'];
        $code = $data['code'];

        $actual = $this->otp->get($phone);

        if ($actual === null || ! hash_equals($actual, $code)) {
            throw new InvalidOtpException;
        }

        $user = $this->providers->firstOrCreateUser(AuthProviderName::PhoneOtp, $phone);

        // Код "сжигаем" только после успешного логина/создания юзера —
        // иначе сбой записи в БД потерял бы уже введённый верный код.
        $this->otp->forget($phone);

        return $user;
    }

    /**
     * Digits-only sanity check (post-normalize, so no +/spaces/dashes
     * survive to here) plus the country allow-list from config.
     */
    private function phoneRules(): array
    {
        return ['required', 'string', 'regex:/^[1-9]\d{8,14}$/', new AllowedPhoneCountry];
    }

    private function dispatch(string $phone, string $code): void
    {
        // TODO: подключить реальный SMS-шлюз вместо лога.
        Log::info("OTP for {$phone}: {$code}");
    }
}
