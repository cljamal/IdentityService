<?php

namespace App\Notifications\Otp;

/**
 * Identifies the operation throughout delivery and monitoring.
 * SMS text is rendered separately before reaching the notifier.
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
