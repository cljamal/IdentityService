<?php

namespace App\Notifications\Otp;

/**
 * Where an OTP code needs to go: which channel, and the contact value on
 * that channel (a phone number or an email address). Bundled together
 * so a caller can never end up with a channel and contact that disagree.
 */
final readonly class OtpDestination
{
    public function __construct(
        public OtpChannel $channel,
        public string $contact,
    ) {}
}
