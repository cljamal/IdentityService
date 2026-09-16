<?php

namespace App\Auth\Strategies\Contracts;

use App\Models\User;

/**
 * Additional contract for password-based providers that have a channel
 * to deliver a reset code to (e.g. email). Providers without one (e.g.
 * a bare username) simply don't implement this — the generic reset
 * actions treat that as an unsupported operation (400).
 */
interface ResetsPassword
{
    public function passwordResetRequestRules(): array;

    public function requestPasswordReset(array $data): void;

    public function passwordResetRules(): array;

    public function resetPassword(array $data): User;
}
