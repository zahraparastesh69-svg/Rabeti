<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';

$errors = [];
$saved  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name    = trim($_POST['name'] ?? '');
    $mobile  = trim($_POST['mobile'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (mb_strlen($name) < 3)                $errors['name'] = 'نام را کامل وارد کنید';
    if (!preg_match('/^09\d{9}$/', $mobile)) $errors['mobile'] = 'شماره موبایل معتبر نیست';
    if (mb_strlen($message) < 10)            $errors['message'] = 'پیام را کامل‌تر بنویسید';

    if (!$errors) {
        $stmt = db()->prepare("
            INSERT INTO contact_messages (name, mobile, subject, message)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$name, $mobile, $subject ?: null, $message]);
        $saved = true;
    }
}

$pageTitle = 'تماس با ما | جوراب رابطی';
ob_start();
?>

<section class="page-head">
    <h1 class="section-title">تماس با ما</h1>
    <p class="section-sub">شنبه تا پنجشنبه، ۹ تا ۱۷ — پاسخ در کمترین زمان</p>
</section>

<div class="contact-grid">

    <!-- اطلاعات تماس -->
    <div class="contact-info">
        <div class="contact-item">
            <span class="contact-icon">☎️</span>
            <div>
                <b>تلفن ثابت</b>
                <p dir="ltr" class="ltr-num">021-12345678</p>
            </div>
        </div>
        <div class="contact-item">
            <span class="contact-icon">📱</span>
            <div>
                <b>موبایل / واتساپ</b>
                <p dir="ltr" class="ltr-num">0912-1234567</p>
            </div>
        </div>
        <div class="contact-item">
            <span class="contact-icon">📍</span>
            <div>
                <b>آدرس کارخانه</b>
                <p>تهران، شهرک صنعتی — خیابان تولید، پلاک ۱۲</p>
            </div>
        </div>
    </div>

    <!-- فرم -->
    <div class="contact-form-wrap">
        <?php if ($saved): ?>
            <div class="empty-state">
                <div class="empty-icon">✅</div>
                <h2>پیام شما ثبت شد</h2>
                <a href="/" class="btn btn-orange">بازگشت</a>
            </div>
        <?php else: ?>
            <form class="co-form" method="post" action="/contact" novalidate>
                <?php if ($errors): ?>
                    <div class="form-alert">⚠️ لطفاً خطاها را اصلاح کنید</div>
                <?php endif; ?>

                <div class="form-field">
                    <label>نام <i>*</i></label>
                    <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    <?php if (isset($errors['name'])): ?><span class="field-error"><?= $errors['name'] ?></span><?php endif; ?>
                </div>

                <div class="form-field">
                    <label>موبایل <i>*</i></label>
                    <input type="tel" name="mobile" dir="ltr" value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>">
                    <?php if (isset($errors['mobile'])): ?><span class="field-error"><?= $errors['mobile'] ?></span><?php endif; ?>
                </div>

                <div class="form-field">
                    <label>موضوع</label>
                    <input type="text" name="subject" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                        placeholder="مثلاً: استعلام نمونه">
                </div>

                <div class="form-field">
                    <label>پیام <i>*</i></label>
                    <textarea name="message" rows="5"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    <?php if (isset($errors['message'])): ?><span class="field-error"><?= $errors['message'] ?></span><?php endif; ?>
                </div>

                <button type="submit" class="btn btn-orange co-submit">ارسال پیام ←</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/main.php';
