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

3. **`config/database.php`** در ریپو هست و اطلاعات اتصال را فقط از `.env` می‌خواند؛ نیازی به ویرایش آن نیست.

4. **بارگذاری migrations**
   ```bash
   # ابتدا پشتیبان بگیرید
   mysqldump --single-transaction DB_NAME | gzip > backup_$(date +%Y%m%d_%H%M%S).sql.gz
   # فایل‌های شماره‌دار را به ترتیب اجرا کنید (روی دیتابیس موجود فقط فایل‌های جدید را)
   for f in $(ls database/[0-9]*.sql | sort); do mysql DB_NAME < "$f"; done
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
scripts/         اسکریپت‌های CLI نگهداری (مثل optimize-images.php)
```

## پشتیبان‌گیری

```bash
mysqldump --single-transaction DB_NAME | gzip > backup_$(date +%Y%m%d_%H%M%S).sql.gz
# از پوشهٔ public/uploads/ (به‌ویژه receipts و custom-orders) هم جداگانه پشتیبان بگیرید
```

## مراحل اصلاحات پروژه

- مرحلهٔ ۰ (آماده‌سازی) ✓
- مرحلهٔ ۱ (MVP) — اصلاح باگ‌های بحرانی + تکمیل هسته
- مرحلهٔ ۲ (توسعهٔ عملیات)
- مرحلهٔ ۳ (قابلیت‌های پیشرفته)

گزارش کامل ارزیابی در `download/گزارش-ارزیابی-و-نقشه-اصلاحات.md`.
