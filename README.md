# رابطی (JoorabRabeti) — سامانه فروش عمده جوراب

پنل مدیریت + سایت مشتری، بر پایه PHP ۸.۲+ و MySQL/MariaDB، بدون فریم‌ورک.

## نصب

1. **پیش‌نیازها**
   - PHP >= ۸.۲ با extensionهای pdo_mysql, mbstring, gd/fileinfo, openssl
   - MariaDB >= ۱۰.۴ یا MySQL >= ۸
   - Composer (فقط برای TCPDF)

2. **تنظیم `.env`**
   ```bash
   cp .env.example .env
   # مقادیر DB_HOST/DB_NAME/DB_USER/DB_PASS را ویرایش کنید
   chmod 600 .env
   ```

3. **تنظیم `config/database.php`** (در `.gitignore` است؛ باید دستی ساخته شود)
   - این فایل به‌صورت یک singleton PDO است؛ نمونهٔ حداقلی در `config/database.example.php` موجود است.

4. **بارگذاری migrations**
   ```bash
   ./scripts/backup.sh                 # پشتیبان
   ./scripts/migrate.sh --dry-run      # پیش‌نمایش
   ./scripts/migrate.sh                # اجرا
   ```

5. **وابستگی‌های Composer**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

6. **کاربر ادمین اولیه**
   - از طریق SQL مستقیم یک کاربر با `role='admin'` و `password_hash` حاصل از `password_hash('xxx', PASSWORD_DEFAULT)` در جدول `users` بسازید.

## ساختار پوشه‌ها

```
config/          bootstrap، database، auth، settings، upload
app/services/    سرویس‌های Pricing/Invoice/Order/Sms/Shipping/Pdf
database/        migrations شماره‌گذاری‌شده (001 → 999)
public/admin/    صفحات پنل مدیریت
public/api/      API مشتری
public/          صفحات سایت مشتری
views/layouts/   قالب‌های مشترک (admin.php، main.php)
scripts/         اسکریپت‌های پشتیبان، migrate، نگهداری
tests/           سناریوهای آزمونی
```

## پشتیبان‌گیری

```bash
./scripts/backup.sh
# خروجی: scripts/_backups/sock_b2b_YYYYMMDD_HHMMSS.sql.gz
```

## مراحل اصلاحات پروژه

- مرحلهٔ ۰ (آماده‌سازی) ✓
- مرحلهٔ ۱ (MVP) — اصلاح باگ‌های بحرانی + تکمیل هسته
- مرحلهٔ ۲ (توسعهٔ عملیات)
- مرحلهٔ ۳ (قابلیت‌های پیشرفته)

گزارش کامل ارزیابی در `download/گزارش-ارزیابی-و-نقشه-اصلاحات.md`.
