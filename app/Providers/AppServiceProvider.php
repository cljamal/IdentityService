<?php

namespace App\Providers;

use App\Auth\AuthStrategyResolver;
use App\Auth\Guards\IdApiGuard;
use App\Auth\Rescue\MetaTableRescueContactResolver;
use App\Auth\Rescue\NullRescueContactResolver;
use App\Auth\Rescue\RescueContactResolver;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\OtpRepositoryInterface;
use App\Repositories\EloquentAuthProviderRepository;
use App\Repositories\RedisOtpRepository;
use Illuminate\Support\Facades\Auth;
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

        $this->app->bind(RescueContactResolver::class, function () {
            $table = config('identity.username_password_rescue.table');

            if (blank($table)) {
                return new NullRescueContactResolver;
            }

            return new MetaTableRescueContactResolver(
                table: $table,
                userIdColumn: config('identity.username_password_rescue.user_id_column'),
                keyColumn: config('identity.username_password_rescue.key_column'),
                valueColumn: config('identity.username_password_rescue.value_column'),
                metaKey: config('identity.username_password_rescue.meta_key'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::extend('id-api', IdApiGuard::resolve(...));
    }
}
