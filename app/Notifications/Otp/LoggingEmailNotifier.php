<?php

namespace App\Notifications\Otp;

use Illuminate\Support\Facades\Log;

/**
 * Stand-in until a real mail/transactional-email gateway is wired up.
 * Swap the binding in AppServiceProvider for a real implementation —
 * nothing upstream needs to change.
 */
final class LoggingEmailNotifier implements EmailNotifier
{
    public function notify(string $email, string $code, OtpPurpose $purpose): void
    {
        Log::info("[email:{$purpose->value}] {$email}: {$code}");
    }
}
