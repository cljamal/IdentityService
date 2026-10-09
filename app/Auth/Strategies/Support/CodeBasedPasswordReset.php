<?php

namespace App\Auth\Strategies\Support;

use App\Auth\CurrentClient;
use App\Auth\Enums\AuthProviderName;
use App\Auth\History\IdentityChangeAction;
use App\Auth\Strategies\Concerns\GeneratesVerificationCode;
use App\Events\Notifications\OtpCodeIssued;
use App\Exceptions\Auth\InvalidOtpException;
use App\Exceptions\Auth\OtpThrottledException;
use App\Models\AuthProvider;
use App\Models\User;
use App\Notifications\Otp\OtpDestination;
use App\Notifications\Otp\OtpPurpose;
use App\Notifications\Otp\SmsTemplate;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\IdentityChangeLogRepositoryInterface;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Closure;
use Illuminate\Support\Facades\DB;
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
        private CurrentClient $currentClient,
    ) {}

    /**
     * @throws OtpThrottledException
     */
    public function request(AuthProviderName $provider, string $identifier, ?AuthProvider $identity, ?OtpDestination $destination, ?SmsTemplate $sms = null): void
    {
        $subject = $this->subject($provider, $identifier, $identity?->id);

        if (! $this->otp->canBeRequested($subject)) {
            throw new OtpThrottledException($this->otp->secondsUntilNextRequest($subject));
        }

        $code = $this->generateCode();

        // Всегда пишем код и cooldown, даже если $destination пуст — иначе
        // по разнице в throttling можно понять, существует ли identity/контакт.
        $this->otp->put($subject, $code);

        if ($destination !== null) {
            OtpCodeIssued::dispatch($destination, $code, OtpPurpose::PasswordReset, $sms);
        }
    }

    /**
     * @throws InvalidOtpException
     */
    public function confirm(AuthProviderName $provider, string $identifier, string $code, string $newPassword, Closure $afterReset): User
    {
        $identity = $this->providers->findByIdentifier($provider, $identifier);

        if ($identity === null) {
            throw new InvalidOtpException;
        }

        $subject = $this->subject($provider, $identifier, $identity->id);

        return $this->otp->consume($subject, $code, function () use ($identity, $provider, $identifier, $newPassword, $afterReset): User {
            return DB::transaction(function () use ($identity, $provider, $identifier, $newPassword, $afterReset): User {
                $currentIdentity = $this->providers->findByIdForUpdate($identity->id);

                if ($currentIdentity === null
                    || $currentIdentity->provider !== $provider->value
                    || $currentIdentity->identifier !== $identifier
                    || $currentIdentity->user_id !== $identity->user_id) {
                    throw new InvalidOtpException;
                }

                $user = $currentIdentity->user;

                if ($user === null) {
                    throw new InvalidOtpException;
                }

                $this->providers->updateSecret($currentIdentity, ['password' => Hash::make($newPassword)]);
                $this->providers->markVerified($provider, $user);
                $this->history->log($user, $provider, IdentityChangeAction::PasswordReset, null, null);
                $afterReset($user);

                return $user;
            });
        });
    }

    private function subject(AuthProviderName $provider, string $identifier, ?int $identityId): string
    {
        $generation = $identityId === null ? 'unmatched' : (string) $identityId;

        return "{$this->currentClient->get()->id}:{$provider->value}-reset:{$generation}:{$identifier}";
    }
}
