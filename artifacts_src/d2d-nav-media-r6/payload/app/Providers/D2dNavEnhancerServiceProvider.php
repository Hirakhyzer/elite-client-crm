<?php

namespace App\Providers;

use App\Http\Middleware\D2dNavEnhancerMiddleware;
use Illuminate\Support\ServiceProvider;

class D2dNavEnhancerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Additive nav-only provider. No existing page bindings are replaced.
    }

    public function boot(): void
    {
        $this->app['router']->pushMiddlewareToGroup('web', D2dNavEnhancerMiddleware::class);
    }
}
