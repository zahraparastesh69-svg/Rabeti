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

// ══════════ حذف ورود/ثبت‌نام از منوی کشویی ══════════
document.querySelector(".drawer-login-button")?.remove();

document.querySelectorAll(".r6-drawer-link").forEach((link) => {
  link.addEventListener("click", () => {
    closeDrawer();
  });
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

// ══════════ نوار پایین ══════════
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
    closeDrawer();

    openAuthModal(button.dataset.authOpen || "login");
  });
});

authClose?.addEventListener("click", closeAuthModal);

authOverlay?.addEventListener("click", (event) => {
  if (event.target === authOverlay) {
    closeAuthModal();
  }
});

authTabs.forEach((tabButton) => {
  tabButton.addEventListener("click", () => {
    switchAuthTab(tabButton.dataset.tab || "login");
  });
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") {
    closeDrawer();
    closeAuthModal();
  }
});

// ══════════ تبدیل اعداد فارسی ══════════
const faToEn = (value) =>
  String(value).replace(/[۰-۹]/g, (digit) => "۰۱۲۳۴۵۶۷۸۹".indexOf(digit));

const enToFa = (number) =>
  String(number).replace(/\d/g, (digit) => "۰۱۲۳۴۵۶۷۸۹"[digit]);

// ══════════ Stepper ══════════
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

// ══════════ خطای فرم احراز هویت ══════════
function getFormMessage(form) {
  let box = form.querySelector(".form-error");

  if (!box) {
    box = document.createElement("div");

    box.className = "form-error";

    box.style.display = "none";

    form.prepend(box);
  }

  return box;
}

// ══════════ ورود ══════════
paneLogin?.addEventListener("submit", async (event) => {
  event.preventDefault();

  const button = document.getElementById("loginBtn");

  const error = document.getElementById("loginError");

  const mobile = document.getElementById("loginMobile")?.value.trim() || "";

  const password = document.getElementById("loginPassword")?.value || "";

  if (!button || !error) return;

  button.disabled = true;
  button.textContent = "در حال ورود...";

  error.style.display = "none";

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
      error.textContent = data.error || "ورود ناموفق بود.";

      error.style.display = "block";

      button.disabled = false;
      button.textContent = "ورود";

      return;
    }

    if (data.role === "admin") {
      window.location.href = data.redirect || "/admin";

      return;
    }

    closeAuthModal();

    window.location.reload();
  } catch (errorObject) {
    error.textContent = "خطای اتصال — دوباره تلاش کنید.";

    error.style.display = "block";

    button.disabled = false;
    button.textContent = "ورود";
  }
});

// ══════════ ثبت‌نام مشترک برای مودال و صفحه مستقل ══════════
document
  .querySelectorAll('#pane-register, form[action="/register"]')
  .forEach((form) => {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();

      const button = form.querySelector('button[type="submit"]');

      const message = getFormMessage(form);

      const fullName =
        form.querySelector('[name="full_name"]')?.value.trim() || "";

      const companyName =
        form.querySelector('[name="company_name"]')?.value.trim() || "";

      const mobile = form.querySelector('[name="mobile"]')?.value.trim() || "";

      const password = form.querySelector('[name="password"]')?.value || "";

      const password2 = form.querySelector('[name="password2"]')?.value || "";

      if (!fullName) {
        message.textContent = "نام و نام خانوادگی را وارد کنید.";

        message.style.display = "block";

        return;
      }

      if (!/^09\d{9}$/.test(faToEn(mobile))) {
        message.textContent = "شماره موبایل صحیح نیست.";

        message.style.display = "block";

        return;
      }

      if (password.length < 8) {
        message.textContent = "رمز عبور باید حداقل ۸ کاراکتر باشد.";

        message.style.display = "block";

        return;
      }

      if (password2 && password !== password2) {
        message.textContent = "تکرار رمز عبور صحیح نیست.";

        message.style.display = "block";

        return;
      }

      message.style.display = "none";

      if (button) {
        button.disabled = true;
        button.textContent = "در حال ایجاد حساب...";
      }

      try {
        const formData = new FormData();

        formData.append("full_name", fullName);

        formData.append("company_name", companyName);

        formData.append("mobile", faToEn(mobile));

        formData.append("password", password);

        if (password2) {
          formData.append("password2", password2);
        }

        const response = await fetch("/api/register", {
          method: "POST",
          body: formData,
          headers: {
            Accept: "application/json",
          },
        });

        const data = await response.json();

        if (!data.ok) {
          message.textContent = data.error || "ثبت‌نام انجام نشد.";

          message.style.display = "block";

          if (button) {
            button.disabled = false;
            button.textContent = "ایجاد حساب";
          }

          return;
        }

        /*
         * API بعد از ثبت‌نام، کاربر را
         * خودکار وارد می‌کند.
         */
        if (form.id === "pane-register") {
          closeAuthModal();
          window.location.reload();
          return;
        }

        window.location.href = "/";
      } catch (errorObject) {
        message.textContent = "خطای اتصال — دوباره تلاش کنید.";

        message.style.display = "block";

        if (button) {
          button.disabled = false;
          button.textContent = "ایجاد حساب";
        }
      }
    });
  });
