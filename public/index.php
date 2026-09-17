<?php
$pageTitle = 'تولیدی جوراب رابطی — تولید و فروش عمده جوراب';
$pageDescription = 'خرید عمده انواع جوراب مردانه، زنانه و بچگانه با قیمت تولیدکننده و تخفیف پلکانی.';
ob_start();
?>

<!-- ▂▂▂ ۱. بنر اسلایدر اصلی (مطابق عکس ۱) ▂▂▂ -->
<section class="relative w-full rounded-[24px] lg:rounded-[32px] overflow-hidden bg-gray-100 mb-8 border border-gray-150 shadow-sm">
    <!-- عکس بنر -->
    <div class="relative h-[220px] sm:h-[320px] lg:h-[400px] w-full">
        <img src="/banner.jpg" 
             alt="تولیدی جوراب رابطی" 
             class="w-full h-full object-cover"
             onerror="this.src='https://images.unsplash.com/photo-1586350977771-b3b0abd50c82?auto=format&fit=crop&w=1600&q=80'">
        
        <!-- لایه گرادیان ملایم -->
        <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>

        <!-- دکمه ناوبری راست -->
        <button class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/90 hover:bg-white text-ink-900 flex items-center justify-center shadow-md transition" aria-label="بعدی">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
        </button>

        <!-- دکمه ناوبری چپ -->
        <button class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/90 hover:bg-white text-ink-900 flex items-center justify-center shadow-md transition" aria-label="قبلی">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
        </button>

        <!-- بج تیره گوشه پایین بنر: فروش های همواره تخفیف -->
        <div class="absolute bottom-6 right-6 bg-black/60 backdrop-blur-md text-white text-xs md:text-sm font-bold px-4 py-2 rounded-xl">
            فروش های همواره تخفیف
        </div>

        <!-- نقطه‌های اسلایدر (Pagination) -->
        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-1.5">
            <span class="w-5 h-2 rounded-full bg-accent-orange"></span>
            <span class="w-2 h-2 rounded-full bg-white/70"></span>
            <span class="w-2 h-2 rounded-full bg-white/70"></span>
        </div>
    </div>
</section>

<!-- ▂▂▂ ۲. دایره‌های دسته‌بندی استوری‌طور (مطابق عکس ۱) ▂▂▂ -->
<section class="flex items-center justify-center gap-6 sm:gap-10 py-4 mb-12 overflow-x-auto no-scrollbar">
    <?php
    $categories = [
        ['title' => 'مردانه', 'img' => 'https://images.unsplash.com/photo-1582966772680-860e372bb558?auto=format&fit=crop&w=200&q=80'],
        ['title' => 'زنانه', 'img' => 'https://images.unsplash.com/photo-1586350977771-b3b0abd50c82?auto=format&fit=crop&w=200&q=80'],
        ['title' => 'بچگانه', 'img' => 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=200&q=80'],
        ['title' => 'ورزشی', 'img' => 'https://images.unsplash.com/photo-1608256246200-53e635b5b65f?auto=format&fit=crop&w=200&q=80'],
        ['title' => 'فانتزی', 'img' => 'https://images.unsplash.com/photo-1582966773645-0d04d80a133f?auto=format&fit=crop&w=200&q=80'],
    ];
    foreach ($categories as $cat):
    ?>
        <a href="/products?cat=<?= urlencode($cat['title']) ?>" class="flex flex-col items-center gap-2 group shrink-0">
            <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full p-1 border-2 border-brand/80 group-hover:border-brand group-hover:scale-105 transition duration-300 shadow-sm">
                <img src="<?= $cat['img'] ?>" alt="<?= $cat['title'] ?>" class="w-full h-full rounded-full object-cover">
            </div>
            <span class="text-xs sm:text-sm font-bold text-ink-900 group-hover:text-brand transition">
                <?= $cat['title'] ?>
            </span>
        </a>
    <?php endforeach; ?>
</section>

<!-- ▂▂▂ ۳. بخش مدل‌های تولیدی (مطابق عکس ۲) ▂▂▂ -->
<section class="mb-16">
    <!-- هدر بخش -->
    <div class="flex items-end justify-between mb-6">
        <div>
            <h2 class="text-xl md:text-2xl font-black text-ink-900 mb-1">مدل‌های تولیدی</h2>
            <p class="text-xs md:text-sm text-ink-500">هر مدل با چند طرح و رنگ — با تخفیف پلکانی تعدادی</p>
        </div>
        <a href="/products" class="text-xs md:text-sm font-bold text-brand hover:text-brand-hover transition flex items-center gap-1">
            مشاهده همه محصولات
            <span>←</span>
        </a>
    </div>

    <!-- گرید کارت‌های محصول -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        
        <!-- کارت ۱: معدل خرگوشی -->
        <div class="bg-white rounded-2xl border border-ink-200/80 overflow-hidden hover:shadow-lg hover:border-brand/40 transition-all flex flex-col group">
            <div class="relative w-full aspect-square bg-slate-100 overflow-hidden">
                <img src="https://images.unsplash.com/photo-1582966772680-860e372bb558?auto=format&fit=crop&w=600&q=80" alt="معدل خرگوشی" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
            </div>
            <div class="p-4 flex-1 flex flex-col justify-between text-center">
                <div>
                    <h3 class="font-bold text-ink-900 text-sm mb-1 group-hover:text-brand transition">معدل خرگوشی</h3>
                    <p class="text-[11px] text-ink-400">۰ طرح ۱۰ رنگ ۱۰ سایز</p>
                </div>
                <div class="mt-4 pt-3 border-t border-ink-100">
                    <span class="text-sm font-black text-ink-700">۱,۲۰۰,۰۰۰ <span class="text-[10px] font-normal text-ink-400">تومان</span></span>
                </div>
            </div>
        </div>

        <!-- کارت ۲: جوراب مردانه کلاسیک (با بج تخفیف) -->
        <div class="bg-white rounded-2xl border border-ink-200/80 overflow-hidden hover:shadow-lg hover:border-brand/40 transition-all flex flex-col group relative">
            <div class="relative w-full aspect-square bg-slate-100 overflow-hidden">
                <span class="absolute top-2.5 right-2.5 bg-brand-light text-brand text-[10px] font-extrabold px-2.5 py-1 rounded-md z-10">
                    تخفیف تعدادی
                </span>
                <img src="https://images.unsplash.com/photo-1586350977771-b3b0abd50c82?auto=format&fit=crop&w=600&q=80" alt="جوراب مردانه کلاسیک" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
            </div>
            <div class="p-4 flex-1 flex flex-col justify-between text-center">
                <div>
                    <h3 class="font-bold text-ink-900 text-sm mb-1 group-hover:text-brand transition">جوراب مردانه کلاسیک</h3>
                    <p class="text-[11px] text-ink-400">۰ طرح ۱۰ رنگ ۱۰ سایز</p>
                </div>
                <div class="mt-4 pt-3 border-t border-ink-100 space-y-0.5">
                    <div class="text-xs font-bold text-brand">از ۹۵۰,۰۰۰ <span class="text-[10px] font-normal text-ink-400">تومان</span></div>
                    <div class="text-[10px] text-ink-400 line-through">تک‌عدد ۱,۰۰۰,۰۰۰ تومان</div>
                </div>
            </div>
        </div>

        <!-- کارت ۳: بچه گانه - سیسمونی -->
        <div class="bg-white rounded-2xl border border-ink-200/80 overflow-hidden hover:shadow-lg hover:border-brand/40 transition-all flex flex-col group">
            <div class="relative w-full aspect-square bg-slate-100 overflow-hidden">
                <img src="https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=600&q=80" alt="بچه گانه - سیسمونی" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
            </div>
            <div class="p-4 flex-1 flex flex-col justify-between text-center">
                <div>
                    <h3 class="font-bold text-ink-900 text-sm mb-1 group-hover:text-brand transition">بچه گانه - سیسمونی</h3>
                    <p class="text-[11px] text-ink-400">۰ طرح ۱۰ رنگ ۱۰ سایز</p>
                </div>
                <div class="mt-4 pt-3 border-t border-ink-100">
                    <span class="text-sm font-black text-ink-700">۱۰۰,۰۰۰ <span class="text-[10px] font-normal text-ink-400">تومان</span></span>
                </div>
            </div>
        </div>

        <!-- کارت ۴: جوراب زنانه زمستانه (با بج تخفیف) -->
        <div class="bg-white rounded-2xl border border-ink-200/80 overflow-hidden hover:shadow-lg hover:border-brand/40 transition-all flex flex-col group relative">
            <div class="relative w-full aspect-square bg-slate-100 overflow-hidden">
                <span class="absolute top-2.5 right-2.5 bg-brand-light text-brand text-[10px] font-extrabold px-2.5 py-1 rounded-md z-10">
                    تخفیف تعدادی
                </span>
                <img src="https://images.unsplash.com/photo-1608256246200-53e635b5b65f?auto=format&fit=crop&w=600&q=80" alt="جوراب زنانه زمستانه" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
            </div>
            <div class="p-4 flex-1 flex flex-col justify-between text-center">
                <div>
                    <h3 class="font-bold text-ink-900 text-sm mb-1 group-hover:text-brand transition">جوراب زنانه زمستانه</h3>
                    <p class="text-[11px] text-ink-400">۴ طرح ۲۰ رنگ ۲۰ سایز</p>
                </div>
                <div class="mt-4 pt-3 border-t border-ink-100 space-y-0.5">
                    <div class="text-xs font-bold text-brand">از ۳۵,۰۰۰ <span class="text-[10px] font-normal text-ink-400">تومان</span></div>
                    <div class="text-[10px] text-ink-400 line-through">تک‌عدد ۵۰,۰۰۰ تومان</div>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- ▂▂▂ ۴. بخش پیگیری سفارش (دقیقاً مطابق عکس ۳ و ۴) ▂▂▂ -->
<section class="mb-12 bg-white rounded-3xl p-6 lg:p-12 border border-ink-200/70 shadow-sm">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
        
        <!-- سمت چپ (کارت مراحل پیشرفت سفارش) -->
        <div class="lg:col-span-6 order-2 lg:order-1">
            <div class="bg-white rounded-2xl border border-ink-200 p-6 shadow-md max-w-md mx-auto">
                <div class="flex items-center justify-between text-xs font-bold text-ink-500 mb-2">
                    <span class="text-brand">در حال تولید</span>
                    <span>نمونه وضعیت سفارش</span>
                </div>
                
                <!-- پروگرس‌بار ۵۰ درصد -->
                <div class="w-full bg-ink-100 h-1.5 rounded-full overflow-hidden mb-2">
                    <div class="bg-brand h-full w-1/2 rounded-full"></div>
                </div>
                <div class="flex justify-between text-[10px] text-ink-400 mb-6">
                    <span>۵۰٪ تکمیل شده</span>
                    <span>مرحله ۳ از ۶</span>
                </div>

                <!-- لیست مراحل ۴ تایی -->
                <div class="space-y-4">
                    <!-- مرحله ۱: تکمیل شده -->
                    <div class="flex items-center justify-between">
                        <div class="text-right">
                            <div class="text-xs font-bold text-ink-900">ثبت سفارش</div>
                            <div class="text-[10px] text-ink-400">تکمیل شده</div>
                        </div>
                        <div class="w-7 h-7 rounded-full bg-brand text-white flex items-center justify-center text-xs">
                            ✓
                        </div>
                    </div>

                    <!-- مرحله ۲: تکمیل شده -->
                    <div class="flex items-center justify-between">
                        <div class="text-right">
                            <div class="text-xs font-bold text-ink-900">آماده‌سازی</div>
                            <div class="text-[10px] text-ink-400">تکمیل شده</div>
                        </div>
                        <div class="w-7 h-7 rounded-full bg-brand text-white flex items-center justify-center text-xs">
                            ✓
                        </div>
                    </div>

                    <!-- مرحله ۳: جاری (نارنجی) -->
                    <div class="flex items-center justify-between">
                        <div class="text-right">
                            <div class="text-xs font-bold text-ink-900">تولید</div>
                            <div class="text-[10px] text-accent-orange font-bold">مرحله فعلی</div>
                        </div>
                        <div class="w-7 h-7 rounded-full bg-accent-orange text-white flex items-center justify-center text-xs font-bold shadow-sm">
                            ۳
                        </div>
                    </div>

                    <!-- مرحله ۴: در انتظار -->
                    <div class="flex items-center justify-between">
                        <div class="text-right">
                            <div class="text-xs font-bold text-ink-400">کنترل کیفیت</div>
                            <div class="text-[10px] text-ink-400">در انتظار</div>
                        </div>
                        <div class="w-7 h-7 rounded-full bg-ink-100 text-ink-400 flex items-center justify-center text-xs font-bold">
                            ۴
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- سمت راست (متن و فرم جستجوی سفارش) -->
        <div class="lg:col-span-6 order-1 lg:order-2 text-right">
            <span class="text-xs font-black text-brand tracking-wide mb-3 inline-block">پیگیری سفارش</span>
            <h2 class="text-2xl md:text-4xl font-black text-ink-900 leading-snug mb-4">
                همیشه بدانید<br>
                <span class="text-ink-900">سفارشتان کجاست.</span>
            </h2>
            <p class="text-xs md:text-sm text-ink-500 leading-7 mb-8">
                شماره سفارش خود را وارد کنید و وضعیت سفارش را از ثبت تا ارسال مشاهده کنید.
            </p>

            <!-- باکس اینپوت + دکمه -->
            <form action="/tracking" method="GET" class="flex items-center gap-2 max-w-lg">
                <input type="text"
                       name="order_id"
                       placeholder="مثلاً ORD-175000000000-123"
                       class="flex-1 bg-white border border-ink-200 rounded-2xl px-4 py-3 text-xs md:text-sm text-ink-900 focus:outline-none focus:border-brand transition">
                <button type="submit"
                        class="bg-brand hover:bg-brand-hover text-white font-bold px-6 py-3 rounded-2xl text-xs md:text-sm flex items-center gap-2 transition shadow-sm shrink-0">
                    <span>پیگیری</span>
                    <span>←</span>
                </button>
            </form>
        </div>

    </div>
</section>

<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layouts/main.php';
