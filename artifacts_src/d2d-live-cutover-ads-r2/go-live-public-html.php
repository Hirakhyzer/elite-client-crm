<?php
declare(strict_types=1);

$base = __DIR__;
if (!is_file($base.'/artisan')) {
    fwrite(STDERR, "ERROR: Run this from /home/daresdre/d2d-laravel.\n");
    exit(1);
}

$home = dirname($base);
$public = realpath($base.'/public');
$publicHtml = $home.'/public_html';
$stateFile = $base.'/storage/app/d2d-live-cutover-state.json';

if (!$public || !is_dir($public)) {
    fwrite(STDERR, "ERROR: Laravel public directory not found.\n");
    exit(1);
}

$stamp = date('Ymd-His');
$state = [
    'time' => date(DATE_ATOM),
    'public_html' => $publicHtml,
    'laravel_public' => $public,
    'wordpress_backup' => null,
    'env_backup' => null,
];

// Back up and switch environment to the real domain.
$env = $base.'/.env';
if (is_file($env)) {
    $envBackup = $base.'/storage/app/.env.pre-live-'.$stamp;
    copy($env, $envBackup);
    $state['env_backup'] = $envBackup;
    $text = (string) file_get_contents($env);
    $pairs = [
        'APP_ENV' => 'production',
        'APP_URL' => 'https://dares2dream.com',
        'APP_DEBUG' => 'false',
    ];
    foreach ($pairs as $key => $value) {
        if (preg_match('/^'.preg_quote($key, '/').'=.*/m', $text)) {
            $text = preg_replace('/^'.preg_quote($key, '/').'=.*/m', $key.'='.$value, $text) ?? $text;
        } else {
            $text = rtrim($text)."\n{$key}={$value}\n";
        }
    }
    file_put_contents($env, $text);
    echo "Updated .env for https://dares2dream.com\n";
}

// If public_html already points to Laravel public, nothing destructive is needed.
if (is_link($publicHtml)) {
    $current = realpath($publicHtml);
    if ($current === $public) {
        echo "public_html already points to Laravel public.\n";
        file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        exit(0);
    }
    fwrite(STDERR, "ERROR: public_html is already a symlink to another location. Not changing it automatically.\n");
    exit(1);
}

if (file_exists($publicHtml)) {
    $backup = $home.'/wordpress-old-'.$stamp;
    if (!rename($publicHtml, $backup)) {
        fwrite(STDERR, "ERROR: Could not rename current public_html.\n");
        exit(1);
    }
    $state['wordpress_backup'] = $backup;
    echo "Moved old public_html to: {$backup}\n";
}

if (!symlink($public, $publicHtml)) {
    fwrite(STDERR, "ERROR: Server refused the public_html symlink. Restoring old site if possible.\n");
    if (!empty($state['wordpress_backup']) && is_dir($state['wordpress_backup']) && !file_exists($publicHtml)) {
        rename($state['wordpress_backup'], $publicHtml);
    }
    if (!empty($state['env_backup']) && is_file($state['env_backup'])) {
        copy($state['env_backup'], $env);
    }
    exit(1);
}

file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

echo "\nLIVE SWITCH COMPLETE\n";
echo "public_html -> {$public}\n";
echo "The main domain now serves the SAME Laravel public directory as preview.\n";
echo "No Laravel page files were copied or replaced. Future changes remain shared.\n\n";
echo "Next run:\n";
echo "php artisan optimize:clear\n";
echo "Then visit https://dares2dream.com and https://dares2dream.com/ads.txt\n";
echo "Do NOT delete the WordPress backup until the live site is verified.\n";
