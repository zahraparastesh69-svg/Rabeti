<?php

require_once __DIR__ . '/../config/database.php';

$pdo = db();

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

$h = static function ($value): string {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
};


/*
|--------------------------------------------------------------------------
| Banner
|--------------------------------------------------------------------------
*/

$banner = $pdo->query("
    SELECT *
    FROM banners
    WHERE position = 'hero'
      AND is_active = 1
    ORDER BY sort_order ASC, id DESC
    LIMIT 1
")->fetch();

$banner = $banner ?: [];

$bannerImage = trim(
    $banner['image_path'] ?? ''
);

$bannerLink = trim(
    $banner['link'] ?? '/products'
);

if ($bannerLink === '') {
    $bannerLink = '/products';
}


/*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
*/

$categories = $pdo->query("
    SELECT
        id,
        parent_id,
        name,
        slug,
        icon,
        image_path,
        small_image_path,
        sort_order,
        is_active
    FROM categories
    WHERE is_active = 1
    ORDER BY sort_order ASC, id ASC
")->fetchAll();


/*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
*/

$products = $pdo->query("
    SELECT
        p.id,
        p.slug,
        p.code,
        p.name,
        p.base_moq,
        pr.price,
        inv.qty_available
    FROM products p

    LEFT JOIN product_prices pr
        ON pr.product_id = p.id
        AND pr.customer_group = 'wholesale'

    LEFT JOIN inventory inv
        ON inv.product_id = p.id

    WHERE p.is_active = 1

    ORDER BY p.id DESC

    LIMIT 4
")->fetchAll();


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = 'بازار عمده جوراب | جوراب‌لند B2B';

ob_start();

?>

<!-- =========================================================
     HERO
========================================================= -->

<section class="hero-section">

    <?php if ($bannerImage): ?>

        <a
            href="<?= $h($bannerLink) ?>"
            class="hero-banner">

            <img
                src="<?= $h($bannerImage) ?>"
                alt="<?= $h($banner['title'] ?? 'بازار عمده جوراب') ?>"
                class="hero-banner-image">

            <div class="hero-banner-overlay"></div>

            <div class="hero-banner-content">

                <?php if (!empty($banner['eyebrow'])): ?>

                    <span class="hero-eyebrow">
                        <?= $h($banner['eyebrow']) ?>
                    </span>

                <?php endif; ?>


                <?php if (!empty($banner['title'])): ?>

                    <h1>
                        <?= $h($banner['title']) ?>
                    </h1>

                <?php else: ?>

                    <h1>
                        بازار عمده جوراب
                    </h1>

                <?php endif; ?>


                <?php if (!empty($banner['subtitle'])): ?>

                    <p>
                        <?= $h($banner['subtitle']) ?>
                    </p>

                <?php else: ?>

                    <p>
                        خرید مستقیم جوراب از تولیدکننده
                        با شرایط ویژه همکاری
                    </p>

                <?php endif; ?>


                <span class="hero-button">
                    مشاهده محصولات
                    <b>←</b>
                </span>

            </div>

        </a>

    <?php else: ?>

        <div class="hero-banner hero-banner-empty">

            <div class="hero-banner-overlay"></div>

            <div class="hero-banner-content">

                <span class="hero-eyebrow">
                    بازار B2B جوراب
                </span>

                <h1>
                    خرید عمده جوراب
                    از تولیدکننده
                </h1>

                <p>
                    انتخاب مدل، ثبت سفارش و همکاری
                    مستقیم با تولیدکننده
                </p>

                <a
                    href="/products"
                    class="hero-button">
                    مشاهده محصولات
                    <b>←</b>
                </a>

            </div>

        </div>

    <?php endif; ?>

</section>


<!-- =========================================================
     CATEGORY SECTION
========================================================= -->

<section class="premium-categories">

    <div class="section-heading">

        <div>

            <span class="section-eyebrow">
                دسته‌بندی محصولات
            </span>

            <h2>
                جوراب مناسب هر سلیقه
            </h2>

        </div>


        <a
            href="/products"
            class="section-more">
            مشاهده همه

            <span>
                ←
            </span>

        </a>

    </div>


    <?php if ($categories): ?>

        <div class="premium-category-grid">

            <?php foreach ($categories as $index => $cat): ?>

                <?php

                /*
                 * تصویر بزرگ
                 */

                $largeImage = trim(
                    $cat['image_path'] ?? ''
                );


                /*
                 * تصویر کوچک
                 */

                $smallImage = trim(
                    $cat['small_image_path'] ?? ''
                );


                /*
                 * ایموجی
                 */

                $icon = trim(
                    $cat['icon'] ?? ''
                );

                if ($icon === '') {
                    $icon = '🧦';
                }


                /*
                 * شماره کارت
                 */

                $number = str_pad(
                    (string)($index + 1),
                    2,
                    '0',
                    STR_PAD_LEFT
                );

                ?>

                <a
                    href="/products?cat=<?= urlencode($cat['slug']) ?>"
                    class="premium-cat-card">

                    <!-- تصویر بزرگ -->

                    <div class="cat-large-media">

                        <?php if ($largeImage): ?>

                            <img
                                src="<?= $h($largeImage) ?>"
                                alt="<?= $h($cat['name']) ?>"
                                class="cat-large-image"
                                loading="lazy">

                        <?php else: ?>

                            <div class="cat-no-image">
                                <?= $h($icon) ?>
                            </div>

                        <?php endif; ?>


                        <div class="cat-large-overlay"></div>

                    </div>


                    <!-- شماره -->

                    <span class="cat-number">
                        <?= $number ?>
                    </span>


                    <!-- تصویر کوچک -->

                    <div class="premium-cat-icon">

                        <?php if ($smallImage): ?>

                            <img
                                src="<?= $h($smallImage) ?>"
                                alt=""
                                class="cat-small-image"
                                loading="lazy">

                        <?php else: ?>

                            <span class="cat-emoji">
                                <?= $h($icon) ?>
                            </span>

                        <?php endif; ?>

                    </div>


                    <!-- متن دسته -->

                    <div class="premium-cat-copy">

                        <strong>
                            <?= $h($cat['name']) ?>
                        </strong>

                        <span>

                            مشاهده مدل‌ها

                            <b>
                                ←
                            </b>

                        </span>

                    </div>


                    <span class="cat-card-glow"></span>

                </a>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="empty-state">

            هنوز دسته‌بندی فعالی ثبت نشده است.

        </div>

    <?php endif; ?>

</section>


<!-- =========================================================
     PRODUCTS
========================================================= -->

<section class="products-section">

    <div class="section-heading">

        <div>

            <span class="section-eyebrow">
                محصولات
            </span>

            <h2>
                جدیدترین محصولات
            </h2>

        </div>


        <a
            href="/products"
            class="section-more">
            مشاهده همه

            <span>
                ←
            </span>

        </a>

    </div>


    <?php if ($products): ?>

        <div class="products-grid">

            <?php foreach ($products as $product): ?>

                <?php

                $price = $product['price'] !== null
                    ? (float)$product['price']
                    : 0;

                $moq = (int)(
                    $product['base_moq'] ?? 0
                );

                $stock = (int)(
                    $product['qty_available'] ?? 0
                );

                ?>

                <article class="product-card">

                    <!-- تصویر / آیکون محصول -->

                    <div class="product-media">

                        <div class="product-sock">
                            🧦
                        </div>

                    </div>


                    <!-- اطلاعات محصول -->

                    <div class="product-content">

                        <?php if (!empty($product['code'])): ?>

                            <span class="product-code">
                                <?= $h($product['code']) ?>
                            </span>

                        <?php endif; ?>


                        <h3 class="product-title">

                            <?= $h($product['name']) ?>

                        </h3>


                        <div class="product-meta">

                            <?php if ($moq > 0): ?>

                                <span>
                                    حداقل سفارش:
                                    <?= number_format($moq) ?>
                                </span>

                            <?php endif; ?>


                            <?php if ($stock > 0): ?>

                                <span>
                                    موجودی:
                                    <?= number_format($stock) ?>
                                </span>

                            <?php endif; ?>

                        </div>


                        <div class="product-bottom">

                            <div class="product-price">

                                <?php if ($price > 0): ?>

                                    <strong>
                                        <?= number_format($price) ?>
                                    </strong>

                                    <span>
                                        تومان
                                    </span>

                                <?php else: ?>

                                    <span>
                                        استعلام قیمت
                                    </span>

                                <?php endif; ?>

                            </div>


                            <a
                                href="/products/<?= urlencode($product['slug']) ?>"
                                class="product-button">
                                مشاهده
                            </a>

                        </div>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="empty-state">

            هنوز محصول فعالی ثبت نشده است.

        </div>

    <?php endif; ?>

</section>


<!-- =========================================================
     B2B CTA
========================================================= -->

<section class="b2b-cta">

    <div class="b2b-cta-content">

        <span class="section-eyebrow">
            همکاری عمده
        </span>

        <h2>
            برای خرید عمده آماده همکاری هستیم
        </h2>

        <p>
            اگر فروشگاه، بنکداری یا مجموعه پخش دارید،
            محصولات موردنظر خود را انتخاب کرده و سفارش خود را ثبت کنید.
        </p>

        <div class="b2b-cta-actions">

            <a
                href="/products"
                class="btn-orange">
                مشاهده محصولات
            </a>

            <a
                href="/register"
                class="btn-outline-light">
                ثبت‌نام مشتری
            </a>

        </div>

    </div>

</section>


<?php

$content = ob_get_clean();


/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require ROOT_PATH . '/views/layouts/main.php';
