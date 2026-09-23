<?php

/**
 * آپلود امن تصویر — دو لایه:
 *  upload_image()      → سازگار با کدهای قبلی (مسیر یا null)
 *  upload_image_ex()   → با پیام خطای دقیق (برای فرم‌های ادمین)
 */

function upload_image(array $file, string $subdir, int $maxKB = 2048): ?string
{
    $r = upload_image_ex($file, $subdir, $maxKB);
    return $r['ok'] ? $r['path'] : null;
}

function upload_image_ex(array $file, string $subdir, int $maxKB = 2048): array
{
    $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($code === UPLOAD_ERR_NO_FILE)
        return ['ok' => false, 'error' => 'فایلی انتخاب نشده است'];

    if ($code === UPLOAD_ERR_INI_SIZE)
        return ['ok' => false, 'error' => 'حجم فایل از محدودیت سرور بیشتر است'];

    if ($code !== UPLOAD_ERR_OK)
        return ['ok' => false, 'error' => 'خطای آپلود (کد ' . $code . ')'];

    if (($file['size'] ?? 0) <= 0)
        return ['ok' => false, 'error' => 'فایل خالی است'];

    if ($file['size'] > $maxKB * 1024)
        return ['ok' => false, 'error' => 'حجم فایل بیش از ' . $maxKB . ' کیلوبایت است'];

    $allowed = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
    ];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext]))
        return ['ok' => false, 'error' => 'فرمت مجاز نیست — فقط JPG/PNG/WebP'];

    if (!function_exists('finfo_open'))
        return ['ok' => false, 'error' => 'افزونه fileinfo در PHP فعال نیست (php.ini → extension=fileinfo)'];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($mime !== $allowed[$ext])
        return ['ok' => false, 'error' => 'محتوای فایل با پسوندش نمی‌خواند'];

    $dir = PUBLIC_PATH . '/uploads/' . trim($subdir, '/');
    if (!is_dir($dir) && !mkdir($dir, 0775, true))
        return ['ok' => false, 'error' => 'ساخت پوشه uploads ناموفق بود'];

    if (!is_writable($dir))
        return ['ok' => false, 'error' => 'پوشه uploads قابل نوشتن نیست'];

    $name = bin2hex(random_bytes(16)) . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name))
        return ['ok' => false, 'error' => 'انتقال فایل به مقصد ناموفق بود'];

    return ['ok' => true, 'path' => '/uploads/' . trim($subdir, '/') . '/' . $name];
}
