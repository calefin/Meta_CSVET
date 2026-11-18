<?php

namespace App\Providers;

use App\Models\ConsultationRequest;
use App\Policies\ConsultationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        // HostGator shared hosting may proxy/terminate TLS in a way that
        // doesn't always set the request scheme correctly; force https
        // for any generated URL (emails, redirects) in production.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Gate::policy(ConsultationRequest::class, ConsultationPolicy::class);
    }
}
