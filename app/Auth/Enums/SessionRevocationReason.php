<?php

namespace App\Auth\Enums;

/**
 * Why a session was revoked, carried on the UserSessionRevoked ops
 * broadcast. Deliberately doesn't cover every revoke() call site — a
 * refresh-token replay or an account deletion get their own, more specific
 * broadcast events instead (RefreshTokenReuseDetected, AccountDeleted),
 * since a client's correct reaction to those is qualitatively different
 * from "this user should re-authenticate."
 */
enum SessionRevocationReason: string
{
    case Logout = 'logout';
    case ExplicitRevoke = 'explicit_revoke';
    case PasswordReset = 'password_reset';
}
