<?php

declare(strict_types=1);

/**
 * D2D CRM Phase 4.4 — Popup Preview UX
 * - Adds a CRM-only popup preview lab.
 * - Adds Preview buttons to existing Popup Manager pages via a safe global partial.
 * - No migrations, no public-site changes, no Portal changes.
 */

$root = __DIR__;
if (! is_file($root.'/artisan')) {
    fwrite(STDERR, "ERROR: Extract this patch into the CRM Laravel root first.\n");
    exit(1);
}

function p44ViewPath(string $root, string $view): string
{
    return $root.'/resources/views/'.str_replace('.', '/', $view).'.blade.php';
}

function p44Backup(string $path): void
{
    $backup = $path.'.phase4.4-backup';
    if (is_file($path) && ! is_file($backup)) {
        copy($path, $backup);
    }
}

function p44AppendOnce(string $path, string $marker, string $content): bool
{
    if (! is_file($path)) return false;
    $current = (string) file_get_contents($path);
    if (str_contains($current, $marker)) return true;
    p44Backup($path);
    file_put_contents($path, rtrim($current)."\n\n".$content."\n");
    return true;
}

// Detect the working CRM layout from dashboard.
$layout = null;
foreach ([
    $root.'/resources/views/crm/dashboard.blade.php',
    $root.'/resources/views/crm/index.blade.php',
    $root.'/resources/views/dashboard.blade.php',
] as $dashboard) {
    if (! is_file($dashboard)) continue;
    $contents = (string) file_get_contents($dashboard);
    if (preg_match('/@extends\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*\)/', $contents, $m)) {
        $candidate = trim($m[1]);
        if ($candidate !== '' && is_file(p44ViewPath($root, $candidate))) {
            $layout = $candidate;
            break;
        }
    }
}
if ($layout === null) {
    foreach (['crm.layout','crm.layouts.master','crm.layouts.dashboard','layouts.crm','layouts.app'] as $candidate) {
        if (is_file(p44ViewPath($root, $candidate))) {
            $layout = $candidate;
            break;
        }
    }
}
if ($layout === null) {
    fwrite(STDERR, "ERROR: Could not detect the active CRM Blade layout.\n");
    exit(2);
}

// Replace layout token in Phase 4.4 views.
$viewDir = $root.'/resources/views/crm/phase4_4';
if (is_dir($viewDir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewDir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) continue;
        $path = $file->getPathname();
        $contents = (string) file_get_contents($path);
        if (str_contains($contents, '__D2D_LAYOUT__')) {
            p44Backup($path);
            file_put_contents($path, str_replace('__D2D_LAYOUT__', $layout, $contents));
        }
    }
}

// Register routes.
$routeFile = $root.'/routes/web.php';
p44AppendOnce(
    $routeFile,
    'D2D_PHASE4_4_ROUTES',
    "// D2D_PHASE4_4_ROUTES\nif (file_exists(__DIR__.'/phase4_4.php')) { require __DIR__.'/phase4_4.php'; }\n// /D2D_PHASE4_4_ROUTES"
);

// Inject global helper partial into working CRM layout before </body> where possible.
$layoutPath = p44ViewPath($root, $layout);
if (is_file($layoutPath)) {
    $contents = (string) file_get_contents($layoutPath);
    if (!str_contains($contents, 'D2D_PHASE4_4_POPUP_PREVIEW')) {
        p44Backup($layoutPath);
        $snippet = "\n{{-- D2D_PHASE4_4_POPUP_PREVIEW --}}\n@includeIf('crm.partials.phase4-4-popup-preview')\n{{-- /D2D_PHASE4_4_POPUP_PREVIEW --}}\n";
        $pos = strripos($contents, '</body>');
        if ($pos !== false) {
            $contents = substr($contents,0,$pos).$snippet.substr($contents,$pos);
        } else {
            $contents .= $snippet;
        }
        file_put_contents($layoutPath, $contents);
    }
}

echo "D2D CRM Phase 4.4 Popup Preview UX installed.\n";
echo "Detected CRM layout: {$layout}\n";
echo "No migration is required.\n\n";
echo "Now run:\n";
echo "  php artisan optimize:clear\n";
echo "  php artisan route:list | grep popup-preview\n";
echo "  php artisan optimize\n\n";
echo "Preview Lab: ".rtrim((string)getenv('APP_URL'), '/')."/phase4/popup-preview\n";
