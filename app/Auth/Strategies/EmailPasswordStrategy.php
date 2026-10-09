<?php

namespace App\Auth\Strategies;

use App\Auth\CurrentClient;
use App\Auth\Enums\AuthProviderName;
use App\Auth\Strategies\Contracts\ConfirmsDeletion;
use App\Auth\Strategies\Contracts\NormalizesInput;
use App\Auth\Strategies\Contracts\ResendsRegistrationCode;
use App\Auth\Strategies\Contracts\ResetsPassword;
use App\Auth\Strategies\Support\AccountDeletionConfirmer;
use App\Auth\Strategies\Support\CodeBasedPasswordReset;
use App\Auth\Strategies\Support\RegistrationVerifier;
use App\Exceptions\Auth\NoLinkedIdentityException;
use App\Models\User;
use App\Notifications\Otp\OtpChannel;
use App\Notifications\Otp\OtpDestination;
use App\Notifications\Otp\SmsTemplate;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\IdentityChangeLogRepositoryInterface;
use Closure;
use Illuminate\Support\Str;

final readonly class EmailPasswordStrategy extends PasswordStrategy implements ConfirmsDeletion, NormalizesInput, ResendsRegistrationCode, ResetsPassword
{
    public function __construct(
        AuthProviderRepositoryInterface $providers,
        RegistrationVerifier $verification,
        IdentityChangeLogRepositoryInterface $history,
        CurrentClient $currentClient,
        private CodeBasedPasswordReset $reset,
        private AccountDeletionConfirmer $deletion,
    ) {
        parent::__construct($providers, $verification, $history, $currentClient);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Case-fold the email before it's validated/looked up — otherwise
     * "Foo@Bar.com" and "foo@bar.com" could end up as different identities
     * depending on the DB's collation.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalize(array $data): array
    {
        if (isset($data['email']) && is_string($data['email'])) {
            $data['email'] = Str::lower(trim($data['email']));
        }

        return $data;
    }

    protected function provider(): AuthProviderName
    {
        return AuthProviderName::EmailPassword;
    }

    protected function identifierField(): string
    {
        return 'email';
    }

    /**
     * @return array<string, mixed>
     */
    protected function identifierRules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', $this->uniqueIdentifierRule()],
        ];
    }

    /**
     * Email always has a channel — the address itself.
     */
    protected function beginVerification(User $user, string $identifier, ?SmsTemplate $sms = null): bool
    {
        $this->verification->send($this->provider(), $identifier, new OtpDestination(OtpChannel::Email, $identifier), $sms);

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function registrationResendRules(): array
    {
        return ['email' => ['required', 'email', 'max:255']];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function resendRegistrationCode(array $data, ?SmsTemplate $sms = null): void
    {
        $email = $data['email'];
        $this->verification->resend(
            $this->provider(),
            $email,
            new OtpDestination(OtpChannel::Email, $email),
            $sms,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function passwordResetRequestRules(): array
    {
        return ['email' => ['required', 'email']];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function requestPasswordReset(array $data, ?SmsTemplate $sms = null): void
    {
        $email = $data['email'];
        $identity = $this->providers->findByIdentifier($this->provider(), $email);

        // Канал доставки для email-password — сам identifier.
        $this->reset->request($this->provider(), $email, $identity, $identity ? new OtpDestination(OtpChannel::Email, $email) : null, $sms);
    }

    /**
     * @return array<string, mixed>
     */
    public function passwordResetRules(): array
    {
        return [
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:4'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function resetPassword(array $data, Closure $afterPasswordReset): User
    {
        return $this->reset->confirm($this->provider(), $data['email'], $data['code'], $data['password'], $afterPasswordReset);
    }

    public function requestDeletion(User $user, ?SmsTemplate $sms = null): void
    {
        $identity = $this->providers->findByUser($this->provider(), $user);

        if (! $identity) {
            throw new NoLinkedIdentityException($this->provider()->value);
        }

        $this->deletion->request($this->provider(), $user, new OtpDestination(OtpChannel::Email, $identity->identifier), $sms);
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
    public function confirmDeletion(User $user, array $data, Closure $onConfirmed): void
    {
        $this->deletion->confirm($this->provider(), $user, $data['code'], $onConfirmed);
    }
}
