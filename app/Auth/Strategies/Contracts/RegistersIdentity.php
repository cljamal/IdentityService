<?php

namespace App\Auth\Strategies\Contracts;

use App\Auth\Strategies\Support\RegistrationResult;
use App\Models\User;
use App\Notifications\Otp\SmsTemplate;

/**
 * Additional contract for strategies where possession of the credential
 * itself (e.g. a password) does not prove ownership of the identifier,
 * so the identity must be created through an explicit registration step
 * rather than auto-created on first login (unlike e.g. phone OTP).
 */
interface RegistersIdentity
{
    /**
     * Validation rules for the registration step.
     *
     * @return array<string, mixed>
     */
    public function registrationRules(): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, ?SmsTemplate $sms = null): RegistrationResult;

    /**
     * Validation rules for confirming the code sent after register().
     *
     * @return array<string, mixed>
     */
    public function registrationVerificationRules(): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function verifyRegistration(array $data): User;
}
