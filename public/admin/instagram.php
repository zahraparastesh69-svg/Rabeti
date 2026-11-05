<?php

declare(strict_types=1);

/* Auto-detect bootstrap (local + host) */
$__bp = [
    __DIR__ . '/../../sock-b2b/config/bootstrap.php',
    __DIR__ . '/../../config/bootstrap.php',
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
require_once ROOT_PATH . '/config/upload.php';

require_admin();

$db = db();
$error = '';
$success = '';

/* ═══════════════ POST ═══════════════ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $do = $_POST['do'] ?? '';

    /* ─── افزودن ─── */
    if ($do === 'add') {

        $caption   = trim($_POST['caption'] ?? '');
        $link      = trim($_POST['link'] ?? '');
        $sortOrder = max(0, (int)($_POST['sort_order'] ?? 99));

        $imagePath = upload_image($_FILES['image'] ?? [], 'instagram');

        if (!$imagePath) {
            $error = 'آپلود تصویر ناموفق بود.';
        } else {
            $db->prepare("
                INSERT INTO instagram_posts
                    (image_path, caption, link, sort_order, is_active)
                VALUES (?,?,?,?,1)
            ")->execute([
                $imagePath,
                $caption !== '' ? $caption : null,
                $link !== '' ? $link : null,
                $sortOrder
            ]);

            header('Location: /admin/instagram?success=added');
            exit;
        }
    }

    /* ─── ویرایش ─── */
    if ($do === 'edit') {

        $id        = (int)($_POST['id'] ?? 0);
        $caption   = trim($_POST['caption'] ?? '');
        $link      = trim($_POST['link'] ?? '');
        $sortOrder = max(0, (int)($_POST['sort_order'] ?? 99));

        if ($id <= 0) {
            $error = 'شناسه نامعتبر است.';
        } else {
            $stmt = $db->prepare("SELECT image_path FROM instagram_posts WHERE id=?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();

            if (!$row) {
                $error = 'پست پیدا نشد.';
            } else {
                $imagePath = $row['image_path'];
                $newImage = upload_image($_FILES['image'] ?? [], 'instagram');
                if ($newImage) $imagePath = $newImage;

                $db->prepare("
                    UPDATE instagram_posts
                    SET caption=?, link=?, sort_order=?, image_path=?
                    WHERE id=?
                ")->execute([
                    $caption !== '' ? $caption : null,
                    $link !== '' ? $link : null,
                    $sortOrder,
                    $imagePath,
                    $id
                ]);

                header('Location: /admin/instagram?success=edited');
                exit;
            }
        }
    }

    /* ─── فعال/غیرفعال ─── */
    if ($do === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE instagram_posts SET is_active = 1 - is_active WHERE id=?")
                ->execute([$id]);
            header('Location: /admin/instagram?success=toggle');
            exit;
        }
    }

    /* ─── حذف ─── */
    if ($do === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("SELECT image_path FROM instagram_posts WHERE id=?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();

            if ($row) {
                $file = ROOT_PATH . '/public' . $row['image_path'];
                if (is_file($file)) @unlink($file);

                $db->prepare("DELETE FROM instagram_posts WHERE id=?")->execute([$id]);
            }
            header('Location: /admin/instagram?success=deleted');
            exit;
        }
    }
}

if (isset($_GET['success'])) {
    $success = [
        'added'   => 'پست اضافه شد.',
        'edited'  => 'ویرایش شد.',
        'deleted' => 'حذف شد.',
        'toggle'  => 'وضعیت تغییر کرد.',
    ][$_GET['success']] ?? '';
}

$posts = $db->query("
    SELECT * FROM instagram_posts
    ORDER BY sort_order ASC, id DESC
")->fetchAll();

$activeMenu = 'instagram';
$pageTitle  = 'گالری اینستاگرام';

ob_start();
?>

<?php if ($error): ?>
    <div class="form-alert error" style="display:block;margin-bottom:18px;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="form-alert" style="display:block;margin-bottom:18px;background:#e8f7f3;color:var(--teal-800);">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>


<!-- ═══ افزودن پست جدید ═══ -->
<div class="admin-card" style="padding:22px;margin-bottom:22px;">

    <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;">
        <div style="width:34px;height:34px;display:grid;place-items:center;border-radius:9px;background:var(--admin-primary-soft);color:var(--admin-primary);">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                <rect x="3" y="3" width="18" height="18" rx="5" />
                <circle cx="12" cy="12" r="4" />
                <circle cx="17.5" cy="6.5" r=".8" fill="currentColor" />
            </svg>
        </div>
        <h2 style="margin:0;font-size:1rem;font-weight:800;color:var(--admin-text);">
            افزودن پست جدید
        </h2>
    </div>

    <form method="post" enctype="multipart/form-data"
        style="display:grid;grid-template-columns:1.2fr 1fr 1fr 100px auto;gap:14px;align-items:end;">
        <?= csrf_field() ?>

        <input type="hidden" name="do" value="add">

        <div>
            <label style="display:block;font-size:.72rem;font-weight:700;color:var(--admin-text);margin-bottom:6px;">
                تصویر <span style="color:var(--admin-danger);">*</span>
            </label>
            <label class="admin-file" style="padding:6px 10px;min-height:42px;">
                <input type="file" name="image" required
                    accept="image/jpeg,image/png,image/webp"
                    onchange="updateFileName(this)">
                <span class="admin-file-btn" style="padding:6px 12px;font-size:.72rem;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                        <polyline points="17 8 12 3 7 8" />
                        <line x1="12" y1="3" x2="12" y2="15" />
                    </svg>
                    انتخاب
                </span>
                <span class="admin-file-name" data-placeholder="—" style="font-size:.7rem;">—</span>
            </label>
        </div>

        <div>
            <label style="display:block;font-size:.72rem;font-weight:700;color:var(--admin-text);margin-bottom:6px;">
                متن (کپشن)
            </label>
            <input type="text" name="caption"
                placeholder="مثلاً: جدیدترین طرح"
                style="width:100%;padding:10px 14px;border:1.5px solid var(--admin-border);border-radius:10px;font-family:inherit;font-size:.86rem;">
        </div>

        <div>
            <label style="display:block;font-size:.72rem;font-weight:700;color:var(--admin-text);margin-bottom:6px;">
                لینک پست
            </label>
            <input type="url" name="link" dir="ltr"
                placeholder="https://instagram.com/p/..."
                style="width:100%;padding:10px 14px;border:1.5px solid var(--admin-border);border-radius:10px;font-family:inherit;font-size:.86rem;">
        </div>

        <div>
            <label style="display:block;font-size:.72rem;font-weight:700;color:var(--admin-text);margin-bottom:6px;">
                ترتیب
            </label>
            <input type="number" name="sort_order" value="99" min="0"
                style="width:100%;padding:10px;border:1.5px solid var(--admin-border);border-radius:10px;text-align:center;font-family:inherit;font-size:.86rem;">
        </div>

        <button type="submit" class="btn btn-orange" style="padding:11px 22px;font-size:.82rem;">
            + افزودن
        </button>
    </form>
</div>


<!-- ═══ لیست پست‌ها ═══ -->
<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th style="width:100px;">تصویر</th>
                <th>متن</th>
                <th>لینک</th>
                <th style="width:80px;">ترتیب</th>
                <th style="width:100px;">وضعیت</th>
                <th style="width:220px;">عملیات</th>
            </tr>
        </thead>
        <tbody>

            <?php if (!$posts): ?>
                <tr>
                    <td colspan="6" style="text-align:center;padding:30px;color:var(--admin-text-muted);">
                        هنوز پستی اضافه نشده.
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($posts as $post): ?>
                <tr>
                    <td>
                        <img src="<?= htmlspecialchars($post['image_path']) ?>" alt=""
                            style="width:64px;height:64px;object-fit:cover;border-radius:10px;border:1px solid var(--admin-border);">
                    </td>

                    <td>
                        <?= htmlspecialchars($post['caption'] ?? '—') ?>
                    </td>

                    <td>
                        <?php if (!empty($post['link'])): ?>
                            <a href="<?= htmlspecialchars($post['link']) ?>"
                                target="_blank" rel="noopener"
                                dir="ltr"
                                style="color:var(--admin-primary);font-size:.75rem;font-weight:600;">
                                مشاهده ↗
                            </a>
                        <?php else: ?>
                            <span style="color:var(--admin-text-muted);">—</span>
                        <?php endif; ?>
                    </td>

                    <td style="text-align:center;">
                        <?= (int)$post['sort_order'] ?>
                    </td>

                    <td>
                        <span class="st-badge <?= $post['is_active'] ? 'st-converted' : 'st-rejected' ?>">
                            <?= $post['is_active'] ? 'فعال' : 'غیرفعال' ?>
                        </span>
                    </td>

                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">

                            <button type="button" class="btn btn-sm"
                                onclick='openEditIg(
                                    <?= (int)$post["id"] ?>,
                                    <?= htmlspecialchars(json_encode($post["caption"] ?? "", JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>,
                                    <?= htmlspecialchars(json_encode($post["link"] ?? "", JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>,
                                    <?= (int)$post["sort_order"] ?>
                                )'
                                style="background:var(--admin-primary-soft);color:var(--admin-primary);border-color:transparent;">
                                ✏️ ویرایش
                            </button>

                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="do" value="toggle">
                                <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
                                <button class="btn btn-sm">
                                    <?= $post['is_active'] ? 'غیرفعال' : 'فعال' ?>
                                </button>
                            </form>

                            <form method="post" style="display:inline;"
                                onsubmit="return confirm('حذف شود؟');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="do" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
                                <button class="btn btn-sm"
                                    style="background:var(--admin-danger-soft);color:var(--admin-danger);border-color:transparent;">
                                    🗑
                                </button>
                            </form>

                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

        </tbody>
    </table>
</div>


<!-- ═══ Modal ویرایش ═══ -->
<div id="editIgModal" class="admin-modal-overlay" style="display:none;">
    <div class="admin-modal">

        <div class="admin-modal-head">
            <h3>ویرایش پست</h3>
            <button type="button" class="admin-modal-close" onclick="closeEditIg()" aria-label="بستن">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6L6 18M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form method="post" enctype="multipart/form-data" class="admin-modal-body">
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="edit">
            <input type="hidden" name="id" id="edit_ig_id">

            <div class="admin-modal-field">
                <label>متن (کپشن)</label>
                <input type="text" name="caption" id="edit_ig_caption">
            </div>

            <div class="admin-modal-field">
                <label>لینک پست</label>
                <input type="url" name="link" id="edit_ig_link" dir="ltr">
            </div>

            <div class="admin-modal-field">
                <label>ترتیب</label>
                <input type="number" name="sort_order" id="edit_ig_sort" min="0">
            </div>

            <div class="admin-modal-field">
                <label>تصویر جدید (اختیاری)</label>
                <label class="admin-file">
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp"
                        onchange="updateFileName(this)">
                    <span class="admin-file-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                            <polyline points="17 8 12 3 7 8" />
                            <line x1="12" y1="3" x2="12" y2="15" />
                        </svg>
                        انتخاب تصویر
                    </span>
                    <span class="admin-file-name" data-placeholder="تصویری انتخاب نشده">
                        تصویری انتخاب نشده
                    </span>
                </label>
                <small class="admin-modal-hint">اگر عکس جدید انتخاب کنید جایگزین تصویر فعلی می‌شود.</small>
            </div>

            <div class="admin-modal-actions">
                <button type="button" class="btn" onclick="closeEditIg()">انصراف</button>
                <button type="submit" class="btn btn-orange">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" />
                        <polyline points="17 21 17 13 7 13 7 21" />
                        <polyline points="7 3 7 8 15 8" />
                    </svg>
                    ذخیره تغییرات
                </button>
            </div>
        </form>

    </div>
</div>


<script>
    function openEditIg(id, caption, link, sortOrder) {
        document.getElementById('edit_ig_id').value = id;
        document.getElementById('edit_ig_caption').value = caption || '';
        document.getElementById('edit_ig_link').value = link || '';
        document.getElementById('edit_ig_sort').value = sortOrder ?? 99;
        document.getElementById('editIgModal').style.display = 'flex';
    }

    function closeEditIg() {
        document.getElementById('editIgModal').style.display = 'none';
    }

    document.getElementById('editIgModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditIg();
    });

    function updateFileName(input) {
        var label = input.closest('.admin-file');
        var nameEl = label ? label.querySelector('.admin-file-name') : null;
        if (!nameEl) return;

        if (input.files && input.files.length > 0) {
            nameEl.textContent = input.files[0].name;
            nameEl.classList.add('is-selected');
        } else {
            nameEl.textContent = nameEl.dataset.placeholder || 'تصویری انتخاب نشده';
            nameEl.classList.remove('is-selected');
        }
    }
</script>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/admin.php';
