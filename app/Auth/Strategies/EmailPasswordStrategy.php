<?php

namespace App\Auth\Strategies;

use App\Auth\Enums\AuthProviderName;
use App\Auth\Strategies\Contracts\ConfirmsDeletion;
use App\Auth\Strategies\Contracts\NormalizesInput;
use App\Auth\Strategies\Contracts\ResetsPassword;
use App\Auth\Strategies\Support\AccountDeletionConfirmer;
use App\Auth\Strategies\Support\CodeBasedPasswordReset;
use App\Auth\Strategies\Support\RegistrationVerifier;
use App\Exceptions\Auth\NoLinkedIdentityException;
use App\Models\User;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\IdentityChangeLogRepositoryInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final readonly class EmailPasswordStrategy extends PasswordStrategy implements ConfirmsDeletion, NormalizesInput, ResetsPassword
{
    public function __construct(
        AuthProviderRepositoryInterface $providers,
        RegistrationVerifier $verification,
        IdentityChangeLogRepositoryInterface $history,
        private CodeBasedPasswordReset $reset,
        private AccountDeletionConfirmer $deletion,
    ) {
        parent::__construct($providers, $verification, $history);
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
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('auth_providers', 'identifier')
                    ->where('provider', AuthProviderName::EmailPassword->value),
            ],
        ];
    }

    /**
     * Email always has a channel — the address itself.
     */
    protected function beginVerification(User $user, string $identifier): bool
    {
        $this->verification->send($this->provider(), $identifier, $identifier);

        return false;
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
    public function requestPasswordReset(array $data): void
    {
        $email = $data['email'];
        $identity = $this->providers->findByIdentifier($this->provider(), $email);

        // Канал доставки для email-password — сам identifier.
        $this->reset->request($this->provider(), $email, $identity ? $email : null);
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
    public function resetPassword(array $data): User
    {
        return $this->reset->confirm($this->provider(), $data['email'], $data['code'], $data['password']);
    }

    public function requestDeletion(User $user): void
    {
        $identity = $this->providers->findByUser($this->provider(), $user);

        if (! $identity) {
            throw new NoLinkedIdentityException($this->provider()->value);
        }

        $this->deletion->request($this->provider(), $user, $identity->identifier);
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
        $this->deletion->confirm($this->provider(), $user, $data['code']);
    }
}
