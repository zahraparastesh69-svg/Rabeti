<?php
/**
 * آپلود امن تصویر — قواعد قفل‌شده پروژه:
 * whitelist پسوند + بررسی MIME واقعی فایل + اسم تصادفی + سقف حجم
 */
function upload_image(array $file, string $subdir, int $maxKB = 2048): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxKB * 1024) return null;

    $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png', 'webp' => 'image/webp'];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) return null;

    // MIME واقعی محتوا، نه اسم فایل
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if ($mime !== $allowed[$ext]) return null;

    $dir = __DIR__ . '/../public/uploads/' . trim($subdir, '/');
    if (!is_dir($dir)) mkdir($dir, 0775, true);

    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) return null;

    return '/uploads/' . trim($subdir, '/') . '/' . $name;
}