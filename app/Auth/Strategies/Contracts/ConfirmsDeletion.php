<?php

namespace App\Auth\Strategies\Contracts;

use App\Models\User;
use App\Notifications\Otp\SmsTemplate;
use Closure;

/**
 * Additional contract for strategies that can confirm account deletion
 * via their own channel (phone OTP, email code, ...). Not every provider
 * has to implement this — a provider without it means deletion can't be
 * confirmed through it (400), the account holder must use a different
 * linked provider that does, if any.
 */
interface ConfirmsDeletion
{
    public function requestDeletion(User $user, ?SmsTemplate $sms = null): void;

    /**
     * @return array<string, mixed>
     */
    public function confirmDeletionRules(): array;

    /**
     * @param  array<string, mixed>  $data
     * @param  Closure(): void  $onConfirmed
     */
    public function confirmDeletion(User $user, array $data, Closure $onConfirmed): void;
}
