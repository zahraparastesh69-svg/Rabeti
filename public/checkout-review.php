<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';
require_once ROOT_PATH . '/app/services/AddressService.php';
require_once ROOT_PATH . '/app/services/ShippingService.php';

if (!is_logged_in()) {
    header('Location: /');
    exit;
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$userId = (int)($_SESSION['admin_id'] ?? $_SESSION['customer_id'] ?? 0);
$db = db();

$addressId = (int)($_SESSION['checkout_address_id'] ?? 0);
$shipping  = $_SESSION['checkout_shipping'] ?? null;
$address   = $addressId ? AddressService::get($userId, $addressId) : null;

if (!$address || !$shipping) {
    header('Location: /checkout-address');
    exit;
}

$error = '';

/* ═══ COMMIT نهایی — تمام اعتبارسنجی‌های امنیتی §۱۲ ═══ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $receiverName   = trim($_POST['receiver_name'] ?? '');
    $receiverMobile = strtr(
        trim($_POST['receiver_mobile'] ?? ''),
        ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']
    );

    if ($receiverName !== '' && mb_strlen($receiverName) < 3) {
        $error = 'نام گیرنده را کامل وارد کنید';
    }
    if ($receiverMobile !== '' && !preg_match('/^09\d{9}$/', $receiverMobile)) {
        $error = 'موبایل گیرنده معتبر نیست';
    }

    if (!$error) {
        try {
            $db->beginTransaction();

            /* ۱+۲. قفل سبد کاربر */
            $stmt = $db->prepare("
                SELECT ci.qty, p.id AS product_id, p.code, p.name, p.base_moq, p.is_active,
                       pr.price, COALESCE(inv.qty_available,0) AS stock
                FROM cart_items ci
                JOIN carts c ON c.id = ci.cart_id
                JOIN products p ON p.id = ci.product_id
                LEFT JOIN product_prices pr ON pr.product_id = p.id AND pr.customer_group='wholesale' AND pr.min_qty=1
                LEFT JOIN inventory inv ON inv.product_id = p.id
                WHERE c.user_id = ? AND c.status = 'active'
                FOR UPDATE
            ");
            $stmt->execute([$userId]);
            $items = $stmt->fetchAll();
            if (!$items) throw new RuntimeException('سبد شما خالی است');

            /* ۳+۴. اعتبارسنجی اقلام */
            $subtotal = 0;
            foreach ($items as $it) {
                if (!$it['is_active'])      throw new RuntimeException('محصول «' . $it['name'] . '» غیرفعال شده است');
                if ($it['price'] === null)  throw new RuntimeException('قیمت «' . $it['name'] . '» نیاز به استعلام دارد');
                if ((int)$it['qty'] < (int)$it['base_moq'])
                    throw new RuntimeException('تعداد «' . $it['name'] . '» کمتر از حداقل سفارش (' . (int)$it['base_moq'] . ' جفت) است');
                if ((int)$it['stock'] < (int)$it['qty'])
                    throw new RuntimeException('موجودی «' . $it['name'] . '» کافی نیست — فقط ' . number_format((int)$it['stock']) . ' جفت');
                $subtotal += (int)$it['price'] * (int)$it['qty'];
            }

            /* ۵+۶. آدرس — مالکیت و فعال بودن (سرور، مجدد) */
            $addr = AddressService::get($userId, $addressId);
            if (!$addr) throw new RuntimeException('آدرس ارسال معتبر نیست');

            /* ۷+۸+۹. روش و بازه — اعتبارسنجی قطعی سرویس */
            $ship = ShippingService::validateChoice(
                $addr['province'],
                $shipping['method'],
                $shipping['slot'],
                $addressId,
                $userId
            );

            /* ۱۰. رزرو ظرفیت */
            ShippingService::reserveSlot($shipping['slot']);

            /* ۱۱. snapshot سفارش + ارسال */
            $companyId = null;
            $stmt = $db->prepare("SELECT company_id FROM company_users WHERE user_id=? LIMIT 1");
            $stmt->execute([$userId]);
            $companyId = $stmt->fetchColumn() ?: null;

            $orderNumber = 'ORD-' . date('ymd') . '-' . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $total = $subtotal + (int)$ship['fee'];

            $db->prepare("
                INSERT INTO orders
                    (order_number, user_id, company_id, subtotal, shipping_fee, total,
                     shipping_name, shipping_mobile, shipping_province, shipping_city,
                     shipping_address, shipping_postal)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
            ")->execute([
                $orderNumber,
                $userId,
                $companyId,
                $subtotal,
                (int)$ship['fee'],
                $total,
                $receiverName !== '' ? $receiverName : $addr['receiver_name'] ?: $addr['label'],
                $receiverMobile !== '' ? $receiverMobile : $addr['receiver_mobile'] ?: ($_SESSION['customer_mobile'] ?? ''),
                $addr['province'],
                $addr['city'],
                $addr['address'],
                $addr['postal_code']
            ]);
            $orderId = (int)$db->lastInsertId();

            $insItem = $db->prepare("INSERT INTO order_items (order_id, product_id, product_code, product_name, qty, unit_price, line_total) VALUES (?,?,?,?,?,?,?)");
            $cutStock = $db->prepare("UPDATE inventory SET qty_available = qty_available - ? WHERE product_id = ? AND qty_available >= ?");

            foreach ($items as $it) {
                $insItem->execute([
                    $orderId,
                    $it['product_id'],
                    $it['code'],
                    $it['name'],
                    (int)$it['qty'],
                    (int)$it['price'],
                    (int)$it['price'] * (int)$it['qty']
                ]);
                $cutStock->execute([(int)$it['qty'], $it['product_id'], (int)$it['qty']]);
            }

            /* snapshot ارسال + آدرس */
            $db->prepare("
                INSERT INTO order_shipments
                    (order_id, method, fee, delivery_date, slot_label,
                     ship_name, ship_mobile, ship_province, ship_city, ship_address, ship_postal)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)
            ")->execute([
                $orderId,
                $ship['method'],
                (int)$ship['fee'],
                $ship['date'],
                $ship['label'],
                $receiverName !== '' ? $receiverName : $addr['receiver_name'] ?: $addr['label'],
                $receiverMobile !== '' ? $receiverMobile : $addr['receiver_mobile'] ?: '',
                $addr['province'],
                $addr['city'],
                $addr['address'],
                $addr['postal_code']
            ]);

            /* ۱۳. سبد → converted */
            $db->prepare("UPDATE carts SET status='converted' WHERE user_id=? AND status='active'")->execute([$userId]);
            $db->prepare("DELETE ci FROM cart_items ci JOIN carts c ON c.id = ci.cart_id WHERE c.user_id=?")->execute([$userId]);

            /* فاکتور */
            $invNum = 'INV-' . date('ymd') . '-' . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $db->prepare("INSERT INTO invoices (invoice_number, order_id, user_id, total) VALUES (?,?,?,?)")
                ->execute([$invNum, $orderId, $userId, $total]);

            $db->prepare("INSERT INTO audit_logs (user_id, action, entity, entity_id, ip)
                          VALUES (?, 'order.create', 'order', ?, ?)")
                ->execute([$userId, $orderId, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);

            $db->commit();

            /* پاکسازی session checkout */
            unset($_SESSION['checkout_address_id'], $_SESSION['checkout_shipping']);

            header('Location: /payment?order=' . urlencode($orderNumber));
            exit;
        } catch (RuntimeException $e) {
            $db->rollBack();
            $error = $e->getMessage();
            /* اگر خطا از slot بود → برگشت به صفحه ارسال */
            if (mb_strpos($error, 'بازه') !== false) {
                header('Location: /checkout-shipping?error=' . urlencode($error));
                exit;
            }
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('CHECKOUT COMMIT ERROR: ' . $e->getMessage());
            $error = 'خطای سرور در ثبت سفارش — دوباره تلاش کنید';
        }
    }
}

/* ─── نمایش خلاصه ─── */
$stmt = $db->prepare("
    SELECT ci.qty, p.code, p.name, pr.price
    FROM cart_items ci
    JOIN carts c ON c.id = ci.cart_id
    JOIN products p ON p.id = ci.product_id
    LEFT JOIN product_prices pr ON pr.product_id = p.id AND pr.customer_group='wholesale' AND pr.min_qty=1
    WHERE c.user_id = ? AND c.status='active'
");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

if (!$items && !$error) {
    header('Location: /cart');
    exit;
}

$subtotal = 0;
foreach ($items as $it) $subtotal += ($it['price'] ?? 0) * (int)$it['qty'];
$total = $subtotal + (int)($shipping['fee'] ?? 0);

[$shipDate, $shipRange] = explode('|', $shipping['slot'] ?? '||');

$pageTitle = 'خلاصه سفارش | رابطی';
ob_start();
?>

<nav class="breadcrumb">
    <a href="/">خانه</a> › <a href="/cart">سبد</a> ›
    <a href="/checkout-address">آدرس</a> ›
    <a href="/checkout-shipping">ارسال</a> › <span>خلاصه</span>
</nav>

<section class="catalog-head">
    <h1 class="catalog-title">خلاصه سفارش</h1>
    <p class="catalog-sub">یک بار دیگر مرور کنید و تأیید نهایی</p>
</section>

<?php if ($error): ?>
    <div class="form-alert error" style="display:block;">⚠️ <?= $h($error) ?></div>
<?php endif; ?>

<div class="review-grid">

    <div class="review-col">

        <div class="pd-specs-box">
            <div class="pd-price-title">اقلام سفارش</div>
            <?php foreach ($items as $it): ?>
                <div class="cs-row">
                    <span><?= $h($it['name']) ?> <small>× <?= (int)$it['qty'] ?></small></span>
                    <b><?= $it['price'] !== null ? number_format((int)$it['price'] * (int)$it['qty']) : '—' ?></b>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="pd-specs-box">
            <div class="pd-price-title">آدرس ارسال</div>
            <p class="review-text">
                🏪 <?= $h($address['label']) ?><br>
                <?= $h($address['province']) ?>، <?= $h($address['city']) ?><br>
                <?= $h($address['address']) ?>
                <?= $address['plate_no'] ? '، پلاک ' . $h($address['plate_no']) : '' ?>
            </p>
            <a href="/checkout-address" class="cs-edit">تغییر آدرس ←</a>
        </div>

        <div class="pd-specs-box">
            <div class="pd-price-title">ارسال</div>
            <p class="review-text">
                🚚 <?= $h($options['methods'][$shipping['method']]['label'] ?? '') ?><br>
                📅 <?= $h(Time::dayLabelFa(new DateTimeImmutable($shipDate))) ?><br>
                ⏰ <?= $h($shipping['slot'] ? explode('|', $shipping['slot'])[2] : '') ?>
            </p>
            <a href="/checkout-shipping" class="cs-edit">تغییر ارسال ←</a>
        </div>

    </div>

    <div class="review-col">
        <div class="cart-summary" style="position:static;">
            <div class="cart-summary-row">
                <span>جمع کالاها</span>
                <b><?= number_format((float)$subtotal) ?> تومان</b>
            </div>
            <div class="cart-summary-row">
                <span>هزینه ارسال</span>
                <b><?= number_format((int)$shipping['fee']) ?> تومان</b>
            </div>
            <div class="cart-summary-row total">
                <span>مبلغ نهایی</span>
                <b><?= number_format((float)$total) ?> تومان</b>
            </div>

            <div class="form-field" style="margin-top:14px;">
                <label>گیرنده (اختیاری — خالی = خودتان)</label>
                <input type="text" name="receiver_name" form="commitForm"
                    value="<?= $h($_POST['receiver_name'] ?? '') ?>">
            </div>
            <div class="form-field">
                <label>موبایل گیرنده (اختیاری)</label>
                <input type="tel" name="receiver_mobile" dir="ltr" form="commitForm"
                    value="<?= $h($_POST['receiver_mobile'] ?? '') ?>">
            </div>

            <form method="post" id="commitForm">
                <button type="submit" class="btn btn-primary cart-checkout-btn">
                    تأیید نهایی و پرداخت ←
                </button>
            </form>
        </div>
    </div>

</div>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/main.php';
