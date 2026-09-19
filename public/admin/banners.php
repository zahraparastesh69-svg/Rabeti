<?php
require_once __DIR__ . '/../config/database.php'; 
require __DIR__ . '/../../config/upload.php';
require __DIR__ . '/../../config/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? '';
    if ($do === 'save') {
        $id       = (int)($_POST['id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $link     = trim($_POST['link'] ?? '/products');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $imgPath = null;
        if (!empty($_FILES['image']['name'])) {
            $imgPath = upload_image($_FILES['image'], 'products'); // همان پوشه
        }

        if ($id > 0) {
            if ($imgPath) {
                db()->prepare("UPDATE banners SET title=?, subtitle=?, link=?, image_path=?, is_active=? WHERE id=?")
                    ->execute([$title, $subtitle, $link, $imgPath, $isActive, $id]);
            } else {
                db()->prepare("UPDATE banners SET title=?, subtitle=?, link=?, is_active=? WHERE id=?")
                    ->execute([$title, $subtitle, $link, $isActive, $id]);
            }
        } else {
            db()->prepare("INSERT INTO banners (title, subtitle, image_path, link, position, is_active) VALUES (?,?,?,?,'hero',?)")
                ->execute([$title, $subtitle, $imgPath, $link, $isActive]);
        }
        header('Location: /admin/banners'); exit;
    }
}

 $banners = db()->query("SELECT * FROM banners WHERE position='hero' ORDER BY id")->fetchAll();

 $activeMenu = 'banners';
 $pageTitle  = 'بنر صفحه اصلی';
ob_start();
?>

<?php foreach ($banners as $b): ?>
<form method="post" enctype="multipart/form-data" class="co-form" style="max-width:100%;margin-bottom:20px;">
    <input type="hidden" name="do" value="save">
    <input type="hidden" name="id" value="<?= $b['id'] ?>">

    <div class="form-field">
        <label>تیتر اصلی (H1)</label>
        <input type="text" name="title" value="<?= htmlspecialchars($b['title']) ?>">
    </div>
    <div class="form-field">
        <label>زیرتیتر</label>
        <textarea name="subtitle" rows="2"><?= htmlspecialchars($b['subtitle'] ?? '') ?></textarea>
    </div>
    <div class="form-row">
        <div class="form-field">
            <label>لینک دکمه</label>
            <input type="text" name="link" dir="ltr" value="<?= htmlspecialchars($b['link'] ?? '/products') ?>">
        </div>
        <div class="form-field">
            <label>وضعیت</label>
            <select name="is_active">
                <option value="1" <?= $b['is_active'] ? 'selected' : '' ?>>فعال</option>
                <option value="0" <?= !$b['is_active'] ? 'selected' : '' ?>>غیرفعال</option>
            </select>
        </div>
    </div>
    <div class="form-field">
        <label>عکس بنر (پس‌زمینه هیرو)</label>
        <?php if ($b['image_path']): ?>
            <img src="<?= htmlspecialchars($b['image_path']) ?>" alt="" style="max-width:280px;border-radius:14px;margin-bottom:8px;display:block;">
        <?php endif; ?>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
        <span class="field-hint">عکس افقی باکیفیت — حداقل ۱۲۰۰px عرض — حداکثر ۲MB</span>
    </div>

    <button class="btn btn-orange co-submit">ذخیره بنر ←</button>
</form>
<?php endforeach; ?>

<?php
 $content = ob_get_clean();
require __DIR__ . '/../../views/layouts/admin.php';