<?php

namespace App\Providers;

use App\Http\Middleware\D2dAdsUnifiedMiddleware;
use Illuminate\Support\ServiceProvider;

class D2dAdsUnifiedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Isolated ads layer only. No application bindings or page templates are replaced.
    }

    public function boot(): void
    {
        // Registered first by the installer so this middleware is the outermost web wrapper.
        // That lets it remove legacy/Auto-Ad artifacts after all older middleware has run.
        $this->app['router']->prependMiddlewareToGroup('web', D2dAdsUnifiedMiddleware::class);

        if ($this->app->runningInConsole()) {
            require base_path('routes/phase5-ads-r5-console.php');
        }
    }
}
