<?php

declare(strict_types=1);

$base = __DIR__;
$source = $base . '/public/v11';
$public = $base . '/public';

echo "=== D2D Phase 5A.2 — V11 Asset Fix ===\n";

if (!is_file($base . '/artisan')) {
    fwrite(STDERR, "ERROR: artisan not found. Run this from /home/daresdre/d2d-laravel.\n");
    exit(1);
}

if (!is_dir($source) || !is_file($source . '/index.html')) {
    fwrite(STDERR, "ERROR: public/v11/index.html not found.\n");
    fwrite(STDERR, "Run: php artisan phase5:doctor --install-v11\n");
    exit(1);
}

function copyRecursive(string $src, string $dst): void
{
    if (!is_dir($dst)) {
        mkdir($dst, 0775, true);
    }

    $items = scandir($src);
    if ($items === false) {
        throw new RuntimeException("Cannot read directory: {$src}");
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $from = $src . '/' . $item;
        $to = $dst . '/' . $item;

        if (is_dir($from)) {
            copyRecursive($from, $to);
        } else {
            if (!copy($from, $to)) {
                throw new RuntimeException("Failed copying {$from} to {$to}");
            }
        }
    }
}

$skip = ['index.html', 'README.md'];

foreach (scandir($source) ?: [] as $item) {
    if ($item === '.' || $item === '..' || in_array($item, $skip, true)) {
        continue;
    }

    $from = $source . '/' . $item;
    $to = $public . '/' . $item;

    if (is_dir($from)) {
        copyRecursive($from, $to);
        echo "Mirrored directory: {$item}/\n";
    } else {
        if (!copy($from, $to)) {
            throw new RuntimeException("Failed copying {$item}");
        }
        echo "Mirrored file: {$item}\n";
    }
}

if (is_file($source . '/assets/favicon.png')) {
    copy($source . '/assets/favicon.png', $public . '/favicon.png');
    echo "Compatibility favicon: favicon.png\n";
}

echo "\nAsset verification:\n";
$checks = [
    'styles.css',
    'app.js',
    'assets/d2d-eagle.png',
    'assets/favicon.png',
    'hero-background.mp4',
    'hero-video-base.jpg',
    'hero-video-base.webp',
];

foreach ($checks as $check) {
    echo str_pad($check, 34) . (is_file($public . '/' . $check) ? " OK\n" : " MISSING\n");
}

echo "\nDONE. Now run:\n";
echo "php artisan optimize:clear\n";
echo "php artisan optimize\n";
echo "\nThen hard-refresh https://preview.dares2dream.com\n";
