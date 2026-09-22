<?php
/* ═══════════════════════════════════════════════════════
   🔧 ابزار یک‌بارمصرف ساخت ادمین
   ۱. رمز دلخواه را در خط پایین عوض کنید
   ۲. در مرورگر باز کنید: /create-admin.php
   ۳. پیام موفقیت دیدید → فایل را از پروژه حذف کنید!
   ═══════════════════════════════════════════════════════ */

$ADMIN_MOBILE   = '09127264560';   // ← نام کاربری شما (همین را در صفحه ورود می‌زنید)
$ADMIN_PASSWORD = 'Admin@1234';    // ← رمز دلخواه خودتان را اینجا بنویسید
$ADMIN_NAME     = 'کاظم قطبی';    // ← اسم شما

require __DIR__ . '/../config/database.php';

$hash = password_hash($ADMIN_PASSWORD, PASSWORD_DEFAULT);

// اگر ادمین با این موبایل هست → رمز را به‌روز کن، وگرنه بساز
$stmt = db()->prepare("SELECT id FROM users WHERE mobile = ? AND role = 'admin' LIMIT 1");
$stmt->execute([$ADMIN_MOBILE]);

if ($stmt->fetch()) {
    db()->prepare("UPDATE users SET password_hash = ?, full_name = ?, is_active = 1 WHERE mobile = ? AND role = 'admin'")
        ->execute([$hash, $ADMIN_NAME, $ADMIN_MOBILE]);
    echo "<p dir='rtl' style='font-family:Tahoma'>✅ رمز ادمین به‌روز شد. حالا این فایل را حذف کنید!</p>";
} else {
    db()->prepare("INSERT INTO users (full_name, mobile, password_hash, role) VALUES (?,?,?,'admin')")
        ->execute([$ADMIN_NAME, $ADMIN_MOBILE, $hash]);
    echo "<p dir='rtl' style='font-family:Tahoma'>✅ ادمین ساخته شد:<br>موبایل: <b dir='ltr'>$ADMIN_MOBILE</b><br>رمز: همان که در فایل نوشتید<br><br>⚠️ حالا این فایل را حذف کنید!</p>";
}
