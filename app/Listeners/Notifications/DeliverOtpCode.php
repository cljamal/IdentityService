<?php

namespace App\Listeners\Notifications;

use App\Actions\Auth\RenderOtpSmsAction;
use App\Events\Notifications\OtpCodeDeliveryRequested;
use App\Notifications\Otp\EmailNotifier;
use App\Notifications\Otp\OtpChannel;
use App\Notifications\Otp\SmsNotifier;

/**
 * The only place responsible for actually getting a code to someone.
 * Swapping LoggingSmsNotifier/LoggingEmailNotifier for a real gateway in
 * AppServiceProvider is the only change a real integration needs — this
 * listener, the events, and every OTP call site stay untouched.
 */
final readonly class DeliverOtpCode
{
    public function __construct(
        private SmsNotifier $sms,
        private EmailNotifier $email,
    ) {}

    public function handle(OtpCodeDeliveryRequested $event): void
    {
        match ($event->destination->channel) {
            OtpChannel::Phone => $this->sms->notify($event->destination->contact, RenderOtpSmsAction::run($event->code, $event->sms), $event->purpose),
            OtpChannel::Email => $this->email->notify($event->destination->contact, $event->code, $event->purpose),
        };
    }
}
