<?php

require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/config/upload.php';
require_once ROOT_PATH . '/config/auth.php';

require_admin();

$db = db();

$error   = '';
$success = '';

/*
|--------------------------------------------------------------------------
| عملیات POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $do = $_POST['do'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | افزودن دسته‌بندی
    |--------------------------------------------------------------------------
    */

    if ($do === 'add') {

        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? '');

        if (mb_strlen($name) < 2) {

            $error = 'نام دسته‌بندی باید حداقل ۲ حرف باشد.';
        } else {

            /*
             * تصویر دسته‌بندی
             * تصویر در image_path ذخیره می‌شود.
             */
            $imagePath = upload_image(
                $_FILES['category_image'] ?? [],
                'categories'
            );

            /*
             * اگر ایموجی وارد نشده باشد
             */
            if ($icon === '') {
                $icon = '🧦';
            }

            /*
             * ساخت slug
             */
            $maxId = (int)$db
                ->query("SELECT COALESCE(MAX(id), 0) FROM categories")
                ->fetchColumn();

            $slug = 'cat-' . ($maxId + 1);

            /*
             * ذخیره
             */
            $stmt = $db->prepare("
                INSERT INTO categories
                    (
                        parent_id,
                        name,
                        slug,
                        icon,
                        image_path,
                        sort_order,
                        is_active
                    )
                VALUES
                    (
                        NULL,
                        ?,
                        ?,
                        ?,
                        ?,
                        99,
                        1
                    )
            ");

            $stmt->execute([
                $name,
                $slug,
                $icon,
                $imagePath ?: null
            ]);

            header('Location: /admin/categories?success=added');
            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | ویرایش دسته‌بندی
    |--------------------------------------------------------------------------
    */

    if ($do === 'edit') {

        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? '');

        if ($id <= 0) {

            $error = 'دسته‌بندی نامعتبر است.';
        } elseif (mb_strlen($name) < 2) {

            $error = 'نام دسته‌بندی باید حداقل ۲ حرف باشد.';
        } else {

            /*
             * دریافت اطلاعات فعلی
             */
            $stmt = $db->prepare("
                SELECT
                    icon,
                    image_path
                FROM categories
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$id]);

            $category = $stmt->fetch();

            if (!$category) {

                $error = 'دسته‌بندی پیدا نشد.';
            } else {

                /*
                 * اگر ایموجی خالی باشد،
                 * ایموجی قبلی حفظ می‌شود.
                 */
                if ($icon === '') {
                    $icon = $category['icon'] ?: '🧦';
                }

                /*
                 * تصویر جدید
                 */
                $newImagePath = upload_image(
                    $_FILES['category_image'] ?? [],
                    'categories'
                );

                /*
                 * اگر تصویر جدید انتخاب نشده،
                 * تصویر قبلی حفظ می‌شود.
                 */
                $imagePath = $newImagePath ?: ($category['image_path'] ?: null);

                /*
                 * بروزرسانی
                 */
                $stmt = $db->prepare("
                    UPDATE categories
                    SET
                        name = ?,
                        icon = ?,
                        image_path = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $name,
                    $icon,
                    $imagePath,
                    $id
                ]);

                header('Location: /admin/categories?success=edited');
                exit;
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | فعال / غیرفعال
    |--------------------------------------------------------------------------
    */

    if ($do === 'toggle') {

        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {

            $stmt = $db->prepare("
                UPDATE categories
                SET is_active = 1 - is_active
                WHERE id = ?
            ");

            $stmt->execute([$id]);

            header('Location: /admin/categories?success=toggle');
            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | حذف دسته‌بندی
    |--------------------------------------------------------------------------
    */

    if ($do === 'delete') {

        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {

            /*
             * اگر محصول داشته باشد حذف نمی‌شود.
             */
            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM products
                WHERE category_id = ?
            ");

            $stmt->execute([$id]);

            $productCount = (int)$stmt->fetchColumn();

            if ($productCount > 0) {

                $error =
                    'این دسته‌بندی دارای ' .
                    number_format($productCount) .
                    ' محصول است و فعلاً قابل حذف نیست. ابتدا محصولات آن را جابه‌جا کنید.';
            } else {

                $stmt = $db->prepare("
                    DELETE FROM categories
                    WHERE id = ?
                ");

                $stmt->execute([$id]);

                header('Location: /admin/categories?success=deleted');
                exit;
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| پیام موفقیت
|--------------------------------------------------------------------------
*/

if (isset($_GET['success'])) {

    switch ($_GET['success']) {

        case 'added':
            $success = 'دسته‌بندی با موفقیت اضافه شد.';
            break;

        case 'edited':
            $success = 'دسته‌بندی با موفقیت ویرایش شد.';
            break;

        case 'deleted':
            $success = 'دسته‌بندی با موفقیت حذف شد.';
            break;

        case 'toggle':
            $success = 'وضعیت دسته‌بندی تغییر کرد.';
            break;
    }
}


/*
|--------------------------------------------------------------------------
| دریافت دسته‌بندی‌ها
|--------------------------------------------------------------------------
*/

$categories = $db->query("
    SELECT
        c.*,
        (
            SELECT COUNT(*)
            FROM products p
            WHERE p.category_id = c.id
        ) AS product_count
    FROM categories c
    ORDER BY c.sort_order ASC, c.id ASC
")->fetchAll();


$activeMenu = 'categories';
$pageTitle  = 'دسته‌بندی‌ها';

ob_start();

?>


<!-- =========================================================
     پیام‌ها
========================================================= -->

<?php if ($error): ?>

    <div
        class="form-alert error"
        style="display:block;margin-bottom:18px;">
        <?= htmlspecialchars($error) ?>
    </div>

<?php endif; ?>


<?php if ($success): ?>

    <div
        class="form-alert"
        style="
            display:block;
            margin-bottom:18px;
            background:#e8f7f3;
            color:var(--teal-800);
        ">
        <?= htmlspecialchars($success) ?>
    </div>

<?php endif; ?>


<!-- =========================================================
     افزودن دسته‌بندی
========================================================= -->

<div
    class="admin-card"
    style="
        padding:18px;
        margin-bottom:22px;
    ">

    <h2
        class="section-title"
        style="
            font-size:1rem;
            margin-bottom:14px;
        ">
        افزودن دسته‌بندی جدید
    </h2>


    <form
        method="post"
        enctype="multipart/form-data"
        style="
            display:grid;
            grid-template-columns:minmax(180px,1fr) 90px minmax(180px,1fr) auto;
            gap:10px;
            align-items:center;
        ">

        <input
            type="hidden"
            name="do"
            value="add">


        <!-- نام -->

        <input
            type="text"
            name="name"
            placeholder="نام دسته‌بندی"
            required
            style="
                padding:10px 14px;
                border:1px solid var(--card-border);
                border-radius:12px;
                font-family:inherit;
            ">


        <!-- ایموجی -->

        <input
            type="text"
            name="icon"
            placeholder="🧦"
            title="ایموجی جایگزین تصویر"
            style="
                width:90px;
                text-align:center;
                padding:10px;
                border:1px solid var(--card-border);
                border-radius:12px;
                font-size:1.1rem;
            ">


        <!-- تصویر -->

        <input
            type="file"
            name="category_image"
            accept="image/jpeg,image/png,image/webp"
            title="تصویر دسته‌بندی"
            style="font-size:.78rem;">


        <!-- افزودن -->

        <button
            type="submit"
            class="btn btn-orange"
            style="
                padding:10px 22px;
                font-size:.82rem;
            ">
            + افزودن
        </button>

    </form>

</div>


<!-- =========================================================
     لیست دسته‌بندی‌ها
========================================================= -->

<div class="admin-table-wrap">

    <table class="admin-table">

        <thead>

            <tr>

                <th>تصویر</th>

                <th>نام دسته</th>

                <th>تعداد محصول</th>

                <th>وضعیت</th>

                <th style="min-width:250px;">
                    عملیات
                </th>

            </tr>

        </thead>


        <tbody>

            <?php if ($categories): ?>

                <?php foreach ($categories as $c): ?>

                    <?php

                    $imagePath = trim($c['image_path'] ?? '');
                    $icon      = trim($c['icon'] ?? '');

                    ?>


                    <tr>


                        <!-- =================================================
                             تصویر
                        ================================================== -->

                        <td>

                            <?php if ($imagePath): ?>

                                <img
                                    src="<?= htmlspecialchars($imagePath) ?>"
                                    alt="<?= htmlspecialchars($c['name']) ?>"
                                    style="
                                        width:52px;
                                        height:52px;
                                        object-fit:cover;
                                        border-radius:12px;
                                        border:1px solid var(--card-border);
                                        display:block;
                                    ">

                            <?php else: ?>

                                <span
                                    style="
                                        font-size:1.7rem;
                                        display:inline-block;
                                        width:52px;
                                        text-align:center;
                                    ">
                                    <?= htmlspecialchars($icon ?: '🧦') ?>
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- =================================================
                             نام
                        ================================================== -->

                        <td>

                            <b>
                                <?= htmlspecialchars($c['name']) ?>
                            </b>

                        </td>


                        <!-- =================================================
                             تعداد محصولات
                        ================================================== -->

                        <td>

                            <?= number_format((int)$c['product_count']) ?>

                        </td>


                        <!-- =================================================
                             وضعیت
                        ================================================== -->

                        <td>

                            <span
                                class="st-badge <?= $c['is_active']
                                                    ? 'st-converted'
                                                    : 'st-rejected'
                                                ?>">
                                <?= $c['is_active']
                                    ? 'فعال'
                                    : 'غیرفعال'
                                ?>
                            </span>

                        </td>


                        <!-- =================================================
                             عملیات
                        ================================================== -->

                        <td>

                            <div
                                style="
                                    display:flex;
                                    gap:6px;
                                    flex-wrap:wrap;
                                    align-items:center;
                                ">


                                <!-- ویرایش -->

                                <button
                                    type="button"
                                    class="btn"
                                    onclick="openEditCategory(
                                        <?= (int)$c['id'] ?>,
                                        <?= htmlspecialchars(
                                            json_encode(
                                                $c['name'],
                                                JSON_UNESCAPED_UNICODE
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>,
                                        <?= htmlspecialchars(
                                            json_encode(
                                                $c['icon'] ?? '',
                                                JSON_UNESCAPED_UNICODE
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>,
                                        <?= htmlspecialchars(
                                            json_encode(
                                                $c['image_path'] ?? '',
                                                JSON_UNESCAPED_UNICODE
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    )"
                                    style="
                                        padding:6px 12px;
                                        font-size:.72rem;
                                        background:var(--teal-100);
                                        color:var(--teal-800);
                                    ">
                                    ✏️ ویرایش
                                </button>


                                <!-- فعال / غیرفعال -->

                                <form
                                    method="post"
                                    style="display:inline;">

                                    <input
                                        type="hidden"
                                        name="do"
                                        value="toggle">

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$c['id'] ?>">

                                    <button
                                        type="submit"
                                        class="btn"
                                        style="
                                            padding:6px 12px;
                                            font-size:.72rem;
                                        ">
                                        <?= $c['is_active']
                                            ? 'غیرفعال'
                                            : 'فعال'
                                        ?>
                                    </button>

                                </form>


                                <!-- حذف -->

                                <form
                                    method="post"
                                    style="display:inline;"
                                    onsubmit="return confirm(
                                        'آیا از حذف این دسته‌بندی مطمئن هستید؟'
                                    );">

                                    <input
                                        type="hidden"
                                        name="do"
                                        value="delete">

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$c['id'] ?>">

                                    <button
                                        type="submit"
                                        class="btn"
                                        style="
                                            padding:6px 12px;
                                            font-size:.72rem;
                                            background:#fff0f0;
                                            color:#c62828;
                                        ">
                                        🗑️ حذف
                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="5"
                        style="
                            text-align:center;
                            color:var(--muted);
                            padding:30px;
                        ">
                        هنوز دسته‌بندی‌ای ثبت نشده است.
                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>


<!-- =========================================================
     Modal ویرایش
========================================================= -->

<div
    id="editCategoryModal"
    style="
        display:none;
        position:fixed;
        inset:0;
        background:rgba(0,0,0,.45);
        z-index:9999;
        align-items:center;
        justify-content:center;
        padding:20px;
    ">

    <div
        style="
            width:min(500px,100%);
            background:var(--card-bg,#fff);
            border-radius:18px;
            padding:22px;
            box-shadow:0 20px 60px rgba(0,0,0,.2);
        ">

        <h3
            style="
                margin:0 0 18px;
            ">
            ویرایش دسته‌بندی
        </h3>


        <form
            method="post"
            enctype="multipart/form-data">

            <input
                type="hidden"
                name="do"
                value="edit">

            <input
                type="hidden"
                name="id"
                id="edit_category_id">


            <!-- نام -->

            <div class="form-field">

                <label>
                    نام دسته‌بندی
                </label>

                <input
                    type="text"
                    name="name"
                    id="edit_category_name"
                    required>

            </div>


            <!-- ایموجی -->

            <div class="form-field">

                <label>
                    ایموجی
                </label>

                <input
                    type="text"
                    name="icon"
                    id="edit_category_icon"
                    placeholder="🧦">

            </div>


            <!-- تصویر فعلی -->

            <div
                id="currentCategoryImage"
                style="
                    display:none;
                    margin:12px 0;
                ">

                <label
                    style="
                        display:block;
                        margin-bottom:8px;
                    ">
                    تصویر فعلی
                </label>

                <img
                    id="currentCategoryImagePreview"
                    src=""
                    alt=""
                    style="
                        width:90px;
                        height:90px;
                        object-fit:cover;
                        border-radius:14px;
                        border:1px solid var(--card-border);
                    ">

            </div>


            <!-- تصویر جدید -->

            <div class="form-field">

                <label>
                    تغییر تصویر
                </label>

                <input
                    type="file"
                    name="category_image"
                    accept="image/jpeg,image/png,image/webp">

                <small
                    style="
                        display:block;
                        margin-top:6px;
                        color:var(--muted);
                    ">
                    اگر تصویر جدید انتخاب کنید، تصویر فعلی جایگزین می‌شود.
                </small>

            </div>


            <!-- دکمه‌ها -->

            <div
                style="
                    display:flex;
                    gap:8px;
                    justify-content:flex-end;
                    margin-top:18px;
                ">

                <button
                    type="button"
                    class="btn"
                    onclick="closeEditCategory()">
                    انصراف
                </button>

                <button
                    type="submit"
                    class="btn btn-orange">
                    ذخیره تغییرات
                </button>

            </div>

        </form>

    </div>

</div>


<script>
    function openEditCategory(id, name, icon, imagePath) {

        document.getElementById('edit_category_id').value = id;

        document.getElementById('edit_category_name').value = name;

        document.getElementById('edit_category_icon').value = icon || '';


        const imageBox = document.getElementById(
            'currentCategoryImage'
        );

        const imagePreview = document.getElementById(
            'currentCategoryImagePreview'
        );


        /*
         * نمایش تصویر فعلی
         */

        if (imagePath) {

            imagePreview.src = imagePath;

            imagePreview.alt = name;

            imageBox.style.display = 'block';

        } else {

            imagePreview.src = '';

            imageBox.style.display = 'none';

        }


        /*
         * باز کردن Modal
         */

        document.getElementById(
            'editCategoryModal'
        ).style.display = 'flex';

    }


    function closeEditCategory() {

        document.getElementById(
            'editCategoryModal'
        ).style.display = 'none';

    }


    /*
     * کلیک روی فضای بیرون Modal
     */

    document
        .getElementById('editCategoryModal')
        .addEventListener('click', function(event) {

            if (event.target === this) {

                closeEditCategory();

            }

        });


    /*
     * بستن Modal با کلید Escape
     */

    document.addEventListener('keydown', function(event) {

        if (event.key === 'Escape') {

            closeEditCategory();

        }

    });
</script>


<?php

$content = ob_get_clean();

require ROOT_PATH . '/views/layouts/admin.php';
