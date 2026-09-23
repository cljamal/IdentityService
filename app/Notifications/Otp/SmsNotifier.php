<?php

namespace App\Notifications\Otp;

interface SmsNotifier
{
    public function notify(string $phone, string $code, OtpPurpose $purpose): void;
}
