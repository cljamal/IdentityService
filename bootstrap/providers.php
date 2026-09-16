<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider::class,
];
