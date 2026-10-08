<?php

namespace App\Auth\Strategies\Contracts;

use App\Models\User;
use App\Notifications\Otp\SmsTemplate;

/**
 * Additional contract for strategies that support changing their
 * identifier for an already-authenticated user (e.g. phone number).
 * A 3-step flow: request → confirm ownership of the OLD identifier →
 * confirm ownership of the NEW one, only then does the change apply.
 */
interface ChangesIdentifier
{
    /**
     * @return array<string, mixed>
     */
    public function changeRules(User $user): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function requestChange(User $user, array $data, ?SmsTemplate $sms = null): void;

    /**
     * @return array<string, mixed>
     */
    public function confirmOldRules(): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function confirmOld(User $user, array $data, ?SmsTemplate $sms = null): void;

    /**
     * @return array<string, mixed>
     */
    public function confirmNewRules(): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function confirmNew(User $user, array $data): void;
}
