<?php

namespace App\Auth\Strategies\Contracts;

use App\Notifications\Otp\SmsTemplate;

/**
 * Additional contract for strategies that require a code to be
 * sent to the user before they can authenticate (e.g. phone OTP).
 */
interface IssuesVerificationCode
{
    /**
     * Validation rules for the send-code step.
     *
     * @return array<string, mixed>
     */
    public function codeRules(): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendCode(array $data, ?SmsTemplate $sms = null): void;
}
