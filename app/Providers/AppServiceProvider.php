<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();

        // Livewire re-applies only a short built-in list of route middleware on later action requests.
        // Ours must be on it, or a suspended user could keep acting on an already-open page.
        \Livewire\Livewire::addPersistentMiddleware([
            \App\Http\Middleware\EnsureNotSuspended::class,
            \App\Http\Middleware\EnsureAdmin::class,
        ]);
    }

    /** Limits are read from config at request time so they can be tuned per environment. */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(config('limits.webhook_per_minute'))->by($request->ip()));

        RateLimiter::for('money', fn (Request $request) => Limit::perMinute(config('limits.money_per_minute'))
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('web', fn (Request $request) => Limit::perMinute(config('limits.web_per_minute'))->by($request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
