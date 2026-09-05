<?php

declare(strict_types=1);

/**
 * D2D CRM Phase 4 installer
 * - Adds only route/console registrations and conservative CRM navigation links.
 * - Does not run migrations and never touches portal.dares2dream.com.
 * - Re-running is safe; markers prevent duplicate edits.
 */

$root = __DIR__;
if (! is_file($root.'/artisan')) {
    fwrite(STDERR, "ERROR: Put this patch in the CRM Laravel root before running install-phase4.php.\n");
    exit(1);
}

function phase4Backup(string $path): void
{
    $backup = $path.'.phase4-backup';
    if (is_file($path) && ! is_file($backup)) {
        copy($path, $backup);
    }
}

function phase4AppendOnce(string $path, string $marker, string $content): bool
{
    if (! is_file($path)) {
        return false;
    }
    $current = (string) file_get_contents($path);
    if (str_contains($current, $marker)) {
        return true;
    }
    phase4Backup($path);
    file_put_contents($path, rtrim($current)."\n\n".$content."\n");
    return true;
}

$webRoutes = $root.'/routes/web.php';
phase4AppendOnce(
    $webRoutes,
    'D2D_PHASE4_ROUTES',
    "// D2D_PHASE4_ROUTES\nif (file_exists(__DIR__.'/phase4.php')) {\n    require __DIR__.'/phase4.php';\n}\n// /D2D_PHASE4_ROUTES"
);

$consoleRoutes = $root.'/routes/console.php';
$consoleBlock = <<<'PHP'
// D2D_PHASE4_CONSOLE
\Illuminate\Support\Facades\Artisan::command('phase4:doctor', function () {
    $this->info('D2D CRM Phase 4 Doctor');
    $checks = [
        'content_posts' => \Illuminate\Support\Facades\Schema::hasTable('content_posts'),
        'seo_meta' => \Illuminate\Support\Facades\Schema::hasTable('seo_meta'),
        'wordpress_imports' => \Illuminate\Support\Facades\Schema::hasTable('wordpress_imports'),
        'guidebook_resources' => \Illuminate\Support\Facades\Schema::hasTable('guidebook_resources'),
        'guidebook_resource_versions' => \Illuminate\Support\Facades\Schema::hasTable('guidebook_resource_versions'),
        'ZipArchive PHP extension' => class_exists(\ZipArchive::class),
        'Phase 4 route file' => is_file(base_path('routes/phase4.php')),
        'Phase 4 import storage' => is_writable(storage_path('app/phase4-import')),
    ];
    foreach ($checks as $name => $ok) {
        $ok ? $this->info('OK  '.$name) : $this->error('FAIL '.$name);
    }
    if (\Illuminate\Support\Facades\Schema::hasTable('content_posts')) {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('content_posts');
        $this->line('content_posts columns: '.implode(', ', $columns));
    }
    $this->newLine();
    $this->line('WordPress migration: '.url('/phase4/wordpress'));
    $this->line('Guidebooks & Resources: '.url('/phase4/guidebooks'));
})->purpose('Check D2D CRM Phase 4 installation');

\Illuminate\Support\Facades\Artisan::command('wordpress:dry-run {sql=storage/app/phase4-import/legacy.sql} {--uploads=storage/app/phase4-import/uploads.zip}', function () {
    $resolve = static function (string $path): string {
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            return $path;
        }
        return base_path($path);
    };
    $sql = $resolve((string) $this->argument('sql'));
    $zipArg = (string) $this->option('uploads');
    $zip = $zipArg !== '' ? $resolve($zipArg) : null;
    if (! is_file($sql)) {
        $this->error('SQL file not found: '.$sql);
        return 1;
    }
    if ($zip && ! is_file($zip)) {
        $zip = null;
        $this->warn('uploads.zip not found; media availability will not be verified.');
    }
    @set_time_limit(0);
    $report = app(\App\Services\Phase4\WordPressPostImporter::class)->analyze($sql, $zip, 'd2d_');
    foreach ($report['counts'] as $key => $value) {
        $this->line(str_pad($key, 24).$value);
    }
    if (! empty($report['target']['warnings'])) {
        foreach ($report['target']['warnings'] as $warning) {
            $this->warn($warning);
        }
    }
    $reportPath = storage_path('app/phase4-import/dry-run.json');
    file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $this->info('Dry Run saved: '.$reportPath);
    $this->info('Nothing was imported.');
    return 0;
})->purpose('Dry Run the D2D WordPress posts-only migration');

\Illuminate\Support\Facades\Artisan::command('wordpress:import {sql=storage/app/phase4-import/legacy.sql} {--uploads=storage/app/phase4-import/uploads.zip} {--yes}', function () {
    if (! $this->option('yes')) {
        $this->error('Refusing to import without --yes. Run wordpress:dry-run first.');
        return 1;
    }
    $reportPath = storage_path('app/phase4-import/dry-run.json');
    if (! is_file($reportPath)) {
        $this->error('Dry Run report is missing. Run php artisan wordpress:dry-run first.');
        return 1;
    }
    $resolve = static function (string $path): string {
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            return $path;
        }
        return base_path($path);
    };
    $sql = $resolve((string) $this->argument('sql'));
    $zipArg = (string) $this->option('uploads');
    $zip = ($zipArg !== '' && is_file($resolve($zipArg))) ? $resolve($zipArg) : null;
    @set_time_limit(0);
    $result = app(\App\Services\Phase4\WordPressPostImporter::class)->importReady($sql, $zip, null, 'd2d_');
    $this->info('Imported: '.$result['imported']);
    $this->line('Skipped: '.$result['skipped']);
    $this->line('Failed: '.$result['failed']);
    $this->line('SEO warnings: '.$result['seo_warnings']);
    $this->line('Media warnings: '.$result['media_warnings']);
    @unlink($reportPath);
    return $result['failed'] > 0 ? 2 : 0;
})->purpose('Import READY WordPress posts after a Dry Run');
// /D2D_PHASE4_CONSOLE
PHP;
phase4AppendOnce($consoleRoutes, 'D2D_PHASE4_CONSOLE', $consoleBlock);

$storageDirs = [
    $root.'/storage/app/phase4-import',
    $root.'/storage/app/public/legacy-wordpress',
    $root.'/storage/app/public/guidebooks',
];
foreach ($storageDirs as $dir) {
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    @chmod($dir, 0775);
}

// Conservative navigation injection. If the Phase 3.5 layout structure is different,
// installation continues and the two Phase 4 routes remain fully usable.
$navMarker = 'D2D_PHASE4_NAV';
$navSnippet = <<<'BLADE'
{{-- D2D_PHASE4_NAV --}}
<div class="d2d-phase4-nav" style="padding:12px 12px 4px;">
    <div style="font-size:10px;letter-spacing:.14em;opacity:.5;margin:6px 8px;">RESOURCES</div>
    <a href="{{ route('crm.phase4.guidebooks.index') }}" style="display:block;padding:9px 10px;border-radius:9px;text-decoration:none;">Guidebooks &amp; Resources</a>
    <div style="font-size:10px;letter-spacing:.14em;opacity:.5;margin:12px 8px 6px;">MIGRATION</div>
    <a href="{{ route('crm.phase4.wordpress.index') }}" style="display:block;padding:9px 10px;border-radius:9px;text-decoration:none;">WordPress Migration</a>
</div>
{{-- /D2D_PHASE4_NAV --}}
BLADE;

$navCandidates = [
    $root.'/resources/views/crm/partials/sidebar.blade.php',
    $root.'/resources/views/crm/layouts/app.blade.php',
    $root.'/resources/views/layouts/crm.blade.php',
];
$navPatched = false;
foreach ($navCandidates as $candidate) {
    if (! is_file($candidate)) {
        continue;
    }
    $contents = (string) file_get_contents($candidate);
    if (str_contains($contents, $navMarker)) {
        $navPatched = true;
        break;
    }
    $position = strripos($contents, '</aside>');
    if ($position === false) {
        $position = strripos($contents, '</nav>');
    }
    if ($position !== false) {
        phase4Backup($candidate);
        $contents = substr($contents, 0, $position)."\n".$navSnippet."\n".substr($contents, $position);
        file_put_contents($candidate, $contents);
        $navPatched = true;
        break;
    }
}

echo "\nD2D CRM Phase 4 files installed.\n";
echo $navPatched ? "CRM navigation links added.\n" : "Navigation layout was not auto-modified; use /phase4/wordpress and /phase4/guidebooks.\n";
echo "\nRun these safe commands next:\n";
echo "  php artisan optimize:clear\n";
echo "  php artisan migrate --force\n";
echo "  php artisan phase4:doctor\n";
echo "  php artisan optimize\n\n";
echo "Do NOT use migrate:fresh, migrate:reset, migrate:refresh, rollback or db:wipe on the shared database.\n";
