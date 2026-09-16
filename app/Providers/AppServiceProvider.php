<?php

namespace App\Providers;

use App\Auth\AuthStrategyResolver;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\OtpRepositoryInterface;
use App\Repositories\EloquentAuthProviderRepository;
use App\Repositories\RedisOtpRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OtpRepositoryInterface::class, RedisOtpRepository::class);
        $this->app->bind(AuthProviderRepositoryInterface::class, EloquentAuthProviderRepository::class);
        $this->app->singleton(AuthStrategyResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
