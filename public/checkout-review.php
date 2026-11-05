<?php

declare(strict_types=1);

/* Auto-detect bootstrap (local + host) */
$__bp = [
    __DIR__ . '/../sock-b2b/config/bootstrap.php',
    __DIR__ . '/../config/bootstrap.php',
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
require_once ROOT_PATH . '/app/services/AddressService.php';
require_once ROOT_PATH . '/app/services/ShippingService.php';
require_once ROOT_PATH . '/app/services/PricingService.php';
require_once ROOT_PATH . '/app/services/OrderService.php';

/* ═══ نمایش شمسی و بازه فارسی ═══ */

/* میلادی → جلالی */
$jalaliFa = static function (?string $gregorianDate): string {
    if (!$gregorianDate) return '—';
    $ts = strtotime($gregorianDate);
    if ($ts === false) return '—';

    $gy = (int)date('Y', $ts);
    $gm = (int)date('n', $ts);
    $gd = (int)date('j', $ts);
    $g = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? $gy + 1 : $gy;
    $d = (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) - 80 + $gd + $g[$gm - 1];
    $jy += 33 * intdiv($d, 12053);
    $d %= 12053;
    $jy += 4 * intdiv($d, 1461);
    $d %= 1461;
    if ($d > 365) {
        $jy += intdiv($d - 1, 365);
        $d = ($d - 1) % 365;
    }
    $jm = ($d < 186) ? 1 + intdiv($d, 31) : 7 + intdiv($d - 186, 30);
    $jd = 1 + (($d < 186) ? $d % 31 : ($d - 186) % 30);

    /* روز هفته فارسی */
    $days = [7 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه', 6 => 'شنبه'];
    $dayName = $days[(int)date('N', $ts)];

    return $dayName . ' ' . strtr(
        $jy . '/' . str_pad((string)$jm, 2, '0', STR_PAD_LEFT) . '/' . str_pad((string)$jd, 2, '0', STR_PAD_LEFT),
        ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']
    );
};

/* بازه: 16:00-19:00 → «از ۴ عصر تا ۷ عصر» */
$slotFa = static function (string $range): string {
    if (!str_contains($range, '-')) return $range;
    [$s, $e] = array_map('trim', explode('-', $range, 2));

    /* اگر قبلاً فارسی است، دست نزن */
    if (!preg_match('/^\d{1,2}:\d{2}$/', $s)) return $range;

    $sh = (int)explode(':', $s)[0];
    $eh = (int)explode(':', $e)[0];

    $fa = static fn(int $n): string => strtr(
        (string)($n > 12 ? $n - 12 : $n),
        ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']
    );
    $tod = static fn(int $hh): string => ($hh >= 5 && $hh < 12) ? 'صبح' : (($hh >= 12 && $hh < 17) ? 'ظهر' : (($hh >= 17 && $hh < 21) ? 'عصر' : 'شب'));

    return 'از ' . $fa($sh) . ' ' . $tod($sh) . ' تا ' . $fa($eh) . ' ' . $tod($eh);
};

if (!is_customer_logged_in()) {
    header('Location: ' . (is_admin_logged_in() ? '/admin' : '/'));
    exit;
}

/*
|--------------------------------------------------------------------------
| Checkout pages must not be indexed
|--------------------------------------------------------------------------
|
| صفحات سبد خرید و Checkout محتوای قابل جستجو برای گوگل ندارند.
| جلوگیری از ایندکس شدن آنها به تمرکز Crawl Budget روی محصولات و دسته‌ها کمک می‌کند.
|
*/
header('X-Robots-Tag: noindex, nofollow, noarchive', true);

$h = static fn($v) => htmlspecialchars(
    (string)$v,
    ENT_QUOTES,
    'UTF-8'
);

$userId = (int)($_SESSION['customer_id'] ?? 0);

if ($userId <= 0) {
    header('Location: /');
    exit;
}

$db = db();

/*
|--------------------------------------------------------------------------
| Checkout session
|--------------------------------------------------------------------------
*/

$addressId = (int)(
    $_SESSION['checkout_address_id'] ?? 0
);

$shipping = $_SESSION['checkout_shipping'] ?? null;

$address = $addressId > 0
    ? AddressService::get($userId, $addressId)
    : null;

if (!$address || !is_array($shipping)) {
    header('Location: /checkout-address');
    exit;
}

$shippingMethod = trim(
    (string)($shipping['method'] ?? '')
);

$shippingSlot = trim(
    (string)($shipping['slot'] ?? '')
);

if ($shippingMethod === '') {
    header('Location: /checkout-shipping');
    exit;
}

/*
|--------------------------------------------------------------------------
| گزینه‌های ارسال
|--------------------------------------------------------------------------
*/

$options = ShippingService::getOptions(
    (string)$address['province']
);

if (
    !isset(
        $options['methods'][$shippingMethod]
    )
) {
    unset($_SESSION['checkout_shipping']);

    header('Location: /checkout-shipping');
    exit;
}

$selectedShippingMethod =
    $options['methods'][$shippingMethod];

$shippingRequiresSlot =
    !empty($selectedShippingMethod['days']);

/*
|--------------------------------------------------------------------------
| روش‌های بدون Slot
|--------------------------------------------------------------------------
|
| پست شهرستان و تحویل حضوری تاریخ/بازه قابل انتخاب ندارند.
| بنابراین Slot باید همیشه خالی باشد.
|
*/

if (!$shippingRequiresSlot) {
    $shippingSlot = '';
}

/*
|--------------------------------------------------------------------------
| نام روش ارسال
|--------------------------------------------------------------------------
*/

$shippingMethodLabels = [

    'peyk' =>
    'پیک موتوری',

    'post' =>
    'پست پیشتاز',

    'in_person' =>
    'تحویل حضوری از کارگاه',

];

$shippingMethodLabel =
    $shippingMethodLabels[$shippingMethod]
    ?? trim(
        (string)(
            $shipping['method_label']
            ?? $selectedShippingMethod['label']
            ?? $shippingMethod
        )
    );

$error = '';

/*
|--------------------------------------------------------------------------
| نام روز
|--------------------------------------------------------------------------
*/

$dayLabelFa = static function (
    DateTimeInterface $date
): string {

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
| فرمت تاریخ
|--------------------------------------------------------------------------
*/

$formatDeliveryDate = static function (
    ?string $date
) use (
    $dayLabelFa
): string {

    if (!$date) {
        return '';
    }

    try {

        $dt = new DateTimeImmutable($date);

        return
            $dayLabelFa($dt)
            . ' '
            . $dt->format('Y/m/d');
    } catch (Throwable $e) {

        return $date;
    }
};

/*
|--------------------------------------------------------------------------
| ثبت نهایی سفارش
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();


    $receiverName = trim(
        (string)(
            $_POST['receiver_name'] ?? ''
        )
    );

    $receiverMobile = trim(
        (string)(
            $_POST['receiver_mobile'] ?? ''
        )
    );

    /*
     * اعداد فارسی → انگلیسی
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
     * اعتبارسنجی نام
     */
    if (
        $receiverName !== ''
        && mb_strlen($receiverName) < 3
    ) {

        $error =
            'نام گیرنده را کامل وارد کنید.';
    }

    /*
     * اعتبارسنجی موبایل
     */
    if (
        !$error
        && $receiverMobile !== ''
        && !preg_match(
            '/^09\d{9}$/',
            $receiverMobile
        )
    ) {

        $error =
            'موبایل گیرنده معتبر نیست.';
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

            $userGroup = current_user_group();

            /*
            |--------------------------------------------------------------------------
            | قفل سبد خرید
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
        p.base_price,
        COALESCE(inv.qty_physical, 0) AS qty_physical,
        COALESCE(inv.reserved, 0) AS qty_reserved,
        COALESCE(inv.unsellable, 0) AS qty_unsellable,
        GREATEST(0, COALESCE(inv.qty_physical, 0) - COALESCE(inv.reserved, 0) - COALESCE(inv.unsellable, 0)) AS available_for_sale

    FROM cart_items ci

    JOIN carts c
        ON c.id = ci.cart_id

    JOIN products p
        ON p.id = ci.product_id

    LEFT JOIN inventory inv
        ON inv.product_id = p.id

    WHERE c.user_id = ?
      AND c.status = 'active'

    FOR UPDATE
");

            $stmt->execute([$userId]);

            $items = $stmt->fetchAll();

            if (!$items) {

                throw new RuntimeException(
                    'سبد شما خالی است.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | اعتبارسنجی کالاها
            |--------------------------------------------------------------------------
            */

            $subtotal = 0;
            $discountTotal = 0;
            foreach ($items as &$it) {

                $qty = (int)$it['qty'];

                if ($qty <= 0) {
                    continue;
                }

                if (!(int)$it['is_active']) {
                    throw new RuntimeException(
                        'محصول «' . $it['name'] . '» دیگر فعال نیست. لطفاً آن را از سبد حذف کنید.'
                    );
                }

                $moq = max(1, (int)$it['base_moq']);
                if ($qty < $moq) {
                    throw new RuntimeException(
                        'حداقل سفارش «' . $it['name'] . '» ' . number_format($moq) . ' عدد است. لطفاً تعداد را در سبد اصلاح کنید.'
                    );
                }

                $pricing = PricingService::calculate(
                    $db,
                    (int)$it['product_id'],
                    $qty,
                    $userGroup
                );

                if ($pricing['base_price'] <= 0) {
                    throw new RuntimeException(
                        'قیمت محصول «' . $it['name'] . '» مشخص نشده است. لطفاً با پشتیبانی تماس بگیرید.'
                    );
                }

                $it['base_unit_price']        = $pricing['base_price'];
                $it['unit_price']             = $pricing['unit_price'];
                $it['discount_percent']       = $pricing['discount_percent'];
                $it['discount_amount']        = $pricing['discount_amount'];
                $it['base_line_total']        = $pricing['base_line_total'];
                $it['line_total']             = $pricing['line_total'];
                $it['discount_source']        = $pricing['discount_source'];
                $it['group_discount_percent'] = $pricing['discount_source'] === 'group'
                    ? $pricing['discount_percent']
                    : 0;
                $it['tier_discount_percent']  = $pricing['discount_source'] === 'tier'
                    ? $pricing['discount_percent']
                    : 0;

                $qtyAvailable = (int)$it['available_for_sale'];
                $qtyReserved  = min($qty, max(0, $qtyAvailable));
                $qtyToProduce = max(0, $qty - $qtyReserved);

                $it['qty_reserved']   = $qtyReserved;
                $it['qty_to_produce'] = $qtyToProduce;

                $subtotal      += $pricing['base_line_total'];
                $discountTotal += $pricing['discount_amount'];
            }
            unset($it);
            /*
            |--------------------------------------------------------------------------
            | دریافت مجدد آدرس
            |--------------------------------------------------------------------------
            */

            $addr = AddressService::get(
                $userId,
                $addressId
            );

            if (!$addr) {

                throw new RuntimeException(
                    'آدرس ارسال معتبر نیست.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | دریافت مجدد تنظیمات ارسال
            |--------------------------------------------------------------------------
            |
            | اطلاعات ارسال Session به تنهایی قابل اعتماد نیست.
            | در لحظه ثبت سفارش دوباره از ShippingService می‌خوانیم.
            |
            */

            $freshShippingOptions =
                ShippingService::getOptions(
                    (string)$addr['province']
                );

            if (
                !isset(
                    $freshShippingOptions['methods'][$shippingMethod]
                )
            ) {

                throw new RuntimeException(
                    'روش ارسال انتخاب‌شده دیگر برای این آدرس قابل استفاده نیست. لطفاً روش ارسال را دوباره انتخاب کنید.'
                );
            }

            $freshMethod =
                $freshShippingOptions['methods'][$shippingMethod];

            $freshRequiresSlot =
                !empty($freshMethod['days']);

            /*
            |--------------------------------------------------------------------------
            | جلوگیری از استفاده از Slot قدیمی
            |--------------------------------------------------------------------------
            */

            if (!$freshRequiresSlot) {

                $shippingSlot = '';
            }

            /*
            |--------------------------------------------------------------------------
            | اعتبارسنجی ارسال
            |--------------------------------------------------------------------------
            */

            $ship = null;

            if ($freshRequiresSlot) {

                /*
                 * روش‌هایی که Slot واقعی دارند:
                 * پیک تهران / پست تهران
                 */

                if ($shippingSlot === '') {

                    throw new RuntimeException(
                        'تاریخ و زمان ارسال انتخاب نشده است.'
                    );
                }

                $ship =
                    ShippingService::validateChoice(
                        $addr['province'],
                        $shippingMethod,
                        $shippingSlot,
                        $addressId,
                        $userId
                    );

                if (!is_array($ship)) {

                    throw new RuntimeException(
                        'اطلاعات ارسال معتبر نیست.'
                    );
                }

                /*
                 * رزرو ظرفیت فقط برای Slot واقعی
                 */
                ShippingService::reserveSlot(
                    $shippingSlot
                );
            } else {

                /*
                |--------------------------------------------------------------------------
                | روش‌های بدون Slot
                |--------------------------------------------------------------------------
                |
                | نکته مهم:
                | delivery_date در دیتابیس NOT NULL است.
                |
                | بنابراین NULL ارسال نمی‌کنیم.
                |
                | برای روش بدون Slot، تاریخ ثبت سفارش به‌عنوان
                | تاریخ پردازش/ارسال Snapshot می‌شود و
                | بازه واقعی در slot_label ذخیره می‌شود.
                |
                */

                $noSlotDate =
                    date('Y-m-d');

                $noSlotLabel =
                    trim(
                        (string)(
                            $freshMethod['estimate']
                            ?? ''
                        )
                    );

                if ($noSlotLabel === '') {

                    $noSlotLabel =
                        trim(
                            (string)(
                                $freshMethod['label']
                                ?? 'بدون نیاز به انتخاب تاریخ و ساعت'
                            )
                        );
                }

                $ship = [

                    'method' =>
                    $shippingMethod,

                    'date' =>
                    $noSlotDate,

                    'label' =>
                    $noSlotLabel,

                    'fee' =>
                    (int)(
                        $freshMethod['fee']
                        ?? 0
                    ),

                ];
            }

            /*
            |--------------------------------------------------------------------------
            | کنترل نهایی ساختار Ship
            |--------------------------------------------------------------------------
            */

            if (
                !is_array($ship)
                || empty($ship['method'])
            ) {

                throw new RuntimeException(
                    'اطلاعات ارسال ناقص است.'
                );
            }

            /*
            | delivery_date نباید هیچ‌وقت NULL باشد.
            */
            $deliveryDate =
                trim(
                    (string)(
                        $ship['date']
                        ?? ''
                    )
                );

            if ($deliveryDate === '') {

                /*
                 * Fail-safe:
                 * حتی اگر ShippingService در آینده مقدار خالی برگرداند،
                 * ثبت سفارش با NULL متوقف نمی‌شود.
                 */
                $deliveryDate =
                    date('Y-m-d');
            }

            /*
            |--------------------------------------------------------------------------
            | شرکت مشتری
            |--------------------------------------------------------------------------
            */

            $stmt = $db->prepare("
                SELECT company_id
                FROM company_users
                WHERE user_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $userId
            ]);

            $companyId =
                $stmt->fetchColumn();

            $companyId =
                $companyId !== false
                ? (int)$companyId
                : null;

            /*
            |--------------------------------------------------------------------------
            | گیرنده نهایی
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
                    : (string)(
                        $_SESSION['customer_mobile'] ?? ''
                    )
                );

            /*
            |--------------------------------------------------------------------------
            | شماره سفارش
            |--------------------------------------------------------------------------
            */

            $orderNumber = generate_unique_number($db, 'ORD', 'orders', 'order_number');

            /*
            |--------------------------------------------------------------------------
            | مبلغ
            |--------------------------------------------------------------------------
            */

            $shippingFee =
                (int)(
                    $ship['fee']
                    ?? 0
                );

            $finalSubtotal =
                max(
                    0,
                    $subtotal - $discountTotal
                );

            $total =
                $finalSubtotal
                +
                $shippingFee;
            /*
|--------------------------------------------------------------------------
| ثبت سفارش
|--------------------------------------------------------------------------
*/

            $insOrder = $db->prepare("
    INSERT INTO orders
    (
        order_number,
        user_id,
        company_id,
        status,
        subtotal,
        discount_total,
        shipping_fee,
        total,
        shipping_name,
        shipping_mobile,
        shipping_province,
        shipping_city,
        shipping_address,
        shipping_postal
    )
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");

            $insOrder->execute([

                $orderNumber,
                $userId,
                $companyId,
                'pending_review',

                /*
     * subtotal = مبلغ کالاها قبل از تخفیف
     */
                $subtotal,

                /*
     * مجموع تخفیف کالاها
     */
                $discountTotal,

                /*
     * هزینه ارسال
     */
                $shippingFee,

                /*
     * مبلغ نهایی:
     * قیمت کالاها بعد از تخفیف + ارسال
     */
                $total,

                $finalReceiverName,
                $finalReceiverMobile,
                $addr['province'],
                $addr['city'],
                $addr['address'],
                $addr['postal_code']

            ]);

            $orderId =
                (int)$db->lastInsertId();


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
        qty_reserved,
        qty_to_produce,
        base_unit_price,
        group_discount_percent,
        tier_discount_percent,
        discount_percent,
        discount_source,
        discount_amount,
        unit_price,
        line_total
    )
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");

            foreach ($items as $it) {

                $qty =
                    (int)$it['qty'];

                $qtyReserved =
                    (int)$it['qty_reserved'];

                $qtyToProduce =
                    (int)$it['qty_to_produce'];

                $baseUnitPrice =
                    (int)$it['base_unit_price'];

                $discountPercent =
                    (float)$it['discount_percent'];

                $discountAmount =
                    (int)$it['discount_amount'];

                $unitPrice =
                    (int)$it['unit_price'];

                $lineTotal =
                    (int)$it['line_total'];
                $insItem->execute([
                    $orderId,
                    (int)$it['product_id'],
                    $it['code'],
                    $it['name'],
                    (int)$it['qty'],
                    (int)$it['qty_reserved'],
                    (int)$it['qty_to_produce'],
                    (int)$it['base_unit_price'],
                    (float)$it['group_discount_percent'],
                    (float)$it['tier_discount_percent'],
                    (float)$it['discount_percent'],
                    (string)$it['discount_source'],
                    (int)$it['discount_amount'],
                    (int)$it['unit_price'],
                    (int)$it['line_total'],
                ]);
            }

            /*
          /*
|--------------------------------------------------------------------------
| کاهش موجودی
|--------------------------------------------------------------------------
*/

            /*
             * رزرو موجودی: به‌جای کاهش qty_available،
             * مقدار qty_reserved را افزایش می‌دهیم.
             * این حفظ invariant available_for_sale = qty_physical - reserved - unsellable
             * را تضمین می‌کند و امکان تفکیک رزرو/تولید را در order_items می‌دهد.
             *
             * اگر qty_to_produce > 0، آن بخش از موجودی رزرو نمی‌شود
             * (قلم در پنل ادمین به‌عنوان نیازمند تولید شناخته می‌شود).
             */
            $reserveStock = $db->prepare("
                UPDATE inventory
                SET reserved = reserved + ?
                WHERE product_id = ?
            ");

            $logMovement = $db->prepare("
                INSERT INTO stock_movements
                (product_id, movement_type, quantity, reference_type, reference_id,
                 order_id, order_item_id, reason, created_by, ip)
                VALUES (?, 'reserve', ?, 'order', ?, ?, ?, ?, ?, ?)
            ");

            $adminUser = $userId;

            foreach ($items as $it) {

                $qty         = (int)$it['qty'];
                $qtyReserved = (int)$it['qty_reserved'];
                $productId   = (int)$it['product_id'];

                /*
                 * ثبت رزرو در inventory
                 */
                if ($qtyReserved > 0) {
                    $reserveStock->execute([
                        $qtyReserved,
                        $productId
                    ]);

                    if ($reserveStock->rowCount() !== 1) {
                        throw new RuntimeException(
                            'رزرو موجودی برای محصول «'
                                . $it['name']
                                . '» در لحظه ثبت سفارش شکست خورد. لطفاً دوباره تلاش کنید.'
                        );
                    }

                    /*
                     * ثبت گردش موجودی
                     */
                    $logMovement->execute([
                        $productId,
                        $qtyReserved,
                        null,
                        $orderId,
                        null,
                        'رزرو برای سفارش جدید',
                        $adminUser,
                        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                    ]);
                }
            }
            /*
            |--------------------------------------------------------------------------
            | Snapshot ارسال
            |--------------------------------------------------------------------------
            */

            $shipmentLabel =
                trim(
                    (string)(
                        $ship['label']
                        ?? ''
                    )
                );

            if ($shipmentLabel === '') {

                $shipmentLabel =
                    trim(
                        (string)(
                            $freshMethod['estimate']
                            ?? $freshMethod['label']
                            ?? 'روش ارسال'
                        )
                    );
            }

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
                $deliveryDate,
                $shipmentLabel,
                $finalReceiverName,
                $finalReceiverMobile,
                $addr['province'],
                $addr['city'],
                $addr['address'],
                $addr['postal_code']

            ]);

            /*
            |--------------------------------------------------------------------------
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
            | Invoice
            |--------------------------------------------------------------------------
            */

            $invNum = generate_unique_number($db, 'INV', 'invoices', 'invoice_number');

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
                $_SERVER['REMOTE_ADDR']
                    ?? '0.0.0.0'

            ]);

            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

            $db->commit();

            $transactionStarted = false;

            /* ─── ارتقای خودکار سطح مشتری/شرکت ─── */
            try {
                if ($companyId !== null && $companyId > 0) {
                    OrderService::maybeUpgradeCompanyGroup($db, $companyId);
                }
                OrderService::maybeUpgradeCustomerGroup($db, $userId);
            } catch (Throwable $e) {
                error_log('AUTO UPGRADE ERROR: ' . $e->getMessage());
            }

            unset(
                $_SESSION['checkout_address_id'],
                $_SESSION['checkout_shipping']
            );

            header(
                'Location: /payment?order='
                    . urlencode($orderNumber)
            );

            exit;

            /*
            |--------------------------------------------------------------------------
            | پاک کردن Session Checkout
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
                'Location: /payment?order='
                    . urlencode($orderNumber)
            );

            exit;
        } catch (RuntimeException $e) {

            if (
                $transactionStarted
                && $db->inTransaction()
            ) {

                $db->rollBack();
            }

            $error =
                $e->getMessage();
        } catch (Throwable $e) {

            if (
                $transactionStarted
                && $db->inTransaction()
            ) {

                $db->rollBack();
            }

            error_log(
                'CHECKOUT COMMIT ERROR: '
                    . $e->getMessage()
            );

            $error =
                'خطایی در ثبت سفارش رخ داد. لطفاً دوباره تلاش کنید.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| اقلام برای نمایش Review
|--------------------------------------------------------------------------
*/

$userGroup = current_user_group();
$stmt = $db->prepare("
    SELECT
        ci.qty,
        p.id AS product_id,
        p.code,
        p.name,
        p.base_price

    FROM cart_items ci

    JOIN carts c
        ON c.id = ci.cart_id

    JOIN products p
        ON p.id = ci.product_id

    WHERE c.user_id = ?
      AND c.status = 'active'
");

$stmt->execute([$userId]);

$items = $stmt->fetchAll();

if (
    !$items
    && !$error
) {

    header('Location: /cart');
    exit;
}

/*
|--------------------------------------------------------------------------
| محاسبه مبلغ نمایش
|--------------------------------------------------------------------------
*/

$subtotal = 0;
$discountTotal = 0;

foreach ($items as &$it) {

    $qty = (int)$it['qty'];

    if ($qty <= 0) {
        continue;
    }

    $pricing = PricingService::calculate(
        $db,
        (int)$it['product_id'],
        $qty,
        $userGroup
    );

    if ($pricing['base_price'] <= 0) {
        continue;
    }

    $it['base_unit_price'] = $pricing['base_price'];
    $it['unit_price']      = $pricing['unit_price'];
    $it['discount_percent'] = $pricing['discount_percent'];
    $it['discount_amount']  = $pricing['discount_amount'];
    $it['base_line_total']  = $pricing['base_line_total'];
    $it['line_total']       = $pricing['line_total'];
    $it['discount_source']  = $pricing['discount_source'];

    $subtotal      += $pricing['base_line_total'];
    $discountTotal += $pricing['discount_amount'];
}

unset($it);

$finalSubtotal =
    max(
        0,
        $subtotal - $discountTotal
    );

$shippingFeeDisplay =
    (int)(
        $shipping['fee']
        ??
        $selectedShippingMethod['fee']
        ??
        0
    );

$total =
    $finalSubtotal
    +
    $shippingFeeDisplay;
/*
|--------------------------------------------------------------------------
| اطلاعات تاریخ / Slot
|--------------------------------------------------------------------------
*/
/* --------------------------------------------------------------------------
| اطلاعات تاریخ / Slot
|--------------------------------------------------------------------------
*/

$shipDate = '';
$shipTimeLabel = '';

if ($shippingSlot !== '') {

    $slotParts = explode('|', $shippingSlot, 3);

    /*
     * ساختار Slot فعلی پروژه:
     * بخش دوم = تاریخ
     * بخش سوم = عنوان بازه
     */
    $shipDate = $slotParts[1] ?? '';
    $shipTimeLabel = $slotParts[2] ?? '';
}

/*
|--------------------------------------------------------------------------
| روش‌های بدون Slot
|--------------------------------------------------------------------------
*/

if ($shippingSlot === '') {

    $shipDate = '';

    $shipTimeLabel = trim(
        (string)(
            $shipping['estimate']
            ?? $selectedShippingMethod['estimate']
            ?? ''
        )
    );

    if ($shipTimeLabel === '') {
        $shipTimeLabel = 'بدون نیاز به انتخاب تاریخ و ساعت';
    }
}

/* ══════════════════════════════════════════════════════════
   ⭐ تبدیل نهایی — بعد از همه محاسبات
   ══════════════════════════════════════════════════════════ */

$deliveryDateLabel = $shipDate !== ''
    ? $jalaliFa($shipDate)
    : '—';

$shipTimeLabel = $slotFa($shipTimeLabel);
/*
|--------------------------------------------------------------------------
| SEO / Page metadata
|--------------------------------------------------------------------------
*/

$pageTitle =
    'خلاصه سفارش | رابطی';

$activeMenu = '';

ob_start();

?>

<!-- Checkout pages are transactional and should not be indexed. -->
<meta
    name="robots"
    content="noindex,nofollow,noarchive">

<nav class="breadcrumb">

    <a href="/">
        خانه
    </a>
    ›

    <a href="/cart">
        سبد
    </a>
    ›

    <a href="/checkout-address">
        آدرس
    </a>
    ›

    <a href="/checkout-shipping">
        ارسال
    </a>
    ›

    <span>
        خلاصه
    </span>

</nav>


<section class="catalog-head">

    <h1 class="catalog-title">
        خلاصه سفارش
    </h1>

    <p class="catalog-sub">
        یک بار دیگر مرور کنید و تأیید نهایی را انجام دهید.
    </p>

</section>


<?php if ($error): ?>

    <div
        class="form-alert error"
        style="display:block;">

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
                        <small>× <?= number_format((int)$it['qty']) ?></small>

                        <?php if (!empty($it['discount_percent']) && $it['discount_percent'] > 0): ?>
                            <small style="color:#27ae60;display:block;">
                                <?= $it['discount_source'] === 'group' ? 'تخفیف سطح' : 'تخفیف پلکانی' ?>
                                <?= number_format((float)$it['discount_percent'], 2) ?>٪
                            </small>
                        <?php endif; ?>
                    </span>
                    <b><?= number_format((int)$it['line_total']) ?></b>
                </div>


            <?php endforeach; ?>

        </div>


        <div class="pd-specs-box">

            <div class="pd-price-title">
                آدرس ارسال
            </div>

            <p class="review-text">

                📍
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

                <?php if ($deliveryDateLabel !== ''): ?>

                    <br>

                    📅
                    <?= $h($deliveryDateLabel) ?>

                <?php endif; ?>

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
                    <?= number_format(
                        $shippingFeeDisplay
                    ) ?>
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
                    گیرنده
                    (اختیاری — خالی = خودتان)
                </label>

                <input
                    type="text"
                    name="receiver_name"
                    form="commitForm"
                    value="<?= $h(
                                $_POST['receiver_name']
                                    ?? ''
                            ) ?>">

            </div>


            <div class="form-field">

                <label>
                    موبایل گیرنده
                    (اختیاری)
                </label>

                <input
                    type="tel"
                    name="receiver_mobile"
                    dir="ltr"
                    form="commitForm"
                    value="<?= $h(
                                $_POST['receiver_mobile']
                                    ?? ''
                            ) ?>">

            </div>


            <form
                method="post"
                id="commitForm">
                <?= csrf_field() ?>

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

$content =
    ob_get_clean();

require ROOT_PATH
    . '/views/layouts/main.php';
