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

/* آدرس انتخاب‌شده در مرحله قبل */
$addressId = (int)($_SESSION['checkout_address_id'] ?? 0);
$address = AddressService::get($userId, $addressId);
if (!$address) {
    header('Location: /checkout-address');
    exit;
}

/* ─── ذخیره انتخاب ارسال ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $_POST['method'] ?? '';
    $slotId = $_POST['slot'] ?? '';
    try {
        /* پیش‌اعتبارسنجی سریع — اعتبارسنجی قطعی در commit */
        $options = ShippingService::getOptions($address['province']);
        if (!isset($options['methods'][$method])) {
            throw new RuntimeException('روش ارسال مجاز نیست');
        }
        $_SESSION['checkout_shipping'] = [
            'method' => $method,
            'slot'   => $slotId,
            'fee'    => $options['methods'][$method]['fee'],
        ];
        header('Location: /checkout-review');
        exit;
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

$options = ShippingService::getOptions($address['province']);

/* خلاصه سبد برای نمایش هزینه نهایی */
$stmt = $db->prepare("
    SELECT COALESCE(SUM(ci.qty * pr.price), 0) AS subtotal,
           COALESCE(SUM(ci.qty), 0) AS total_qty
    FROM cart_items ci
    JOIN carts c ON c.id = ci.cart_id
    LEFT JOIN product_prices pr ON pr.product_id = ci.product_id AND pr.customer_group='wholesale' AND pr.min_qty=1
    WHERE c.user_id = ? AND c.status='active'
");
$stmt->execute([$userId]);
$cart = $stmt->fetch();

$activeMenu = '';
$pageTitle = 'روش ارسال | رابطی';
ob_start();
?>

<nav class="breadcrumb">
    <a href="/">خانه</a> › <a href="/cart">سبد</a> ›
    <a href="/checkout-address">آدرس</a> › <span>ارسال</span>
</nav>

<section class="catalog-head">
    <h1 class="catalog-title">روش و زمان ارسال</h1>
    <p class="catalog-sub">سفارش به: <?= $h($address['province']) ?>، <?= $h($address['city']) ?></p>
</section>

<?php if (!empty($error)): ?>
    <div class="form-alert error" style="display:block;">⚠️ <?= $h($error) ?></div>
<?php endif; ?>

<form method="post" class="co-form shipping-form">

    <?php foreach ($options['methods'] as $mKey => $m): ?>
        <div class="ship-method-block">

            <label class="pay-method">
                <input type="radio" name="method" value="<?= $h($mKey) ?>" required>
                <span class="pay-method-body">
                    <b><?= $h($m['label']) ?> — <?= number_format((float)$m['fee']) ?> تومان</b>
                    <small>پنجره <?= count($m['days']) ?> روز آینده</small>
                </span>
            </label>

            <div class="ship-days" data-method="<?= $h($mKey) ?>" style="display:none;">
                <?php foreach ($m['days'] as $day): ?>
                    <div class="ship-day">
                        <div class="ship-day-label"><?= $h($day['label']) ?></div>
                        <div class="ship-slots">
                            <?php foreach ($day['slots'] as $slot): ?>
                                <label class="ship-slot <?= !$slot['available'] ? 'is-full' : '' ?>">
                                    <input type="radio" name="slot" value="<?= $h($slot['id']) ?>"
                                        <?= !$slot['available'] ? 'disabled' : '' ?>>
                                    <span><?= $h($slot['label']) ?></span>
                                    <?php if (!$slot['available']): ?>
                                        <em>تکمیل</em>
                                    <?php elseif ($slot['left'] <= 2): ?>
                                        <em class="almost"><?= $slot['left'] ?> ظرفیت باقی‌مانده</em>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    <?php endforeach; ?>

    <div class="cart-summary" style="margin-top:20px;">
        <div class="cart-summary-row">
            <span>جمع کالاها (<?= number_format((int)$cart['total_qty']) ?> جفت)</span>
            <b><?= number_format((float)$cart['subtotal']) ?> تومان</b>
        </div>
        <div class="cart-summary-row">
            <span>هزینه ارسال</span>
            <b id="shipFeeView">— انتخاب کنید</b>
        </div>
        <div class="cart-summary-row total">
            <span>مبلغ نهایی</span>
            <b id="totalView"><?= number_format((float)$cart['subtotal']) ?> تومان</b>
        </div>
        <button type="submit" class="btn btn-primary cart-checkout-btn">ادامه و مشاهده خلاصه ←</button>
    </div>

</form>

<script>
    // نمایش روزهای روش انتخابی + محاسبه هزینه
    document.querySelectorAll('input[name="method"]').forEach(r => {
        r.addEventListener('change', () => {
            document.querySelectorAll('.ship-days').forEach(d => d.style.display = 'none');
            const block = document.querySelector('.ship-days[data-method="' + r.value + '"]');
            if (block) block.style.display = 'block';

            const fee = Number(r.closest('.pay-method-body').querySelector('b').textContent.match(/([\d,]+)/)?.[1]?.replace(/,/g, '') || 0);
            const sub = <?= (int)$cart['subtotal'] ?>;
            document.getElementById('shipFeeView').textContent = fee.toLocaleString('fa-IR') + ' تومان';
            document.getElementById('totalView').textContent = (sub + fee).toLocaleString('fa-IR') + ' تومان';
        });
    });
</script>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/main.php';
