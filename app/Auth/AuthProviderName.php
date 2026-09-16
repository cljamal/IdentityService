<?php

namespace App\Auth;

enum AuthProviderName: string
{
    case PhoneOtp = 'phone-otp';
    case UsernamePassword = 'username-password';
    case EmailPassword = 'email-password';
}
