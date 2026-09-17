<?php

namespace App\Auth\Strategies\Contracts;

use App\Models\User;

/**
 * Additional contract for password-based providers: change the secret
 * for an already-authenticated user, given their current password.
 */
interface ChangesPassword
{
    /**
     * @return array<string, mixed>
     */
    public function changePasswordRules(): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function changePassword(User $user, array $data): void;
}
