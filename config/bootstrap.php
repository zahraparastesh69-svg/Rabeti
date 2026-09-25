<?php
/* نقطه شروع همه فایل‌ها — مسیرها + زمان */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', ROOT_PATH . '/public');
}

/* منبع واحد زمان — ۱۶:۰۰ باید همیشه یک معنا داشته باشد */
if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Asia/Tehran');
}
date_default_timezone_set(APP_TIMEZONE);
