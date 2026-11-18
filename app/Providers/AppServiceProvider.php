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
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
     */
    protected function configureHttps(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        URL::forceHttps(true);
        // A forwarded or spoofed Host header must not end up in emailed login links.
        URL::forceRootUrl(config('app.url'));
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
