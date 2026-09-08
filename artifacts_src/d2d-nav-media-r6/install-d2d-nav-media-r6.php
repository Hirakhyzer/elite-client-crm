<?php
declare(strict_types=1);

$base = __DIR__;
$payload = $base.'/payload';

echo "=== D2D R6 — NAV + HERO MEDIA HELPER ===\n";

if (!is_file($base.'/artisan')) {
    fwrite(STDERR, "ERROR: artisan not found. Extract this package into /home/daresdre/d2d-laravel/.\n");
    exit(1);
}
if (str_contains($base, 'crm.dares2dream.com') || str_contains($base, 'portal.dares2dream.com')) {
    fwrite(STDERR, "ERROR: Wrong application. Public Laravel only.\n");
    exit(1);
}

$stamp = date('Ymd-His');
$backup = $base.'/storage/app/phase5-backups/nav-media-r6-'.$stamp;
@mkdir($backup, 0775, true);

$targets = [
    'app/Providers/D2dNavEnhancerServiceProvider.php',
    'app/Http/Middleware/D2dNavEnhancerMiddleware.php',
    'public/d2d-nav-r6.js',
];

foreach ($targets as $relative) {
    $src = $payload.'/'.$relative;
    $dst = $base.'/'.$relative;
    if (!is_file($src)) {
        fwrite(STDERR, "ERROR: Payload missing {$relative}\n");
        exit(1);
    }
    if (is_file($dst)) {
        @mkdir(dirname($backup.'/'.$relative), 0775, true);
        copy($dst, $backup.'/'.$relative);
    }
    @mkdir(dirname($dst), 0775, true);
    if (!copy($src, $dst)) {
        fwrite(STDERR, "ERROR: Failed to install {$relative}\n");
        exit(1);
    }
    echo "Installed: {$relative}\n";
}

$providers = $base.'/bootstrap/providers.php';
if (!is_file($providers)) {
    fwrite(STDERR, "ERROR: bootstrap/providers.php not found.\n");
    exit(1);
}
@mkdir(dirname($backup.'/bootstrap/providers.php'), 0775, true);
copy($providers, $backup.'/bootstrap/providers.php');

$content = (string) file_get_contents($providers);
$provider = 'App\\Providers\\D2dNavEnhancerServiceProvider::class';
if (!str_contains($content, $provider)) {
    $pos = strrpos($content, '];');
    if ($pos === false) {
        fwrite(STDERR, "ERROR: Could not safely register provider.\n");
        exit(1);
    }
    $before = rtrim(substr($content, 0, $pos));
    $after = substr($content, $pos);
    $comma = str_ends_with($before, ',') ? '' : ',';
    $content = $before.$comma."\n    ".$provider.",\n".$after;
    file_put_contents($providers, $content);
    echo "Registered D2dNavEnhancerServiceProvider.\n";
} else {
    echo "D2dNavEnhancerServiceProvider already registered.\n";
}

file_put_contents($base.'/storage/app/d2d-nav-media-r6-backup.txt', $backup.PHP_EOL);

echo "\nNAV ITEMS:\n";
echo "- Opportunities -> /opportunities\n";
echo "- Jobs Abroad -> /jobs\n";
echo "- Internships -> /internships\n";
echo "\nNo Blade pages, controllers, routes, CRM, Portal, ads, or database content changed.\n";
echo "No migrations.\n\n";
echo "Run:\n";
echo "php artisan optimize:clear\n";
echo "php locate-v11-hero.php\n";
