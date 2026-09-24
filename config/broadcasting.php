<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This was missing entirely before — config('broadcasting.default') had no
    | value to read, so BroadcastManager::getDefaultDriver() silently fell
    | back to its own hardcoded 'null', regardless of BROADCAST_CONNECTION in
    | .env. NullBroadcaster's auth() and broadcast() are both empty no-ops, so
    | every broadcast event this app has ever dispatched (see
    | OtpCodeBroadcast) has gone nowhere — not even the log. Falling back to
    | 'log' here (not Laravel's own default of 'null') matches what
    | .env.example already documents as this project's baseline.
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | 'reverb' and 'pusher' are both wired but unused today — no transport is
    | installed yet (no pusher/pusher-php-server in composer.json), so
    | selecting either would fail loudly instead of silently. Left in place,
    | with no fake placeholder credentials, for whenever a transport is
    | actually chosen. 'redis' is a realistic alternative for this project
    | specifically, since it's already Redis-backed end to end.
    |
    */

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
