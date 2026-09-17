<?php

namespace App\Auth\Strategies\Contracts;

use App\Auth\Strategies\Support\RegistrationResult;
use App\Models\User;

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
     */
    public function registrationRules(): array;

    public function register(array $data): RegistrationResult;

    /**
     * Validation rules for confirming the code sent after register().
     */
    public function registrationVerificationRules(): array;

    public function verifyRegistration(array $data): User;
}
