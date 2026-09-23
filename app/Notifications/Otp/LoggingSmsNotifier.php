<?php

namespace App\Notifications\Otp;

use Illuminate\Support\Facades\Log;

/**
 * Stand-in until a real SMS gateway is wired up. Swap the binding in
 * AppServiceProvider for a real implementation — nothing upstream
 * (actions, strategies, the event/listener chain) needs to change.
 */
final class LoggingSmsNotifier implements SmsNotifier
{
    public function notify(string $phone, string $code, OtpPurpose $purpose): void
    {
        Log::info("[sms:{$purpose->value}] {$phone}: {$code}");
    }
}
