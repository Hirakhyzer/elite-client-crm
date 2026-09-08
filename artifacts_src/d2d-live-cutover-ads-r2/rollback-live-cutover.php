<?php
declare(strict_types=1);

$base = __DIR__;
$stateFile = $base.'/storage/app/d2d-live-cutover-state.json';
if (!is_file($stateFile)) {
    fwrite(STDERR, "ERROR: No live cutover state file found.\n");
    exit(1);
}
$state = json_decode((string) file_get_contents($stateFile), true);
if (!is_array($state)) {
    fwrite(STDERR, "ERROR: Invalid state file.\n");
    exit(1);
}

$publicHtml = $state['public_html'] ?? dirname($base).'/public_html';
$wpBackup = $state['wordpress_backup'] ?? null;
$envBackup = $state['env_backup'] ?? null;

if (is_link($publicHtml)) {
    unlink($publicHtml);
    echo "Removed Laravel public_html symlink.\n";
}

if ($wpBackup && is_dir($wpBackup) && !file_exists($publicHtml)) {
    rename($wpBackup, $publicHtml);
    echo "Restored old public_html.\n";
}

if ($envBackup && is_file($envBackup)) {
    copy($envBackup, $base.'/.env');
    echo "Restored pre-live .env.\n";
}

echo "Rollback complete. Run php artisan optimize:clear\n";
