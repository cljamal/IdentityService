<?php

namespace App\Auth\History;

enum IdentityChangeAction: string
{
    case Registered = 'registered';
    case Verified = 'verified';
    case PasswordChanged = 'password_changed';
    case PasswordReset = 'password_reset';
    case IdentifierChanged = 'identifier_changed';
    case IdentifierReleased = 'identifier_released';
    case AccountDeleted = 'account_deleted';
}
