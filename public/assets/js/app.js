// ══════════ کشوی منو ══════════
const drawer   = document.getElementById('drawer');
const overlay  = document.getElementById('overlay');
const menuBtn  = document.getElementById('menuBtn');
const closeBtn = document.getElementById('closeBtn');

function openDrawer() {
    drawer.classList.add('open');
    overlay.classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeDrawer() {
    drawer.classList.remove('open');
    overlay.classList.remove('show');
    document.body.style.overflow = '';
}
menuBtn?.addEventListener('click', openDrawer);
closeBtn?.addEventListener('click', closeDrawer);
overlay?.addEventListener('click', closeDrawer);
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });

// ══════════ هدر در اسکرول (is-scrolled) ══════════
const header = document.getElementById('siteHeader');
window.addEventListener('scroll', () => {
    header?.classList.toggle('is-scrolled', window.scrollY > 10);
}, { passive: true });

// ══════════ بازگشت به بالا ══════════
const backToTop = document.getElementById('backToTop');
window.addEventListener('scroll', () => {
    backToTop?.classList.toggle('show', window.scrollY > 500);
}, { passive: true });
backToTop?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

// ══════════ توست سراسری ══════════
let toastTimer;
window.showToast = function (message, duration = 2800) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = message;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), duration);
};

// ══════════ آکاردئون دسته‌ها (صفحاتی که استفاده می‌کنند) ══════════
document.querySelectorAll('.cat-card[role="button"]').forEach(card => {
    card.addEventListener('click', () => {
        const wasOpen = card.classList.contains('active');
        document.querySelectorAll('.cat-card.active').forEach(c => c.classList.remove('active'));
        if (!wasOpen) card.classList.add('active');
    });
});
// ══════════ نوار پایین — هایلایت صفحه جاری ══════════
document.querySelectorAll('.bottom-nav .bn-item').forEach(item => {
    const href = item.getAttribute('href');
    const path = window.location.pathname;
    const isHome = href === '/' && (path === '/' || path === '/index.php');
    const isInside = href !== '/' && path.startsWith(href);
    if (isHome || isInside) item.classList.add('active');
});