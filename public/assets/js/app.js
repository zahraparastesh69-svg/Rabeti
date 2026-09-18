// ══════════ کشوی منو ══════════
const drawer = document.getElementById("drawer");
const overlay = document.getElementById("overlay");
const menuBtn = document.getElementById("menuBtn");
const closeBtn = document.getElementById("closeBtn");

function openDrawer() {
  drawer.classList.add("open");
  overlay.classList.add("show");
  document.body.style.overflow = "hidden";
}
function closeDrawer() {
  drawer.classList.remove("open");
  overlay.classList.remove("show");
  document.body.style.overflow = "";
}

menuBtn.addEventListener("click", openDrawer);
closeBtn.addEventListener("click", closeDrawer);
overlay.addEventListener("click", closeDrawer);
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeDrawer();
});

// ══════════ آکاردئون دسته‌بندی‌ها ══════════
document.querySelectorAll(".cat-card").forEach((card) => {
  card.addEventListener("click", () => {
    const wasOpen = card.classList.contains("active");
    // همه را ببند
    document
      .querySelectorAll(".cat-card.active")
      .forEach((c) => c.classList.remove("active"));
    // کلیک دوباره = بستن
    if (!wasOpen) card.classList.add("active");
  });
});
