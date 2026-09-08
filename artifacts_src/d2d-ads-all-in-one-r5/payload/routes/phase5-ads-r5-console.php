<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('phase5:ads-r5-doctor', function () {
    $this->newLine();
    $this->info('D2D Ads All-in-One R5 Doctor');
    $this->line(str_repeat('=', 46));
    $this->line('Publisher: ca-pub-5807824613154542');
    $this->line('Content Mid slot: 9432468543');
    $this->line('Content End / right rail slot: 8039495827');
    $this->newLine();
    $this->info('Hard-approved live detail routes');
    foreach ([
        '/blog/{slug}',
        '/universities/{slug}',
        '/scholarships/{slug}',
        '/opportunities/{slug}',
        '/jobs/{slug}',
    ] as $route) $this->line('  '.$route);

    $this->newLine();
    $this->info('Hard-blocked');
    $this->line('Homepage, all listing pages, tools, resources/guidebooks, consultants, CRM and Portal.');

    $this->newLine();
    $this->info('AdSense account settings for the screenshot-style bottom bar + side rails');
    $this->line('Auto ads: ON');
    $this->line('Intent-driven > Ad intent anchors: ON');
    $this->line('Intent-driven > Ad intent links: OFF');
    $this->line('Intent-driven > Ad intent chips: OFF');
    $this->line('Overlay > Side rail ads: ON (Right only recommended)');
    $this->line('Overlay > Anchor ads: OFF');
    $this->line('Overlay > Vignette ads: OFF');
    $this->line('In-page > Banner ads: OFF');
    $this->line('In-page > Multiplex ads: OFF');
    $this->line('Existing ads optimization: OFF');

    $this->newLine();
    $this->line('R5 inserts the AdSense site code only on approved detail pages, so Auto formats cannot run on other D2D pages.');
    $this->line('No migrations. No database writes. No page templates changed.');
    return 0;
});
