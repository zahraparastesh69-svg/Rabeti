const adminSidebar = document.getElementById("adminSidebar");

const adminOverlay = document.getElementById("adminOverlay");

const adminBurger = document.getElementById("adminBurger");

function openAdminSidebar() {
  adminSidebar?.classList.add("open");
  adminOverlay?.classList.add("show");
  document.body.classList.add("admin-menu-open");
}

function closeAdminSidebar() {
  adminSidebar?.classList.remove("open");
  adminOverlay?.classList.remove("show");
  document.body.classList.remove("admin-menu-open");
}

adminBurger?.addEventListener("click", openAdminSidebar);

adminOverlay?.addEventListener("click", closeAdminSidebar);

document.querySelectorAll(".admin-nav-link").forEach((link) => {
  link.addEventListener("click", () => {
    closeAdminSidebar();
  });
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") {
    closeAdminSidebar();
  }
});

window.addEventListener("resize", () => {
  if (window.innerWidth > 900) {
    closeAdminSidebar();
  }
});
