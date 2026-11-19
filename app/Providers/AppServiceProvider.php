<?php

namespace App\Providers;

use App\Http\Responses\PasskeyDeletedResponse;
use App\Http\Responses\PasskeyLoginResponse;
use App\Models\Event;
use App\Models\Poll;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as EventDispatcher;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passkeys\Contracts\PasskeyDeletedResponse as PasskeyDeletedResponseContract;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PasskeyLoginResponseContract::class, PasskeyLoginResponse::class);
        $this->app->bind(PasskeyDeletedResponseContract::class, PasskeyDeletedResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureHttps();
    }

    /**
     * Force https and the configured host in production so signed links match the public URL.
     *
     * Wayfinder bakes a forced root into the frontend bundle, so route generation runs without it and the
     * bundle keeps relative URLs that work on any host and without APP_URL at build time.
     */
    protected function configureHttps(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        URL::forceHttps(true);
        // A forwarded or spoofed Host header must not end up in emailed login links.
        URL::forceRootUrl(config('app.url'));

        EventDispatcher::listen(function (CommandStarting $event): void {
            if ($event->command !== 'wayfinder:generate') {
                return;
            }

            URL::forceRootUrl(null);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Relation::enforceMorphMap([
            'roster' => Roster::class,
            'roster_item' => RosterItem::class,
            'event' => Event::class,
            'poll' => Poll::class,
            'user' => User::class,
        ]);

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
