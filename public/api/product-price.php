<?php
/* ============================================================
 * API: محاسبه قیمت داینامیک یک محصول بر اساس تعداد
 * Method: GET
 * Params: id (int), qty (int)
 * Response: JSON
 * ============================================================ */

/* Auto-detect bootstrap (local + host) */
$__bp = [
    __DIR__ . '/../../sock-b2b/config/bootstrap.php',
    __DIR__ . '/../../config/bootstrap.php',
    __DIR__ . '/../sock-b2b/config/bootstrap.php',
    __DIR__ . '/../config/bootstrap.php',
    __DIR__ . '/config/bootstrap.php',
];
foreach ($__bp as $__b) {
    if (is_file($__b)) {
        require_once $__b;
        break;
    }
}
unset($__bp, $__b);

/* اگر bootstrap پیدا نشد، خطای واضح بده */
if (!defined('ROOT_PATH')) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'bootstrap پیدا نشد. مسیر ROOT_PATH را بررسی کنید.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';
require_once ROOT_PATH . '/app/services/PricingService.php';

/* ============================================================
 * هدرها
 * ============================================================ */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

/* فقط GET مجاز است */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'متد مجاز نیست'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ============================================================
 * اعتبارسنجی ورودی
 * ============================================================ */
$id  = (int)($_GET['id'] ?? 0);
$qty = (int)($_GET['qty'] ?? 1);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'شناسه محصول نامعتبر است'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($qty <= 0) {
    $qty = 1;
}

/* ============================================================
 * بررسی وجود محصول و فعال بودن آن
 * ============================================================ */
try {
    $stmt = db()->prepare("
        SELECT id, base_moq, base_price
        FROM products
        WHERE id = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if (!$product) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'محصول یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* کلمپ تعداد به حداقل سفارش (MOQ) */
    $moq = max(1, (int)($product['base_moq'] ?? 1));
    if ($qty < $moq) {
        $qty = $moq;
    }

    /* سقف منطقی برای جلوگیری از سوءاستفاده */
    if ($qty > 100000) {
        $qty = 100000;
    }

    /* ============================================================
     * محاسبه قیمت
     * ============================================================ */
    $userGroup = current_user_group();
    $pricing   = PricingService::calculate(db(), $id, $qty, $userGroup);
    $groupData = PricingService::getGroup(db(), $userGroup);

    if (!is_array($pricing)) {
        throw new RuntimeException('پاسخ PricingService نامعتبر است');
    }

    $unitPrice  = (float)($pricing['unit_price']       ?? 0);
    $basePrice  = (float)($pricing['base_price']       ?? 0);
    $discountPc = (float)($pricing['discount_percent'] ?? 0);
    $source     = (string)($pricing['discount_source'] ?? '');

    /* اگر unit_price صفر بود ولی base_price موجود بود، از base استفاده کن */
    if ($unitPrice <= 0 && $basePrice > 0) {
        $unitPrice = $basePrice;
    }

    /* ============================================================
     * خروجی JSON
     * ============================================================ */
    echo json_encode([
        'ok'                => true,
        'product_id'        => $id,
        'qty'               => $qty,
        'moq'               => $moq,
        'unit_price'        => $unitPrice,
        'base_price'        => $basePrice,
        'discount_percent'  => $discountPc,
        'discount_source'   => $source,
        'group_name'        => (string)($groupData['name'] ?? ''),
        'total_price'       => $unitPrice * $qty,
        'price_available'   => ($unitPrice > 0),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    /* لاگ خطا برای دیباگ (اختیاری) */
    error_log('[product-price] ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'خطا در محاسبه قیمت. لطفاً بعداً تلاش کنید.',
    ], JSON_UNESCAPED_UNICODE);
}
