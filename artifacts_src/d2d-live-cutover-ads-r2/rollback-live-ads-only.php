<?php
declare(strict_types=1);

$base = __DIR__;
$pointer = $base.'/storage/app/d2d-live-ads-backup.txt';
if (!is_file($pointer)) {
    fwrite(STDERR, "ERROR: No live ads backup pointer found.\n");
    exit(1);
}
$backup = trim((string) file_get_contents($pointer));
if ($backup === '' || !is_dir($backup)) {
    fwrite(STDERR, "ERROR: Ads backup directory unavailable.\n");
    exit(1);
}

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($backup, FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $file) {
    if (!$file->isFile()) continue;
    $relative = substr($file->getPathname(), strlen($backup)+1);
    $target = $base.'/'.$relative;
    @mkdir(dirname($target), 0775, true);
    copy($file->getPathname(), $target);
    echo "Restored: {$relative}\n";
}

echo "Ads-only rollback complete. Run php artisan optimize:clear\n";
