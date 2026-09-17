<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'بازار عمده جوراب' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { font-family: Vazirmatn, Tahoma, sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900">

    <!-- ▂▂▂ Header ▂▂▂ -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between gap-4">

            <!-- لوگو -->
            <a href="/" class="font-extrabold text-xl text-gray-900 shrink-0">
                جوراب‌لند <span class="text-blue-600">B2B</span>
            </a>

            <!-- منو -->
            <nav class="hidden md:flex items-center gap-6 text-sm font-medium">
                <a href="/" class="hover:text-blue-600">خانه</a>
                <a href="/products" class="hover:text-blue-600">محصولات</a>
                <a href="/custom-order" class="hover:text-blue-600">سفارش اختصاصی</a>
                <a href="/about" class="hover:text-blue-600">درباره ما</a>
            </nav>

            <!-- سمت چپ: سبد و ورود -->
            <div class="flex items-center gap-3">
                <a href="/cart" class="relative p-2 hover:bg-gray-100 rounded-lg" title="سبد خرید">
                    🛒
                    <span id="cart-badge" class="hidden absolute -top-1 -left-1 bg-blue-600 text-white text-[10px] w-5 h-5 rounded-full flex items-center justify-center">0</span>
                </a>
                <a href="/login" class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    ورود / ثبت‌نام
                </a>
            </div>
        </div>
    </header>

    <!-- ▂▂▂ محتوای صفحه ▂▂▂ -->
    <main class="max-w-7xl mx-auto px-4 py-8">
        <?= $content ?? '' ?>
    </main>

    <!-- ▂▂▂ Footer ▂▂▂ -->
    <footer class="bg-gray-900 text-gray-300 mt-16">
        <div class="max-w-7xl mx-auto px-4 py-10 grid md:grid-cols-3 gap-8 text-sm">
            <div>
                <div class="font-bold text-white text-lg mb-3">جوراب‌لند B2B</div>
                <p class="leading-7">تولید و پخش عمده جوراب — سفارش آنلاین، قیمت عمده، ارسال به سراسر کشور</p>
            </div>
            <div>
                <div class="font-bold text-white mb-3">دسترسی سریع</div>
                <ul class="space-y-2">
                    <li><a href="/products" class="hover:text-white">کاتالوگ محصولات</a></li>
                    <li><a href="/register" class="hover:text-white">ثبت‌نام عمده‌فروشان</a></li>
                    <li><a href="/custom-order" class="hover:text-white">سفارش تولید اختصاصی</a></li>
                </ul>
            </div>
            <div>
                <div class="font-bold text-white mb-3">تماس با ما</div>
                <ul class="space-y-2">
                    <li>☎️ ۰۲۱-۱۲۳۴۵۶۷۸</li>
                    <li>📱 ۰۹۱۲-۱۲۳۴۵۶۷</li>
                    <li>🕐 شنبه تا پنجشنبه — ۹ تا ۱۷</li>
                </ul>
            </div>
        </div>
        <div class="border-t border-gray-800 py-4 text-center text-xs text-gray-500">
            © تمامی حقوق برای جوراب‌لند محفوظ است
        </div>
    </footer>

</body>
</html>