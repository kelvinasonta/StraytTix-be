<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;
use Modules\Events\app\Models\Event;
use Modules\Events\app\Policies\EventPolicy;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Policies\OrganizerPolicy;

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
        Gate::policy(Organizer::class, OrganizerPolicy::class);
        Gate::policy(Event::class, EventPolicy::class);

        RateLimiter::for('api', function (Request $request) {
            if (Feature::for('global')->active('api-rate-limiting')) {
                return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
            }

            return Limit::none();
        });
    }
}
