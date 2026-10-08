<?php

namespace App\Events\Notifications;

use App\Notifications\Otp\OtpDestination;
use App\Notifications\Otp\OtpPurpose;
use App\Notifications\Otp\SmsTemplate;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The single outgoing signal every OTP-issuing code path fires: "this
 * code needs to reach that destination." Callers (RegistrationVerifier,
 * PhoneChangeCoordinator, CodeBasedPasswordReset, AccountDeletionConfirmer,
 * PhoneOtpStrategy) dispatch this ONE event and are done — RouteOtpCodeDelivery
 * decides what happens next (broadcast it, get it actually delivered),
 * so no action ever has to know or care how many places a code fans out to.
 */
final readonly class OtpCodeIssued
{
    use Dispatchable;

    public function __construct(
        public OtpDestination $destination,
        public string $code,
        public OtpPurpose $purpose,
        public ?SmsTemplate $sms = null,
    ) {}
}
