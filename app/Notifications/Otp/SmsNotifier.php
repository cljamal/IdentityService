<?php

namespace App\Notifications\Otp;

interface SmsNotifier
{
    public function notify(string $phone, string $message, OtpPurpose $purpose): void;
}
