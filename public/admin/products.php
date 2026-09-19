<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../../config/auth.php';
require_admin();

/* حذف (فعلاً منطقی: is_active=0 — حذف فیزیکی نکنیم هرگز) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'deactivate') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        db()->prepare("UPDATE products SET is_active=0 WHERE id=?")->execute([$id]);
        header('Location: /admin/products');
        exit;
    }
}

$products = db()->query("
    SELECT p.id, p.code, p.name, p.slug, p.base_moq, p.is_active,
           c.name AS cat_name,
           pr.price, inv.qty_available
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN product_prices pr ON pr.product_id = p.id AND pr.customer_group='wholesale'
    LEFT JOIN inventory inv ON inv.product_id = p.id
    ORDER BY p.id DESC
")->fetchAll();

$activeMenu = 'products';
$pageTitle  = 'مدیریت محصولات';
ob_start();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
    <h2 style="font-size:1rem;"><?= count($products) ?> محصول</h2>
    <a href="/admin/products?action=new" class="btn btn-orange" style="padding:10px 22px;font-size:.82rem;">+ محصول جدید</a>
</div>

<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>کد</th>
                <th>نام</th>
                <th>دسته</th>
                <th>قیمت عمده</th>
                <th>موجودی</th>
                <th>MOQ</th>
                <th>وضعیت</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
                <tr style="<?= $p['is_active'] ? '' : 'opacity:.5;' ?>">
                    <td><b><?= $p['code'] ?></b></td>
                    <td>
                        <a href="/product/<?= $p['slug'] ?>" target="_blank" style="color:var(--teal-700);">
                            <?= htmlspecialchars($p['name']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($p['cat_name']) ?></td>
                    <td><?= $p['price'] ? number_format($p['price']) . ' ت' : '—' ?></td>
                    <td><?= number_format($p['qty_available'] ?? 0) ?></td>
                    <td><?= $p['base_moq'] ?></td>
                    <td>
                        <span class="st-badge <?= $p['is_active'] ? 'st-converted' : 'st-rejected' ?>">
                            <?= $p['is_active'] ? 'فعال' : 'غیرفعال' ?>
                        </span>
                    </td>
                    <td style="white-space:nowrap;">
                        <a href="/admin/products?action=edit&id=<?= $p['id'] ?>"
                            class="btn" style="padding:6px 14px;font-size:.72rem;background:var(--teal-100);color:var(--teal-800);">ویرایش</a>
                        <?php if ($p['is_active']): ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('این محصول از فروشگاه مخفی شود؟')">
                                <input type="hidden" name="do" value="deactivate">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button class="btn" style="padding:6px 14px;font-size:.72rem;background:#fdeeee;color:#b4443c;">حذف</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../views/layouts/admin.php';
