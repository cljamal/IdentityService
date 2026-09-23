<?php

namespace App\Auth\Strategies;

use App\Auth\Enums\AuthProviderName;
use App\Auth\Rules\AllowedPhoneCountry;
use App\Auth\Strategies\Concerns\GeneratesVerificationCode;
use App\Auth\Strategies\Contracts\AuthStrategy;
use App\Auth\Strategies\Contracts\ChangesIdentifier;
use App\Auth\Strategies\Contracts\ConfirmsDeletion;
use App\Auth\Strategies\Contracts\IssuesVerificationCode;
use App\Auth\Strategies\Contracts\NormalizesInput;
use App\Auth\Strategies\Support\AccountDeletionConfirmer;
use App\Auth\Strategies\Support\PhoneChangeCoordinator;
use App\Exceptions\Auth\InvalidOtpException;
use App\Exceptions\Auth\NoLinkedIdentityException;
use App\Exceptions\Auth\OtpThrottledException;
use App\Models\User;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

final readonly class PhoneOtpStrategy implements AuthStrategy, ChangesIdentifier, ConfirmsDeletion, IssuesVerificationCode, NormalizesInput
{
    use GeneratesVerificationCode;

    private const PHONE_FIELDS = ['phone', 'new_phone'];

    public function __construct(
        private OtpRepositoryInterface $otp,
        private AuthProviderRepositoryInterface $providers,
        private PhoneChangeCoordinator $phoneChange,
        private AccountDeletionConfirmer $deletion,
    ) {}

    /**
     * Flatten to digits-only (998 90 012-34-56 / +998 (90) 012 34 56 /
     * 998(90)0123456 → 998900123456) before anything else touches it —
     * validation, storage and Redis keys all assume this shape.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalize(array $data): array
    {
        foreach (self::PHONE_FIELDS as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = preg_replace('/\D+/', '', $data[$field]);
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function codeRules(): array
    {
        return ['phone' => $this->phoneRules()];
    }

    /**
     * @param  array<string, mixed>  $data
     */
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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => $this->phoneRules(),
            'code' => ['required', 'digits:4'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
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
     * @return array<string, mixed>
     */
    public function changeRules(User $user): array
    {
        $identity = $this->providers->findByUser(AuthProviderName::PhoneOtp, $user);

        return [
            'new_phone' => [
                ...$this->phoneRules(),
                Rule::unique('auth_providers', 'identifier')
                    ->where('provider', AuthProviderName::PhoneOtp->value)
                    ->ignore($identity?->id),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function requestChange(User $user, array $data): void
    {
        $this->phoneChange->requestChange($user, $data['new_phone']);
    }

    /**
     * @return array<string, mixed>
     */
    public function confirmOldRules(): array
    {
        return ['code' => ['required', 'digits:4']];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function confirmOld(User $user, array $data): void
    {
        $this->phoneChange->confirmOld($user, $data['code']);
    }

    /**
     * @return array<string, mixed>
     */
    public function confirmNewRules(): array
    {
        return ['code' => ['required', 'digits:4']];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function confirmNew(User $user, array $data): void
    {
        $this->phoneChange->confirmNew($user, $data['code']);
    }

    public function requestDeletion(User $user): void
    {
        $identity = $this->providers->findByUser(AuthProviderName::PhoneOtp, $user);

        if (! $identity) {
            throw new NoLinkedIdentityException(AuthProviderName::PhoneOtp->value);
        }

        $this->deletion->request(AuthProviderName::PhoneOtp, $user, $identity->identifier);
    }

    /**
     * @return array<string, mixed>
     */
    public function confirmDeletionRules(): array
    {
        return ['code' => ['required', 'digits:4']];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function confirmDeletion(User $user, array $data): void
    {
        $this->deletion->confirm(AuthProviderName::PhoneOtp, $user, $data['code']);
    }

    /**
     * Digits-only sanity check (post-normalize, so no +/spaces/dashes
     * survive to here) plus the country allow-list from config.
     *
     * @return array<int, mixed>
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
