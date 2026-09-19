<?php

require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/config/upload.php';
require_once ROOT_PATH . '/config/auth.php';

require_admin();

$pdo = db();

$message = '';
$error   = '';

/*
|--------------------------------------------------------------------------
| عملیات فرم
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | افزودن دسته‌بندی
    |--------------------------------------------------------------------------
    */
    if ($action === 'add') {

        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? '🧦');

        if ($name === '') {
            $error = 'نام دسته‌بندی را وارد کنید.';
        } else {

            // تصویر بزرگ
            $largeImage = upload_image(
                $_FILES['category_image'] ?? [],
                'categories'
            );

            // تصویر کوچک
            $smallImage = upload_image(
                $_FILES['category_small_image'] ?? [],
                'categories'
            );

            $stmt = $pdo->prepare("
                INSERT INTO categories
                (
                    parent_id,
                    name,
                    slug,
                    icon,
                    image_path,
                    small_image_path,
                    sort_order,
                    is_active
                )
                VALUES
                (
                    NULL,
                    :name,
                    :slug,
                    :icon,
                    :image_path,
                    :small_image_path,
                    :sort_order,
                    1
                )
            ");

            $stmt->execute([
                ':name'             => $name,
                ':slug'             => create_slug($name),
                ':icon'             => $icon !== '' ? $icon : '🧦',
                ':image_path'       => $largeImage,
                ':small_image_path' => $smallImage,
                ':sort_order'       => (int)($_POST['sort_order'] ?? 0),
            ]);

            $message = 'دسته‌بندی با موفقیت اضافه شد.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ویرایش دسته‌بندی
    |--------------------------------------------------------------------------
    */ elseif ($action === 'edit') {

        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? '🧦');

        if ($id <= 0 || $name === '') {
            $error = 'اطلاعات دسته‌بندی صحیح نیست.';
        } else {

            $stmt = $pdo->prepare("
                SELECT
                    image_path,
                    small_image_path,
                    icon
                FROM categories
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$id]);

            $current = $stmt->fetch();

            if (!$current) {
                $error = 'دسته‌بندی پیدا نشد.';
            } else {

                /*
                 * اگر عکس جدید آپلود نشده باشد،
                 * عکس قبلی حفظ می‌شود.
                 */

                $largeImage = $current['image_path'] ?? null;

                if (
                    isset($_FILES['category_image']) &&
                    ($_FILES['category_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
                ) {
                    $uploaded = upload_image(
                        $_FILES['category_image'],
                        'categories'
                    );

                    if ($uploaded) {
                        $largeImage = $uploaded;
                    }
                }

                $smallImage = $current['small_image_path'] ?? null;

                if (
                    isset($_FILES['category_small_image']) &&
                    ($_FILES['category_small_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
                ) {
                    $uploaded = upload_image(
                        $_FILES['category_small_image'],
                        'categories'
                    );

                    if ($uploaded) {
                        $smallImage = $uploaded;
                    }
                }

                $stmt = $pdo->prepare("
                    UPDATE categories
                    SET
                        name = :name,
                        slug = :slug,
                        icon = :icon,
                        image_path = :image_path,
                        small_image_path = :small_image_path,
                        sort_order = :sort_order
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':name'             => $name,
                    ':slug'             => create_slug($name),
                    ':icon'             => $icon !== '' ? $icon : '🧦',
                    ':image_path'       => $largeImage,
                    ':small_image_path' => $smallImage,
                    ':sort_order'       => (int)($_POST['sort_order'] ?? 0),
                    ':id'               => $id,
                ]);

                $message = 'دسته‌بندی با موفقیت ویرایش شد.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | فعال / غیرفعال
    |--------------------------------------------------------------------------
    */ elseif ($action === 'toggle') {

        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {

            $stmt = $pdo->prepare("
                UPDATE categories
                SET is_active = IF(is_active = 1, 0, 1)
                WHERE id = ?
            ");

            $stmt->execute([$id]);

            $message = 'وضعیت دسته‌بندی تغییر کرد.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | حذف
    |--------------------------------------------------------------------------
    */ elseif ($action === 'delete') {

        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {

            // بررسی اینکه محصولی به این دسته متصل نباشد
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM products
                WHERE category_id = ?
            ");

            $stmt->execute([$id]);

            $productCount = (int)$stmt->fetchColumn();

            if ($productCount > 0) {

                $error = 'این دسته‌بندی دارای محصول است و امکان حذف آن وجود ندارد.';
            } else {

                $stmt = $pdo->prepare("
                    DELETE FROM categories
                    WHERE id = ?
                ");

                $stmt->execute([$id]);

                $message = 'دسته‌بندی حذف شد.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| ساخت Slug
|--------------------------------------------------------------------------
*/

function create_slug(string $text): string
{
    $slug = trim($text);

    $slug = preg_replace('/\s+/u', '-', $slug);
    $slug = preg_replace('/[^\p{L}\p{N}\-_]+/u', '', $slug);
    $slug = trim($slug, '-');

    return strtolower($slug);
}

/*
|--------------------------------------------------------------------------
| دریافت دسته‌بندی‌ها
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
    ORDER BY sort_order ASC, id ASC
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>مدیریت دسته‌بندی‌ها</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f8f8;
            color: #16363a;
            font-family:
                Tahoma,
                Arial,
                sans-serif;
        }

        .page {
            max-width: 1250px;
            margin: 0 auto;
            padding: 30px 20px 60px;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-title h1 {
            margin: 0 0 8px;
            font-size: 26px;
            color: #00666b;
        }

        .page-title p {
            margin: 0;
            color: #718285;
            font-size: 14px;
        }

        .panel {
            background: #fff;
            border-radius: 22px;
            padding: 25px;
            box-shadow:
                0 10px 35px rgba(0, 70, 75, .08);
            border: 1px solid #e5eeee;
            margin-bottom: 25px;
        }

        .panel-title {
            margin: 0 0 20px;
            color: #00666b;
            font-size: 19px;
        }

        .form-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        .field label {
            font-size: 13px;
            font-weight: 700;
            color: #456165;
        }

        .field input {
            width: 100%;
            height: 46px;
            border: 1px solid #d6e3e4;
            border-radius: 12px;
            padding: 0 13px;
            font-family: inherit;
            outline: none;
            background: #fbfdfd;
        }

        .field input:focus {
            border-color: #00666b;
            box-shadow:
                0 0 0 3px rgba(0, 102, 107, .08);
        }

        .upload-box {
            border: 1px dashed #aac8ca;
            border-radius: 15px;
            padding: 15px;
            background: #f8fbfb;
        }

        .upload-box input {
            height: auto;
            padding: 10px;
            background: #fff;
        }

        .upload-help {
            margin-top: 7px;
            font-size: 11px;
            color: #839294;
        }

        .btn {
            border: 0;
            border-radius: 12px;
            padding: 11px 20px;
            cursor: pointer;
            font-family: inherit;
            font-weight: 700;
        }

        .btn-primary {
            background: #00666b;
            color: #fff;
        }

        .btn-primary:hover {
            background: #00565a;
        }

        .btn-orange {
            background: #f28c28;
            color: #fff;
        }

        .btn-light {
            background: #edf4f4;
            color: #31575b;
        }

        .btn-danger {
            background: #fff0f0;
            color: #b42318;
        }

        .form-actions {
            margin-top: 20px;
            display: flex;
            justify-content: flex-start;
            gap: 10px;
        }

        .alert {
            border-radius: 13px;
            padding: 13px 16px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #eaf8f1;
            color: #147344;
            border: 1px solid #c9ecd9;
        }

        .alert-error {
            background: #fff0f0;
            color: #b42318;
            border: 1px solid #f4cccc;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th,
        td {
            padding: 14px 10px;
            text-align: right;
            border-bottom: 1px solid #edf2f2;
            vertical-align: middle;
        }

        th {
            color: #527073;
            font-size: 12px;
            background: #f7fafa;
        }

        td {
            font-size: 13px;
        }

        .status {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
        }

        .status-active {
            background: #e9f8f0;
            color: #147344;
        }

        .status-inactive {
            background: #f3f4f4;
            color: #6b7779;
        }

        .category-images {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .large-thumb {
            width: 82px;
            height: 58px;
            border-radius: 12px;
            overflow: hidden;
            background: #edf4f4;
            border: 1px solid #dce8e9;
        }

        .large-thumb img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }

        .small-thumb {
            width: 46px;
            height: 46px;
            border-radius: 11px;
            overflow: hidden;
            background: #fff;
            border: 2px solid #dce8e9;
            display: grid;
            place-items: center;
            font-size: 21px;
        }

        .small-thumb img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }

        .actions {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
        }

        .actions form {
            margin: 0;
        }

        .modal {
            position: fixed;
            inset: 0;
            background: rgba(5, 35, 38, .58);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 1000;
        }

        .modal.active {
            display: flex;
        }

        .modal-box {
            width: min(700px, 100%);
            max-height: 92vh;
            overflow-y: auto;
            background: #fff;
            border-radius: 24px;
            padding: 25px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, .2);
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
        }

        .modal-header h2 {
            margin: 0;
            color: #00666b;
            font-size: 20px;
        }

        .close-modal {
            width: 38px;
            height: 38px;
            border: 0;
            border-radius: 50%;
            background: #eef5f5;
            color: #416164;
            cursor: pointer;
            font-size: 20px;
        }

        .current-images {
            display: flex;
            gap: 18px;
            margin-top: 10px;
            margin-bottom: 5px;
        }

        .current-image-box {
            display: flex;
            flex-direction: column;
            gap: 7px;
            color: #708083;
            font-size: 11px;
        }

        .current-large {
            width: 130px;
            height: 90px;
            border-radius: 13px;
            object-fit: cover;
            border: 1px solid #dce7e8;
        }

        .current-small {
            width: 75px;
            height: 75px;
            border-radius: 13px;
            object-fit: cover;
            border: 1px solid #dce7e8;
        }

        @media (max-width: 700px) {

            .page {
                padding: 18px 12px 40px;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .field.full {
                grid-column: auto;
            }

            .panel {
                padding: 17px;
                border-radius: 18px;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
            }

            .modal-box {
                padding: 18px;
                border-radius: 19px;
            }
        }
    </style>

</head>

<body>

    <div class="page">

        <div class="page-header">

            <div class="page-title">

                <h1>مدیریت دسته‌بندی‌ها</h1>

                <p>
                    مدیریت تصویر بزرگ، تصویر کوچک و آیکون دسته‌بندی‌ها
                </p>

            </div>

        </div>


        <?php if ($message): ?>

            <div class="alert alert-success">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="alert alert-error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>


        <!-- افزودن دسته‌بندی -->

        <section class="panel">

            <h2 class="panel-title">
                افزودن دسته‌بندی جدید
            </h2>

            <form
                method="post"
                enctype="multipart/form-data">

                <input
                    type="hidden"
                    name="action"
                    value="add">

                <div class="form-grid">

                    <div class="field">

                        <label>
                            نام دسته‌بندی
                        </label>

                        <input
                            type="text"
                            name="name"
                            placeholder="مثلاً مردانه"
                            required>

                    </div>


                    <div class="field">

                        <label>
                            ایموجی جایگزین
                        </label>

                        <input
                            type="text"
                            name="icon"
                            value="🧦"
                            placeholder="🧦">

                    </div>


                    <div class="field">

                        <label>
                            تصویر بزرگ دسته‌بندی
                        </label>

                        <div class="upload-box">

                            <input
                                type="file"
                                name="category_image"
                                accept="image/jpeg,image/png,image/webp">

                            <div class="upload-help">
                                این تصویر پس‌زمینه و تصویر اصلی کارت خواهد بود.
                            </div>

                        </div>

                    </div>


                    <div class="field">

                        <label>
                            تصویر کوچک دسته‌بندی
                        </label>

                        <div class="upload-box">

                            <input
                                type="file"
                                name="category_small_image"
                                accept="image/jpeg,image/png,image/webp">

                            <div class="upload-help">
                                این تصویر داخل قاب کوچک روی کارت نمایش داده می‌شود.
                            </div>

                        </div>

                    </div>


                    <div class="field">

                        <label>
                            ترتیب نمایش
                        </label>

                        <input
                            type="number"
                            name="sort_order"
                            value="0">

                    </div>

                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn btn-primary">
                        افزودن دسته‌بندی
                    </button>

                </div>

            </form>

        </section>


        <!-- لیست -->

        <section class="panel">

            <h2 class="panel-title">
                دسته‌بندی‌های موجود
            </h2>


            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>

                            <th>تصاویر</th>

                            <th>نام</th>

                            <th>Slug</th>

                            <th>ترتیب</th>

                            <th>وضعیت</th>

                            <th>عملیات</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach ($categories as $c): ?>

                            <?php

                            $largeImage = trim(
                                $c['image_path'] ?? ''
                            );

                            $smallImage = trim(
                                $c['small_image_path'] ?? ''
                            );

                            $icon = trim(
                                $c['icon'] ?? '🧦'
                            );

                            ?>

                            <tr>

                                <td>

                                    <div class="category-images">

                                        <div class="large-thumb">

                                            <?php if ($largeImage): ?>

                                                <img
                                                    src="<?= htmlspecialchars($largeImage, ENT_QUOTES, 'UTF-8') ?>"
                                                    alt="">

                                            <?php else: ?>

                                                <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>

                                            <?php endif; ?>

                                        </div>


                                        <div class="small-thumb">

                                            <?php if ($smallImage): ?>

                                                <img
                                                    src="<?= htmlspecialchars($smallImage, ENT_QUOTES, 'UTF-8') ?>"
                                                    alt="">

                                            <?php else: ?>

                                                <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                </td>


                                <td>

                                    <strong>
                                        <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </strong>

                                </td>


                                <td>

                                    <?= htmlspecialchars($c['slug'], ENT_QUOTES, 'UTF-8') ?>

                                </td>


                                <td>

                                    <?= (int)$c['sort_order'] ?>

                                </td>


                                <td>

                                    <?php if ((int)$c['is_active'] === 1): ?>

                                        <span class="status status-active">
                                            فعال
                                        </span>

                                    <?php else: ?>

                                        <span class="status status-inactive">
                                            غیرفعال
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <div class="actions">

                                        <button
                                            type="button"
                                            class="btn btn-light"
                                            onclick='openEditCategory(
                                        <?= (int)$c['id'] ?>,
                                        <?= json_encode($c['name'], JSON_UNESCAPED_UNICODE) ?>,
                                        <?= json_encode($icon, JSON_UNESCAPED_UNICODE) ?>,
                                        <?= json_encode($largeImage, JSON_UNESCAPED_UNICODE) ?>,
                                        <?= json_encode($smallImage, JSON_UNESCAPED_UNICODE) ?>,
                                        <?= (int)$c['sort_order'] ?>
                                    )'>
                                            ویرایش
                                        </button>


                                        <form method="post">

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="toggle">

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$c['id'] ?>">

                                            <button
                                                type="submit"
                                                class="btn btn-orange">
                                                <?= (int)$c['is_active'] === 1
                                                    ? 'غیرفعال'
                                                    : 'فعال'
                                                ?>
                                            </button>

                                        </form>


                                        <form
                                            method="post"
                                            onsubmit="return confirm('آیا از حذف این دسته‌بندی مطمئن هستید؟');">

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete">

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$c['id'] ?>">

                                            <button
                                                type="submit"
                                                class="btn btn-danger">
                                                حذف
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>


                        <?php if (!$categories): ?>

                            <tr>

                                <td
                                    colspan="6"
                                    style="text-align:center;padding:35px;color:#839294;">
                                    هنوز دسته‌بندی‌ای ثبت نشده است.
                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </div>


    <!-- Modal ویرایش -->

    <div
        class="modal"
        id="editModal">

        <div class="modal-box">

            <div class="modal-header">

                <h2>
                    ویرایش دسته‌بندی
                </h2>

                <button
                    type="button"
                    class="close-modal"
                    onclick="closeEditModal()">
                    ×
                </button>

            </div>


            <form
                method="post"
                enctype="multipart/form-data">

                <input
                    type="hidden"
                    name="action"
                    value="edit">

                <input
                    type="hidden"
                    name="id"
                    id="edit_id">


                <div class="form-grid">

                    <div class="field">

                        <label>
                            نام دسته‌بندی
                        </label>

                        <input
                            type="text"
                            name="name"
                            id="edit_name"
                            required>

                    </div>


                    <div class="field">

                        <label>
                            ایموجی جایگزین
                        </label>

                        <input
                            type="text"
                            name="icon"
                            id="edit_icon">

                    </div>


                    <div class="field full">

                        <label>
                            تصاویر فعلی
                        </label>

                        <div
                            class="current-images"
                            id="currentImages">
                        </div>

                    </div>


                    <div class="field">

                        <label>
                            تغییر تصویر بزرگ
                        </label>

                        <div class="upload-box">

                            <input
                                type="file"
                                name="category_image"
                                accept="image/jpeg,image/png,image/webp">

                            <div class="upload-help">
                                در صورت انتخاب، تصویر بزرگ قبلی جایگزین می‌شود.
                            </div>

                        </div>

                    </div>


                    <div class="field">

                        <label>
                            تغییر تصویر کوچک
                        </label>

                        <div class="upload-box">

                            <input
                                type="file"
                                name="category_small_image"
                                accept="image/jpeg,image/png,image/webp">

                            <div class="upload-help">
                                در صورت انتخاب، تصویر کوچک قبلی جایگزین می‌شود.
                            </div>

                        </div>

                    </div>


                    <div class="field">

                        <label>
                            ترتیب نمایش
                        </label>

                        <input
                            type="number"
                            name="sort_order"
                            id="edit_sort_order"
                            value="0">

                    </div>

                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn btn-primary">
                        ذخیره تغییرات
                    </button>

                    <button
                        type="button"
                        class="btn btn-light"
                        onclick="closeEditModal()">
                        انصراف
                    </button>

                </div>

            </form>

        </div>

    </div>


    <script>
        function openEditCategory(
            id,
            name,
            icon,
            imagePath,
            smallImagePath,
            sortOrder
        ) {

            document.getElementById('edit_id').value = id;

            document.getElementById('edit_name').value = name;

            document.getElementById('edit_icon').value = icon;

            document.getElementById('edit_sort_order').value = sortOrder;


            const currentImages =
                document.getElementById('currentImages');


            let html = '';


            if (imagePath) {

                html += `
            <div class="current-image-box">

                <span>
                    تصویر بزرگ
                </span>

                <img
                    class="current-large"
                    src="${escapeHtml(imagePath)}"
                    alt=""
                >

            </div>
        `;

            }


            if (smallImagePath) {

                html += `
            <div class="current-image-box">

                <span>
                    تصویر کوچک
                </span>

                <img
                    class="current-small"
                    src="${escapeHtml(smallImagePath)}"
                    alt=""
                >

            </div>
        `;

            }


            if (!html) {

                html = `
            <div
                style="
                    color:#839294;
                    font-size:13px;
                "
            >
                تصویری برای این دسته‌بندی ثبت نشده است.
            </div>
        `;

            }


            currentImages.innerHTML = html;


            document
                .getElementById('editModal')
                .classList.add('active');
        }


        function closeEditModal() {

            document
                .getElementById('editModal')
                .classList.remove('active');
        }


        document
            .getElementById('editModal')
            .addEventListener('click', function(event) {

                if (event.target === this) {
                    closeEditModal();
                }

            });


        function escapeHtml(value) {

            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>

</body>

</html>