<?php

namespace App\Auth\Strategies\Support;

use App\Auth\AuthProviderName;
use App\Auth\Strategies\Concerns\GeneratesVerificationCode;
use App\Exceptions\Auth\InvalidOtpException;
use App\Exceptions\Auth\OtpThrottledException;
use App\Models\User;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Shared request/confirm mechanics for a code-based password reset,
 * reused by any PasswordStrategy that supports one. Deciding *who* to
 * notify (identifier itself, a resolved rescue contact, ...) stays with
 * the calling strategy — this class is blind to that.
 */
class CodeBasedPasswordReset
{
    use GeneratesVerificationCode;

    public function __construct(
        private readonly OtpRepositoryInterface $otp,
        private readonly AuthProviderRepositoryInterface $providers,
    ) {
    }

    /**
     * @throws OtpThrottledException
     */
    public function request(AuthProviderName $provider, string $identifier, ?string $contact): void
    {
        $subject = $this->subject($provider, $identifier);

        if (! $this->otp->canBeRequested($subject)) {
            throw new OtpThrottledException($this->otp->secondsUntilNextRequest($subject));
        }

        $code = $this->generateCode();

        // Всегда пишем код и cooldown, даже если $contact пуст — иначе по
        // разнице в throttling можно понять, существует ли identity/контакт.
        $this->otp->put($subject, $code);

        if ($contact) {
            // TODO: подключить реальный email/SMS-шлюз вместо лога.
            Log::info("Password reset code for {$contact}: {$code}");
        }
    }

    /**
     * @throws InvalidOtpException
     */
    public function confirm(AuthProviderName $provider, string $identifier, string $code, string $newPassword): User
    {
        $subject = $this->subject($provider, $identifier);
        $actual = $this->otp->get($subject);

        if ($actual === null || ! hash_equals($actual, $code)) {
            throw new InvalidOtpException();
        }

        $identity = $this->providers->findByIdentifier($provider, $identifier);

        if (! $identity) {
            throw new InvalidOtpException();
        }

        $this->providers->updateSecret($identity, ['password' => Hash::make($newPassword)]);

        // Код "сжигаем" только после успешной записи — иначе сбой записи
        // потерял бы уже введённый верный код без всякой пользы для юзера.
        $this->otp->forget($subject);

        return $identity->userOrFail();
    }

    private function subject(AuthProviderName $provider, string $identifier): string
    {
        return "{$provider->value}-reset:{$identifier}";
    }
}
