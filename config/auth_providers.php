<?php

use App\Auth\AuthProviderName;
use App\Auth\Strategies\EmailPasswordStrategy;
use App\Auth\Strategies\PhoneOtpStrategy;
use App\Auth\Strategies\UsernamePasswordStrategy;

return [

    /*
    |--------------------------------------------------------------------------
    | Auth Providers
    |--------------------------------------------------------------------------
    |
    | Maps each AuthProviderName case to the strategy that implements it.
    | Set "enabled" to false (or the matching env var) to take a provider
    | out of service without removing its code or enum case.
    |
    */

    'providers' => [
        AuthProviderName::PhoneOtp->value => [
            'strategy' => PhoneOtpStrategy::class,
            'enabled' => env('AUTH_PROVIDER_PHONE_OTP_ENABLED', true),
        ],

        AuthProviderName::UsernamePassword->value => [
            'strategy' => UsernamePasswordStrategy::class,
            'enabled' => env('AUTH_PROVIDER_USERNAME_PASSWORD_ENABLED', true),
        ],

        AuthProviderName::EmailPassword->value => [
            'strategy' => EmailPasswordStrategy::class,
            'enabled' => env('AUTH_PROVIDER_EMAIL_PASSWORD_ENABLED', true),
        ],
    ],

];
