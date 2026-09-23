<?php

namespace App\Notifications\Otp;

/**
 * Why a code was issued — carried all the way to the notifier so the
 * actual message text ("your login code" vs "confirm account deletion")
 * can be chosen without the calling code (RegistrationVerifier,
 * PhoneChangeCoordinator, ...) knowing anything about delivery.
 */
enum OtpPurpose: string
{
    case Registration = 'registration';
    case Login = 'login';
    case PasswordReset = 'password_reset';
    case IdentifierChangeOld = 'identifier_change_old';
    case IdentifierChangeNew = 'identifier_change_new';
    case AccountDeletion = 'account_deletion';
}
