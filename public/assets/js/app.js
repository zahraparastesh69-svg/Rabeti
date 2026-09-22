// ══════════ کشوی منو ══════════
const drawer = document.getElementById("drawer");
const overlay = document.getElementById("overlay");
const menuBtn = document.getElementById("menuBtn");
const closeBtn = document.getElementById("closeBtn");

function openDrawer() {
  drawer?.classList.add("open");
  overlay?.classList.add("show");
  document.body.style.overflow = "hidden";
}

function closeDrawer() {
  drawer?.classList.remove("open");
  overlay?.classList.remove("show");
  document.body.style.overflow = "";
}

menuBtn?.addEventListener("click", openDrawer);
closeBtn?.addEventListener("click", closeDrawer);
overlay?.addEventListener("click", closeDrawer);

document.querySelectorAll(".r6-drawer-link").forEach((link) => {
  link.addEventListener("click", () => {
    if (!link.classList.contains("drawer-login-button")) {
      closeDrawer();
    }
  });
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    closeDrawer();
    closeAuthModal();
  }
});

// ══════════ هدر در اسکرول ══════════
const header = document.getElementById("siteHeader");

window.addEventListener(
  "scroll",
  () => {
    header?.classList.toggle("is-scrolled", window.scrollY > 10);
  },
  { passive: true },
);

// ══════════ بازگشت به بالا ══════════
const backToTop = document.getElementById("backToTop");

window.addEventListener(
  "scroll",
  () => {
    backToTop?.classList.toggle("show", window.scrollY > 500);
  },
  { passive: true },
);

backToTop?.addEventListener("click", () => {
  window.scrollTo({
    top: 0,
    behavior: "smooth",
  });
});

// ══════════ توست ══════════
let toastTimer;

window.showToast = function (message, duration = 2800) {
  const toast = document.getElementById("toast");

  if (!toast) return;

  toast.textContent = message;
  toast.classList.add("show");

  clearTimeout(toastTimer);

  toastTimer = setTimeout(() => {
    toast.classList.remove("show");
  }, duration);
};

// ══════════ آکاردئون دسته‌ها ══════════
document.querySelectorAll('.cat-card[role="button"]').forEach((card) => {
  card.addEventListener("click", () => {
    const wasOpen = card.classList.contains("active");

    document.querySelectorAll(".cat-card.active").forEach((item) => {
      item.classList.remove("active");
    });

    if (!wasOpen) {
      card.classList.add("active");
    }
  });
});

// ══════════ نوار پایین — صفحه جاری ══════════
document.querySelectorAll(".bottom-nav .bn-item").forEach((item) => {
  const href = item.getAttribute("href");
  const path = window.location.pathname;

  const isHome = href === "/" && (path === "/" || path === "/index.php");

  const isInside = href && href !== "/" && path.startsWith(href);

  if (isHome || isInside) {
    item.classList.add("active");
  }
});

// ══════════ Modal ورود / ثبت‌نام ══════════
const authOverlay = document.getElementById("authModalOverlay");

const authClose = document.getElementById("authModalClose");

const authTabs = document.querySelectorAll(".auth-tab");

const authTabsBox = document.querySelector(".auth-tabs");

const paneLogin = document.getElementById("pane-login");

const paneRegister = document.getElementById("pane-register");

function openAuthModal(tab = "login") {
  authOverlay?.classList.add("show");
  document.body.style.overflow = "hidden";
  switchAuthTab(tab);
}

function closeAuthModal() {
  authOverlay?.classList.remove("show");
  document.body.style.overflow = "";
}

function switchAuthTab(tab) {
  const isRegister = tab === "register";

  authTabsBox?.classList.toggle("register-mode", isRegister);

  authTabs.forEach((tabButton) => {
    tabButton.classList.toggle("active", tabButton.dataset.tab === tab);
  });

  paneLogin?.classList.toggle("active", tab === "login");

  paneRegister?.classList.toggle("active", tab === "register");
}

document.querySelectorAll("[data-auth-open]").forEach((button) => {
  button.addEventListener("click", () => {
    openAuthModal(button.dataset.authOpen || "login");
  });
});

authClose?.addEventListener("click", closeAuthModal);

authOverlay?.addEventListener("click", (e) => {
  if (e.target === authOverlay) {
    closeAuthModal();
  }
});

authTabs.forEach((tabButton) => {
  tabButton.addEventListener("click", () => {
    switchAuthTab(tabButton.dataset.tab || "login");
  });
});

// ══════════ Stepper تعداد ══════════
const faToEn = (value) =>
  String(value).replace(/[۰-۹]/g, (digit) => "۰۱۲۳۴۵۶۷۸۹".indexOf(digit));

const enToFa = (number) =>
  String(number).replace(/\d/g, (digit) => "۰۱۲۳۴۵۶۷۸۹"[digit]);

document.querySelectorAll(".r6-stepper").forEach((stepper) => {
  const num = stepper.querySelector(".r6-num");

  if (!num) return;

  const min = parseInt(stepper.dataset.min || "1", 10);

  let value = Math.max(min, parseInt(faToEn(num.textContent), 10) || min);

  stepper.querySelector(".plus")?.addEventListener("click", () => {
    value++;
    num.textContent = enToFa(value);
  });

  stepper.querySelector(".minus")?.addEventListener("click", () => {
    if (value > min) {
      value--;
      num.textContent = enToFa(value);
    }
  });
});

// ══════════ ورود با AJAX ══════════
paneLogin?.addEventListener("submit", async (e) => {
  e.preventDefault();

  const btn = document.getElementById("loginBtn");

  const err = document.getElementById("loginError");

  const mobile = document.getElementById("loginMobile")?.value.trim() || "";

  const password = document.getElementById("loginPassword")?.value || "";

  if (!btn || !err) return;

  btn.disabled = true;
  btn.textContent = "در حال ورود...";

  err.style.display = "none";
  err.textContent = "";

  try {
    const formData = new FormData();

    formData.append("mobile", mobile);

    formData.append("password", password);

    const response = await fetch("/api/login", {
      method: "POST",
      body: formData,
      headers: {
        Accept: "application/json",
      },
    });

    const data = await response.json();

    if (!data.ok) {
      err.textContent = data.error || "ورود ناموفق بود";

      err.style.display = "block";

      btn.disabled = false;
      btn.textContent = "ورود";

      return;
    }

    if (data.role === "admin") {
      window.location.href = data.redirect || "/admin";

      return;
    }

    // مشتری
    closeAuthModal();

    window.location.reload();
  } catch (error) {
    err.textContent = "خطای اتصال — دوباره تلاش کنید";

    err.style.display = "block";

    btn.disabled = false;
    btn.textContent = "ورود";
  }
});
