<?php
/* Auto-detect bootstrap (local + host) */
$__bp = [
    __DIR__ . '/../../sock-b2b/config/bootstrap.php',
    __DIR__ . '/../../config/bootstrap.php',
];
foreach ($__bp as $__b) {
    if (is_file($__b)) {
        require_once $__b;
        break;
    }
}
unset($__bp, $__b);
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

header('Content-Type: application/json; charset=utf-8');

/* سبد فقط برای کاربر وارد‌شده (ادمین یا مشتری) */
if (!is_customer_logged_in()) {
    http_response_code(is_admin_logged_in() ? 403 : 401);
    $msg = is_admin_logged_in() ? 'حساب مدیر امکان خرید ندارد؛ با حساب مشتری وارد شوید' : 'برای خرید ابتدا وارد شوید';
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)($_SESSION['customer_id'] ?? 0);
$db = db();

/* helper: سبد فعال کاربر را بگیر یا بساز */
function getOrCreateCart(PDO $db, int $userId): int
{
    $stmt = $db->prepare("SELECT id FROM carts WHERE user_id = ? AND status='active' LIMIT 1");
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();
    if ($id) return (int)$id;

    /* سبد قدیمی (converted) وجود دارد → همان را دوباره فعال کن */
    $stmt = $db->prepare("SELECT id FROM carts WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();
    if ($id) {
        $db->prepare("UPDATE carts SET status='active' WHERE id=?")->execute([$id]);
        return (int)$id;
    }

    $db->prepare("INSERT INTO carts (user_id) VALUES (?)")->execute([$userId]);
    return (int)$db->lastInsertId();
}

/* helper: تعداد اقلام سبد */
function cartCount(PDO $db, int $cartId): int
{
    $stmt = $db->prepare("SELECT COALESCE(SUM(qty),0) FROM cart_items WHERE cart_id = ?");
    $stmt->execute([$cartId]);
    return (int)$stmt->fetchColumn();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

/* ═══════════════════════════════════════════════════════════
 * POST — افزودن به سبد
 * ═══════════════════════════════════════════════════════════ */
if ($method === 'POST') {
    verify_csrf();

    $productId = (int)($_POST['product_id'] ?? 0);
    $qty       = max(1, (int)($_POST['qty'] ?? 1));

    if ($productId < 1) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'محصول نامعتبر'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* محصول فعال و موجود؟ */
    $stmt = $db->prepare("
        SELECT p.id, p.base_moq, GREATEST(0, COALESCE(inv.qty_physical, 0) - COALESCE(inv.reserved, 0) - COALESCE(inv.unsellable, 0)) AS stock
        FROM products p
        LEFT JOIN inventory inv ON inv.product_id = p.id
        WHERE p.id = ? AND p.is_active = 1 LIMIT 1
    ");
    $stmt->execute([$productId]);
    $p = $stmt->fetch();

    if (!$p) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'محصول یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $cartId = getOrCreateCart($db, $userId);

    $stmt = $db->prepare("SELECT qty FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1");
    $stmt->execute([$cartId, $productId]);
    $inCart = (int)($stmt->fetchColumn() ?: 0);

    if ((int)$p['stock'] < $inCart + $qty) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'موجودی کافی نیست — موجودی فعلی: ' . number_format($p['stock']) . ' جفت'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* Upsert: اگر قبلاً در سبد است، جمع شود */
    $db->prepare("
        INSERT INTO cart_items (cart_id, product_id, qty)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)
    ")->execute([$cartId, $productId, $qty]);

    echo json_encode([
        'ok'    => true,
        'count' => cartCount($db, $cartId),
        'msg'   => '✓ به سبد اضافه شد'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ═══════════════════════════════════════════════════════════
 * GET — گرفتن لیست اقلام سبد (برای همگام‌سازی آیکون‌ها)
 * ═══════════════════════════════════════════════════════════ */
if ($method === 'GET') {
    $cartId = 0;
    $stmt = $db->prepare("SELECT id FROM carts WHERE user_id = ? AND status='active' LIMIT 1");
    $stmt->execute([$userId]);
    $cartId = (int)($stmt->fetchColumn() ?: 0);

    // اگر سبد فعال وجود نداشت، لیست خالی برگردان
    if ($cartId === 0) {
        echo json_encode(['ok' => true, 'items' => [], 'count' => 0]);
        exit;
    }

    // گرفتن تمام اقلام سبد
    $stmt = $db->prepare("SELECT product_id, qty FROM cart_items WHERE cart_id = ?");
    $stmt->execute([$cartId]);

    $items = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $items[(string)$row['product_id']] = (int)$row['qty'];
    }

    echo json_encode([
        'ok'    => true,
        'items' => $items,
        'count' => cartCount($db, $cartId)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ═══════════════════════════════════════════════════════════
 * درخواست نامعتبر
 * ═══════════════════════════════════════════════════════════ */
http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'درخواست نامعتبر'], JSON_UNESCAPED_UNICODE);
