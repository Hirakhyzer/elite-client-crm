<?php
declare(strict_types=1);

$base = __DIR__;
$pointer = $base.'/storage/app/d2d-nav-media-r6-backup.txt';

if (!is_file($pointer)) {
    fwrite(STDERR, "No R6 backup pointer found.\n");
    exit(1);
}
$backup = trim((string) file_get_contents($pointer));
if ($backup === '' || !is_dir($backup)) {
    fwrite(STDERR, "Backup unavailable: {$backup}\n");
    exit(1);
}

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($backup, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if (!$file->isFile()) continue;
    $relative = substr($file->getPathname(), strlen($backup)+1);
    $target = $base.'/'.$relative;
    @mkdir(dirname($target), 0775, true);
    copy($file->getPathname(), $target);
    echo "Restored: {$relative}\n";
}

echo "Rollback complete. Run php artisan optimize:clear\n";
