<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

if (!is_logged_in()) {
    header('Location: /');
    exit;
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$userId = (int)($_SESSION['admin_id'] ?? $_SESSION['customer_id'] ?? 0);
$db = db();

/* ─── تغییر تعداد / حذف (POST) ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do     = $_POST['do'] ?? '';
    $itemId = (int)($_POST['item_id'] ?? 0);

    /* Object-Level Auth: آیتم متعلق به سبد همین کاربر */
    $stmt = $db->prepare("
        SELECT ci.id FROM cart_items ci
        JOIN carts c ON c.id = ci.cart_id
        WHERE ci.id = ? AND c.user_id = ? AND c.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$itemId, $userId]);
    if (!$stmt->fetch()) {
        header('Location: /cart');
        exit;
    }

    if ($do === 'update') {
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        $db->prepare("UPDATE cart_items SET qty = ? WHERE id = ?")->execute([$qty, $itemId]);
    }
    if ($do === 'remove') {
        $db->prepare("DELETE FROM cart_items WHERE id = ?")->execute([$itemId]);
    }
    header('Location: /cart');
    exit;
}

/* ─── اقلام سبد ─── */
$stmt = $db->prepare("
    SELECT ci.id, ci.qty,
           p.id AS product_id, p.code, p.name, p.slug, p.base_moq,
           pr.price,
           COALESCE(inv.qty_available, 0) AS stock,
           (SELECT image_path FROM product_images pi
            WHERE pi.product_id = p.id
            ORDER BY pi.is_main DESC, pi.sort_order ASC LIMIT 1) AS img
    FROM cart_items ci
    JOIN carts c ON c.id = ci.cart_id
    JOIN products p ON p.id = ci.product_id
    LEFT JOIN product_prices pr ON pr.product_id = p.id AND pr.customer_group='wholesale' AND pr.min_qty=1
    LEFT JOIN inventory inv ON inv.product_id = p.id
    WHERE c.user_id = ? AND c.status = 'active'
    ORDER BY ci.added_at DESC
");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

$total = 0;
foreach ($items as $it) $total += ($it['price'] ?? 0) * (int)$it['qty'];

/* بررسی MOQ/موجودی برای غیرفعال‌سازی Checkout */
$hasIssue = false;
foreach ($items as $it) {
    if ((int)$it['qty'] < (int)$it['base_moq'] || (int)$it['stock'] < (int)$it['qty']) {
        $hasIssue = true;
        break;
    }
}

$pageTitle = 'سبد سفارش | رابطی';
ob_start();
?>

<nav class="breadcrumb"><a href="/">خانه</a> › <span>سبد سفارش</span></nav>

<section class="catalog-head">
    <h1 class="catalog-title">سبد سفارش شما</h1>
    <p class="catalog-sub"><?= count($items) ?> قلم کالا — قیمت‌ها همکاری است</p>
</section>

<?php if ($items): ?>

    <div class="cart-list">

        <?php foreach ($items as $it): ?>
            <?php $lineTotal = ($it['price'] ?? 0) * (int)$it['qty']; ?>
            <article class="cart-item">

                <a href="/product/<?= $h($it['slug']) ?>" class="cart-item-img">
                    <?php if (!empty($it['img'])): ?>
                        <img src="<?= $h($it['img']) ?>" alt="<?= $h($it['name']) ?>">
                    <?php else: ?>
                        <span>🧦</span>
                    <?php endif; ?>
                </a>

                <div class="cart-item-info">
                    <h3><a href="/product/<?= $h($it['slug']) ?>"><?= $h($it['name']) ?></a></h3>
                    <span class="card-attr">کد <?= $h($it['code']) ?> · حداقل <?= (int)$it['base_moq'] ?> جفت</span>

                    <div class="cart-item-price">
                        <?php if ($it['price'] !== null): ?>
                            <b><?= number_format((float)$it['price']) ?> تومان</b>
                            <small>قیمت همکاری</small>
                        <?php else: ?>
                            <b class="cart-no-price">قیمت نیاز به استعلام دارد</b>
                        <?php endif; ?>
                    </div>

                    <span class="stock <?= $it['stock'] >= $it['qty'] ? 'in' : 'out' ?>">
                        <?= $it['stock'] >= $it['qty']
                            ? '● موجود در انبار'
                            : '⚠ موجودی انبار فقط ' . number_format((int)$it['stock']) . ' جفت' ?>
                    </span>

                    <?php if ((int)$it['qty'] < (int)$it['base_moq']): ?>
                        <div class="cart-item-warn">⚠️ حداقل سفارش این محصول <?= (int)$it['base_moq'] ?> جفت است</div>
                    <?php endif; ?>
                </div>

                <div class="cart-item-side">

                    <div class="stepper" data-item="<?= (int)$it['id'] ?>">
                        <button type="button" class="step plus">+</button>
                        <input type="text" inputmode="numeric" class="step-num step-input" value="<?= (int)$it['qty'] ?>">
                        <button type="button" class="step minus">−</button>
                    </div>

                    <div class="cart-line-total">
                        <small>جمع</small>
                        <b><?= number_format((float)$lineTotal) ?> تومان</b>
                    </div>

                    <form method="post" onsubmit="return confirm('این قلم حذف شود؟');">
                        <input type="hidden" name="do" value="remove">
                        <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                        <button type="submit" class="cart-remove">🗑 حذف</button>
                    </form>

                </div>

            </article>
        <?php endforeach; ?>

        <div class="cart-summary">
            <div class="cart-summary-row">
                <span>تعداد کل</span>
                <b><?= number_format(array_sum(array_column($items, 'qty'))) ?> جفت</b>
            </div>
            <div class="cart-summary-row total">
                <span>جمع کل سفارش</span>
                <b><?= number_format((float)$total) ?> تومان</b>
            </div>

            <?php if ($hasIssue): ?>
                <div class="form-alert error" style="display:block;">
                    ⚠️ برخی اقلام مشکل تعداد/موجودی دارند — با فلش‌های روی هر قلم اصلاح کنید
                </div>
            <?php else: ?>
                <a href="/checkout-address" class="btn btn-primary cart-checkout-btn">ادامه و ثبت سفارش ←</a>
            <?php endif; ?>

            <a href="/products" class="cart-continue">ادامه خرید</a>
        </div>

    </div>

<?php else: ?>

    <div class="empty-state">
        <div class="empty-icon">🛒</div>
        <h2>سبد سفارش شما خالی است</h2>
        <a href="/products" class="btn btn-primary">مشاهده محصولات</a>
    </div>

<?php endif; ?>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/main.php';
