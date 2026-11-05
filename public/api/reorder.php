<?php
/* [FILE] public/api/reorder.php
 * Reorder: copy order items → cart, with availability guard.
 */

/* Auto-detect bootstrap (local + host) */
$__bp = [
    __DIR__ . '/../../sock-b2b/config/bootstrap.php',
    __DIR__ . '/../../config/bootstrap.php',
];
foreach ($__bp as $__b) { if (is_file($__b)) { require_once $__b; break; } }
unset($__bp, $__b);

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

/* ═══ Auth ═══ */
if (!is_customer_logged_in()) {
    header('Location: /login');
    exit;
}

verify_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /account-orders');
    exit;
}

$userId  = (int)$_SESSION['customer_id'];
$orderId = (int)($_POST['order_id'] ?? 0);

if ($orderId <= 0) {
    header('Location: /account-orders');
    exit;
}

$db = db();

/* ═══ Ownership ═══ */
$stmt = $db->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $userId]);
if (!$stmt->fetchColumn()) {
    header('Location: /account-orders');
    exit;
}

/* ═══ Report accumulator ═══ */
$report = [
    'added'          => 0,
    'skipped_oos'    => 0,     // ناموجود
    'skipped_off'    => 0,     // غیرفعال
    'adjusted'       => 0,     // تعداد کم شد
];

try {
    $db->beginTransaction();

    /* ═══ Cart فعال: پیدا یا بساز ═══ */
    /* پیدا کردن یا بازیافت cart */
    $stmt = $db->prepare("SELECT id FROM carts WHERE user_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$userId]);
    $cartId = $stmt->fetchColumn();

    if (!$cartId) {
        /* چک کن cart قدیمی هست */
        $stmt = $db->prepare("SELECT id FROM carts WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $existingCartId = $stmt->fetchColumn();

        if ($existingCartId) {
            /* فعالش کن */
            $db->prepare("UPDATE carts SET status = 'active' WHERE id = ?")
                ->execute([$existingCartId]);
            $cartId = (int)$existingCartId;
        } else {
            /* بساز */
            $db->prepare("INSERT INTO carts (user_id, status, created_at) VALUES (?, 'active', NOW())")
                ->execute([$userId]);
            $cartId = (int)$db->lastInsertId();
        }
    }

    /* ═══ آیتم‌های سفارش + وضعیت فعلی محصول ═══ */
    $stmt = $db->prepare("
        SELECT
            oi.product_id,
            oi.qty            AS old_qty,
            p.is_active,
            p.base_moq,
            GREATEST(0, COALESCE(inv.qty_physical, 0) - COALESCE(inv.reserved, 0) - COALESCE(inv.unsellable, 0)) AS available
        FROM order_items oi
        INNER JOIN products p ON p.id = oi.product_id
        LEFT JOIN inventory inv ON inv.product_id = oi.product_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ═══ آماده‌سازی upsert ═══ */
    $upsert = $db->prepare("
        INSERT INTO cart_items (cart_id, product_id, qty)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)
    ");

    foreach ($items as $item) {
        $pid     = (int)$item['product_id'];
        $oldQty  = (int)$item['old_qty'];
        $avail   = (int)$item['available'];
        $moq     = max(1, (int)$item['base_moq']);
        $isActive = (int)$item['is_active'] === 1;

        /* ۱) غیرفعال */
        if (!$isActive) {
            $report['skipped_off']++;
            continue;
        }

        /* ۲) ناموجود */
        if ($avail <= 0) {
            $report['skipped_oos']++;
            continue;
        }

        /* ۳) qty نهایی: ≤ موجودی، ≥ MOQ */
        $qty = $oldQty;
        if ($qty > $avail) {
            $qty = $avail;
            $report['adjusted']++;
        }
        if ($qty < $moq) {
            $qty = $moq;
            $report['adjusted']++;
        }

        /* ۴) upsert */
        $upsert->execute([$cartId, $pid, $qty]);
        $report['added']++;
    }

    /* ═══ ذخیره گزارش برای نمایش در cart.php ═══ */
    if ($report['added'] === 0 && ($report['skipped_oos'] + $report['skipped_off']) > 0) {
        $_SESSION['reorder_report'] = $report;
        $db->rollBack();
        header('Location: /account-orders?reorder=empty');
        exit;
    }

    $_SESSION['reorder_report'] = $report;

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('REORDER FAILED: ' . $e->getMessage());
    $_SESSION['reorder_report'] = ['error' => true];
    header('Location: /account-orders?reorder=error');
    exit;
}

header('Location: /cart');
exit;
