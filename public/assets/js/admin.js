(function () {
  "use strict";

  const sidebar = document.getElementById("adminSidebar");
  const overlay = document.getElementById("adminOverlay");
  const burger = document.getElementById("adminBurger");

  if (!sidebar || !overlay || !burger) {
    return;
  }

  const body = document.body;

  // ── قفل اسکرول بدنه هنگام باز بودن منوی موبایل ──
  // برای اینکه پشت منوی باز اسکرول نخورد، body را fixed می‌کنیم و موقعیت
  // اسکرول را ذخیره/بازگردانی می‌کنیم.
  let savedScrollY = 0;

  function lockBodyScroll() {
    savedScrollY = window.scrollY || window.pageYOffset;
    body.classList.add("admin-menu-open", "admin-menu-fixed");
    body.style.top = -savedScrollY + "px";
  }

  function unlockBodyScroll() {
    body.classList.remove("admin-menu-open", "admin-menu-fixed");
    body.style.top = "";
    if (savedScrollY) {
      window.scrollTo(0, savedScrollY);
      savedScrollY = 0;
    }
  }

  function openAdminSidebar() {
    sidebar.classList.add("open");
    overlay.classList.add("show");
    lockBodyScroll();
    burger.setAttribute("aria-expanded", "true");
  }

  function closeAdminSidebar() {
    sidebar.classList.remove("open");
    overlay.classList.remove("show");
    unlockBodyScroll();
    burger.setAttribute("aria-expanded", "false");
  }

  function toggleAdminSidebar() {
    if (sidebar.classList.contains("open")) {
      closeAdminSidebar();
    } else {
      openAdminSidebar();
    }
  }

  burger.addEventListener("click", toggleAdminSidebar);
  overlay.addEventListener("click", closeAdminSidebar);

  // ── منو: بستن منوی موبایل هنگام کلیک روی هر لینک ──
  sidebar.querySelectorAll(".admin-nav-link").forEach(function (link) {
    link.addEventListener("click", function () {
      // فقط در حالت موبایل (زیر 900px) منو باز است و باید بسته شود
      if (window.innerWidth <= 900) {
        closeAdminSidebar();
      }
    });
  });

  document
    .querySelectorAll(".admin-sidebar-footer-link")
    .forEach(function (link) {
      link.addEventListener("click", function () {
        if (window.innerWidth <= 900) {
          closeAdminSidebar();
        }
      });
    });

  // ── آکاردئون بخش‌های منو (single-open) ──
  const sections = Array.prototype.slice.call(
    sidebar.querySelectorAll("[data-section]")
  );

  sections.forEach(function (section) {
    const head = section.querySelector("[data-section-toggle]");
    if (!head) {
      return;
    }

    head.addEventListener("click", function () {
      const isOpen = section.classList.contains("is-open");

      // فقط یک بخش همزمان باز باشد (single-open accordion)
      sections.forEach(function (other) {
        if (other !== section && other.classList.contains("is-open")) {
          other.classList.remove("is-open");
          const otherHead = other.querySelector("[data-section-toggle]");
          if (otherHead) {
            otherHead.setAttribute("aria-expanded", "false");
          }
        }
      });

      if (isOpen) {
        section.classList.remove("is-open");
        head.setAttribute("aria-expanded", "false");
      } else {
        section.classList.add("is-open");
        head.setAttribute("aria-expanded", "true");
      }
    });
  });

  // ── کلید Escape: بستن منوی موبایل ──
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && sidebar.classList.contains("open")) {
      closeAdminSidebar();
    }
  });

  // ── تغییر اندازه: اگر صفحه بزرگ شد (دسکتاپ)، منوی موبایل را ببند ──
  let resizeTimer;
  window.addEventListener("resize", function () {
    if (window.innerWidth > 900 && sidebar.classList.contains("open")) {
      closeAdminSidebar();
    }
    if (resizeTimer) {
      clearTimeout(resizeTimer);
    }
    resizeTimer = setTimeout(function () {
      // پس از پایان resize، اگر نوار اسکرول سایدبار مخفی شده، دوباره تنظیم کن
    }, 150);
  });
})();

/* ═══════════════════════════════════════════════════════════════════
   فایل‌آپلود یکدست — جایگزینی نمایش پیش‌فرض مرورگر («No file chosen»)
   هر input[type=file] که رابط سفارشی خودش را ندارد، به‌طور خودکار با
   کامپوننت .file-box (دکمهٔ فارسی + نام فایل انتخابی) پوشانده می‌شود.
   اسکیپ‌ها: .file-box (خودش)، .admin-file، .bs-upload، .pfi-uploader
   و هر input که عمداً display:none است (آپلودر درگ‌انددراپ اختصاصی).
   ═══════════════════════════════════════════════════════════════════ */
(function () {
  "use strict";

  var SKIP_SELECTOR = ".file-box, .admin-file, .bs-upload, .pfi-uploader";
  var EMPTY_TEXT = "فایلی انتخاب نشده";

  function enhance(input) {
    if (input.dataset.fileBoxBound) return;
    input.dataset.fileBoxBound = "1";

    // ورودی‌های مخفی رابط سفارشی خودشان را دارند — دست نمی‌زنیم
    var cs = window.getComputedStyle(input);
    if (cs.display === "none" || cs.visibility === "hidden") return;

    var box = document.createElement("label");
    box.className = "file-box";

    var btn = document.createElement("span");
    btn.className = "file-box-btn";
    btn.textContent = "انتخاب فایل";

    var name = document.createElement("span");
    name.className = "file-box-name";
    name.textContent = EMPTY_TEXT;

    input.parentNode.insertBefore(box, input);
    box.appendChild(btn);
    box.appendChild(name);
    box.appendChild(input);

    input.addEventListener("change", function () {
      if (input.files && input.files.length > 0) {
        name.textContent = input.files.length === 1
          ? input.files[0].name
          : input.files.length + " فایل انتخاب شد";
        name.classList.add("has-file");
      } else {
        name.textContent = EMPTY_TEXT;
        name.classList.remove("has-file");
      }
    });
  }

  document.querySelectorAll('input[type="file"]').forEach(function (inp) {
    if (!inp.closest(SKIP_SELECTOR)) {
      enhance(inp);
    }
  });
})();
