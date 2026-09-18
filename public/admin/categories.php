<?php
require __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? '';
    if ($do === 'add') {
        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? '🧦');
        if (mb_strlen($name) >= 2) {
            $slug = 'cat-' . (int)(db()->query("SELECT MAX(id) FROM categories")->fetchColumn() + 1);
            db()->prepare("INSERT INTO categories (name, slug, icon, sort_order) VALUES (?,?,?,99)")
                ->execute([$name, $slug, $icon]);
        }
        header('Location: /admin/categories');
        exit;
    }
    if ($do === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare("UPDATE categories SET is_active = 1 - is_active WHERE id=?")->execute([$id]);
        header('Location: /admin/categories');
        exit;
    }
}

$categories = db()->query("
    SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
    FROM categories c ORDER BY c.sort_order
")->fetchAll();

$activeMenu = 'categories';
$pageTitle  = 'دسته‌بندی‌ها';
ob_start();
?>

<form method="post" style="display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap;">
    <input type="hidden" name="do" value="add">
    <input type="text" name="name" placeholder="نام دسته جدید" style="flex:1;min-width:160px;padding:10px 14px;border:1px solid var(--card-border);border-radius:12px;font-family:inherit;">
    <input type="text" name="icon" placeholder="🧦" style="width:70px;text-align:center;padding:10px;border:1px solid var(--card-border);border-radius:12px;font-size:1.2rem;">
    <button class="btn btn-orange" style="padding:10px 22px;font-size:.82rem;">+ افزودن</button>
</form>

<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>آیکون</th>
                <th>نام</th>
                <th>تعداد محصول</th>
                <th>وضعیت</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $c): ?>
                <tr>
                    <td style="font-size:1.3rem;"><?= $c['icon'] ?></td>
                    <td><b><?= htmlspecialchars($c['name']) ?></b> <span style="font-size:.7rem;color:var(--muted);dir=" ltr">(<?= $c['slug'] ?>)</span></td>
                    <td><?= $c['product_count'] ?></td>
                    <td><span class="st-badge <?= $c['is_active'] ? 'st-converted' : 'st-rejected' ?>"><?= $c['is_active'] ? 'فعال' : 'غیرفعال' ?></span></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="do" value="toggle">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button class="btn" style="padding:6px 14px;font-size:.72rem;background:var(--teal-100);color:var(--teal-800);">
                                <?= $c['is_active'] ? 'غیرفعال کن' : 'فعال کن' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../views/layouts/admin.php';
