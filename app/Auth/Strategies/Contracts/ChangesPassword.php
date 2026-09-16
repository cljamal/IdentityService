<?php

namespace App\Auth\Strategies\Contracts;

use App\Models\User;

/**
 * Additional contract for password-based providers: change the secret
 * for an already-authenticated user, given their current password.
 */
interface ChangesPassword
{
    public function changePasswordRules(): array;

    public function changePassword(User $user, array $data): void;
}
