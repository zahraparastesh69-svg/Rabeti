<?php

require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';
require_once ROOT_PATH . '/app/services/AddressService.php';
require_once ROOT_PATH . '/app/services/ShippingService.php';

require_once ROOT_PATH . '/app/services/ShippingService.php';

if (!is_logged_in()) {
    header('Location: /');
    exit;
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$userId = (int)(
    $_SESSION['admin_id']
    ?? $_SESSION['customer_id']
    ?? 0
);

if ($userId <= 0) {
    header('Location: /');
    exit;
}

$db = db();

$addressId = (int)($_SESSION['checkout_address_id'] ?? 0);
$shipping  = $_SESSION['checkout_shipping'] ?? null;

$address = $addressId > 0
    ? AddressService::get($userId, $addressId)
    : null;

/*
|--------------------------------------------------------------------------
| بررسی اولیه Checkout
|--------------------------------------------------------------------------
*/

if (!$address || !is_array($shipping)) {
    header('Location: /checkout-address');
    exit;
}

$shippingMethod = trim((string)($shipping['method'] ?? ''));
$shippingSlot   = trim((string)($shipping['slot'] ?? ''));

if ($shippingMethod === '' || $shippingSlot === '') {
    header('Location: /checkout-shipping');
    exit;
}

$error = '';

/*
|--------------------------------------------------------------------------
| نام روش ارسال
|--------------------------------------------------------------------------
| برای جلوگیری از وابستگی به متغیر $options که در این فایل تعریف نشده بود.
*/

$shippingMethodLabels = [
    'courier'   => 'پیک',
    'post'      => 'پست',
    'pickup'    => 'حضوری',

    'پیک'       => 'پیک',
    'پست'       => 'پست',
    'حضوری'     => 'حضوری',

    'express'   => 'پیک',
    'postal'    => 'پست',
    'self'      => 'حضوری',
    'in_person' => 'حضوری',
];

$shippingMethodLabel =
    $shippingMethodLabels[$shippingMethod]
    ?? trim((string)($shipping['method_label'] ?? ''))
    ?? $shippingMethod;

/*
|--------------------------------------------------------------------------
| تابع نام روز فارسی
|--------------------------------------------------------------------------
*/

$dayLabelFa = static function (DateTimeInterface $date): string {
    $days = [
        0 => 'یکشنبه',
        1 => 'دوشنبه',
        2 => 'سه‌شنبه',
        3 => 'چهارشنبه',
        4 => 'پنجشنبه',
        5 => 'جمعه',
        6 => 'شنبه',
    ];

    return $days[(int)$date->format('w')] ?? '';
};

/*
|--------------------------------------------------------------------------
| تبدیل تاریخ میلادی به نمایش فارسی
|--------------------------------------------------------------------------
| خود تاریخ در دیتابیس/منطق سیستم میلادی باقی می‌ماند.
| فقط برای نمایش، نام روز فارسی می‌شود.
|--------------------------------------------------------------------------
*/

$formatDeliveryDate = static function (?string $date) use ($dayLabelFa): string {
    if (!$date) {
        return '';
    }

    try {
        $dt = new DateTimeImmutable($date);
        return $dayLabelFa($dt) . ' ' . $dt->format('Y/m/d');
    } catch (Throwable $e) {
        return $date;
    }
};

/*
|--------------------------------------------------------------------------
| COMMIT نهایی سفارش
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $receiverName = trim(
        (string)($_POST['receiver_name'] ?? '')
    );

    $receiverMobile = trim(
        (string)($_POST['receiver_mobile'] ?? '')
    );

    /*
    |--------------------------------------------------------------------------
    | تبدیل اعداد فارسی موبایل به انگلیسی
    |--------------------------------------------------------------------------
    */

    $receiverMobile = strtr(
        $receiverMobile,
        [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
        ]
    );

    /*
    |--------------------------------------------------------------------------
    | Validation گیرنده
    |--------------------------------------------------------------------------
    */

    if ($receiverName !== '' && mb_strlen($receiverName) < 3) {
        $error = 'نام گیرنده را کامل وارد کنید';
    }

    if (
        !$error &&
        $receiverMobile !== '' &&
        !preg_match('/^09\d{9}$/', $receiverMobile)
    ) {
        $error = 'موبایل گیرنده معتبر نیست';
    }

    if (!$error) {

        $transactionStarted = false;

        try {

            /*
            |--------------------------------------------------------------------------
            | شروع Transaction
            |--------------------------------------------------------------------------
            */

            $db->beginTransaction();
            $transactionStarted = true;

            /*
            |--------------------------------------------------------------------------
            | 1 + 2
            | قفل سبد کاربر
            |--------------------------------------------------------------------------
            */

            $stmt = $db->prepare("
                SELECT
                    ci.qty,
                    p.id AS product_id,
                    p.code,
                    p.name,
                    p.base_moq,
                    p.is_active,
                    pr.price,
                    COALESCE(inv.qty_available, 0) AS stock
                FROM cart_items ci
                JOIN carts c
                    ON c.id = ci.cart_id
                JOIN products p
                    ON p.id = ci.product_id
                LEFT JOIN product_prices pr
                    ON pr.product_id = p.id
                    AND pr.customer_group = 'wholesale'
                    AND pr.min_qty = 1
                LEFT JOIN inventory inv
                    ON inv.product_id = p.id
                WHERE c.user_id = ?
                  AND c.status = 'active'
                FOR UPDATE
            ");

            $stmt->execute([$userId]);

            $items = $stmt->fetchAll();

            if (!$items) {
                throw new RuntimeException('سبد شما خالی است');
            }

            /*
            |--------------------------------------------------------------------------
            | 3 + 4
            | اعتبارسنجی اقلام
            |--------------------------------------------------------------------------
            */

            $subtotal = 0;

            foreach ($items as $it) {

                if (!(int)$it['is_active']) {
                    throw new RuntimeException(
                        'محصول «' . $it['name'] . '» غیرفعال شده است'
                    );
                }

                if ($it['price'] === null) {
                    throw new RuntimeException(
                        'قیمت «' . $it['name'] . '» نیاز به استعلام دارد'
                    );
                }

                if ((int)$it['qty'] < (int)$it['base_moq']) {
                    throw new RuntimeException(
                        'تعداد «' . $it['name'] .
                            '» کمتر از حداقل سفارش (' .
                            (int)$it['base_moq'] .
                            ' جفت) است'
                    );
                }

                if ((int)$it['stock'] < (int)$it['qty']) {
                    throw new RuntimeException(
                        'موجودی «' . $it['name'] .
                            '» کافی نیست — فقط ' .
                            number_format((int)$it['stock']) .
                            ' جفت'
                    );
                }

                $subtotal +=
                    (int)$it['price'] *
                    (int)$it['qty'];
            }

            /*
            |--------------------------------------------------------------------------
            | 5 + 6
            | بررسی مجدد مالکیت و فعال بودن آدرس
            |--------------------------------------------------------------------------
            */

            $addr = AddressService::get(
                $userId,
                $addressId
            );

            if (!$addr) {
                throw new RuntimeException(
                    'آدرس ارسال معتبر نیست'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 7 + 8 + 9
            | اعتبارسنجی قطعی روش و بازه ارسال در Backend
            |--------------------------------------------------------------------------
            */

            $ship = ShippingService::validateChoice(
                $addr['province'],
                $shippingMethod,
                $shippingSlot,
                $addressId,
                $userId
            );

            if (!is_array($ship)) {
                throw new RuntimeException(
                    'اطلاعات ارسال معتبر نیست'
                );
            }

            if (
                !isset(
                    $ship['method'],
                    $ship['fee'],
                    $ship['date'],
                    $ship['label']
                )
            ) {
                throw new RuntimeException(
                    'اطلاعات روش ارسال ناقص است'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 10
            | رزرو ظرفیت ارسال
            |--------------------------------------------------------------------------
            */

            ShippingService::reserveSlot(
                $shippingSlot
            );

            /*
            |--------------------------------------------------------------------------
            | 11
            | پیدا کردن شرکت مشتری
            |--------------------------------------------------------------------------
            */

            $stmt = $db->prepare("
                SELECT company_id
                FROM company_users
                WHERE user_id = ?
                LIMIT 1
            ");

            $stmt->execute([$userId]);

            $companyId = $stmt->fetchColumn();

            $companyId = $companyId !== false
                ? (int)$companyId
                : null;

            /*
            |--------------------------------------------------------------------------
            | تعیین اطلاعات نهایی گیرنده
            |--------------------------------------------------------------------------
            */

            $finalReceiverName =
                $receiverName !== ''
                ? $receiverName
                : (
                    !empty($addr['receiver_name'])
                    ? $addr['receiver_name']
                    : $addr['label']
                );

            $finalReceiverMobile =
                $receiverMobile !== ''
                ? $receiverMobile
                : (
                    !empty($addr['receiver_mobile'])
                    ? $addr['receiver_mobile']
                    : (string)($_SESSION['customer_mobile'] ?? '')
                );

            /*
            |--------------------------------------------------------------------------
            | شماره سفارش
            |--------------------------------------------------------------------------
            */

            $orderNumber =
                'ORD-' .
                date('ymd') .
                '-' .
                str_pad(
                    (string)random_int(0, 9999),
                    4,
                    '0',
                    STR_PAD_LEFT
                );

            /*
            |--------------------------------------------------------------------------
            | مبلغ نهایی
            |--------------------------------------------------------------------------
            */

            $shippingFee = (int)$ship['fee'];

            $total = $subtotal + $shippingFee;

            /*
            |--------------------------------------------------------------------------
            | 11
            | ثبت سفارش + Snapshot آدرس
            |--------------------------------------------------------------------------
            */

            $db->prepare("
                INSERT INTO orders
                    (
                        order_number,
                        user_id,
                        company_id,
                        subtotal,
                        shipping_fee,
                        total,
                        shipping_name,
                        shipping_mobile,
                        shipping_province,
                        shipping_city,
                        shipping_address,
                        shipping_postal
                    )
                VALUES
                    (?,?,?,?,?,?,?,?,?,?,?,?)
            ")->execute([
                $orderNumber,
                $userId,
                $companyId,
                $subtotal,
                $shippingFee,
                $total,
                $finalReceiverName,
                $finalReceiverMobile,
                $addr['province'],
                $addr['city'],
                $addr['address'],
                $addr['postal_code']
            ]);

            $orderId = (int)$db->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | ثبت اقلام سفارش
            |--------------------------------------------------------------------------
            */

            $insItem = $db->prepare("
                INSERT INTO order_items
                    (
                        order_id,
                        product_id,
                        product_code,
                        product_name,
                        qty,
                        unit_price,
                        line_total
                    )
                VALUES (?,?,?,?,?,?,?)
            ");

            /*
            |--------------------------------------------------------------------------
            | کاهش موجودی
            |--------------------------------------------------------------------------
            */

            $cutStock = $db->prepare("
                UPDATE inventory
                SET qty_available = qty_available - ?
                WHERE product_id = ?
                  AND qty_available >= ?
            ");

            foreach ($items as $it) {

                $qty = (int)$it['qty'];
                $unitPrice = (int)$it['price'];
                $lineTotal = $unitPrice * $qty;

                $insItem->execute([
                    $orderId,
                    (int)$it['product_id'],
                    $it['code'],
                    $it['name'],
                    $qty,
                    $unitPrice,
                    $lineTotal
                ]);

                $cutStock->execute([
                    $qty,
                    (int)$it['product_id'],
                    $qty
                ]);

                /*
                |--------------------------------------------------------------------------
                | کنترل نهایی کاهش موجودی
                |--------------------------------------------------------------------------
                */

                if ($cutStock->rowCount() !== 1) {
                    throw new RuntimeException(
                        'موجودی محصول «' .
                            $it['name'] .
                            '» در لحظه ثبت سفارش تغییر کرده است. لطفاً دوباره تلاش کنید.'
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Snapshot ارسال + آدرس
            |--------------------------------------------------------------------------
            */

            $db->prepare("
                INSERT INTO order_shipments
                    (
                        order_id,
                        method,
                        fee,
                        delivery_date,
                        slot_label,
                        ship_name,
                        ship_mobile,
                        ship_province,
                        ship_city,
                        ship_address,
                        ship_postal
                    )
                VALUES (?,?,?,?,?,?,?,?,?,?,?)
            ")->execute([
                $orderId,
                $ship['method'],
                $shippingFee,
                $ship['date'],
                $ship['label'],
                $finalReceiverName,
                $finalReceiverMobile,
                $addr['province'],
                $addr['city'],
                $addr['address'],
                $addr['postal_code']
            ]);

            /*
            |--------------------------------------------------------------------------
            | 13
            | تبدیل سبد به سفارش
            |--------------------------------------------------------------------------
            */

            $db->prepare("
                UPDATE carts
                SET status = 'converted'
                WHERE user_id = ?
                  AND status = 'active'
            ")->execute([
                $userId
            ]);

            /*
            |--------------------------------------------------------------------------
            | پاک کردن اقلام سبد
            |--------------------------------------------------------------------------
            */

            $db->prepare("
                DELETE ci
                FROM cart_items ci
                JOIN carts c
                    ON c.id = ci.cart_id
                WHERE c.user_id = ?
            ")->execute([
                $userId
            ]);

            /*
            |--------------------------------------------------------------------------
            | ایجاد فاکتور
            |--------------------------------------------------------------------------
            */

            $invNum =
                'INV-' .
                date('ymd') .
                '-' .
                str_pad(
                    (string)random_int(0, 9999),
                    4,
                    '0',
                    STR_PAD_LEFT
                );

            $db->prepare("
                INSERT INTO invoices
                    (
                        invoice_number,
                        order_id,
                        user_id,
                        total
                    )
                VALUES (?,?,?,?)
            ")->execute([
                $invNum,
                $orderId,
                $userId,
                $total
            ]);

            /*
            |--------------------------------------------------------------------------
            | Audit Log
            |--------------------------------------------------------------------------
            */

            $db->prepare("
                INSERT INTO audit_logs
                    (
                        user_id,
                        action,
                        entity,
                        entity_id,
                        ip
                    )
                VALUES
                    (?, 'order.create', 'order', ?, ?)
            ")->execute([
                $userId,
                $orderId,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ]);

            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

            $db->commit();
            $transactionStarted = false;

            /*
            |--------------------------------------------------------------------------
            | پاکسازی Session Checkout
            |--------------------------------------------------------------------------
            */

            unset(
                $_SESSION['checkout_address_id'],
                $_SESSION['checkout_shipping']
            );

            /*
            |--------------------------------------------------------------------------
            | انتقال به پرداخت
            |--------------------------------------------------------------------------
            */

            header(
                'Location: /payment?order=' .
                    urlencode($orderNumber)
            );

            exit;
        } catch (RuntimeException $e) {

            if ($transactionStarted && $db->inTransaction()) {
                $db->rollBack();
            }

            $error = $e->getMessage();

            /*
            |--------------------------------------------------------------------------
            | اگر خطا مربوط به بازه ارسال باشد
            |--------------------------------------------------------------------------
            */

            if (mb_strpos($error, 'بازه') !== false) {

                header(
                    'Location: /checkout-shipping?error=' .
                        urlencode($error)
                );

                exit;
            }
        } catch (Throwable $e) {

            if ($transactionStarted && $db->inTransaction()) {
                $db->rollBack();
            }

            error_log(
                'CHECKOUT COMMIT ERROR: ' .
                    $e->getMessage()
            );

            $error =
                'خطای سرور در ثبت سفارش — دوباره تلاش کنید';
        }
    }
}

/*
|--------------------------------------------------------------------------
| نمایش خلاصه سفارش
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT
        ci.qty,
        p.code,
        p.name,
        pr.price
    FROM cart_items ci
    JOIN carts c
        ON c.id = ci.cart_id
    JOIN products p
        ON p.id = ci.product_id
    LEFT JOIN product_prices pr
        ON pr.product_id = p.id
        AND pr.customer_group = 'wholesale'
        AND pr.min_qty = 1
    WHERE c.user_id = ?
      AND c.status = 'active'
");

$stmt->execute([$userId]);

$items = $stmt->fetchAll();

if (!$items && !$error) {
    header('Location: /cart');
    exit;
}

/*
|--------------------------------------------------------------------------
| محاسبه مبالغ برای نمایش
|--------------------------------------------------------------------------
*/

$subtotal = 0;

foreach ($items as $it) {
    $subtotal +=
        (int)($it['price'] ?? 0) *
        (int)$it['qty'];
}

$shippingFeeDisplay =
    (int)($shipping['fee'] ?? 0);

$total =
    $subtotal +
    $shippingFeeDisplay;

/*
|--------------------------------------------------------------------------
| اطلاعات بازه ارسال
|--------------------------------------------------------------------------
*/

$slotParts = explode('|', $shippingSlot);

$shipDate = $slotParts[0] ?? '';
$shipRange = $slotParts[1] ?? '';
$shipTimeLabel = $slotParts[2] ?? '';

/*
|--------------------------------------------------------------------------
| اگر label زمان در Session نبود،
| از label سرویس ارسال استفاده می‌کنیم.
|--------------------------------------------------------------------------
*/

if ($shipTimeLabel === '') {
    $shipTimeLabel =
        trim((string)($shipping['label'] ?? ''));
}

$deliveryDateLabel =
    $formatDeliveryDate($shipDate);

$pageTitle = 'خلاصه سفارش | رابطی';

ob_start();

?>

<nav class="breadcrumb">
    <a href="/">خانه</a> ›
    <a href="/cart">سبد</a> ›
    <a href="/checkout-address">آدرس</a> ›
    <a href="/checkout-shipping">ارسال</a> ›
    <span>خلاصه</span>
</nav>

<section class="catalog-head">

    <h1 class="catalog-title">
        خلاصه سفارش
    </h1>

    <p class="catalog-sub">
        یک بار دیگر مرور کنید و تأیید نهایی
    </p>

</section>

<?php if ($error): ?>

    <div class="form-alert error" style="display:block;">
        ⚠️ <?= $h($error) ?>
    </div>

<?php endif; ?>

<div class="review-grid">

    <div class="review-col">

        <div class="pd-specs-box">

            <div class="pd-price-title">
                اقلام سفارش
            </div>

            <?php foreach ($items as $it): ?>

                <div class="cs-row">

                    <span>
                        <?= $h($it['name']) ?>

                        <small>
                            × <?= (int)$it['qty'] ?>
                        </small>
                    </span>

                    <b>
                        <?=
                        $it['price'] !== null
                            ? number_format(
                                (int)$it['price'] *
                                    (int)$it['qty']
                            )
                            : '—'
                        ?>
                    </b>

                </div>

            <?php endforeach; ?>

        </div>

        <div class="pd-specs-box">

            <div class="pd-price-title">
                آدرس ارسال
            </div>

            <p class="review-text">

                🏪
                <?= $h($address['label']) ?>

                <br>

                <?= $h($address['province']) ?>،
                <?= $h($address['city']) ?>

                <br>

                <?= $h($address['address']) ?>

                <?php if (!empty($address['plate_no'])): ?>

                    ، پلاک
                    <?= $h($address['plate_no']) ?>

                <?php endif; ?>

            </p>

            <a
                href="/checkout-address"
                class="cs-edit">
                تغییر آدرس ←
            </a>

        </div>

        <div class="pd-specs-box">

            <div class="pd-price-title">
                ارسال
            </div>

            <p class="review-text">

                🚚
                <?= $h($shippingMethodLabel) ?>

                <br>

                📅
                <?= $h($deliveryDateLabel) ?>

                <br>

                ⏰
                <?= $h($shipTimeLabel) ?>

            </p>

            <a
                href="/checkout-shipping"
                class="cs-edit">
                تغییر ارسال ←
            </a>

        </div>

    </div>

    <div class="review-col">

        <div
            class="cart-summary"
            style="position:static;">

            <div class="cart-summary-row">

                <span>
                    جمع کالاها
                </span>

                <b>
                    <?= number_format($subtotal) ?>
                    تومان
                </b>

            </div>

            <div class="cart-summary-row">

                <span>
                    هزینه ارسال
                </span>

                <b>
                    <?= number_format($shippingFeeDisplay) ?>
                    تومان
                </b>

            </div>

            <div class="cart-summary-row total">

                <span>
                    مبلغ نهایی
                </span>

                <b>
                    <?= number_format($total) ?>
                    تومان
                </b>

            </div>

            <div
                class="form-field"
                style="margin-top:14px;">

                <label>
                    گیرنده (اختیاری — خالی = خودتان)
                </label>

                <input
                    type="text"
                    name="receiver_name"
                    form="commitForm"
                    value="<?= $h($_POST['receiver_name'] ?? '') ?>">

            </div>

            <div class="form-field">

                <label>
                    موبایل گیرنده (اختیاری)
                </label>

                <input
                    type="tel"
                    name="receiver_mobile"
                    dir="ltr"
                    form="commitForm"
                    value="<?= $h($_POST['receiver_mobile'] ?? '') ?>">

            </div>

            <form
                method="post"
                id="commitForm">

                <button
                    type="submit"
                    class="btn btn-primary cart-checkout-btn">
                    تأیید نهایی و پرداخت ←
                </button>

            </form>

        </div>

    </div>

</div>

<?php

$content = ob_get_clean();

require ROOT_PATH . '/views/layouts/main.php';
