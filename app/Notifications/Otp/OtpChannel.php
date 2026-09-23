<?php

namespace App\Notifications\Otp;

/**
 * Delivery medium for an OTP code — deliberately separate from
 * AuthProviderName: "how the user signs in" (phone-otp/email-password/
 * username-password) isn't the same axis as "where we can reach them"
 * (a username-password account has no channel of its own and borrows
 * one via rescue — see RescueContactResolver).
 */
enum OtpChannel: string
{
    case Phone = 'phone';
    case Email = 'email';
}
