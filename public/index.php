<?php
$pageTitle = 'بازار عمده جوراب | جوراب‌لند B2B';
ob_start();
?>

<!-- =====================================================
     HERO
===================================================== -->

<section class="relative overflow-hidden mb-14">

    <div class="relative bg-[#111111] rounded-[28px] min-h-[500px] md:min-h-[560px] overflow-hidden">

        <!-- Decorative 3D circles -->

        <div class="absolute -top-32 -left-32 w-80 h-80 rounded-full bg-[#E8A83E]/20 blur-3xl"></div>

        <div class="absolute -bottom-40 right-10 w-96 h-96 rounded-full bg-white/[0.04] blur-3xl"></div>


        <!-- Grid texture -->

        <div
            class="absolute inset-0 opacity-[0.035]"
            style="
                background-image:
                linear-gradient(rgba(255,255,255,.5) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.5) 1px, transparent 1px);
                background-size: 40px 40px;
            "></div>


        <div class="relative z-10 grid lg:grid-cols-2 min-h-[500px] md:min-h-[560px]">

            <!-- TEXT -->

            <div class="flex flex-col justify-center p-7 sm:p-10 md:p-16 lg:p-20">

                <div class="inline-flex items-center gap-2 mb-7">

                    <span class="w-2 h-2 rounded-full bg-[#E8A83E]"></span>

                    <span class="text-xs font-bold text-gray-400">
                        بازار B2B جوراب
                    </span>

                </div>


                <h1
                    class="text-3xl sm:text-4xl md:text-5xl lg:text-[52px]
                           font-black leading-[1.45] tracking-tight text-white">
                    خرید عمده جوراب،
                    <br>

                    <span class="text-[#E8A83E]">
                        مستقیم از تولیدکننده
                    </span>
                </h1>


                <p class="mt-6 text-sm md:text-base text-gray-400 leading-8 max-w-xl">

                    کاتالوگ محصولات، قیمت ویژه عمده‌فروشان،
                    ثبت سفارش آنلاین و تولید اختصاصی؛
                    همه در یکجا.

                </p>


                <div class="flex flex-col sm:flex-row gap-3 mt-9">

                    <a
                        href="/products"
                        class="btn-accent px-7 py-3.5 text-center">
                        مشاهده محصولات
                    </a>


                    <a
                        href="/register"
                        class="inline-flex items-center justify-center
                               min-h-[46px] px-7
                               rounded-[13px]
                               border border-white/15
                               text-white text-sm font-bold
                               hover:bg-white/10 transition">
                        ثبت‌نام عمده‌فروش
                    </a>

                </div>

            </div>


            <!-- VISUAL -->

            <div class="hidden lg:flex items-center justify-center relative">

                <!-- Main floating card -->

                <div
                    class="relative w-[310px] h-[390px]
                           rounded-[32px]
                           bg-[#F4F2ED]
                           rotate-[5deg]
                           shadow-[0_40px_100px_rgba(0,0,0,.45)]
                           p-5">

                    <div
                        class="absolute inset-4
                               rounded-[24px]
                               border border-black/5"></div>


                    <div class="relative h-full flex flex-col justify-between">

                        <div class="flex justify-between items-start">

                            <div>

                                <div class="text-[10px] text-gray-400 font-bold">
                                    RABETI
                                </div>

                                <div class="text-lg font-black mt-1">
                                    جوراب‌لند
                                </div>

                            </div>


                            <div
                                class="w-10 h-10 rounded-xl bg-black
                                       text-white flex items-center justify-center
                                       font-black text-xs">
                                ج
                            </div>

                        </div>


                        <!-- Sock visual -->

                        <div class="flex justify-center">

                            <div
                                class="w-28 h-48
                                       bg-white
                                       rounded-[45px_45px_28px_28px]
                                       shadow-[15px_20px_35px_rgba(0,0,0,.12)]
                                       rotate-[-8deg]
                                       relative">

                                <div
                                    class="absolute top-0 inset-x-0
                                           h-12
                                           bg-[#191919]
                                           rounded-t-[45px]"></div>

                                <div
                                    class="absolute bottom-0 right-[-24px]
                                           w-20 h-10
                                           bg-white
                                           rounded-r-[30px]"></div>

                            </div>

                        </div>


                        <div>

                            <div class="text-xs text-gray-400">
                                PREMIUM SOCKS
                            </div>

                            <div class="text-xl font-black mt-1">
                                تولید مستقیم
                            </div>

                        </div>

                    </div>

                </div>


                <!-- Floating small card -->

                <div
                    class="absolute bottom-16 right-[50px]
                           bg-white rounded-2xl
                           px-5 py-4
                           shadow-[0_20px_50px_rgba(0,0,0,.25)]
                           -rotate-3">

                    <div class="text-[10px] text-gray-400 mb-1">
                        حداقل سفارش
                    </div>

                    <div class="font-black text-lg">
                        مناسب عمده‌فروش
                    </div>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- =====================================================
     TRUST / FEATURES
===================================================== -->

<section class="mb-16">

    <div class="flex items-end justify-between mb-6">

        <div>

            <div class="text-xs font-bold text-[#E8A83E] mb-2">
                چرا جوراب‌لند؟
            </div>

            <h2 class="section-title">
                خرید عمده، ساده‌تر از همیشه
            </h2>

        </div>

    </div>


    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-5">

        <?php

        $features = [

            [
                '01',
                'قیمت تولیدکننده',
                'خرید مستقیم از کارخانه، بدون واسطه'
            ],

            [
                '02',
                'حداقل سفارش منطقی',
                'امکان شروع سفارش با حجم مناسب'
            ],

            [
                '03',
                'ارسال به سراسر کشور',
                'ثبت سفارش و ارسال مستقیم'
            ],

            [
                '04',
                'تولید اختصاصی',
                'لوگو، لیبل و بسته‌بندی برند شما'
            ],

        ];

        foreach ($features as [$number, $title, $desc]):

        ?>

            <div class="card-3d p-5 md:p-6">

                <div
                    class="w-10 h-10 rounded-xl
                           bg-[#F3F1EC]
                           flex items-center justify-center
                           text-xs font-black
                           text-gray-500 mb-6">
                    <?= $number ?>
                </div>


                <h3 class="font-black text-sm md:text-base mb-2">
                    <?= $title ?>
                </h3>


                <p class="text-xs text-gray-500 leading-6">
                    <?= $desc ?>
                </p>

            </div>

        <?php endforeach; ?>

    </div>

</section>


<!-- =====================================================
     CATEGORIES
===================================================== -->

<section class="mb-16">

    <div class="flex items-end justify-between mb-7">

        <div>

            <div class="text-xs font-bold text-[#E8A83E] mb-2">
                کاتالوگ
            </div>

            <h2 class="section-title">
                دسته‌بندی محصولات
            </h2>

        </div>


        <a
            href="/products"
            class="hidden sm:inline-flex
                   items-center gap-2
                   text-xs font-bold text-gray-500
                   hover:text-black transition">
            مشاهده همه

            <span>←</span>

        </a>

    </div>


    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-5">

        <?php

        $categories = [

            [
                'مردانه',
                'جوراب‌های مردانه',
                'M',
                'from-[#E9E6DF] to-[#F8F7F4]'
            ],

            [
                'زنانه',
                'جوراب‌های زنانه',
                'W',
                'from-[#EEE9E4] to-[#FAF8F6]'
            ],

            [
                'بچگانه',
                'جوراب‌های بچگانه',
                'K',
                'from-[#E8ECE9] to-[#F7F9F7]'
            ],

            [
                'ورزشی',
                'جوراب‌های ورزشی',
                'S',
                'from-[#E6E8EB] to-[#F7F8F9]'
            ],

        ];

        foreach ($categories as [$short, $name, $letter, $gradient]):

        ?>

            <a
                href="/products"
                class="group card-3d overflow-hidden">

                <div
                    class="
                        relative aspect-[1.05/1]
                        bg-gradient-to-br <?= $gradient ?>
                        flex items-center justify-center
                        overflow-hidden
                    ">

                    <!-- Large 3D letter -->

                    <span
                        class="
                            text-[90px] md:text-[120px]
                            font-black
                            text-black/[0.045]
                            select-none
                            transition-transform duration-500
                            group-hover:scale-110
                        ">
                        <?= $letter ?>
                    </span>


                    <!-- Product-like shape -->

                    <div
                        class="
                            absolute
                            w-16 h-28
                            bg-white
                            rounded-[28px_28px_15px_15px]
                            shadow-[10px_15px_25px_rgba(0,0,0,.10)]
                            rotate-[-7deg]
                            opacity-90
                            transition-transform duration-500
                            group-hover:rotate-0
                            group-hover:scale-105
                        "></div>

                </div>


                <div class="p-4 md:p-5">

                    <div class="text-xs text-gray-400 mb-1">
                        <?= $short ?>
                    </div>

                    <div class="font-black text-sm">
                        <?= $name ?>
                    </div>

                    <div
                        class="mt-3 text-[10px] text-gray-400
                               group-hover:text-black transition">
                        مشاهده محصولات ←
                    </div>

                </div>

            </a>

        <?php endforeach; ?>

    </div>

</section>


<!-- =====================================================
     CUSTOM ORDER
===================================================== -->

<section class="mb-8">

    <div
        class="
            relative overflow-hidden
            rounded-[28px]
            bg-[#E8A83E]
            p-7 md:p-12
        ">

        <!-- Decorative -->

        <div
            class="
                absolute -left-20 -top-20
                w-60 h-60
                rounded-full
                bg-white/20
                blur-2xl
            "></div>


        <div
            class="
                absolute -right-24 -bottom-24
                w-72 h-72
                rounded-full
                bg-black/10
                blur-3xl
            "></div>


        <div
            class="
                relative z-10
                flex flex-col md:flex-row
                md:items-center
                justify-between
                gap-8
            ">

            <div class="max-w-2xl">

                <div
                    class="text-xs font-black text-black/50 mb-3">
                    تولید اختصاصی
                </div>


                <h2
                    class="
                        text-2xl md:text-4xl
                        font-black
                        leading-tight
                    ">
                    برند خودتان را
                    <br class="sm:hidden">
                    روی جوراب بسازید.
                </h2>


                <p
                    class="
                        mt-4
                        text-sm
                        text-black/60
                        leading-7
                    ">
                    طراحی اختصاصی، لوگو، لیبل و بسته‌بندی
                    با تولید مستقیم توسط کارگاه.
                </p>

            </div>


            <a
                href="/custom-order"
                class="
                    shrink-0
                    inline-flex
                    items-center justify-center
                    min-h-[50px]
                    px-7
                    rounded-[14px]
                    bg-black
                    text-white
                    text-sm
                    font-bold
                    shadow-[0_6px_0_rgba(0,0,0,.25)]
                    hover:translate-y-[-2px]
                    hover:shadow-[0_8px_0_rgba(0,0,0,.25)]
                    transition
                ">
                درخواست تولید اختصاصی
            </a>

        </div>

    </div>

</section>


<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layouts/main.php';
?>