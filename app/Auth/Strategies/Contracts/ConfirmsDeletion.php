<?php

namespace App\Auth\Strategies\Contracts;

use App\Models\User;

/**
 * Additional contract for strategies that can confirm account deletion
 * via their own channel (phone OTP, email code, ...). Not every provider
 * has to implement this — a provider without it means deletion can't be
 * confirmed through it (400), the account holder must use a different
 * linked provider that does, if any.
 */
interface ConfirmsDeletion
{
    public function requestDeletion(User $user): void;

    /**
     * @return array<string, mixed>
     */
    public function confirmDeletionRules(): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function confirmDeletion(User $user, array $data): void;
}
