<?php

declare(strict_types=1);

/**
 * D2D CRM Phase 4.3 — Dashboard + Analytics Foundation
 * - Adds guidebook/resource counts to the CRM dashboard.
 * - Adds a separate Analytics tab with 7/30/90-day views.
 * - Creates D2D-owned analytics event + daily metrics tables.
 * - Does not fabricate traffic; zero-state is shown until the public Laravel site records events.
 * - No Portal changes.
 */

$root = __DIR__;
if (! is_file($root.'/artisan')) {
    fwrite(STDERR, "ERROR: Extract this patch into the CRM Laravel root first.\n");
    exit(1);
}

function p43Backup(string $path): void
{
    $backup = $path.'.phase4.3-backup';
    if (is_file($path) && ! is_file($backup)) {
        copy($path, $backup);
    }
}

function p43ViewPath(string $root, string $view): string
{
    return $root.'/resources/views/'.str_replace('.', '/', $view).'.blade.php';
}

function p43AppendOnce(string $path, string $marker, string $content): bool
{
    if (! is_file($path)) return false;
    $current = (string) file_get_contents($path);
    if (str_contains($current, $marker)) return true;
    p43Backup($path);
    file_put_contents($path, rtrim($current)."\n\n".$content."\n");
    return true;
}

// Detect working layout exactly as the live dashboard uses it.
$layout = null;
$dashboardPath = null;
foreach ([
    $root.'/resources/views/crm/dashboard.blade.php',
    $root.'/resources/views/crm/index.blade.php',
    $root.'/resources/views/dashboard.blade.php',
] as $candidatePath) {
    if (! is_file($candidatePath)) continue;
    $contents = (string) file_get_contents($candidatePath);
    if (preg_match('/@extends\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*\)/', $contents, $m)) {
        $candidate = trim($m[1]);
        if ($candidate !== '' && is_file(p43ViewPath($root, $candidate))) {
            $layout = $candidate;
            $dashboardPath = $candidatePath;
            break;
        }
    }
}

if ($layout === null) {
    foreach (['crm.layout','crm.layouts.master','crm.layouts.dashboard','layouts.crm','layouts.app'] as $candidate) {
        if (is_file(p43ViewPath($root, $candidate))) {
            $layout = $candidate;
            break;
        }
    }
}

if ($layout === null) {
    fwrite(STDERR, "ERROR: Could not detect the live CRM Blade layout.\n");
    exit(2);
}

// Fix the analytics page placeholder to the exact live layout.
$analyticsView = $root.'/resources/views/crm/phase4/analytics/index.blade.php';
if (! is_file($analyticsView)) {
    fwrite(STDERR, "ERROR: Analytics view is missing from the extracted patch.\n");
    exit(3);
}
$viewContents = (string) file_get_contents($analyticsView);
if (str_contains($viewContents, "@extends('__D2D_LAYOUT__')")) {
    file_put_contents($analyticsView, str_replace("@extends('__D2D_LAYOUT__')", "@extends('{$layout}')", $viewContents));
}

// Register Analytics route safely.
p43AppendOnce(
    $root.'/routes/web.php',
    'D2D_PHASE43_ANALYTICS_ROUTES',
    "// D2D_PHASE43_ANALYTICS_ROUTES\nif (file_exists(__DIR__.'/phase4-analytics.php')) {\n    require __DIR__.'/phase4-analytics.php';\n}\n// /D2D_PHASE43_ANALYTICS_ROUTES"
);

// Add Analytics under the premium Phase 4.2 sidebar if available.
$phase4Nav = $root.'/resources/views/crm/partials/phase4-nav.blade.php';
if (is_file($phase4Nav)) {
    $nav = (string) file_get_contents($phase4Nav);
    if (! str_contains($nav, 'D2D_PHASE43_ANALYTICS_NAV')) {
        p43Backup($phase4Nav);
        $analyticsNav = <<<'BLADE'
{{-- D2D_PHASE43_ANALYTICS_NAV --}}
<div class="d2d-p42-nav-label">INSIGHTS</div>
<a href="{{ route('crm.analytics.index') }}" class="d2d-p42-nav-link {{ request()->routeIs('crm.analytics.*') ? 'is-active' : '' }}">
    <span class="d2d-p42-nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V9"/><path d="M10 19V5"/><path d="M16 19v-7"/><path d="M22 19V3"/></svg>
    </span>
    <span>Analytics</span>
</a>
<div style="height:14px"></div>
{{-- /D2D_PHASE43_ANALYTICS_NAV --}}
BLADE;
        file_put_contents($phase4Nav, $analyticsNav."\n".$nav);
    }
}

// Inject dashboard guidebook counts + analytics graph without changing DashboardController variables.
if ($dashboardPath && is_file($dashboardPath)) {
    $dashboard = (string) file_get_contents($dashboardPath);
    if (! str_contains($dashboard, 'D2D_PHASE43_DASHBOARD')) {
        p43Backup($dashboardPath);
        $snippet = "\n{{-- D2D_PHASE43_DASHBOARD --}}\n@include('crm.phase4.analytics.dashboard')\n{{-- /D2D_PHASE43_DASHBOARD --}}\n";
        $pos = strrpos($dashboard, '@endsection');
        if ($pos !== false) {
            $dashboard = substr($dashboard, 0, $pos).$snippet.substr($dashboard, $pos);
        } else {
            $dashboard .= $snippet;
        }
        file_put_contents($dashboardPath, $dashboard);
    }
}

// Add a tiny verification command.
$consoleBlock = <<<'PHP'
// D2D_PHASE43_ANALYTICS_CONSOLE
\Illuminate\Support\Facades\Artisan::command('analytics:doctor', function () {
    $this->info('D2D CRM Phase 4.3 Analytics Doctor');
    $checks = [
        'analytics_events' => \Illuminate\Support\Facades\Schema::hasTable('analytics_events'),
        'analytics_daily_metrics' => \Illuminate\Support\Facades\Schema::hasTable('analytics_daily_metrics'),
        'guidebook_resources' => \Illuminate\Support\Facades\Schema::hasTable('guidebook_resources'),
        'Analytics route' => \Illuminate\Support\Facades\Route::has('crm.analytics.index'),
        'Analytics service' => class_exists(\App\Services\Analytics\AnalyticsDashboardService::class),
        'Analytics recorder' => class_exists(\App\Services\Analytics\D2dAnalytics::class),
        'Dashboard partial' => is_file(resource_path('views/crm/phase4/analytics/dashboard.blade.php')),
    ];
    foreach ($checks as $label => $ok) {
        $ok ? $this->info('OK  '.$label) : $this->error('FAIL '.$label);
    }
    if (\Illuminate\Support\Facades\Schema::hasTable('analytics_events')) {
        $this->line('Analytics events currently stored: '.\Illuminate\Support\Facades\DB::table('analytics_events')->count());
    }
    if (\Illuminate\Support\Facades\Schema::hasTable('guidebook_resources')) {
        $this->line('Guidebooks/resources currently stored: '.\Illuminate\Support\Facades\DB::table('guidebook_resources')->count());
    }
})->purpose('Check D2D CRM Phase 4.3 analytics installation');
// /D2D_PHASE43_ANALYTICS_CONSOLE
PHP;
p43AppendOnce($root.'/routes/console.php', 'D2D_PHASE43_ANALYTICS_CONSOLE', $consoleBlock);

echo "\nD2D CRM Phase 4.3 installed.\n";
echo "Detected CRM layout: {$layout}\n";
echo $dashboardPath ? "Dashboard analytics block registered.\n" : "Dashboard view was not auto-detected; Analytics tab is still available.\n";
echo "\nRun next:\n";
echo "  php artisan optimize:clear\n";
echo "  php artisan migrate --force\n";
echo "  php artisan analytics:doctor\n";
echo "  php artisan optimize\n\n";
echo "Important: analytics will show zero until the future public Laravel site sends real D2D events. No fake traffic is generated.\n";
echo "Never run migrate:fresh, migrate:reset, migrate:refresh, rollback, or db:wipe on the shared database.\n";
