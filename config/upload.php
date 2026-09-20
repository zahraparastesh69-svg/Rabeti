

</upload>
<?php

/**
 * آپلود امن تصویر — قواعد قفل‌شده پروژه:
 * whitelist پسوند + بررسی MIME واقعی + اسم تصادفی + سقف حجم
 * خروجی: مسیر نسبی (مثل /uploads/products/abc.png) یا null
 */
function upload_image(array $file, string $subdir, int $maxKB = 2048): ?string
{

    // فایلی انتخاب نشده یا خطای PHP
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxKB * 1024) return null;

    // فقط این چهار پسوند
    $allowed = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
    ];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) return null;

    // MIME واقعی محتوا — نه اسم فایل (جلوی فایل جعلی)
    if (!function_exists('finfo_open')) return null;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if ($mime !== $allowed[$ext]) return null;

    // ساخت پوشه اگر نیست
    $dir = __DIR__ . '/../public/uploads/' . trim($subdir, '/');
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) return null;

    // اسم تصادفی — جلوی تداخل و دسترسی مستقیم
    $name = bin2hex(random_bytes(16)) . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) return null;

    return '/uploads/' . trim($subdir, '/') . '/' . $name;
}
