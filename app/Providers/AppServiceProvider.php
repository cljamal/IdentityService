<?php

namespace App\Providers;

use App\Auth\AuthStrategyResolver;
use App\Auth\CurrentClient;
use App\Auth\Guards\IdApiGuard;
use App\Auth\Rescue\ChainedRescueContactResolver;
use App\Auth\Rescue\LinkedIdentityRescueContactResolver;
use App\Auth\Rescue\MetaTableRescueContactResolver;
use App\Auth\Rescue\RescueContactResolver;
use App\Notifications\Otp\EmailNotifier;
use App\Notifications\Otp\LoggingEmailNotifier;
use App\Notifications\Otp\LoggingSmsNotifier;
use App\Notifications\Otp\SmsNotifier;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\AuthSessionRepositoryInterface;
use App\Repositories\Contracts\IdentityChangeLogRepositoryInterface;
use App\Repositories\Contracts\OtpRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\EloquentAuthProviderRepository;
use App\Repositories\EloquentAuthSessionRepository;
use App\Repositories\EloquentIdentityChangeLogRepository;
use App\Repositories\EloquentRoleRepository;
use App\Repositories\RedisOtpRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OtpRepositoryInterface::class, RedisOtpRepository::class);
        $this->app->bind(AuthProviderRepositoryInterface::class, EloquentAuthProviderRepository::class);
        $this->app->bind(IdentityChangeLogRepositoryInterface::class, EloquentIdentityChangeLogRepository::class);
        $this->app->bind(AuthSessionRepositoryInterface::class, EloquentAuthSessionRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, EloquentRoleRepository::class);
        $this->app->singleton(AuthStrategyResolver::class);
        $this->app->singleton(CurrentClient::class);

        $this->app->bind(SmsNotifier::class, LoggingSmsNotifier::class);
        $this->app->bind(EmailNotifier::class, LoggingEmailNotifier::class);

        $this->app->bind(RescueContactResolver::class, function ($app) {
            // Linked-identity resolution needs no config at all — it comes
            // first so it works out of the box. The EAV table is an
            // additional, explicit source for accounts that never linked
            // a second provider.
            $resolvers = [$app->make(LinkedIdentityRescueContactResolver::class)];

            $table = config('identity.username_password_rescue.table');

            if (! blank($table)) {
                $resolvers[] = new MetaTableRescueContactResolver(
                    table: $table,
                    userIdColumn: config('identity.username_password_rescue.user_id_column'),
                    keyColumn: config('identity.username_password_rescue.key_column'),
                    valueColumn: config('identity.username_password_rescue.value_column'),
                    metaKey: config('identity.username_password_rescue.meta_key'),
                );
            }

            return new ChainedRescueContactResolver($resolvers);
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
