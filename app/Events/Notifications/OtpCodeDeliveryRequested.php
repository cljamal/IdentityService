<?php

namespace App\Events\Notifications;

use App\Notifications\Otp\OtpDestination;
use App\Notifications\Otp\OtpPurpose;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The "Action" half of the Main -> WS + Action fan-out (see
 * RouteOtpCodeDelivery). Carries the actual code — DeliverOtpCode is the
 * only listener, and it's the one place responsible for getting it to
 * the user for real (today: LoggingSmsNotifier/LoggingEmailNotifier).
 */
final readonly class OtpCodeDeliveryRequested
{
    use Dispatchable;

    public function __construct(
        public OtpDestination $destination,
        public string $code,
        public OtpPurpose $purpose,
    ) {}
}
