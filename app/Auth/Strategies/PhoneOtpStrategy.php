<?php

namespace App\Auth\Strategies;

use App\Auth\CurrentClient;
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
use App\Events\Notifications\OtpCodeIssued;
use App\Exceptions\Auth\InvalidOtpException;
use App\Exceptions\Auth\NoLinkedIdentityException;
use App\Exceptions\Auth\OtpThrottledException;
use App\Models\User;
use App\Notifications\Otp\OtpChannel;
use App\Notifications\Otp\OtpDestination;
use App\Notifications\Otp\OtpPurpose;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\OtpRepositoryInterface;
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
        private CurrentClient $currentClient,
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
        $subject = $this->subject($phone);

        if (! $this->otp->canBeRequested($subject)) {
            throw new OtpThrottledException($this->otp->secondsUntilNextRequest($subject));
        }

        $code = $this->generateCode();

        $this->otp->put($subject, $code);

        OtpCodeIssued::dispatch(new OtpDestination(OtpChannel::Phone, $phone), $code, OtpPurpose::Login);
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
        $subject = $this->subject($phone);

        $actual = $this->otp->get($subject);

        if ($actual === null || ! hash_equals($actual, $code)) {
            throw new InvalidOtpException;
        }

        $user = $this->providers->firstOrCreateUser(AuthProviderName::PhoneOtp, $phone);

        // Код "сжигаем" только после успешного логина/создания юзера —
        // иначе сбой записи в БД потерял бы уже введённый верный код.
        $this->otp->forget($subject);

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
                    ->where('client_id', $this->currentClient->get()->id)
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

        $this->deletion->request(
            AuthProviderName::PhoneOtp,
            $user,
            new OtpDestination(OtpChannel::Phone, $identity->identifier),
        );
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
     * The same phone can legitimately request/hold an OTP under different
     * clients at once — the Redis key has to say which client it's for,
     * or a code issued for one client would verify under another's.
     */
    private function subject(string $phone): string
    {
        return "{$this->currentClient->get()->id}:{$phone}";
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
}
