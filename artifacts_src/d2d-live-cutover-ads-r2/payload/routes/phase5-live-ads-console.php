<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('phase5:live-ads-doctor', function () {
    $this->newLine();
    $this->info('D2D Live Ads Doctor');
    $this->line(str_repeat('=', 42));
    $this->line('Publisher: ca-pub-5807824613154542');
    $this->line('Live hosts: dares2dream.com, www.dares2dream.com');
    $this->line('Preview/staging: AdSense bootstrap is suppressed');
    $this->newLine();
    $this->info('Approved detail routes only');
    foreach ([
        '/blog/{slug}',
        '/universities/{slug}',
        '/scholarships/{slug}',
        '/opportunities/{slug}',
        '/jobs/{slug}',
    ] as $route) {
        $this->line('  '.$route);
    }
    $this->newLine();
    $this->line('Homepage/listings/resources/guidebooks/tools: ad bootstrap stripped.');
    $this->line('Auto Ads must be enabled in the AdSense account if no manual ad-unit slot IDs are configured.');
    $this->line('No migrations. No database writes. No Blade/controller/web-route changes.');
    return 0;
});
