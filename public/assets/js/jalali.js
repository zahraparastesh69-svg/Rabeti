/* تبدیل تاریخ میلادی <-> شمسی (الگوریتم jalaali-js) و توابع کمکی تاریخ.
   تاریخ‌ها در داده‌ها به صورت ISO میلادی (YYYY-MM-DD) ذخیره و همیشه شمسی نمایش داده می‌شوند. */
(function (BPM) {
  'use strict';
  const BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
  const div = (a, b) => ~~(a / b);
  const mod = (a, b) => a - ~~(a / b) * b;

  function jalCal(jy) {
    const gy = jy + 621;
    let leapJ = -14, jp = BREAKS[0], jm, jump = 0, n, i;
    for (i = 1; i < BREAKS.length; i++) {
      jm = BREAKS[i]; jump = jm - jp;
      if (jy < jm) break;
      leapJ = leapJ + div(jump, 33) * 8 + div(mod(jump, 33), 4);
      jp = jm;
    }
    n = jy - jp;
    leapJ = leapJ + div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
    if (mod(jump, 33) === 4 && jump - n === 4) leapJ += 1;
    const leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
    const march = 20 + leapJ - leapG;
    if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33;
    let leap = mod(mod(n + 1, 33) - 1, 4);
    if (leap === -1) leap = 4;
    return { leap, gy, march };
  }
  function g2d(gy, gm, gd) {
    let d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * mod(gm + 9, 12) + 2, 5) + gd - 34840408;
    d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;
    return d;
  }
  function d2g(jdn) {
    let j = 4 * jdn + 139361631;
    j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
    const i = div(mod(j, 1461), 4) * 5 + 308;
    const gd = div(mod(i, 153), 5) + 1;
    const gm = mod(div(i, 153), 12) + 1;
    const gy = div(j, 1461) - 100100 + div(8 - gm, 6);
    return { gy, gm, gd };
  }
  function j2d(jy, jm, jd) {
    const r = jalCal(jy);
    return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1;
  }
  function d2j(jdn) {
    const gy = d2g(jdn).gy;
    let jy = gy - 621;
    const r = jalCal(jy);
    const jdn1f = g2d(gy, 3, r.march);
    let k = jdn - jdn1f, jm, jd;
    if (k >= 0) {
      if (k <= 185) { jm = 1 + div(k, 31); jd = mod(k, 31) + 1; return { jy, jm, jd }; }
      k -= 186;
    } else { jy -= 1; k += 179; if (r.leap === 1) k += 1; }
    jm = 7 + div(k, 30); jd = mod(k, 30) + 1;
    return { jy, jm, jd };
  }

  const toJalali = (gy, gm, gd) => d2j(g2d(gy, gm, gd));
  const toGregorian = (jy, jm, jd) => d2g(j2d(jy, jm, jd));
  const isLeapJalaliYear = jy => jalCal(jy).leap === 0;

  const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
  const WEEKDAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
  const WEEKDAYS_SHORT = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

  const pad = n => String(n).padStart(2, '0');
  const isoFromDate = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  function dateFromIso(iso) { const [y, m, d] = iso.split('-').map(Number); return new Date(y, m - 1, d, 12); }
  const todayISO = () => isoFromDate(new Date());
  function addDays(iso, n) { const d = dateFromIso(iso); d.setDate(d.getDate() + n); return isoFromDate(d); }
  const diffDays = (a, b) => Math.round((dateFromIso(a) - dateFromIso(b)) / 86400000);
  function isoToJalali(iso) { const [y, m, d] = iso.split('-').map(Number); return toJalali(y, m, d); }
  function jalaliToISO(jy, jm, jd) { const g = toGregorian(jy, jm, jd); return g.gy + '-' + pad(g.gm) + '-' + pad(g.gd); }
  /** 0 = شنبه ... 6 = جمعه */
  const weekdayIndex = iso => (dateFromIso(iso).getDay() + 1) % 7;
  function monthLength(jy, jm) { if (jm <= 6) return 31; if (jm <= 11) return 30; return isLeapJalaliYear(jy) ? 30 : 29; }
  const startOfWeek = iso => addDays(iso, -weekdayIndex(iso));
  const endOfWeek = iso => addDays(startOfWeek(iso), 6);
  function startOfMonth(iso) { const j = isoToJalali(iso); return jalaliToISO(j.jy, j.jm, 1); }
  function endOfMonth(iso) { const j = isoToJalali(iso); return jalaliToISO(j.jy, j.jm, monthLength(j.jy, j.jm)); }

  const FA = '۰۱۲۳۴۵۶۷۸۹', AR = '٠١٢٣٤٥٦٧٨٩';
  const toFaDigits = v => String(v).replace(/\d/g, d => FA[d]);
  const toEnDigits = v => String(v).replace(/[۰-۹]/g, d => FA.indexOf(d)).replace(/[٠-٩]/g, d => AR.indexOf(d));

  function format(iso, style) {
    if (!iso) return '—';
    const j = isoToJalali(iso);
    switch (style) {
      case 'long': return toFaDigits(`${WEEKDAYS[weekdayIndex(iso)]} ${j.jd} ${MONTHS[j.jm - 1]} ${j.jy}`);
      case 'medium': return toFaDigits(`${j.jd} ${MONTHS[j.jm - 1]} ${j.jy}`);
      case 'dayMonth': return toFaDigits(`${j.jd} ${MONTHS[j.jm - 1]}`);
      case 'monthYear': return toFaDigits(`${MONTHS[j.jm - 1]} ${j.jy}`);
      case 'weekday': return WEEKDAYS[weekdayIndex(iso)];
      default: return toFaDigits(`${j.jy}/${pad(j.jm)}/${pad(j.jd)}`);
    }
  }
  function formatDateTime(ts) {
    if (!ts) return '—';
    const d = new Date(ts);
    return format(isoFromDate(d)) + ' - ساعت ' + toFaDigits(pad(d.getHours()) + ':' + pad(d.getMinutes()));
  }
  const weekLabel = iso => 'هفته ' + format(startOfWeek(iso), 'dayMonth') + ' تا ' + format(endOfWeek(iso), 'dayMonth');

  BPM.jalali = {
    MONTHS, WEEKDAYS, WEEKDAYS_SHORT, toJalali, toGregorian, isLeapJalaliYear, monthLength,
    isoFromDate, dateFromIso, todayISO, addDays, diffDays, isoToJalali, jalaliToISO, weekdayIndex,
    startOfWeek, endOfWeek, startOfMonth, endOfMonth, toFaDigits, toEnDigits, format, formatDateTime, weekLabel
  };
})(window.BPM = window.BPM || {});
