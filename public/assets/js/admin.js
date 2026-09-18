// سایدبار موبایل
const sidebar = document.getElementById("adminSidebar");
const overlay = document.getElementById("adminOverlay");
const burger = document.getElementById("adminBurger");

burger?.addEventListener("click", () => {
  sidebar.classList.add("open");
  overlay.classList.add("show");
});
overlay?.addEventListener("click", () => {
  sidebar.classList.remove("open");
  overlay.classList.remove("show");
});
