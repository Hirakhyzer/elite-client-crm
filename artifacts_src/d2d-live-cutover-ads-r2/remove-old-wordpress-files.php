<?php
declare(strict_types=1);

$base = __DIR__;
$stateFile = $base.'/storage/app/d2d-live-cutover-state.json';
if (!in_array('--yes', $argv, true)) {
    fwrite(STDERR, "Safety stop. Re-run with --yes only after dares2dream.com is verified on Laravel.\n");
    exit(1);
}
if (!is_file($stateFile)) {
    fwrite(STDERR, "ERROR: No live cutover state file found.\n");
    exit(1);
}
$state = json_decode((string) file_get_contents($stateFile), true);
$wpBackup = is_array($state) ? ($state['wordpress_backup'] ?? null) : null;
$publicHtml = is_array($state) ? ($state['public_html'] ?? dirname($base).'/public_html') : dirname($base).'/public_html';
$laravelPublic = realpath($base.'/public');

if (!$laravelPublic || !is_link($publicHtml) || realpath($publicHtml) !== $laravelPublic) {
    fwrite(STDERR, "ERROR: public_html is not confirmed to point at Laravel public. Refusing deletion.\n");
    exit(1);
}
if (!$wpBackup || !is_dir($wpBackup) || !str_contains(basename($wpBackup), 'wordpress-old-')) {
    fwrite(STDERR, "ERROR: Old WordPress backup directory not found or not recognized.\n");
    exit(1);
}

function deleteTree(string $dir): void {
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isLink() || $item->isFile()) unlink($item->getPathname());
        else rmdir($item->getPathname());
    }
    rmdir($dir);
}

deleteTree($wpBackup);
echo "Deleted old WordPress FILES: {$wpBackup}\n";

$db = null;
$env = $base.'/.env';
if (is_file($env)) {
    foreach (file($env, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (str_starts_with($line, 'DB_DATABASE=')) {
            $db = trim(substr($line, strlen('DB_DATABASE=')), " \t\n\r\0\x0B\"'");
            break;
        }
    }
}
echo "Current Laravel DB_DATABASE: ".($db ?: 'not detected')."\n";
echo "IMPORTANT: this script did NOT delete any database. Never delete the database shown above.\n";
