<?php

use App\Auth\Enums\AuthProviderName;
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

    /*
    |--------------------------------------------------------------------------
    | Refresh Tokens
    |--------------------------------------------------------------------------
    |
    | Opaque, single-use refresh tokens issued alongside every access token
    | (see IdApiGuard::login()/refreshUsingToken()). Each /auth/refresh call
    | rotates it onto a new value; a token presented again after it's
    | already been rotated away is treated as leaked and revokes the whole
    | session instead of just being rejected.
    |
    */

    'refresh_token_ttl' => (int) env('AUTH_REFRESH_TOKEN_TTL', 20160), // minutes (14 days)

    /*
    |--------------------------------------------------------------------------
    | Username/Password Rescue Contact
    |--------------------------------------------------------------------------
    |
    | "username" has no delivery channel of its own, so "forgot password"
    | for it is only possible if some other table on your side records an
    | out-of-band contact (email, phone, ...) per user. Leave "table" empty
    | to keep it unsupported (default) — password/forgot on this provider
    | then responds 400 and never touches the database or Redis.
    |
    | This resolver assumes a generic EAV-style meta table (user_id/key/value).
    | If your rescue contact lives somewhere else (a dedicated column, another
    | service, ...), bind App\Auth\Rescue\RescueContactResolver to your own
    | implementation in AppServiceProvider instead of using this config.
    |
    */

    'username_password_rescue' => [
        'table' => env('AUTH_USERNAME_PASSWORD_RESCUE_TABLE'),
        'user_id_column' => env('AUTH_USERNAME_PASSWORD_RESCUE_USER_ID_COLUMN', 'user_id'),
        'key_column' => env('AUTH_USERNAME_PASSWORD_RESCUE_KEY_COLUMN', 'meta_key'),
        'value_column' => env('AUTH_USERNAME_PASSWORD_RESCUE_VALUE_COLUMN', 'meta_value'),
        'meta_key' => env('AUTH_USERNAME_PASSWORD_RESCUE_META_KEY', 'email'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Phone Countries
    |--------------------------------------------------------------------------
    |
    | Country calling codes allowed to register/login via phone-otp. The
    | phone is normalized to digits-only (no leading +) before this check
    | runs, so "code" here must match the start of that flat string, e.g.
    | "998" matches "998901234567". Toggle "enabled" (or its env var) to
    | open/close a country without touching code — a disabled/unlisted
    | country is rejected before anything is validated further or an OTP
    | is sent.
    |
    */

    'phone_countries' => [
        'uz' => [
            'name' => 'Uzbekistan',
            'code' => '998',
            'enabled' => env('PHONE_COUNTRY_UZ_ENABLED', true),
        ],
    ],

];
