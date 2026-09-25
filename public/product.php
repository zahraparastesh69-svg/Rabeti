<?php

require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/* ─────────── خواندن محصول با slug ─────────── */

$slug = trim($_GET['slug'] ?? '');

$stmt = db()->prepare("
    SELECT
        p.*,
        c.name AS cat_name,
        c.slug AS cat_slug,
        inv.qty_available
    FROM products p
    JOIN categories c
        ON c.id = p.category_id
    LEFT JOIN inventory inv
        ON inv.product_id = p.id
    WHERE p.slug = ?
      AND p.is_active = 1
    LIMIT 1
");

$stmt->execute([$slug]);
$product = $stmt->fetch();


/* ─────────── محصول پیدا نشد ─────────── */

if (!$product) {

    http_response_code(404);

    $pageTitle = 'محصول یافت نشد';

    ob_start();
?>

    <div class="empty-state">
        <div class="empty-icon">🔍</div>

        <h2>این محصول پیدا نشد</h2>

        <a href="/products" class="btn btn-orange">
            مشاهده کاتالوگ
        </a>
    </div>

<?php

    $content = ob_get_clean();

    require ROOT_PATH . '/views/layouts/main.php';

    exit;
}


/* ─────────── قیمت‌ها ─────────── */

$stmt = db()->prepare("
    SELECT
        customer_group,
        min_qty,
        price
    FROM product_prices
    WHERE product_id = ?
    ORDER BY min_qty ASC
");

$stmt->execute([$product['id']]);

$prices = $stmt->fetchAll();


/* ─────────── محصولات مشابه ─────────── */

$stmt = db()->prepare("
    SELECT
        p.id,
        p.name,
        p.slug,
        p.code,
        pr.price,
        (
            SELECT pi.image_path
            FROM product_images pi
            WHERE pi.product_id = p.id
            ORDER BY pi.is_main DESC, pi.sort_order ASC
            LIMIT 1
        ) AS img
    FROM products p

    LEFT JOIN product_prices pr
        ON pr.product_id = p.id
       AND pr.customer_group = 'wholesale'

    WHERE p.category_id = ?
      AND p.id != ?
      AND p.is_active = 1

    LIMIT 4
");

$stmt->execute([
    $product['category_id'],
    $product['id']
]);

$related = $stmt->fetchAll();



/* ─────────── عکس اصلی ─────────── */

$stmt = db()->prepare("
    SELECT
        image_path
    FROM product_images
    WHERE product_id = ?
    ORDER BY is_main DESC, sort_order ASC
    LIMIT 1
");

$stmt->execute([$product['id']]);

$mainImage = $stmt->fetch();


/* ─────────── مشخصات محصول ─────────── */

$stmt = db()->prepare("
    SELECT
        spec_key,
        spec_value
    FROM product_specs
    WHERE product_id = ?
    ORDER BY sort_order, id
");

$stmt->execute([$product['id']]);

$specs = $stmt->fetchAll();


/* ─────────── وضعیت موجودی ─────────── */

$inStock = ((int)($product['qty_available'] ?? 0)) > 0;


/* ─────────── عنوان صفحه ─────────── */

$pageTitle = $product['name'] . ' | جوراب رابطی';


/* ─────────── شروع محتوای صفحه ─────────── */

ob_start();

?>


<!-- ▂▂▂ مسیر راهنما ▂▂▂ -->

<nav class="breadcrumb">

    <a href="/">خانه</a> ›

    <a href="/products">محصولات</a> ›

    <a href="/products?cat=<?= $h($product['cat_slug']) ?>">
        <?= $h($product['cat_name']) ?>
    </a> ›

    <span>
        <?= $h($product['name']) ?>
    </span>

</nav>


<!-- ▂▂▂ محصول ▂▂▂ -->

<section class="product-detail">


    <!-- ▂▂▂ ستون سمت راست: تصویر + سفارش ▂▂▂ -->

    <div class="pd-gallery">

        <div class="pd-main-img premium-visual">

            <?php if ($mainImage && !empty($mainImage['image_path'])): ?>

                <img
                    src="<?= $h($mainImage['image_path']) ?>"
                    alt="<?= $h($product['name']) ?>"
                    loading="eager">

            <?php else: ?>

                <!-- <div class="product-surface"></div> -->

                <!-- <span class="product-sock">🧦</span> -->

            <?php endif; ?>

        </div>


        <?php if (!$mainImage || empty($mainImage['image_path'])): ?>

            <div class="pd-note">
                📷 عکس واقعی به‌زودی
            </div>

        <?php endif; ?>


        <!-- ▂▂▂ انتخاب تعداد — دقیقاً زیر عکس ▂▂▂ -->

        <div
            class="stepper"
            data-min="<?= max(1, (int)$product['base_moq']) ?>"
            style="max-width:220px; margin:16px auto 0;">

            <button
                type="button"
                class="step plus">
                +
            </button>

            <input
                type="text"
                inputmode="numeric"
                class="r6-num step-input"
                value="<?= max(1, (int)$product['base_moq']) ?>">

            <button
                type="button"
                class="step minus">
                −
            </button>

        </div>



        <!-- ▂▂▂ عملیات محصول — زیر عکس ▂▂▂ -->

        <div class="pd-actions">

            <?php if ($inStock): ?>

                <button
                    type="button"
                    class="r6-add pd-order-btn"
                    data-product-id="<?= (int)$product['id'] ?>">
                    افزودن به سبد سفارش
                </button>

            <?php else: ?>

                <button
                    type="button"
                    class="btn pd-disabled"
                    disabled>
                    فعلاً ناموجود
                </button>

            <?php endif; ?>


            <a
                href="/custom-order"
                class="pd-link">
                درخواست تولید با برند اختصاصی ←
            </a>

        </div>

    </div>


    <!-- ▂▂▂ ستون سمت چپ: اطلاعات محصول ▂▂▂ -->

    <div class="pd-info">

        <div class="product-meta-line" style="margin-bottom:8px;">

            <span>
                کد <?= $h($product['code']) ?>
            </span>

            <span class="stock-dot">
                <?= $inStock ? 'موجود' : 'ناموجود' ?>
            </span>

        </div>


        <h1 class="pd-title">
            <?= $h($product['name']) ?>
        </h1>


        <div class="pd-desc">

            <?= nl2br(
                $h(
                    $product['description']
                        ?? 'این محصول مستقیماً از خط تولید کارخانه عرضه می‌شود. برای دریافت نمونه و مذاکره قیمت با ما در تماس باشید.'
                )
            ) ?>

        </div>


        <!-- ▂▂▂ مشخصات ساختاریافته ▂▂▂ -->

        <?php if ($specs): ?>

            <div class="pd-specs-box">

                <div class="pd-price-title">
                    مشخصات:
                </div>

                <ul class="pdp-bullets">

                    <?php foreach ($specs as $s): ?>

                        <li>
                            <b><?= $h($s['spec_key']) ?>:</b>
                            <?= $h($s['spec_value']) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <!-- ▂▂▂ قیمت ▂▂▂ -->

        <div class="pd-price-box">

            <div class="pd-price-title">
                قیمت همکاری
            </div>


            <?php if ($prices): ?>

                <table class="pd-price-table">

                    <thead>

                        <tr>
                            <th>تعداد</th>
                            <th>قیمت واحد</th>
                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach ($prices as $row): ?>

                            <tr>

                                <td>
                                    <?= $row['min_qty'] > 1
                                        ? 'از ' . number_format($row['min_qty']) . ' جفت'
                                        : 'شروع همکاری'
                                    ?>
                                </td>

                                <td class="pd-price-cell">
                                    <?= number_format((float)$row['price']) ?>
                                    تومان
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <div class="pd-no-price">

                    قیمت به‌زودی اعلام می‌شود —

                    تماس:
                    <span dir="ltr">
                        0912-1234567
                    </span>

                </div>

            <?php endif; ?>

        </div>


        <!-- ▂▂▂ مشخصات محصول ▂▂▂ -->

        <div class="pd-specs">

            <div class="pd-spec">

                <span>
                    حداقل سفارش (MOQ)
                </span>

                <b>
                    <?= (int)$product['base_moq'] ?> جفت
                </b>

            </div>


            <div class="pd-spec">

                <span>
                    دسته
                </span>

                <b>
                    <?= $h($product['cat_name']) ?>
                </b>

            </div>


            <div class="pd-spec">

                <span>
                    موجودی انبار
                </span>

                <b>
                    <?= number_format(
                        (int)($product['qty_available'] ?? 0)
                    ) ?>
                    جفت
                </b>

            </div>


            <div class="pd-spec">

                <span>
                    ارسال
                </span>

                <b>
                    سراسر کشور — ۲۴ تا ۷۲ ساعت
                </b>

            </div>

        </div>

    </div>

</section>


<!-- ▂▂▂ محصولات مشابه ▂▂▂ -->

<?php if ($related): ?>

    <section class="section">

        <h2 class="section-title">
            محصولات مشابه
        </h2>


        <div
            class="premium-product-grid"
            style="margin-top:20px;">

            <?php foreach ($related as $r): ?>

                <a
                    href="/product/<?= $h($r['slug']) ?>"
                    class="premium-product-card">

                    <div class="product-visual">

                        <?php if (!empty($r['img'])): ?>

                            <img
                                src="<?= $h($r['img']) ?>"
                                alt="<?= $h($r['name']) ?>"
                                loading="lazy">

                        <?php else: ?>

                            <div class="product-surface"></div>

                            <div class="product-sock">
                                🧦
                            </div>

                            <div class="product-shadow"></div>

                        <?php endif; ?>

                    </div>



                    <div class="premium-product-info">

                        <div class="product-meta-line">

                            <span>
                                کد <?= $h($r['code']) ?>
                            </span>

                        </div>


                        <h3>
                            <?= $h($r['name']) ?>
                        </h3>


                        <div class="product-bottom-line">

                            <div>

                                <small>
                                    قیمت همکاری
                                </small>


                                <strong>

                                    <?= $r['price'] !== null
                                        ? number_format((float)$r['price']) . ' تومان'
                                        : 'استعلام'
                                    ?>

                                </strong>

                            </div>

                        </div>

                    </div>

                </a>

            <?php endforeach; ?>

        </div>

    </section>

<?php endif; ?>


<?php

$content = ob_get_clean();

require ROOT_PATH . '/views/layouts/main.php';
