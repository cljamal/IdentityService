<?php

namespace App\Listeners\Notifications;

use App\Events\Notifications\OtpCodeBroadcast;
use App\Events\Notifications\OtpCodeDeliveryRequested;
use App\Events\Notifications\OtpCodeIssued;

/**
 * The one place that decides where an issued code goes: today that's
 * "broadcast a (codeless) notice" + "actually deliver it" — every OTP
 * call site just fires OtpCodeIssued and never has to know that.
 */
final class RouteOtpCodeDelivery
{
    public function handle(OtpCodeIssued $event): void
    {
        OtpCodeBroadcast::dispatch($event->destination, $event->purpose);
        OtpCodeDeliveryRequested::dispatch($event->destination, $event->code, $event->purpose);
    }
}
