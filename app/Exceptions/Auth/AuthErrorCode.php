<?php

namespace App\Exceptions\Auth;

/**
 * Every value an auth-related error response can carry in its "code"
 * field — the stable, machine-readable contract clients build against.
 * Human-readable text (any language) is the client's job, not this API's.
 */
enum AuthErrorCode: string
{
    case AuthProviderDisabled = 'AUTH_PROVIDER_DISABLED';
    case IdentifierAlreadyTaken = 'IDENTIFIER_ALREADY_TAKEN';
    case IdentityNotVerified = 'IDENTITY_NOT_VERIFIED';
    case InvalidCredentials = 'INVALID_CREDENTIALS';
    case InvalidOtp = 'INVALID_OTP';
    case InvalidRefreshToken = 'INVALID_REFRESH_TOKEN';
    case NoLinkedIdentity = 'NO_LINKED_IDENTITY';
    case OtpThrottled = 'OTP_THROTTLED';
    case RefreshTokenReused = 'REFRESH_TOKEN_REUSED';
    case SessionNotFound = 'SESSION_NOT_FOUND';
    case UnsupportedAuthOperation = 'UNSUPPORTED_AUTH_OPERATION';
}
