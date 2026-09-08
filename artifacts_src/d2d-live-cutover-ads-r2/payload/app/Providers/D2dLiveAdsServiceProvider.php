<?php

namespace App\Providers;

use App\Http\Middleware\D2dLiveAdsMiddleware;
use Illuminate\Support\ServiceProvider;

class D2dLiveAdsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Ads-only provider. No application bindings are replaced.
    }

    public function boot(): void
    {
        $this->app['router']->pushMiddlewareToGroup('web', D2dLiveAdsMiddleware::class);

        if ($this->app->runningInConsole()) {
            require base_path('routes/phase5-live-ads-console.php');
        }
    }
}
