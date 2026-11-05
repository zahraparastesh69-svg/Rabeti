<?php
// فایل موقت — بعد از استفاده حذف شود
header('Content-Type: text/plain; charset=utf-8');

if (!function_exists('imagewebp')) {
    exit("GD با WebP پشتیبانی نمی‌کند. PHP version: " . PHP_VERSION);
}

$dir = __DIR__ . '/uploads/products/';

$files = [
    'ff1f8dc7416fd496db83ca8dbbb1f207.png',
    '778c98c48a676a32ab7ec7970017b46a.png',
];

foreach ($files as $file) {
    $src = $dir . $file;
    $dst = $dir . pathinfo($file, PATHINFO_FILENAME) . '.webp';

    if (!is_file($src)) {
        echo "SKIP (not found): $file\n";
        continue;
    }

    $img = @imagecreatefrompng($src);
    if (!$img) {
        echo "FAIL (cannot open): $file\n";
        continue;
    }

    imagepalettetotruecolor($img);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    $ok = imagewebp($img, $dst, 80);
    imagedestroy($img);

    if ($ok) {
        $oldSize = filesize($src);
        $newSize = filesize($dst);
        echo "OK: {$file}\n";
        echo "  old: " . number_format($oldSize) . " bytes\n";
        echo "  new: " . number_format($newSize) . " bytes\n";
        echo "  saved: " . round((1 - $newSize / $oldSize) * 100, 1) . "%\n\n";
    } else {
        echo "FAIL: $file\n";
    }
}

echo "DONE.\n";
