<?php

declare(strict_types=1);

/**
 * D2D CRM Phase 4.1 layout compatibility hotfix.
 * Fixes Phase 4 views that referenced the non-existent crm.layouts.app view.
 * No migrations. No database writes. No Portal changes.
 */

$root = __DIR__;
if (! is_file($root.'/artisan')) {
    fwrite(STDERR, "ERROR: Put/extract this hotfix in the CRM Laravel root before running it.\n");
    exit(1);
}

function p41ViewPath(string $root, string $view): string
{
    return $root.'/resources/views/'.str_replace('.', '/', $view).'.blade.php';
}

function p41Backup(string $path): void
{
    $backup = $path.'.phase4.1-backup';
    if (is_file($path) && ! is_file($backup)) {
        copy($path, $backup);
    }
}

$layout = null;
$dashboardCandidates = [
    $root.'/resources/views/crm/dashboard.blade.php',
    $root.'/resources/views/crm/index.blade.php',
    $root.'/resources/views/dashboard.blade.php',
];

foreach ($dashboardCandidates as $dashboard) {
    if (! is_file($dashboard)) {
        continue;
    }
    $contents = (string) file_get_contents($dashboard);
    if (preg_match('/@extends\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*\)/', $contents, $m)) {
        $candidate = trim($m[1]);
        if ($candidate !== '' && is_file(p41ViewPath($root, $candidate))) {
            $layout = $candidate;
            break;
        }
    }
}

if ($layout === null) {
    $fallbacks = [
        'crm.layout',
        'crm.layouts.master',
        'crm.layouts.dashboard',
        'layouts.crm',
        'layouts.app',
    ];
    foreach ($fallbacks as $candidate) {
        if (is_file(p41ViewPath($root, $candidate))) {
            $layout = $candidate;
            break;
        }
    }
}

if ($layout === null) {
    fwrite(STDERR, "ERROR: Could not detect the working CRM Blade layout.\n");
    fwrite(STDERR, "Run: grep -R \"@extends\" resources/views/crm/dashboard.blade.php\n");
    exit(2);
}

$phase4Dir = $root.'/resources/views/crm/phase4';
if (! is_dir($phase4Dir)) {
    fwrite(STDERR, "ERROR: Phase 4 views were not found at resources/views/crm/phase4.\n");
    exit(3);
}

$changed = 0;
$checked = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($phase4Dir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }
    $path = $file->getPathname();
    $checked++;
    $contents = (string) file_get_contents($path);

    if (! str_contains($contents, "@extends('crm.layouts.app')") && ! str_contains($contents, '@extends("crm.layouts.app")')) {
        continue;
    }

    p41Backup($path);
    $updated = str_replace(
        ["@extends('crm.layouts.app')", '@extends("crm.layouts.app")'],
        "@extends('".$layout."')",
        $contents
    );
    file_put_contents($path, $updated);
    $changed++;
}

echo "D2D CRM Phase 4.1 hotfix applied.\n";
echo "Detected working CRM layout: {$layout}\n";
echo "Phase 4 Blade views checked: {$checked}\n";
echo "Phase 4 Blade views fixed: {$changed}\n\n";
echo "Guidebooks remain manual CRM CRUD/version uploads only; no WordPress guidebooks are imported.\n";
echo "WordPress Migration remains post_type=post only.\n\n";
echo "Now run:\n";
echo "  php artisan optimize:clear\n";
echo "  php artisan phase4:doctor\n\n";
echo "No migration is required for this hotfix.\n";
