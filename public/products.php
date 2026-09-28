<!-- products.php -->
<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$catSlug   = trim($_GET['cat'] ?? '');
$activeCat = null;

/* ============================================================
 * دسته‌بندی‌ها
 * ============================================================ */
$categories = db()->query("
    SELECT *
    FROM categories
    WHERE is_active = 1
    ORDER BY sort_order
")->fetchAll();

/* ============================================================
 * دسته فعال
 * ============================================================ */
if ($catSlug !== '') {

    $stmt = db()->prepare("
        SELECT *
        FROM categories
        WHERE slug = ?
          AND is_active = 1
        LIMIT 1
    ");

    $stmt->execute([$catSlug]);
    $activeCat = $stmt->fetch();

    if (!$activeCat) {

        http_response_code(404);

        $pageTitle = 'دسته یافت نشد';

        ob_start();
?>

        <div class="empty-state">

            <h2>این دسته پیدا نشد</h2>

            <a
                href="/products"
                class="btn btn-orange">
                همه محصولات
            </a>

        </div>

<?php
        $content = ob_get_clean();

        require ROOT_PATH . '/views/layouts/main.php';
        exit;
    }
}

/* ============================================================
 * محصولات
 *
 * نکته مهم:
 *
 * product_prices دیگر JOIN نشده است.
 *
 * چون حالا یک محصول می‌تواند چند قیمت داشته باشد،
 * JOIN مستقیم باعث Duplicate شدن محصول می‌شود.
 *
 * اینجا فقط قیمت پایه min_qty=1 را می‌گیریم.
 * ============================================================ */

$sql = "
    SELECT
        p.id,
        p.code,
        p.name,
        p.slug,
        p.base_moq,

        (
            SELECT pp.price
            FROM product_prices pp
            WHERE pp.product_id = p.id
              AND pp.customer_group = 'wholesale'
              AND pp.min_qty = 1
            ORDER BY pp.id DESC
            LIMIT 1
        ) AS price,

        COALESCE(inv.qty_available, 0) AS qty_available,

        c.name AS cat_name,

        (
            SELECT image_path
            FROM product_images pi
            WHERE pi.product_id = p.id
            ORDER BY pi.is_main DESC, pi.sort_order ASC
            LIMIT 1
        ) AS img

    FROM products p

    JOIN categories c
        ON c.id = p.category_id

    LEFT JOIN inventory inv
        ON inv.product_id = p.id

    WHERE p.is_active = 1
";

$params = [];

if ($activeCat) {

    $sql .= "
        AND p.category_id = ?
    ";

    $params[] = $activeCat['id'];
}

$sql .= "
    ORDER BY p.id DESC
    LIMIT 60
";

$stmt = db()->prepare($sql);
$stmt->execute($params);

$products = $stmt->fetchAll();

/* ============================================================
 * عنوان صفحه
 * ============================================================ */
$pageTitle =
    ($activeCat ? $activeCat['name'] . ' — ' : '')
    . 'کاتالوگ محصولات | رابطی';

ob_start();
?>

<!-- =========================================================
     سربرگ
     ========================================================= -->

<section class="page-head">

    <h1 class="section-title">
        <?= $activeCat
            ? $h($activeCat['name'])
            : 'همه محصولات'
        ?>
    </h1>

    <p class="section-sub">
        <?= count($products) ?> مدل
        — قیمت همکاری، مستقیم از تولیدی
    </p>

</section>


<!-- =========================================================
     مسیر راهنما
     ========================================================= -->

<nav class="breadcrumb">

    <a href="/">
        خانه
    </a>

    ›

    <a href="/products">
        محصولات
    </a>

    <?php if ($activeCat): ?>

        ›

        <span>
            <?= $h($activeCat['name']) ?>
        </span>

    <?php endif; ?>

</nav>


<!-- =========================================================
     گرید محصولات
     ========================================================= -->

<?php if ($products): ?>

    <section class="prod-grid">

        <?php foreach ($products as $index => $p): ?>

            <?php
            $stock = (int)($p['qty_available'] ?? 0);

            /*
             * MOQ واقعی محصول
             */
            $moq = max(1, (int)($p['base_moq'] ?? 1));

            /*
             * اگر موجودی کمتر از MOQ باشد،
             * محصول عملاً قابل سفارش نیست.
             */
            $canOrder = $stock >= $moq;
            ?>

            <article
                class="prod-card card"
                data-product-id="<?= (int)$p['id'] ?>">

                <!-- =================================================
                     تصویر
                     ================================================= -->

                <a
                    href="/product/<?= $h($p['slug']) ?>"
                    class="prod-img">

                    <?php if ($index === 0 && !$activeCat): ?>

                        <span class="prod-badge">
                            محبوب
                        </span>

                    <?php endif; ?>


                    <?php if (!empty($p['img'])): ?>

                        <img
                            src="<?= $h($p['img']) ?>"
                            alt="<?= $h($p['name']) ?>"
                            loading="lazy">

                    <?php else: ?>

                        <span>
                            🧦
                        </span>

                    <?php endif; ?>

                </a>


                <!-- =================================================
                     اطلاعات محصول
                     ================================================= -->

                <div class="prod-info">

                    <div class="prod-cat">
                        <?= $h($p['cat_name']) ?>
                    </div>


                    <div class="prod-name">

                        <a
                            href="/product/<?= $h($p['slug']) ?>">
                            <?= $h($p['name']) ?>
                        </a>

                    </div>


                    <div class="prod-code">

                        کد <?= $h($p['code']) ?>

                        |

                        حداقل <?= number_format($moq) ?> جفت

                    </div>


                    <!-- =================================================
                         قیمت پایه
                         ================================================= -->

                    <div class="prod-price">

                        <?php if ($p['price'] !== null): ?>

                            <?= number_format((float)$p['price']) ?>

                        <?php else: ?>

                            استعلام

                        <?php endif; ?>

                        تومان

                        <small>
                            قیمت همکاری
                        </small>

                    </div>


                    <!-- =================================================
                         موجودی
                         ================================================= -->

                    <div
                        class="prod-stock <?= $stock > 0 ? 'in' : 'out' ?>">

                        <?php if ($stock > 0): ?>

                            ● موجود در انبار
                            (<?= number_format($stock) ?> جفت)

                        <?php else: ?>

                            ● ناموجود

                        <?php endif; ?>

                    </div>


                    <!-- =================================================
                         خرید سریع
                         ================================================= -->

                    <?php if ($stock > 0 && $p['price'] !== null): ?>

                        <div
                            class="stepper"
                            data-min="<?= $moq ?>"
                            data-max="<?= $stock ?>">

                            <button
                                type="button"
                                class="step plus"
                                aria-label="افزایش تعداد">
                                +
                            </button>


                            <input
                                type="text"
                                inputmode="numeric"
                                class="step-num step-input"
                                value="<?= $moq ?>"
                                aria-label="تعداد سفارش">


                            <button
                                type="button"
                                class="step minus"
                                aria-label="کاهش تعداد">
                                −
                            </button>

                        </div>


                        <button
                            type="button"
                            class="quick-add"
                            <?= !$canOrder ? 'disabled' : '' ?>>
                            افزودن سریع
                        </button>


                        <?php if (!$canOrder): ?>

                            <div
                                class="field-hint"
                                style="margin-top:6px;">
                                موجودی کمتر از حداقل سفارش است.
                            </div>

                        <?php endif; ?>

                    <?php elseif ($stock > 0): ?>

                        <div
                            class="field-hint"
                            style="margin-top:8px;">
                            قیمت این محصول نیاز به استعلام دارد.
                        </div>

                    <?php endif; ?>

                </div>

            </article>

        <?php endforeach; ?>

    </section>

<?php else: ?>

    <div class="empty-state">

        <div class="empty-icon">
            📦
        </div>

        <h2>
            فعلاً محصولی در این دسته نیست
        </h2>

        <a
            href="/products"
            class="btn btn-orange">
            مشاهده همه محصولات
        </a>

    </div>

<?php endif; ?>


<?php
$content = ob_get_clean();

require ROOT_PATH . '/views/layouts/main.php';
