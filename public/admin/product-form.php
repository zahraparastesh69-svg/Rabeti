<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';
require_once ROOT_PATH . '/config/upload.php';

require_admin();

/* ============================================================
 * فیلدهای مشخصات محصول
 * ============================================================ */
$specFields = [
    'نوع جوراب',
    'جنس',
    'طرح',
    'مناسب برای',
    'تعداد در بسته',
    'رنگ',
    'دوخت پنجه',
    'پاشنه و پنجه',
    'وسط‌کش',
    'سایز',
];

$action    = ($_GET['action'] ?? 'new') === 'edit' ? 'edit' : 'new';
$productId = (int)($_GET['id'] ?? 0);

$errors = [];
$currentSpecs = [];
$priceTiers = [];

/* ============================================================
 * مقدارهای پیش‌فرض
 * ============================================================ */
$product = [
    'code'        => '',
    'name'        => '',
    'slug'        => '',
    'description' => '',
    'base_moq'    => '48',
    'category_id' => '',
];

$price = 0;
$qty = 0;
$currentImage = null;
$discountEnabled = false;

/* ============================================================
 * ویرایش محصول
 * ============================================================ */
if ($action === 'edit' && $productId > 0) {

    $stmt = db()->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        header('Location: /admin/products');
        exit;
    }

    /*
     * قیمت پایه
     * همیشه min_qty = 1 قیمت اصلی همکاری است.
     */
    $stmt = db()->prepare("
        SELECT price
        FROM product_prices
        WHERE product_id = ?
          AND customer_group = 'wholesale'
          AND min_qty = 1
        LIMIT 1
    ");
    $stmt->execute([$productId]);
    $price = (int)($stmt->fetchColumn() ?: 0);

    /*
     * قیمت‌های پلکانی
     * تمام ردیف‌های بعد از min_qty=1 پله تخفیف هستند.
     */
    $stmt = db()->prepare("
        SELECT min_qty, price
        FROM product_prices
        WHERE product_id = ?
          AND customer_group = 'wholesale'
          AND min_qty > 1
        ORDER BY min_qty ASC
    ");
    $stmt->execute([$productId]);

    $tierRows = $stmt->fetchAll();

    foreach ($tierRows as $row) {
        $tierMinQty = (int)$row['min_qty'];
        $tierPrice  = (int)$row['price'];

        if ($price > 0 && $tierPrice > 0 && $tierPrice < $price) {
            $discountPercent = round((1 - ($tierPrice / $price)) * 100, 2);
        } else {
            $discountPercent = 0;
        }

        $priceTiers[] = [
            'min_qty' => $tierMinQty,
            'percent' => $discountPercent,
            'price'   => $tierPrice,
        ];
    }

    $discountEnabled = !empty($priceTiers);

    /* موجودی */
    $stmt = db()->prepare("
        SELECT qty_available
        FROM inventory
        WHERE product_id = ?
        LIMIT 1
    ");
    $stmt->execute([$productId]);
    $qty = (int)($stmt->fetchColumn() ?: 0);

    /* تصویر اصلی */
    $stmt = db()->prepare("
        SELECT image_path
        FROM product_images
        WHERE product_id = ?
          AND is_main = 1
        LIMIT 1
    ");
    $stmt->execute([$productId]);
    $currentImage = $stmt->fetchColumn() ?: null;

    /* مشخصات */
    $stmt = db()->prepare("
        SELECT spec_key, spec_value
        FROM product_specs
        WHERE product_id = ?
    ");
    $stmt->execute([$productId]);

    foreach ($stmt->fetchAll() as $s) {
        $currentSpecs[$s['spec_key']] = $s['spec_value'];
    }
}

/* ============================================================
 * ثبت فرم
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $code        = strtoupper(trim($_POST['code'] ?? ''));
    $name        = trim($_POST['name'] ?? '');
    $slug        = trim($_POST['slug'] ?? '');
    $slugEn      = trim($_POST['slug_en'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $baseMoq    = max(1, (int)($_POST['base_moq'] ?? 1));
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $price      = max(0, (int)($_POST['price'] ?? 0));
    $qty        = max(0, (int)($_POST['qty'] ?? 0));

    /*
     * فعال بودن تخفیف پلکانی
     */
    $discountEnabled = isset($_POST['discount_enabled']) && $_POST['discount_enabled'] === '1';

    /*
     * پله‌های تخفیف
     */
    $tierQtyInput = $_POST['discount_qty'] ?? [];
    $tierPercentInput = $_POST['discount_percent'] ?? [];

    $tierQtyInput = is_array($tierQtyInput) ? $tierQtyInput : [];
    $tierPercentInput = is_array($tierPercentInput) ? $tierPercentInput : [];

    $priceTiers = [];

    /* ========================================================
     * مشخصات
     * ======================================================== */
    $specs = [];

    foreach ($specFields as $key) {
        $v = trim($_POST['spec_' . md5($key)] ?? '');

        if ($v !== '') {
            $specs[] = [$key, $v];
        }
    }

    /* ========================================================
     * اعتبارسنجی اصلی
     * ======================================================== */
    if (!preg_match('/^S\d{3}$/', $code)) {
        $errors['code'] = 'کد باید مثل S125 باشد';
    }

    if (mb_strlen($name) < 3) {
        $errors['name'] = 'نام محصول را کامل وارد کنید';
    }

    if ($categoryId < 1) {
        $errors['category_id'] = 'دسته را انتخاب کنید';
    }

    if ($price < 1000) {
        $errors['price'] = 'قیمت را به تومان و بدون نقطه وارد کنید';
    }

    /* ========================================================
     * ساخت Slug
     * ======================================================== */
    if (!$errors && $slug === '') {

        $base = preg_replace(
            '/[^a-z0-9]+/',
            '-',
            strtolower($slugEn !== '' ? $slugEn : $code)
        );

        $base = trim($base, '-');

        if ($base === '') {
            $base = strtolower($code);
        }

        $slug = $base . '-' . strtolower($code);
    }

    /* ========================================================
     * بررسی یکتا بودن کد
     * ======================================================== */
    if (!$errors) {

        $stmt = db()->prepare("
            SELECT id
            FROM products
            WHERE code = ?
              AND id != ?
            LIMIT 1
        ");

        $stmt->execute([$code, $productId]);

        if ($stmt->fetch()) {
            $errors['code'] = 'این کد قبلاً استفاده شده است';
        }
    }

    /* ========================================================
     * پردازش پله‌های تخفیف
     *
     * منطق:
     * - پله اول باید حداقل برابر MOQ باشد.
     * - تعدادها باید صعودی باشند.
     * - درصد بین 0 و کمتر از 100 باشد.
     * - قیمت محاسبه‌شده باید حداقل 1 تومان باشد.
     * ======================================================== */
    if (!$errors && $discountEnabled) {

        $lastQty = 0;
        $rowCount = max(count($tierQtyInput), count($tierPercentInput));

        for ($i = 0; $i < $rowCount; $i++) {

            $tierQty = (int)($tierQtyInput[$i] ?? 0);
            $percentRaw = trim((string)($tierPercentInput[$i] ?? ''));

            /*
             * ردیف کاملاً خالی را نادیده می‌گیریم.
             */
            if ($tierQty === 0 && $percentRaw === '') {
                continue;
            }

            $percent = (float)str_replace(',', '.', $percentRaw);

            if ($tierQty < $baseMoq) {
                $errors['discount'] =
                    'تعداد شروع هر تخفیف نباید کمتر از حداقل سفارش (MOQ) باشد';
                break;
            }

            if ($tierQty <= $lastQty) {
                $errors['discount'] =
                    'تعداد شروع پله‌های تخفیف باید به ترتیب صعودی باشد';
                break;
            }

            if ($percent <= 0 || $percent >= 100) {
                $errors['discount'] =
                    'درصد تخفیف باید بیشتر از صفر و کمتر از ۱۰۰ باشد';
                break;
            }

            /*
             * محاسبه قیمت نهایی.
             *
             * قیمت به نزدیک‌ترین 100 تومان گرد می‌شود
             * تا قیمت‌های نامتعارف مثل 47,375 ایجاد نشود.
             */
            $calculatedPrice = (int)round(
                $price * (1 - ($percent / 100)),
                -2
            );

            if ($calculatedPrice < 1) {
                $errors['discount'] =
                    'درصد تخفیف باعث ایجاد قیمت نامعتبر می‌شود';
                break;
            }

            /*
             * قیمت تخفیفی باید کمتر از قیمت پایه باشد.
             */
            if ($calculatedPrice >= $price) {
                $errors['discount'] =
                    'قیمت پس از تخفیف باید کمتر از قیمت پایه باشد';
                break;
            }

            $priceTiers[] = [
                'min_qty' => $tierQty,
                'percent' => $percent,
                'price'   => $calculatedPrice,
            ];

            $lastQty = $tierQty;
        }

        /*
         * اگر تخفیف فعال شده ولی هیچ پله‌ای وارد نشده،
         * آن را خطا می‌گیریم تا وضعیت مبهم ایجاد نشود.
         */
        if (!$errors && !$priceTiers) {
            $errors['discount'] =
                'حداقل یک پله تخفیف تعریف کنید یا تخفیف پلکانی را غیرفعال کنید';
        }
    }

    /* ========================================================
     * ذخیره
     * ======================================================== */
    if (!$errors) {

        $db = db();

        try {

            $db->beginTransaction();

            /* ------------------------------------------------
             * محصول
             * ------------------------------------------------ */
            if ($action === 'new') {

                $db->prepare("
                    INSERT INTO products
                        (category_id, code, name, slug, description, base_moq)
                    VALUES
                        (?, ?, ?, ?, ?, ?)
                ")->execute([
                    $categoryId,
                    $code,
                    $name,
                    $slug,
                    $description ?: null,
                    $baseMoq
                ]);

                $productId = (int)$db->lastInsertId();
            } else {

                $db->prepare("
                    UPDATE products
                    SET
                        category_id = ?,
                        code = ?,
                        name = ?,
                        slug = ?,
                        description = ?,
                        base_moq = ?
                    WHERE id = ?
                ")->execute([
                    $categoryId,
                    $code,
                    $name,
                    $slug,
                    $description ?: null,
                    $baseMoq,
                    $productId
                ]);
            }

            /* ------------------------------------------------
             * قیمت‌ها
             *
             * تمام قیمت‌های عمده قبلی این محصول حذف و
             * ساختار جدید از نو ذخیره می‌شود.
             *
             * این کار باعث می‌شود پله حذف‌شده از فرم
             * در دیتابیس باقی نماند.
             * ------------------------------------------------ */
            $db->prepare("
                DELETE FROM product_prices
                WHERE product_id = ?
                  AND customer_group = 'wholesale'
            ")->execute([$productId]);

            /*
             * قیمت پایه
             */
            $db->prepare("
                INSERT INTO product_prices
                    (product_id, customer_group, min_qty, price)
                VALUES
                    (?, 'wholesale', 1, ?)
            ")->execute([
                $productId,
                $price
            ]);

            /*
             * پله‌های تخفیف
             */
            foreach ($priceTiers as $tier) {

                $db->prepare("
                    INSERT INTO product_prices
                        (product_id, customer_group, min_qty, price)
                    VALUES
                        (?, 'wholesale', ?, ?)
                ")->execute([
                    $productId,
                    $tier['min_qty'],
                    $tier['price']
                ]);
            }

            /* ------------------------------------------------
             * موجودی
             * ------------------------------------------------ */
            $db->prepare("
                INSERT INTO inventory
                    (product_id, qty_available)
                VALUES
                    (?, ?)
                ON DUPLICATE KEY UPDATE
                    qty_available = VALUES(qty_available)
            ")->execute([
                $productId,
                $qty
            ]);

            /* ------------------------------------------------
             * مشخصات
             * ------------------------------------------------ */
            $db->prepare("
                DELETE FROM product_specs
                WHERE product_id = ?
            ")->execute([$productId]);

            if ($specs) {

                $insSpec = $db->prepare("
                    INSERT INTO product_specs
                        (product_id, spec_key, spec_value, sort_order)
                    VALUES
                        (?, ?, ?, ?)
                ");

                foreach ($specs as $i => $sv) {

                    $insSpec->execute([
                        $productId,
                        $sv[0],
                        $sv[1],
                        $i
                    ]);
                }
            }

            $db->commit();
        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $errors['general'] =
                'ذخیره اطلاعات محصول انجام نشد. لطفاً دوباره تلاش کنید.';
        }

        /* ----------------------------------------------------
         * آپلود تصویر
         *
         * خارج از transaction دیتابیس انجام می‌شود.
         * ---------------------------------------------------- */
        if (!$errors) {

            $uploadFailed = false;

            if (!empty($_FILES['image']['name'])) {

                $up = upload_image_ex(
                    $_FILES['image'],
                    'products'
                );

                if ($up['ok']) {

                    $db->prepare("
                        UPDATE product_images
                        SET is_main = 0
                        WHERE product_id = ?
                    ")->execute([$productId]);

                    $db->prepare("
                        INSERT INTO product_images
                            (product_id, image_path, is_main)
                        VALUES
                            (?, ?, 1)
                    ")->execute([
                        $productId,
                        $up['path']
                    ]);

                    $currentImage = $up['path'];
                } else {

                    $errors['image'] =
                        'آپلود ناموفق: ' . $up['error'];

                    $uploadFailed = true;
                }
            }

            if (!$uploadFailed) {
                header('Location: /admin/products');
                exit;
            }
        }
    }

    /* ========================================================
     * حفظ مقادیر فرم هنگام خطا
     * ======================================================== */
    $product = array_merge($product, [
        'code'        => $code,
        'name'        => $name,
        'slug'        => $slug,
        'description' => $description,
        'base_moq'    => $baseMoq,
        'category_id' => $categoryId,
    ]);
}

/* ============================================================
 * دسته‌بندی‌ها
 * ============================================================ */
$categories = db()->query("
    SELECT id, name
    FROM categories
    WHERE is_active = 1
    ORDER BY sort_order
")->fetchAll();

$activeMenu = 'products';

$pageTitle = (
    $action === 'edit'
    ? 'ویرایش محصول'
    : 'محصول جدید'
);

ob_start();
?>

<div style="margin-bottom:16px;">
    <a
        href="/admin/products"
        style="color:var(--teal-700);font-size:.82rem;">
        ← بازگشت به لیست محصولات
    </a>
</div>

<form
    method="post"
    class="co-form"
    style="max-width:100%;"
    enctype="multipart/form-data">

    <?php if ($errors): ?>

        <div class="form-alert error" style="display:block;">

            ⚠️

            <?php foreach ($errors as $er): ?>

                <?= htmlspecialchars($er, ENT_QUOTES, 'UTF-8') ?><br>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         مشخصات اصلی
         ===================================================== -->

    <div class="form-row">

        <div class="form-field">

            <label>
                کد محصول <i>*</i>
            </label>

            <input
                type="text"
                name="code"
                dir="ltr"
                value="<?= htmlspecialchars($product['code'], ENT_QUOTES, 'UTF-8') ?>"
                placeholder="S125"
                maxlength="4">

            <?php if (isset($errors['code'])): ?>

                <span class="field-error">
                    <?= htmlspecialchars($errors['code'], ENT_QUOTES, 'UTF-8') ?>
                </span>

            <?php endif; ?>

        </div>


        <div class="form-field">

            <label>
                دسته‌بندی <i>*</i>
            </label>

            <select name="category_id">

                <option value="">
                    — انتخاب کنید —
                </option>

                <?php foreach ($categories as $c): ?>

                    <option
                        value="<?= (int)$c['id'] ?>"
                        <?= $product['category_id'] == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <?php if (isset($errors['category_id'])): ?>

                <span class="field-error">
                    <?= htmlspecialchars($errors['category_id'], ENT_QUOTES, 'UTF-8') ?>
                </span>

            <?php endif; ?>

        </div>

    </div>


    <div class="form-field">

        <label>
            نام محصول <i>*</i>
        </label>

        <input
            type="text"
            name="name"
            value="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
            placeholder="مثلاً: جوراب نخی مردانه ساق‌بلند">

        <?php if (isset($errors['name'])): ?>

            <span class="field-error">
                <?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?>
            </span>

        <?php endif; ?>

    </div>


    <div class="form-row">

        <div class="form-field">

            <label>
                نامک انگلیسی (اختیاری)
            </label>

            <input
                type="text"
                name="slug_en"
                dir="ltr"
                value=""
                placeholder="m-cotton-long">

            <span class="field-hint">
                اگر خالی بگذارید، نامک از کد محصول ساخته می‌شود.
            </span>

        </div>


        <div class="form-field">

            <label>
                حداقل سفارش (MOQ) <i>*</i>
            </label>

            <input
                type="number"
                name="base_moq"
                dir="ltr"
                value="<?= htmlspecialchars($product['base_moq'], ENT_QUOTES, 'UTF-8') ?>"
                min="1">

            <span class="field-hint">
                کمترین تعداد قابل سفارش این محصول.
            </span>

        </div>

    </div>


    <!-- =====================================================
         قیمت پایه و موجودی
         ===================================================== -->

    <div class="form-row">

        <div class="form-field">

            <label>
                قیمت همکاری پایه (تومان) <i>*</i>
            </label>

            <input
                type="number"
                name="price"
                dir="ltr"
                value="<?= (int)$price ?>"
                min="0"
                step="100">

            <?php if (isset($errors['price'])): ?>

                <span class="field-error">
                    <?= htmlspecialchars($errors['price'], ENT_QUOTES, 'UTF-8') ?>
                </span>

            <?php endif; ?>

            <span class="field-hint">
                این قیمت برای اولین سطح خرید است و به عنوان قیمت پایه همکاری ذخیره می‌شود.
            </span>

        </div>


        <div class="form-field">

            <label>
                موجودی انبار (جفت)
            </label>

            <input
                type="number"
                name="qty"
                dir="ltr"
                value="<?= (int)$qty ?>"
                min="0"
                step="1">

            <span class="field-hint">
                موجودی واقعی قابل فروش.
            </span>

        </div>

    </div>


    <!-- =====================================================
         قیمت پلکانی
         ===================================================== -->

    <div
        class="form-field"
        style="
            margin-top:8px;
            padding:18px;
            border:1px solid var(--card-border);
            border-radius:16px;
        ">

        <label style="display:block;margin-bottom:8px;">
            قیمت‌گذاری پلکانی
        </label>

        <span class="field-hint" style="display:block;margin-bottom:14px;">
            اگر خرید تعداد بیشتری داشته باشد، می‌توانید برای آن درصد تخفیف تعریف کنید.
            قیمت نهایی هر پله به‌صورت خودکار از قیمت پایه محاسبه می‌شود.
        </span>


        <label
            style="
                display:flex;
                align-items:center;
                gap:8px;
                cursor:pointer;
                margin-bottom:14px;
            ">

            <input
                type="checkbox"
                name="discount_enabled"
                value="1"
                id="discountEnabled"
                <?= $discountEnabled ? 'checked' : '' ?>>

            <span>
                این محصول دارای تخفیف پلکانی است
            </span>

        </label>


        <div
            id="discountTiersBox"
            style="<?= $discountEnabled ? '' : 'display:none;' ?>">

            <div
                style="
                    display:grid;
                    grid-template-columns:1fr 1fr auto;
                    gap:10px;
                    align-items:center;
                    margin-bottom:8px;
                ">

                <strong>
                    شروع از تعداد
                </strong>

                <strong>
                    درصد تخفیف
                </strong>

                <span></span>

            </div>


            <div id="discountTiers">

                <?php if ($priceTiers): ?>

                    <?php foreach ($priceTiers as $tier): ?>

                        <div
                            class="discount-tier"
                            style="
                                display:grid;
                                grid-template-columns:1fr 1fr auto;
                                gap:10px;
                                align-items:center;
                                margin-bottom:10px;
                            ">

                            <input
                                type="number"
                                name="discount_qty[]"
                                value="<?= (int)$tier['min_qty'] ?>"
                                min="<?= (int)$product['base_moq'] ?>"
                                step="1"
                                placeholder="مثلاً 24">

                            <input
                                type="number"
                                name="discount_percent[]"
                                value="<?= htmlspecialchars((string)$tier['percent'], ENT_QUOTES, 'UTF-8') ?>"
                                min="0.01"
                                max="99.99"
                                step="0.01"
                                placeholder="مثلاً 5">

                            <button
                                type="button"
                                class="btn discount-remove"
                                style="white-space:nowrap;">
                                حذف
                            </button>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div
                        class="discount-tier"
                        style="
                            display:grid;
                            grid-template-columns:1fr 1fr auto;
                            gap:10px;
                            align-items:center;
                            margin-bottom:10px;
                        ">

                        <input
                            type="number"
                            name="discount_qty[]"
                            value=""
                            min="<?= (int)$product['base_moq'] ?>"
                            step="1"
                            placeholder="مثلاً 24">

                        <input
                            type="number"
                            name="discount_percent[]"
                            value=""
                            min="0.01"
                            max="99.99"
                            step="0.01"
                            placeholder="مثلاً 5">

                        <button
                            type="button"
                            class="btn discount-remove"
                            style="white-space:nowrap;">
                            حذف
                        </button>

                    </div>

                <?php endif; ?>

            </div>


            <button
                type="button"
                id="addDiscountTier"
                class="btn"
                style="margin-top:4px;">
                + افزودن پله تخفیف
            </button>


            <div
                style="
                    margin-top:14px;
                    padding:12px 14px;
                    border-radius:12px;
                    background:var(--card-bg, #f7f7f7);
                    font-size:.82rem;
                    line-height:1.9;
                ">

                <strong>نحوه محاسبه:</strong>

                <div>
                    مثال: قیمت پایه ۵۰٬۰۰۰ تومان و تخفیف ۱۰٪
                    →
                    قیمت این پله ۴۵٬۰۰۰ تومان می‌شود.
                </div>

                <div>
                    پله‌های تخفیف باید از MOQ بزرگ‌تر یا مساوی باشند و تعداد آن‌ها
                    به ترتیب افزایشی وارد شود.
                </div>

            </div>

        </div>


        <?php if (isset($errors['discount'])): ?>

            <span
                class="field-error"
                style="display:block;margin-top:10px;">
                <?= htmlspecialchars($errors['discount'], ENT_QUOTES, 'UTF-8') ?>
            </span>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         تصویر
         ===================================================== -->

    <div class="form-field">

        <label>
            عکس محصول
        </label>

        <?php if ($currentImage): ?>

            <img
                src="<?= htmlspecialchars($currentImage, ENT_QUOTES, 'UTF-8') ?>"
                alt="تصویر فعلی"
                style="
                    max-width:180px;
                    border-radius:14px;
                    margin-bottom:10px;
                    display:block;
                    border:1px solid var(--card-border);
                ">

            <span class="field-hint">
                تصویر فعلی بالا است — اگر فایل جدید انتخاب کنید جایگزین می‌شود.
            </span>

        <?php endif; ?>


        <input
            type="file"
            name="image"
            accept="image/jpeg,image/png,image/webp">

        <span class="field-hint">
            JPG/PNG/WebP — حداکثر ۲ مگابایت — ترجیحاً مربع
        </span>

        <?php if (isset($errors['image'])): ?>

            <span class="field-error">
                <?= htmlspecialchars($errors['image'], ENT_QUOTES, 'UTF-8') ?>
            </span>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         توضیحات
         ===================================================== -->

    <div class="form-field">

        <label>
            توضیحات
        </label>

        <textarea
            name="description"
            rows="6"
            placeholder="معرفی کوتاه و دقیق محصول..."><?= htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

    </div>


    <!-- =====================================================
         مشخصات
         ===================================================== -->

    <div class="form-field">

        <label>
            مشخصات محصول
        </label>

        <span class="field-hint">
            فیلدهای خالی نادیده گرفته می‌شوند و در صفحه محصول به‌صورت لیست نمایش داده می‌شوند.
        </span>

    </div>


    <?php foreach ($specFields as $key): ?>

        <div class="form-field">

            <label>
                <?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>
            </label>

            <input
                type="text"
                name="spec_<?= md5($key) ?>"
                value="<?= htmlspecialchars($currentSpecs[$key] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        </div>

    <?php endforeach; ?>


    <!-- =====================================================
         دکمه ذخیره
         ===================================================== -->

    <button
        type="submit"
        class="btn btn-orange co-submit">
        <?= $action === 'edit' ? 'ذخیره تغییرات' : 'افزودن محصول' ?>
        →
    </button>

</form>


<script>
    (function() {

        const enabled = document.getElementById('discountEnabled');
        const box = document.getElementById('discountTiersBox');
        const tiers = document.getElementById('discountTiers');
        const addBtn = document.getElementById('addDiscountTier');

        if (!enabled || !box || !tiers || !addBtn) {
            return;
        }

        function updateVisibility() {
            box.style.display = enabled.checked ? '' : 'none';
        }

        enabled.addEventListener('change', updateVisibility);


        function createTier() {

            const row = document.createElement('div');

            row.className = 'discount-tier';

            row.style.cssText = `
            display:grid;
            grid-template-columns:1fr 1fr auto;
            gap:10px;
            align-items:center;
            margin-bottom:10px;
        `;

            row.innerHTML = `
            <input
                type="number"
                name="discount_qty[]"
                min="<?= (int)$product['base_moq'] ?>"
                step="1"
                placeholder="مثلاً 48"
            >

            <input
                type="number"
                name="discount_percent[]"
                min="0.01"
                max="99.99"
                step="0.01"
                placeholder="مثلاً 10"
            >

            <button
                type="button"
                class="btn discount-remove"
                style="white-space:nowrap;"
            >
                حذف
            </button>
        `;

            tiers.appendChild(row);
        }


        addBtn.addEventListener('click', function() {
            createTier();
        });


        tiers.addEventListener('click', function(event) {

            const removeBtn = event.target.closest('.discount-remove');

            if (!removeBtn) {
                return;
            }

            const rows = tiers.querySelectorAll('.discount-tier');

            /*
             * اگر فقط یک ردیف وجود دارد،
             * آن را حذف نمی‌کنیم؛ فقط خالی می‌کنیم.
             */
            if (rows.length === 1) {

                const row = rows[0];

                row.querySelectorAll('input').forEach(function(input) {
                    input.value = '';
                });

                return;
            }

            removeBtn.closest('.discount-tier')?.remove();
        });


        updateVisibility();

    })();
</script>


<?php
$content = ob_get_clean();

require ROOT_PATH . '/views/layouts/admin.php';
