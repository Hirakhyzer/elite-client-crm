<?php
declare(strict_types=1);

$base = __DIR__;
$files = [
    'video' => $base.'/public/v11/hero-background.mp4',
    'hero_jpg' => $base.'/public/v11/hero-video-base.jpg',
    'hero_webp' => $base.'/public/v11/hero-video-base.webp',
];

echo "=== D2D V11 HERO MEDIA ===\n";
foreach ($files as $label => $path) {
    if (is_file($path)) {
        $size = filesize($path);
        echo str_pad($label, 12)."FOUND  {$path}  (".number_format((int)$size)." bytes)\n";
    } else {
        echo str_pad($label, 12)."MISSING {$path}\n";
    }
}

echo "\nPublic URLs:\n";
echo "https://dares2dream.com/v11/hero-background.mp4\n";
echo "https://dares2dream.com/v11/hero-video-base.jpg\n";
echo "https://dares2dream.com/v11/hero-video-base.webp\n";
