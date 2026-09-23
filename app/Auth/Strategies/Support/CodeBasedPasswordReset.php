<?php

namespace App\Auth\Strategies\Support;

use App\Auth\Enums\AuthProviderName;
use App\Auth\History\IdentityChangeAction;
use App\Auth\Strategies\Concerns\GeneratesVerificationCode;
use App\Events\Notifications\OtpCodeIssued;
use App\Exceptions\Auth\InvalidOtpException;
use App\Exceptions\Auth\OtpThrottledException;
use App\Models\User;
use App\Notifications\Otp\OtpDestination;
use App\Notifications\Otp\OtpPurpose;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\IdentityChangeLogRepositoryInterface;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Illuminate\Support\Facades\Hash;

/**
 * Shared request/confirm mechanics for a code-based password reset,
 * reused by any PasswordStrategy that supports one. Deciding *who* to
 * notify (identifier itself, a resolved rescue contact, ...) stays with
 * the calling strategy — this class is blind to that.
 */
final readonly class CodeBasedPasswordReset
{
    use GeneratesVerificationCode;

    public function __construct(
        private OtpRepositoryInterface $otp,
        private AuthProviderRepositoryInterface $providers,
        private IdentityChangeLogRepositoryInterface $history,
    ) {}

    /**
     * @throws OtpThrottledException
     */
    public function request(AuthProviderName $provider, string $identifier, ?OtpDestination $destination): void
    {
        $subject = $this->subject($provider, $identifier);

        if (! $this->otp->canBeRequested($subject)) {
            throw new OtpThrottledException($this->otp->secondsUntilNextRequest($subject));
        }

        $code = $this->generateCode();

        // Всегда пишем код и cooldown, даже если $destination пуст — иначе
        // по разнице в throttling можно понять, существует ли identity/контакт.
        $this->otp->put($subject, $code);

        if ($destination !== null) {
            OtpCodeIssued::dispatch($destination, $code, OtpPurpose::PasswordReset);
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
            throw new InvalidOtpException;
        }

        $identity = $this->providers->findByIdentifier($provider, $identifier);

        if (! $identity) {
            throw new InvalidOtpException;
        }

        $this->providers->updateSecret($identity, ['password' => Hash::make($newPassword)]);

        $user = $identity->userOrFail();

        // Успешное подтверждение кода на тот же канал — точно такое же
        // доказательство владения, как и верификация при регистрации.
        // Иначе аккаунт, ни разу не подтверждённый при регистрации, мог
        // бы навсегда остаться заблокированным на логине даже после
        // легитимного сброса пароля через тот же email/rescue-контакт.
        $this->providers->markVerified($provider, $user);

        $this->history->log($user, $provider, IdentityChangeAction::PasswordReset, null, null);

        // Код "сжигаем" только после успешной записи — иначе сбой записи
        // потерял бы уже введённый верный код без всякой пользы для юзера.
        $this->otp->forget($subject);

        return $user;
    }

    private function subject(AuthProviderName $provider, string $identifier): string
    {
        return "{$provider->value}-reset:{$identifier}";
    }
}
