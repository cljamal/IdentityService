<?php

namespace App\Auth\Strategies\Contracts;

use App\Notifications\Otp\SmsTemplate;

interface ResendsRegistrationCode
{
    /**
     * @return array<string, mixed>
     */
    public function registrationResendRules(): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function resendRegistrationCode(array $data, ?SmsTemplate $sms = null): void;
}
