<?php

namespace App\Notifications\Otp;

interface EmailNotifier
{
    public function notify(string $email, string $code, OtpPurpose $purpose): void;
}
