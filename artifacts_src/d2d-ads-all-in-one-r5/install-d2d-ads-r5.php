<?php
declare(strict_types=1);

$base = __DIR__;
$payload = $base.'/payload';

echo "=== D2D ADS ALL-IN-ONE R5 ===\n";

if (!is_file($base.'/artisan')) {
    fwrite(STDERR, "ERROR: artisan not found. Extract this package into /home/daresdre/d2d-laravel/.\n");
    exit(1);
}
if (str_contains($base, 'crm.dares2dream.com') || str_contains($base, 'portal.dares2dream.com')) {
    fwrite(STDERR, "ERROR: This installer belongs only in the public Laravel app.\n");
    exit(1);
}

$stamp = date('Ymd-His');
$backup = $base.'/storage/app/phase5-backups/ads-r5-'.$stamp;
@mkdir($backup, 0775, true);

$targets = [
    'app/Providers/D2dAdsUnifiedServiceProvider.php',
    'app/Http/Middleware/D2dAdsUnifiedMiddleware.php',
    'routes/phase5-ads-r5-console.php',
    'public/d2d-ads-r5.js',
    'public/d2d-ads-r5.css',
];

foreach ($targets as $relative) {
    $src = $payload.'/'.$relative;
    $dst = $base.'/'.$relative;
    if (!is_file($src)) {
        fwrite(STDERR, "ERROR: payload missing {$relative}\n");
        exit(1);
    }
    if (is_file($dst)) {
        @mkdir(dirname($backup.'/'.$relative), 0775, true);
        copy($dst, $backup.'/'.$relative);
    }
    @mkdir(dirname($dst), 0775, true);
    if (!copy($src, $dst)) {
        fwrite(STDERR, "ERROR: failed to install {$relative}\n");
        exit(1);
    }
    echo "Installed: {$relative}\n";
}

// Preserve and register the unified provider FIRST so its web middleware wraps older middleware.
$providers = $base.'/bootstrap/providers.php';
if (!is_file($providers)) {
    fwrite(STDERR, "ERROR: bootstrap/providers.php not found.\n");
    exit(1);
}
@mkdir(dirname($backup.'/bootstrap/providers.php'), 0775, true);
copy($providers, $backup.'/bootstrap/providers.php');
$content = (string) file_get_contents($providers);
$providerLine = 'App\\Providers\\D2dAdsUnifiedServiceProvider::class,';

// Remove a prior R5 registration only; leave every other provider untouched.
$content = preg_replace('/^\s*App\\\\Providers\\\\D2dAdsUnifiedServiceProvider::class,?\s*$/m', '', $content) ?? $content;
if (preg_match('/return\s*\[/', $content) !== 1) {
    fwrite(STDERR, "ERROR: could not safely edit bootstrap/providers.php.\n");
    exit(1);
}
$content = preg_replace('/return\s*\[/', "return [\n    {$providerLine}", $content, 1) ?? $content;
file_put_contents($providers, $content);
echo "Registered unified ads provider first.\n";

// Recreate authoritative ads.txt for this publisher.
$adsTxt = $base.'/public/ads.txt';
if (is_file($adsTxt)) {
    @mkdir(dirname($backup.'/public/ads.txt'), 0775, true);
    copy($adsTxt, $backup.'/public/ads.txt');
}
file_put_contents($adsTxt, "google.com, pub-5807824613154542, DIRECT, f08c47fec0942fa0\n");
echo "Created: public/ads.txt\n";

file_put_contents($base.'/storage/app/phase5-ads-r5-backup.txt', $backup.PHP_EOL);

echo "\nR5 SAFETY POLICY\n";
echo "- Does NOT replace any Blade view, page controller or web route.\n";
echo "- Does NOT modify CRM or Portal.\n";
echo "- Does NOT run migrations or write business data.\n";
echo "- Cleans legacy D2D AdSense injections from final HTML before rendering R5 ads.\n";
echo "- AdSense code is emitted only on approved single-detail live routes.\n";
echo "- Manual Mid slot: 9432468543\n";
echo "- Manual End/right-rail slot: 8039495827\n";
echo "\nRun now:\n";
echo "php artisan optimize:clear\n";
echo "php artisan phase5:ads-r5-doctor\n";
