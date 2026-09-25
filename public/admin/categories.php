<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';
require_once ROOT_PATH . '/config/upload.php';

require_admin();

$db = db();

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| عملیات POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $do = $_POST['do'] ?? '';

    /* ─── افزودن ─── */
    if ($do === 'add') {

        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $sortOrder = max(0, (int)($_POST['sort_order'] ?? 99));

        if (mb_strlen($name) < 2) {
            $error = 'نام دسته‌بندی باید حداقل ۲ حرف باشد.';
        } else {
            $imagePath = upload_image($_FILES['icon_img'] ?? [], 'categories');
            if ($icon === '') $icon = '🧦';

            $maxId = (int)$db->query("SELECT COALESCE(MAX(id),0) FROM categories")->fetchColumn();
            $slug = 'cat-' . ($maxId + 1);

            $db->prepare("INSERT INTO categories (name, slug, icon, image_path, sort_order, is_active)
                          VALUES (?,?,?,?,?,1)")
                ->execute([$name, $slug, $icon, $imagePath, $sortOrder]);

            header('Location: /admin/categories?success=added');
            exit;
        }
    }

    /* ─── ویرایش ─── */
    if ($do === 'edit') {

        $id        = (int)($_POST['id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        $icon      = trim($_POST['icon'] ?? '');
        $sortOrder = max(0, (int)($_POST['sort_order'] ?? 99));

        if ($id <= 0 || mb_strlen($name) < 2) {
            $error = 'اطلاعات ویرایش نامعتبر است.';
        } else {
            $stmt = $db->prepare("SELECT icon, image_path FROM categories WHERE id=? LIMIT 1");
            $stmt->execute([$id]);
            $category = $stmt->fetch();

            if (!$category) {
                $error = 'دسته‌بندی پیدا نشد.';
            } else {
                $imagePath = trim($category['image_path'] ?? '');
                $newImagePath = upload_image($_FILES['icon_img'] ?? [], 'categories');
                if ($newImagePath) $imagePath = $newImagePath;

                if ($icon === '') {
                    $oldIcon = trim($category['icon'] ?? '');
                    $icon = ($oldIcon === '' || str_starts_with($oldIcon, '/uploads/')) ? '🧦' : $oldIcon;
                }

                $db->prepare("UPDATE categories SET name=?, icon=?, image_path=?, sort_order=? WHERE id=?")
                    ->execute([$name, $icon, $imagePath !== '' ? $imagePath : null, $sortOrder, $id]);

                header('Location: /admin/categories?success=edited');
                exit;
            }
        }
    }

    /* ─── فعال/غیرفعال ─── */
    if ($do === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE categories SET is_active = 1 - is_active WHERE id=?")->execute([$id]);
            header('Location: /admin/categories?success=toggle');
            exit;
        }
    }

    /* ─── حذف ─── */
    if ($do === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id=?");
            $stmt->execute([$id]);
            $productCount = (int)$stmt->fetchColumn();

            if ($productCount > 0) {
                $error = 'این دسته‌بندی دارای ' . number_format($productCount) . ' محصول است و قابل حذف نیست.';
            } else {
                $db->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
                header('Location: /admin/categories?success=deleted');
                exit;
            }
        }
    }

    /* ─── ⭐ جابه‌جایی ترتیب (▲▼) ─── */
    if ($do === 'move') {
        $id       = (int)($_POST['id'] ?? 0);
        $dir      = $_POST['dir'] === 'up' ? 'up' : 'down';

        $stmt = $db->prepare("SELECT id, sort_order FROM categories WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        $current = $stmt->fetch();

        if ($current) {
            if ($dir === 'up') {
                $stmt = $db->prepare("SELECT id, sort_order FROM categories
                                      WHERE sort_order < ? ORDER BY sort_order DESC LIMIT 1");
            } else {
                $stmt = $db->prepare("SELECT id, sort_order FROM categories
                                      WHERE sort_order > ? ORDER BY sort_order ASC LIMIT 1");
            }
            $stmt->execute([$current['sort_order']]);
            $neighbor = $stmt->fetch();

            if ($neighbor) {
                /* جابه‌جایی اعداد دو ردیف — حتی اگر اعداد تکراری باشند */
                $db->prepare("UPDATE categories SET sort_order = ? WHERE id=?")
                    ->execute([-1, $current['id']]);   // موقت، جلوگیری از UNIQUE (اگر بود)
                $db->prepare("UPDATE categories SET sort_order = ? WHERE id=?")
                    ->execute([$current['sort_order'], $neighbor['id']]);
                $db->prepare("UPDATE categories SET sort_order = ? WHERE id=?")
                    ->execute([$neighbor['sort_order'], $current['id']]);
            }
        }
        header('Location: /admin/categories');
        exit;
    }
}

/* ─── پیام موفقیت ─── */
if (isset($_GET['success'])) {
    $success = [
        'added'   => 'دسته‌بندی اضافه شد.',
        'edited'  => 'دسته‌بندی ویرایش شد.',
        'deleted' => 'حذف شد.',
        'toggle'  => 'وضعیت تغییر کرد.',
    ][$_GET['success']] ?? '';
}

/* ─── لیست — با ترتیب صحیح ─── */
$categories = $db->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
    FROM categories c
    ORDER BY c.sort_order ASC, c.id ASC
")->fetchAll();

$activeMenu = 'categories';
$pageTitle  = 'دسته‌بندی‌ها';
ob_start();
?>

<?php if ($error): ?>
    <div class="form-alert error" style="display:block;margin-bottom:18px;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="form-alert" style="display:block;margin-bottom:18px;background:#e8f7f3;color:var(--teal-800);">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<!-- ═══ افزودن ═══ -->
<div class="admin-card" style="padding:18px;margin-bottom:22px;">
    <h2 class="section-title" style="font-size:1rem;margin-bottom:14px;">افزودن دسته‌بندی جدید</h2>

    <form method="post" enctype="multipart/form-data"
        style="display:grid;grid-template-columns:1.2fr 90px 1fr 110px auto;gap:10px;align-items:end;">

        <input type="hidden" name="do" value="add">

        <div>
            <label style="display:block;font-size:.7rem;color:var(--muted);margin-bottom:4px;">نام</label>
            <input type="text" name="name" required
                style="width:100%;padding:10px 14px;border:1px solid var(--card-border);border-radius:12px;font-family:inherit;">
        </div>

        <div>
            <label style="display:block;font-size:.7rem;color:var(--muted);margin-bottom:4px;">ایموجی</label>
            <input type="text" name="icon" placeholder="🧦"
                style="width:100%;text-align:center;padding:10px;border:1px solid var(--card-border);border-radius:12px;font-size:1.1rem;">
        </div>

        <div>
            <label style="display:block;font-size:.7rem;color:var(--muted);margin-bottom:4px;">عکس</label>
            <input type="file" name="icon_img" accept="image/jpeg,image/png,image/webp" style="font-size:.72rem;">
        </div>

        <div>
            <label style="display:block;font-size:.7rem;color:var(--muted);margin-bottom:4px;">ترتیب</label>
            <input type="number" name="sort_order" value="99" min="0"
                style="width:100%;padding:10px;border:1px solid var(--card-border);border-radius:12px;text-align:center;">
        </div>

        <button type="submit" class="btn btn-orange" style="padding:11px 22px;font-size:.82rem;">+ افزودن</button>
    </form>
</div>

<!-- ═══ لیست ═══ -->
<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ترتیب</th>
                <th>تصویر</th>
                <th>نام</th>
                <th>محصولات</th>
                <th>وضعیت</th>
                <th style="min-width:280px;">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $index => $c): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:4px;">
                            <b><?= (int)$c['sort_order'] ?></b>
                            <!-- ▲▼ جابه‌جایی -->
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="do" value="move">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <input type="hidden" name="dir" value="up">
                                <button type="submit" title="یک پله بالاتر"
                                    style="border:none;background:none;cursor:pointer;color:var(--teal-800);font-size:.8rem;padding:0 2px;"
                                    <?= $index === 0 ? 'disabled style="opacity:.25;border:none;background:none;cursor:default;font-size:.8rem;padding:0 2px;"' : '' ?>>▲</button>
                            </form>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="do" value="move">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <input type="hidden" name="dir" value="down">
                                <button type="submit" title="یک پله پایین‌تر"
                                    style="border:none;background:none;cursor:pointer;color:var(--teal-800);font-size:.8rem;padding:0 2px;"
                                    <?= $index === count($categories) - 1 ? 'disabled style="opacity:.25;border:none;background:none;cursor:default;font-size:.8rem;padding:0 2px;"' : '' ?>>▼</button>
                            </form>
                        </div>
                    </td>

                    <td>
                        <?php
                        $imagePath = trim($c['image_path'] ?? '');
                        $iconVal = trim($c['icon'] ?? '');
                        if (str_starts_with($iconVal, '/uploads/')) $iconVal = '🧦';
                        ?>
                        <?php if ($imagePath !== ''): ?>
                            <img src="<?= htmlspecialchars($imagePath) ?>" alt=""
                                style="width:52px;height:52px;object-fit:cover;border-radius:12px;border:1px solid var(--card-border);">
                        <?php else: ?>
                            <span style="font-size:1.7rem;"><?= htmlspecialchars($iconVal ?: '🧦') ?></span>
                        <?php endif; ?>
                    </td>

                    <td><b><?= htmlspecialchars($c['name']) ?></b></td>
                    <td><?= number_format((int)$c['product_count']) ?></td>
                    <td>
                        <span class="st-badge <?= $c['is_active'] ? 'st-converted' : 'st-rejected' ?>">
                            <?= $c['is_active'] ? 'فعال' : 'غیرفعال' ?>
                        </span>
                    </td>

                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                            <button type="button" class="btn"
                                onclick='openEditCategory(<?= (int)$c["id"] ?>,
                                <?= htmlspecialchars(json_encode($c["name"], JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>,
                                <?= htmlspecialchars(json_encode(
                                    str_starts_with(trim($c["icon"] ?? ""), "/uploads/") ? "" : ($c["icon"] ?? ""),
                                    JSON_UNESCAPED_UNICODE
                                ), ENT_QUOTES, "UTF-8") ?>,
                                <?= (int)$c["sort_order"] ?>)'
                                style="padding:6px 12px;font-size:.72rem;background:var(--teal-100);color:var(--teal-800);">
                                ✏️ ویرایش
                            </button>

                            <form method="post" style="display:inline;">
                                <input type="hidden" name="do" value="toggle">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <button class="btn" style="padding:6px 12px;font-size:.72rem;">
                                    <?= $c['is_active'] ? 'غیرفعال' : 'فعال' ?>
                                </button>
                            </form>

                            <form method="post" style="display:inline;" onsubmit="return confirm('حذف شود؟');">
                                <input type="hidden" name="do" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <button class="btn" style="padding:6px 12px;font-size:.72rem;background:#fff0f0;color:#c62828;">🗑</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ═══ Modal ویرایش — حالا با فیلد ترتیب ═══ -->
<div id="editCategoryModal"
    style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;padding:20px;">

    <div style="width:min(500px,100%);background:#fff;border-radius:18px;padding:22px;box-shadow:0 20px 60px rgba(0,0,0,.2);">

        <h3 style="margin:0 0 18px;">ویرایش دسته‌بندی</h3>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="do" value="edit">
            <input type="hidden" name="id" id="edit_category_id">

            <div class="form-field">
                <label>نام دسته‌بندی</label>
                <input type="text" name="name" id="edit_category_name" required>
            </div>

            <div class="form-row" style="display:grid;grid-template-columns:1fr 110px;gap:12px;">
                <div class="form-field">
                    <label>ایموجی</label>
                    <input type="text" name="icon" id="edit_category_icon" placeholder="🧦">
                </div>
                <div class="form-field">
                    <label>ترتیب نمایش</label>
                    <input type="number" name="sort_order" id="edit_category_sort" min="0"
                        style="text-align:center;">
                </div>
            </div>

            <div class="form-field">
                <label>تصویر جدید</label>
                <input type="file" name="icon_img" accept="image/jpeg,image/png,image/webp">
                <small style="display:block;margin-top:6px;color:var(--muted);">
                    اگر عکس جدید انتخاب کنید جایگزین تصویر فعلی می‌شود.
                </small>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:18px;">
                <button type="button" class="btn" onclick="closeEditCategory()">انصراف</button>
                <button type="submit" class="btn btn-orange">ذخیره تغییرات</button>
            </div>
        </form>

    </div>
</div>

<script>
    function openEditCategory(id, name, icon, sortOrder) {
        document.getElementById('edit_category_id').value = id;
        document.getElementById('edit_category_name').value = name;
        document.getElementById('edit_category_icon').value = icon || '';
        document.getElementById('edit_category_sort').value = sortOrder ?? 99;

        const modal = document.getElementById('editCategoryModal');
        modal.style.display = 'flex';
    }

    function closeEditCategory() {
        document.getElementById('editCategoryModal').style.display = 'none';
    }
    document.getElementById('editCategoryModal')
        .addEventListener('click', function(event) {
            if (event.target === this) closeEditCategory();
        });
</script>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/admin.php';
