<?php

/**
 * آپلود امن تصویر — با گزارش خطا
 * خروجی موفق: ['ok'=>true, 'path'=>'/uploads/...']
 * خروجی شکست: ['ok'=>false, 'error'=>'دلیل']
 */
function upload_image(array $file, string $subdir, int $maxKB = 2048): ?string
{
    $err = upload_image_ex($file, $subdir, $maxKB);
    return $err['ok'] ? $err['path'] : null;
}

function upload_image_ex(array $file, string $subdir, int $maxKB = 2048): array
{
    $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($code === UPLOAD_ERR_NO_FILE) return ['ok' => false, 'error' => 'فایلی انتخاب نشده'];
    if ($code === UPLOAD_ERR_INI_SIZE) return ['ok' => false, 'error' => 'حجم فایل از محدودیت PHP بیشتر است'];
    if ($code !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'خطای آپلود (کد ' . $code . ')'];
    if (($file['size'] ?? 0) > $maxKB * 1024) return ['ok' => false, 'error' => 'حجم فایل بیشتر از ' . $maxKB . ' کیلوبایت است'];

    $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) return ['ok' => false, 'error' => 'فرمت مجاز نیست — فقط JPG/PNG/WebP'];

    if (!function_exists('finfo_open')) return ['ok' => false, 'error' => 'افزونه fileinfo در PHP فعال نیست'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if ($mime !== $allowed[$ext]) return ['ok' => false, 'error' => 'محتوای فایل با پسوندش نمی‌خواند'];

    $dir = PUBLIC_PATH . '/uploads/' . trim($subdir, '/');
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) return ['ok' => false, 'error' => 'ساخت پوشه uploads ناموفق بود'];
    if (!is_writable($dir)) return ['ok' => false, 'error' => 'پوشه uploads قابل نوشتن نیست'];

    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) return ['ok' => false, 'error' => 'انتقال فایل ناموفق بود'];

    return ['ok' => true, 'path' => '/uploads/' . trim($subdir, '/') . '/' . $name];
}
