<?php
require_once __DIR__ . '/bootstrap.php';
// ⚙️ تنظیمات اتصال — روی هاست اشتراکی فقط این مقادیر عوض می‌شود
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'sock_b2b');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP پیش‌فرض رمز ندارد
<?php
/* مسیر مطلق ریشه پروژه — یک‌بار، برای همیشه، مستقل از محل فایل صداکننده */
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

/* مسیر مطلق پوشه عمومی (public) */
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', ROOT_PATH . '/public');
}

// ... بقیه database.php بدون تغییر
// آدرس مطلق پوشه sock-b2b

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    return $pdo;
}
