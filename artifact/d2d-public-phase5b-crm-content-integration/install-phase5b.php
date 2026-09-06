<?php

declare(strict_types=1);

$base = __DIR__;

echo "=== D2D Phase 5B — CRM Content Integration ===\n";

if (!is_file($base . '/artisan')) {
    fwrite(STDERR, "ERROR: artisan not found. Extract this ZIP into /home/daresdre/d2d-laravel/.\n");
    exit(1);
}

if (str_contains($base, 'crm.dares2dream.com') || str_contains($base, 'portal.dares2dream.com')) {
    fwrite(STDERR, "ERROR: Phase 5B must only be installed in the separate public Laravel app.\n");
    exit(1);
}

$stamp = date('Ymd-His');
$backup = $base . '/storage/app/phase5-backups/' . $stamp;
@mkdir($backup, 0775, true);

foreach (['routes/web.php', 'routes/console.php'] as $relative) {
    $file = $base . '/' . $relative;
    if (is_file($file)) {
        @mkdir(dirname($backup . '/' . $relative), 0775, true);
        copy($file, $backup . '/' . $relative);
    }
}

function ensureRequire(string $file, string $line): void
{
    $content = is_file($file) ? file_get_contents($file) : "<?php\n";
    if ($content === false) throw new RuntimeException("Cannot read {$file}");
    if (!str_contains($content, $line)) {
        $content = rtrim($content) . "\n\n" . $line . "\n";
        if (file_put_contents($file, $content) === false) throw new RuntimeException("Cannot update {$file}");
        echo "Registered: " . basename($file) . "\n";
    } else {
        echo "Already registered: " . basename($file) . "\n";
    }
}

ensureRequire($base . '/routes/web.php', "require __DIR__.'/phase5b.php';");
ensureRequire($base . '/routes/console.php', "require __DIR__.'/phase5b-console.php';");

if (!is_file($base . '/composer.json')) {
    $composer = [
        'name' => 'd2d/public-website',
        'description' => 'Dare To Dream Public Laravel Website',
        'type' => 'project',
        'license' => 'proprietary',
        'autoload' => ['psr-4' => ['App\\' => 'app/']],
    ];
    file_put_contents($base . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    echo "Created missing composer.json compatibility file.\n";
}

@mkdir($base . '/storage/framework/cache/data', 0775, true);
@mkdir($base . '/storage/framework/sessions', 0775, true);
@mkdir($base . '/storage/framework/views', 0775, true);
@mkdir($base . '/storage/logs', 0775, true);

if (!is_file($base . '/public/v11/index.html')) {
    echo "WARNING: public/v11/index.html was not found. Phase 5B routes will work, but your locked V11 homepage should remain in public/v11/.\n";
}

echo "\nInstalled Phase 5B files and route registration.\n";
echo "Backup: {$backup}\n";
echo "No migrations were run. No database rows were changed.\n\n";
echo "Now run:\n";
echo "php artisan optimize:clear\n";
echo "php artisan phase5b:doctor\n";
echo "php artisan route:list | grep -E 'blog|universities|opportunities|resources|api/v1'\n\n";
echo "Preview URLs:\n";
echo "  /blog\n  /universities\n  /opportunities\n  /resources\n  /api/v1/home\n";
