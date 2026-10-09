<?php

namespace App\Providers;

use App\Auth\AuthStrategyResolver;
use App\Auth\CurrentClient;
use App\Auth\Enums\AuthProviderName;
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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        $definitions = [
            'auth.otp' => ['phone'],
            'auth.login' => ['email', 'username', 'phone'],
            'auth.register' => ['email', 'username'],
            'auth.register.verify' => ['email', 'username'],
            'auth.register.resend' => ['email'],
            'auth.password.forgot' => ['email', 'username'],
            'auth.password.reset' => ['email', 'username'],
            'auth.password.change' => null,
            'auth.identifier.change' => null,
            'auth.identifier.change.confirm.old' => null,
            'auth.identifier.change.confirm.new' => null,
            'auth.account.delete' => null,
            'auth.account.delete.confirm' => null,
            'auth.refresh' => ['refresh_token'],
        ];

        foreach ($definitions as $name => $identifierFields) {
            RateLimiter::for($name, function (Request $request) use ($name, $identifierFields) {
                $currentClient = app(CurrentClient::class)->resolved();
                $clientId = $currentClient === null ? 'unknown' : $currentClient->id;
                $operation = str_replace('.', ':', $name);
                $limits = [Limit::perMinute(30)->by("{$clientId}:{$operation}:ip:{$request->ip()}")];

                $user = $request->user('id-api');

                if ($user !== null) {
                    $limits[] = Limit::perMinute(10)->by("{$clientId}:{$operation}:user:{$user->getAuthIdentifier()}");
                }

                if ($identifierFields !== null) {
                    $provider = $request->route('provider');
                    $provider = $provider instanceof AuthProviderName ? $provider->value : (string) $provider;
                    $preferredField = match ($provider) {
                        AuthProviderName::PhoneOtp->value => 'phone',
                        AuthProviderName::UsernamePassword->value => 'username',
                        default => 'email',
                    };
                    $identifierField = in_array($preferredField, $identifierFields, true)
                        ? $preferredField
                        : $identifierFields[0];
                    $identifier = $request->input($identifierField);

                    if (is_string($identifier) && $identifier !== '') {
                        if ($identifierField === 'phone') {
                            $identifier = preg_replace('/\\D+/', '', $identifier) ?? $identifier;
                        } elseif ($identifierField === 'email') {
                            $identifier = Str::lower(trim($identifier));
                        }

                        $identityKey = hash('sha256', "{$provider}:{$identifier}");
                        $limits[] = Limit::perMinute(10)->by("{$clientId}:{$operation}:identity:{$identityKey}");
                    }
                }

                return $limits;
            });
        }
    }
}
