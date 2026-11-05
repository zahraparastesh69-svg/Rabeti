/* ════ RABETI-CUSTOM-ORDER | نسخه: v2 | 2026-10-22 | جدیدترین نسخه (LATEST) ════ */
/* custom-order.js — v2:
   ۱) دراپ‌زون درگ‌اند‌دراپ فارسی برای همه inputهای فایل ماژول (کلاس .co-drop)
   ۲) اعتبارسنجی فرم ثبت با پیام‌های فارسی + حالت بارگذاری دکمه
   ۳) ردیف‌های پویای چند-آیتمی + جمع زنده (مثل v1)
   ۴) حذف کارت ارجاع محصول
   این فایل با defer لود می‌شود → DOM هنگام اجرا آماده است. */
(function () {
    'use strict';

    var MAX_ROWS  = 8;
    var MIN_ITEM  = 100;
    var MIN_TOTAL = 500;
    var MAX_MB    = 4;
    var TYPES     = ['مردانه', 'زنانه', 'بچگانه', 'ورزشی', 'پشمی', 'سایر'];
    var OK_EXT    = ['jpg', 'jpeg', 'png', 'webp'];
    var OK_TYPE   = ['image/jpeg', 'image/png', 'image/webp'];

    /* ── اعداد فارسی/انگلیسی ── */
    function fa(n) {
        return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[+d]; });
    }
    function en(s) {
        return String(s).replace(/[۰-۹]/g, function (d) {
            return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d);
        });
    }
    function fmtSize(bytes) {
        if (bytes >= 1048576) { return fa((bytes / 1048576).toFixed(1)) + ' مگابایت'; }
        if (bytes >= 1024)    { return fa(Math.round(bytes / 1024)) + ' کیلوبایت'; }
        return fa(bytes) + ' بایت';
    }

    /* ═════════ ۱) دراپ‌زون — همه صفحات ماژول ═════════ */

    function validateFile(file) {
        if (!file) { return null; }
        var ext    = (file.name.split('.').pop() || '').toLowerCase();
        var typeOk = OK_TYPE.indexOf(file.type) > -1;
        var extOk  = OK_EXT.indexOf(ext) > -1;
        if (!typeOk && !extOk) {
            return 'فقط تصویر JPG یا PNG یا WebP پذیرفته می‌شود';
        }
        if (file.size > MAX_MB * 1048576) {
            return 'حجم فایل بیش از ' + fa(MAX_MB) + ' مگابایت است — فایل کوچک‌تری انتخاب کنید';
        }
        return null;
    }

    function initDrop(box) {
        var input = box.querySelector('input[type="file"]');
        var zone  = box.querySelector('.co-dropzone');
        var chip  = box.querySelector('.co-file-chip');
        var errEl = box.querySelector('.co-file-error');
        if (!input || !zone) { return; }

        function showError(msg) {
            if (errEl) { errEl.textContent = msg; errEl.hidden = false; }
        }
        function clearError() {
            if (errEl) { errEl.hidden = true; errEl.textContent = ''; }
        }
        function render() {
            var f = input.files && input.files[0];
            clearError();
            if (!f) {
                box.classList.remove('has-file');
                if (chip) { chip.hidden = true; }
                return;
            }
            var bad = validateFile(f);
            if (bad) {
                input.value = '';
                box.classList.remove('has-file');
                if (chip) { chip.hidden = true; }
                showError(bad);
                return;
            }
            box.classList.add('has-file');
            if (chip) {
                chip.hidden = false;
                var n = chip.querySelector('.co-chip-name');
                var s = chip.querySelector('.co-chip-size');
                if (n) { n.textContent = f.name; }
                if (s) { s.textContent = fmtSize(f.size); }
            }
        }

        input.addEventListener('change', render);

        /* درگ‌اند‌دراپ */
        ['dragenter', 'dragover'].forEach(function (ev) {
            zone.addEventListener(ev, function (e) {
                e.preventDefault();
                e.stopPropagation();
                box.classList.add('is-drag');
            });
        });
        ['dragleave', 'dragend'].forEach(function (ev) {
            zone.addEventListener(ev, function () {
                box.classList.remove('is-drag');
            });
        });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            box.classList.remove('is-drag');
            var dt = e.dataTransfer;
            if (!dt || !dt.files || !dt.files.length) { return; }
            try { input.files = dt.files; } catch (err) { input.dispatchEvent(new Event('change')); return; }
            render();
        });

        /* دسترسی‌پذیری: Enter/Space روی زون */
        zone.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                input.click();
            }
        });

        /* پاک‌کردن فایل انتخابی */
        var clearBtn = box.querySelector('.co-chip-clear');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                input.value = '';
                render();
                zone.focus();
            });
        }
    }

    var boxes = document.querySelectorAll('.co-drop');
    if (boxes.length) {
        /* جلوگیری از باز شدن فایل در تب — فقط برای درگ فایل، نه متن/عکس داخل صفحه */
        var guard = function (e) {
            var types = e.dataTransfer && e.dataTransfer.types;
            if (types && Array.prototype.indexOf.call(types, 'Files') > -1) {
                e.preventDefault();
            }
        };
        document.addEventListener('dragover', guard);
        document.addEventListener('drop', guard);

        boxes.forEach(initDrop);
    }

    /* ═════════ ۲) ردیف‌های پویا (صفحه ثبت) ═════════ */

    var rows = document.getElementById('coiRows');
    var addBtn = document.getElementById('coiAddRow');
    var totalBox = document.getElementById('coiTotal');

    function calcTotal() {
        var total = 0;
        if (!rows) { return 0; }
        var inputs = rows.querySelectorAll('input[name$="[qty]"]');
        inputs.forEach(function (inp) {
            var v = parseInt(en(inp.value).replace(/[^\d]/g, ''), 10);
            if (v > 0) { total += v; }
        });
        if (totalBox) {
            totalBox.innerHTML = 'جمع کل: <b>' + fa(total ? total.toLocaleString('en') : '۰') + '</b> جفت' +
                (total > 0 && total < MIN_TOTAL
                    ? ' <small>(حداقل ' + fa(MIN_TOTAL) + ' جفت — ' + fa(MIN_TOTAL - total) + ' جفت کم است)</small>'
                    : '');
        }
        return total;
    }

    function buildRow(index) {
        var row = document.createElement('div');
        row.className = 'coi-row';

        var opts = TYPES.map(function (t, i) {
            return '<option value="' + t + '"' + (i === 1 ? ' selected' : '') + '>' + t + '</option>';
        }).join('');

        row.innerHTML =
            '<div class="coi-cell coi-cell-type"><label>نوع جوراب</label>' +
            '<select name="items[' + index + '][product_type]">' + opts + '</select></div>' +
            '<div class="coi-cell"><label>رنگ / طرح</label>' +
            '<input type="text" name="items[' + index + '][color]" placeholder="مثلاً سرمه‌ای، راه‌راه…"></div>' +
            '<div class="coi-cell coi-cell-qty"><label>تعداد (جفت)</label>' +
            '<input type="number" name="items[' + index + '][qty]" min="' + MIN_ITEM + '" step="50" dir="ltr" value="' + MIN_ITEM + '"></div>' +
            '<div class="coi-cell"><label>توضیح ردیف</label>' +
            '<input type="text" name="items[' + index + '][notes]" placeholder="اختیاری"></div>' +
            '<button type="button" class="coi-del" aria-label="حذف ردیف" title="حذف ردیف">×</button>';

        return row;
    }

    function reindex() {
        if (!rows) { return; }
        var list = rows.querySelectorAll('.coi-row');
        list.forEach(function (row, i) {
            ['product_type', 'color', 'qty', 'notes'].forEach(function (f) {
                var el = row.querySelector('[name$="[' + f + ']"]');
                if (el) { el.setAttribute('name', 'items[' + i + '][' + f + ']'); }
            });
        });

        var dels = rows.querySelectorAll('.coi-del');
        dels.forEach(function (btn) {
            btn.disabled = list.length <= 1;
        });

        if (addBtn) { addBtn.disabled = list.length >= MAX_ROWS; }

        calcTotal();
    }

    if (rows) {
        rows.addEventListener('click', function (e) {
            var btn = e.target.closest('.coi-del');
            if (!btn) { return; }
            var list = rows.querySelectorAll('.coi-row');
            if (list.length <= 1) { return; }
            btn.closest('.coi-row').remove();
            reindex();
        });

        rows.addEventListener('input', function (e) {
            if (e.target.matches('input[name$="[qty]"]')) { calcTotal(); }
        });

        if (addBtn) {
            addBtn.addEventListener('click', function () {
                var count = rows.querySelectorAll('.coi-row').length;
                if (count >= MAX_ROWS) { return; }
                rows.appendChild(buildRow(count));
                reindex();
                var last = rows.querySelectorAll('.coi-row .coi-cell-qty input');
                if (last.length) { last[last.length - 1].focus(); }
            });
        }

        reindex();
    }

    /* ═════════ ۳) حذف ارجاع محصول ═════════ */
    var refBox = document.getElementById('coiRefBox');
    var refRemove = document.getElementById('coiRefRemove');
    if (refBox && refRemove) {
        refRemove.addEventListener('click', function () {
            var hid = refBox.querySelector('input[name="ref_product_id"]');
            if (hid) { hid.value = ''; }
            refBox.classList.add('is-removed');
            setTimeout(function () { refBox.remove(); }, 200);
        });
    }

    /* ═════════ ۴) اعتبارسنجی فرم ثبت + حالت بارگذاری دکمه ═════════ */
    var form = document.getElementById('coForm');
    if (form) {
        var submitBtn = form.querySelector('button[type="submit"]');
        var mobInp = form.querySelector('input[name="mobile"]');

        /* موبایل: تبدیل ارقام فارسی به انگلیسی هنگام تایپ */
        if (mobInp) {
            mobInp.addEventListener('input', function () {
                var v = en(mobInp.value).replace(/[^\d]/g, '').slice(0, 11);
                if (v !== mobInp.value) { mobInp.value = v; }
            });
        }

        form.addEventListener('submit', function (e) {
            var errs = [];

            var nameEl = form.querySelector('input[name="name"]');
            var descEl = form.querySelector('textarea[name="description"]');
            var nameVal = nameEl ? nameEl.value.trim() : '';
            var descVal = descEl ? descEl.value.trim() : '';
            var mobVal  = mobInp ? en(mobInp.value).replace(/[^\d]/g, '') : '';

            if (nameVal.length < 3)  { errs.push('نام و نام خانوادگی را کامل وارد کنید'); }
            if (!/^09\d{9}$/.test(mobVal)) { errs.push('شماره موبایل معتبر نیست — مثال: 09121234567'); }
            if (descVal.length < 10) { errs.push('توضیحات را کامل‌تر بنویسید (حداقل ۱۰ کاراکتر)'); }

            var total = 0, qtyBad = false;
            form.querySelectorAll('input[name$="[qty]"]').forEach(function (inp) {
                var v = parseInt(en(inp.value).replace(/[^\d]/g, ''), 10);
                if (!v || v < MIN_ITEM || v > 100000) {
                    qtyBad = true;
                    inp.classList.add('co-field-bad');
                } else {
                    inp.classList.remove('co-field-bad');
                    total += v;
                }
            });
            if (qtyBad) { errs.push('تعداد هر ردیف باید بین ۱۰۰ تا ۱۰۰٬۰۰۰ جفت باشد'); }
            if (!qtyBad && total < MIN_TOTAL) {
                errs.push('مجموع سفارش باید حداقل ' + fa(MIN_TOTAL) + ' جفت باشد — الان ' + fa(total) + ' جفت است');
            }

            var alertBox = form.querySelector('.co-js-alert');
            if (errs.length) {
                e.preventDefault();
                if (!alertBox) {
                    alertBox = document.createElement('div');
                    alertBox.className = 'co-js-alert';
                    form.insertBefore(alertBox, form.firstChild);
                }
                alertBox.innerHTML = '<b>لطفاً موارد زیر را اصلاح کنید:</b><ul><li>' +
                    errs.join('</li><li>') + '</li></ul>';

                var firstBad = form.querySelector('.co-field-bad') ||
                    (nameEl && nameVal.length < 3 ? nameEl : null) ||
                    (descEl && descVal.length < 10 ? descEl : null) ||
                    (mobInp && !/^09\d{9}$/.test(mobVal) ? mobInp : null);
                try {
                    if (firstBad) {
                        firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstBad.focus({ preventScroll: true });
                    } else {
                        alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } catch (err) { /* مرورگر قدیمی */ }
                return;
            }

            /* معتبر → ارسال عادی؛ فقط دکمه حالت بارگذاری بگیرد */
            if (alertBox) { alertBox.remove(); }
            if (submitBtn) {
                var origText = submitBtn.textContent;
                setTimeout(function () {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('is-loading');
                    submitBtn.textContent = 'در حال ثبت درخواست…';
                }, 10);
                window.addEventListener('pagehide', function () {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('is-loading');
                    submitBtn.textContent = origText;
                });
            }
        });
    }
})();
