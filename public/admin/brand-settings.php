<?php
/* Auto-detect bootstrap (local + host) */
$__bp = [
    __DIR__ . '/../../sock-b2b/config/bootstrap.php',
    __DIR__ . '/../../config/bootstrap.php',
];
foreach ($__bp as $__b) { if (is_file($__b)) { require_once $__b; break; } }
unset($__bp, $__b);
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';
require_once ROOT_PATH . '/config/settings.php';
require_once ROOT_PATH . '/config/upload.php';

require_admin();

$errors  = [];
$success = '';

/* ═══ پردازش فرم ═══ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $labels = [
        'site_logo'          => 'لوگو اصلی',
        'site_favicon'       => 'Favicon',
        'og_default_image'   => 'og:image',
        'custom_order_image' => 'تصویر سفارش اختصاصی',
    ];

    $changed = 0;

    foreach ($labels as $key => $label) {

        if (empty($_FILES[$key]['name'])) {
            continue;
        }

        $result = upload_image_ex($_FILES[$key], 'brand');

        if (!$result['ok']) {
            $errors[] = $label . ': ' . $result['error'];
            continue;
        }

        /* حذف فایل قدیمی اگه توی uploads/brand بود */
        $oldPath = Setting::get($key, '');
        if ($oldPath && str_starts_with($oldPath, '/uploads/brand/')) {
            $oldFull = PUBLIC_PATH . $oldPath;
            if (is_file($oldFull)) {
                @unlink($oldFull);
            }
        }

        Setting::set($key, $result['path']);
        $changed++;
    }

    if (!$errors && $changed > 0) {
        $success = "✅ $changed تصویر با موفقیت ذخیره شد";
    } elseif (!$errors && $changed === 0) {
        $errors[] = 'هیچ فایلی انتخاب نشده بود';
    }
}

/* ═══ مقادیر فعلی ═══ */
$images = [
    'site_logo' => [
        'label' => 'لوگو اصلی هدر',
        'hint'  => 'JPG، PNG یا WebP — حداکثر ۲MB — مربع توصیه می‌شود',
        'value' => Setting::get('site_logo', '/Image/logo3.jpg'),
    ],
    'site_favicon' => [
        'label' => 'Favicon',
        'hint'  => 'آیکون کوچک تب مرورگر — مربع (مثلاً 512×512)',
        'value' => Setting::get('site_favicon', '/Image/logo3.jpg'),
    ],
    'og_default_image' => [
        'label' => 'og:image پیش‌فرض',
        'hint'  => 'تصویری که در اشتراک‌گذاری واتساپ/تلگرام نمایش داده می‌شود',
        'value' => Setting::get('og_default_image', '/Image/logo3.jpg'),
    ],
    'custom_order_image' => [
        'label' => 'تصویر سفارش اختصاصی',
        'hint'  => 'تصویر بخش «سفارش اختصاصی» در صفحهٔ اصلی',
        'value' => Setting::get('custom_order_image', '/Image/1.webp'),
    ],
];

$adminPageTitle = 'تنظیمات برند';
$activeMenu     = 'brand-settings';

ob_start();
?>

<style>
    .bs-page {
        padding: 24px;
        max-width: 1100px;
        margin: 0 auto;
    }

    .bs-head {
        margin-bottom: 24px;
    }

    .bs-head h1 {
        margin: 0 0 6px;
        font-size: 22px;
        color: #1f2937;
    }

    .bs-head p {
        margin: 0;
        color: #6b7280;
        font-size: 13px;
    }

    .bs-alert {
        padding: 14px 18px;
        border-radius: 12px;
        margin-bottom: 20px;
        font-size: 13px;
        line-height: 1.8;
    }

    .bs-alert-success {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #065f46;
    }

    .bs-alert-error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }

    .bs-alert-error ul {
        margin: 6px 0 0;
        padding-right: 20px;
    }

    .bs-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 18px;
        margin-bottom: 24px;
    }

    .bs-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 18px;
        transition: border-color .2s, box-shadow .2s;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .bs-card:hover {
        border-color: #d1d5db;
        box-shadow: 0 4px 16px rgba(0, 0, 0, .04);
    }

    .bs-card-head strong {
        display: block;
        font-size: 14px;
        color: #1f2937;
        margin-bottom: 3px;
    }

    .bs-card-head small {
        display: block;
        color: #6b7280;
        font-size: 11px;
        line-height: 1.6;
    }

    .bs-preview {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        height: 140px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
    }

    .bs-preview img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        display: block;
    }

    .bs-preview.is-empty::after {
        content: "⚠️ تصویر لود نشد";
        color: #9ca3af;
        font-size: 12px;
    }

    .bs-preview.is-empty img {
        display: none;
    }

    .bs-upload {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .bs-upload input[type="file"] {
        display: none;
    }

    .bs-upload-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        background: #f3f4f6;
        color: #374151;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: background .15s;
        white-space: nowrap;
    }

    .bs-upload-btn:hover {
        background: #e5e7eb;
    }

    .bs-upload-btn svg {
        width: 14px;
        height: 14px;
    }

    .bs-filename {
        font-size: 11px;
        color: #6b7280;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        flex: 1;
        direction: ltr;
        text-align: left;
    }

    .bs-path {
        font-size: 10px;
        color: #9ca3af;
        direction: ltr;
        text-align: left;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-family: monospace;
        padding-top: 8px;
        border-top: 1px dashed #e5e7eb;
    }

    .bs-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
    }

    .bs-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 26px;
        border: none;
        border-radius: 10px;
        font-family: inherit;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        transition: all .15s;
    }

    .bs-btn-primary {
        background: #2a5f78;
        color: #fff;
    }

    .bs-btn-primary:hover {
        background: #1f4a5e;
    }

    .bs-btn-primary:active {
        transform: scale(.98);
    }

    @media (max-width: 720px) {
        .bs-grid {
            grid-template-columns: 1fr;
        }

        .bs-page {
            padding: 16px;
        }
    }
</style>

<section class="bs-page">

    <header class="bs-head">
        <h1>🎨 تنظیمات برند</h1>
        <p>لوگو، favicon و تصاویر اشتراک‌گذاری سایت را از اینجا مدیریت کنید</p>
    </header>

    <?php if ($success): ?>
        <div class="bs-alert bs-alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="bs-alert bs-alert-error">
            <strong>خطاها:</strong>
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="bs-grid">
            <?php foreach ($images as $key => $img): ?>
                <div class="bs-card">
                    <div class="bs-card-head">
                        <strong><?= htmlspecialchars($img['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <small><?= htmlspecialchars($img['hint'], ENT_QUOTES, 'UTF-8') ?></small>
                    </div>

                    <div class="bs-preview" id="preview_<?= $key ?>">
                        <img src="<?= htmlspecialchars($img['value'], ENT_QUOTES, 'UTF-8') ?>"
                            alt=""
                            onerror="this.parentElement.classList.add('is-empty')">
                    </div>

                    <div class="bs-upload">
                        <input type="file"
                            name="<?= $key ?>"
                            id="file_<?= $key ?>"
                            accept="image/jpeg,image/png,image/webp"
                            onchange="bsFileChange('<?= $key ?>')">

                        <label for="file_<?= $key ?>" class="bs-upload-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                            انتخاب فایل
                        </label>

                        <span class="bs-filename" id="name_<?= $key ?>">فایلی انتخاب نشده</span>
                    </div>

                    <div class="bs-path"><?= htmlspecialchars($img['value'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="bs-actions">
            <button type="submit" class="bs-btn bs-btn-primary">
                💾 ذخیره تغییرات
            </button>
        </div>
    </form>

</section>

<script>
    function bsFileChange(key) {
        var input = document.getElementById('file_' + key);
        var nameEl = document.getElementById('name_' + key);
        var preview = document.getElementById('preview_' + key);

        if (!input.files || !input.files[0]) {
            nameEl.textContent = 'فایلی انتخاب نشده';
            return;
        }

        var file = input.files[0];
        nameEl.textContent = file.name;

        var reader = new FileReader();
        reader.onload = function(e) {
            preview.classList.remove('is-empty');
            var img = preview.querySelector('img');
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
</script>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/admin.php';
