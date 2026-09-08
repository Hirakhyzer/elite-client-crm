<?php
declare(strict_types=1);

$base = __DIR__;
$payload = $base.'/payload';
$publisher = 'ca-pub-5807824613154542';
$adsTxtLine = 'google.com, pub-5807824613154542, DIRECT, f08c47fec0942fa0';

echo "=== D2D LIVE ADS ONLY INSTALLER ===\n";

if (!is_file($base.'/artisan')) {
    fwrite(STDERR, "ERROR: Extract this package into /home/daresdre/d2d-laravel/ first.\n");
    exit(1);
}
if (str_contains($base, 'crm.dares2dream.com') || str_contains($base, 'portal.dares2dream.com')) {
    fwrite(STDERR, "ERROR: Wrong app. This installer is only for the public Laravel website.\n");
    exit(1);
}

$stamp = date('Ymd-His');
$backup = $base.'/storage/app/phase5-backups/live-ads-'.$stamp;
@mkdir($backup, 0775, true);

$targets = [
    'app/Providers/D2dLiveAdsServiceProvider.php',
    'app/Http/Middleware/D2dLiveAdsMiddleware.php',
    'routes/phase5-live-ads-console.php',
];

foreach ($targets as $relative) {
    $src = $payload.'/'.$relative;
    $dst = $base.'/'.$relative;
    if (!is_file($src)) {
        fwrite(STDERR, "ERROR: Missing payload file {$relative}\n");
        exit(1);
    }
    if (is_file($dst)) {
        @mkdir(dirname($backup.'/'.$relative), 0775, true);
        copy($dst, $backup.'/'.$relative);
    }
    @mkdir(dirname($dst), 0775, true);
    copy($src, $dst);
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

// Remove only our previous ads providers, leaving every other provider untouched.
$content = preg_replace('/^\s*App\\\\Providers\\\\D2dAdsServiceProvider::class,?\s*$/m', '', $content) ?? $content;
$content = preg_replace('/^\s*App\\\\Providers\\\\D2dLiveAdsServiceProvider::class,?\s*$/m', '', $content) ?? $content;

$needle = 'App\\Providers\\D2dLiveAdsServiceProvider::class';
$pos = strrpos($content, '];');
if ($pos === false) {
    fwrite(STDERR, "ERROR: Could not safely register ads provider.\n");
    exit(1);
}
$before = rtrim(substr($content, 0, $pos));
$after = substr($content, $pos);
$comma = str_ends_with($before, ',') ? '' : ',';
$content = $before.$comma."\n    ".$needle.",\n".$after;
file_put_contents($providers, $content);
echo "Registered D2dLiveAdsServiceProvider.\n";

$adsTxt = $base.'/public/ads.txt';
if (is_file($adsTxt)) {
    @mkdir(dirname($backup.'/public/ads.txt'), 0775, true);
    copy($adsTxt, $backup.'/public/ads.txt');
    $existing = trim((string) file_get_contents($adsTxt));
    if (!str_contains($existing, 'pub-5807824613154542')) {
        $existing = rtrim($existing)."\n".$adsTxtLine."\n";
        file_put_contents($adsTxt, $existing);
    }
} else {
    file_put_contents($adsTxt, $adsTxtLine."\n");
}
echo "ads.txt ready: {$adsTxt}\n";

file_put_contents($base.'/storage/app/d2d-live-ads-backup.txt', $backup.PHP_EOL);

echo "\nPublisher embedded: {$publisher}\n";
echo "Approved only: single Blog, University, Scholarship, Opportunity and Job pages.\n";
echo "Hard blocked: home, listing pages, resources/guidebooks, tools, CRM and Portal.\n";
echo "Preview domain never loads the live AdSense bootstrap.\n";
echo "No page views/controllers/routes were replaced. No migrations or DB writes.\n\n";
echo "Run:\n";
echo "php artisan optimize:clear\n";
echo "php artisan phase5:live-ads-doctor\n";
