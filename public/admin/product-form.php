<?php

require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/config/upload.php';
require_once ROOT_PATH . '/config/auth.php';

require_admin();
$action    = ($_GET['action'] ?? 'new') === 'edit' ? 'edit' : 'new';
$productId = (int)($_GET['id'] ?? 0);
$errors    = [];

/* ─── حالت ویرایش: خواندن داده ─── */
if ($action === 'edit' && $productId > 0) {
    $stmt = db()->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) {
        header('Location: /admin/products');
        exit;
    }

    $stmt = db()->prepare("SELECT price FROM product_prices WHERE product_id=? AND customer_group='wholesale' AND min_qty=1");
    $stmt->execute([$productId]);
    $price = (int)($stmt->fetchColumn() ?: 0);

    $stmt = db()->prepare("SELECT qty_available FROM inventory WHERE product_id=?");
    $stmt->execute([$productId]);
    $qty = (int)($stmt->fetchColumn() ?: 0);

    $stmt = db()->prepare("SELECT image_path FROM product_images WHERE product_id=? AND is_main=1 LIMIT 1");
    $stmt->execute([$productId]);
    $currentImage = $stmt->fetchColumn() ?: null;
} else {
    $action = 'new';
    $product = ['code' => '', 'name' => '', 'slug' => '', 'description' => '', 'base_moq' => '48', 'category_id' => ''];
    $price = 0;
    $qty = 0;
    $currentImage = null;
}

/* ─── ذخیره ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $code        = strtoupper(trim($_POST['code'] ?? ''));
    $name        = trim($_POST['name'] ?? '');
    $slug        = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $baseMoq     = max(1, (int)($_POST['base_moq'] ?? 1));
    $categoryId  = (int)($_POST['category_id'] ?? 0);
    $price       = max(0, (int)($_POST['price'] ?? 0));
    $qty         = max(0, (int)($_POST['qty'] ?? 0));

    /* اعتبارسنجی */
    if (!preg_match('/^S\d{3}$/', $code))          $errors['code'] = 'کد باید مثل S125 باشد';
    if (mb_strlen($name) < 3)                      $errors['name'] = 'نام محصول را کامل وارد کنید';
    if ($categoryId < 1)                           $errors['category_id'] = 'دسته را انتخاب کنید';
    if ($price < 1000)                             $errors['price'] = 'قیمت را به تومان و بدون نقطه وارد کنید';

    /* slug خودکار اگر خالی بود */
    if (!$errors && $slug === '') {
        $base = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($_POST['slug_en'] ?? $code)));
        $slug = trim($base, '-') . '-' . strtolower($code);
    }

    /* تکراری نبودن کد */
    if (!$errors) {
        $stmt = db()->prepare("SELECT id FROM products WHERE code=? AND id != ?");
        $stmt->execute([$code, $productId]);
        if ($stmt->fetch()) $errors['code'] = 'این کد قبلاً استفاده شده';
    }

    if (!$errors) {
        if ($action === 'new') {
            db()->prepare("INSERT INTO products (category_id, code, name, slug, description, base_moq) VALUES (?,?,?,?,?,?)")
                ->execute([$categoryId, $code, $name, $slug, $description ?: null, $baseMoq]);
            $productId = (int)db()->lastInsertId();
        } else {
            db()->prepare("UPDATE products SET category_id=?, code=?, name=?, slug=?, description=?, base_moq=? WHERE id=?")
                ->execute([$categoryId, $code, $name, $slug, $description ?: null, $baseMoq, $productId]);
        }

        /* قیمت */
        db()->prepare("DELETE FROM product_prices WHERE product_id=? AND customer_group='wholesale' AND min_qty=1")
            ->execute([$productId]);
        db()->prepare("INSERT INTO product_prices (product_id, customer_group, min_qty, price) VALUES (?, 'wholesale', 1, ?)")
            ->execute([$productId, $price]);

        /* موجودی */
        db()->prepare("INSERT INTO inventory (product_id, qty_available) VALUES (?, ?)
                       ON DUPLICATE KEY UPDATE qty_available = VALUES(qty_available)")
            ->execute([$productId, $qty]);

        /* عکس محصول — اگر فایل جدید انتخاب شده باشد */
        if (!empty($_FILES['image']['name'])) {
            $imgPath = upload_image($_FILES['image'], 'products');
            if ($imgPath) {
                db()->prepare("UPDATE product_images SET is_main=0 WHERE product_id=?")->execute([$productId]);
                db()->prepare("INSERT INTO product_images (product_id, image_path, is_main) VALUES (?,?,1)")
                    ->execute([$productId, $imgPath]);
                $currentImage = $imgPath;
            }
        }

        header('Location: /admin/products');
        exit;
    }

    /* بازگرداندن مقادیر پس از خطا */
    $product = array_merge($product, [
        'code' => $code,
        'name' => $name,
        'slug' => $slug,
        'description' => $description,
        'base_moq' => $baseMoq,
        'category_id' => $categoryId
    ]);
}

$categories = db()->query("SELECT id, name FROM categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();

$activeMenu = 'products';
$pageTitle  = ($action === 'edit' ? 'ویرایش محصول' : 'محصول جدید');
ob_start();
?>

<div style="margin-bottom:16px;">
    <a href="/admin/products" style="color:var(--teal-700);font-size:.82rem;">→ بازگشت به لیست محصولات</a>
</div>

<form method="post" class="co-form" style="max-width:100%;" enctype="multipart/form-data">

    <?php if ($errors): ?>
        <div class="form-alert">⚠️ خطاها را اصلاح کنید</div>
    <?php endif; ?>

    <div class="form-row">
        <div class="form-field">
            <label>کد محصول <i>*</i></label>
            <input type="text" name="code" dir="ltr" value="<?= htmlspecialchars($product['code']) ?>" placeholder="S125" maxlength="4">
            <?php if (isset($errors['code'])): ?><span class="field-error"><?= $errors['code'] ?></span><?php endif; ?>
        </div>
        <div class="form-field">
            <label>دسته‌بندی <i>*</i></label>
            <select name="category_id">
                <option value="">— انتخاب کنید —</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $product['category_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['category_id'])): ?><span class="field-error"><?= $errors['category_id'] ?></span><?php endif; ?>
        </div>
    </div>

    <div class="form-field">
        <label>نام محصول <i>*</i></label>
        <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" placeholder="مثلاً: جوراب نخی مردانه ساق‌بلند">
        <?php if (isset($errors['name'])): ?><span class="field-error"><?= $errors['name'] ?></span><?php endif; ?>
    </div>

    <div class="form-row">
        <div class="form-field">
            <label>نامک انگلیسی (اختیاری)</label>
            <input type="text" name="slug_en" dir="ltr" value="" placeholder="m-cotton-long">
            <span class="field-hint">اگر خالی بگذارید از کد محصول ساخته می‌شود</span>
        </div>
        <div class="form-field">
            <label>حداقل سفارش (MOQ) <i>*</i></label>
            <input type="number" name="base_moq" dir="ltr" value="<?= htmlspecialchars($product['base_moq']) ?>" min="1">
        </div>
    </div>

    <div class="form-row">
        <div class="form-field">
            <label>قیمت عمده (تومان) <i>*</i></label>
            <input type="number" name="price" dir="ltr" value="<?= $price ?>" min="0" step="1000">
            <?php if (isset($errors['price'])): ?><span class="field-error"><?= $errors['price'] ?></span><?php endif; ?>
        </div>
        <div class="form-field">
            <label>موجودی انبار (جفت)</label>
            <input type="number" name="qty" dir="ltr" value="<?= $qty ?>" min="0" step="10">
        </div>
    </div>

    <div class="form-field">
        <label>عکس محصول</label>
        <?php if ($currentImage): ?>
            <img src="<?= htmlspecialchars($currentImage) ?>" alt="عکس فعلی"
                style="max-width:180px;border-radius:14px;margin-bottom:10px;display:block;border:1px solid var(--card-border);">
            <span class="field-hint">عکس فعلی بالا است — اگر فایل جدید انتخاب کنید جایگزین می‌شود</span>
        <?php endif; ?>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
        <span class="field-hint">JPG/PNG/WebP — حداکثر ۲ مگابایت — ترجیحاً مربعی</span>
    </div>

    <div class="form-field">
        <label>توضیحات</label>
        <textarea name="description" rows="6" placeholder="جنس نخ، مخاطب فروش، رنگ‌بندی، بسته‌بندی..."><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
    </div>

    <button type="submit" class="btn btn-orange co-submit">
        <?= $action === 'edit' ? 'ذخیره تغییرات' : 'افزودن محصول' ?> ←
    </button>
</form>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../views/layouts/admin.php';
