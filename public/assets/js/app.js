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
  link.addEventListener("click", closeDrawer);
});

// ══════════ هدر اسکرول ══════════
const header = document.getElementById("siteHeader");

window.addEventListener(
  "scroll",
  () => {
    header?.classList.toggle("is-scrolled", window.scrollY > 10);
  },
  { passive: true },
);

// ══════════ بازگشت بالا ══════════
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

function isMobileAuth() {
  return window.matchMedia("(max-width: 760px)").matches;
}

function openAuthModal(tab = "login") {
  if (tab === "register" && isMobileAuth()) {
    window.location.href = "/register";
    return;
  }

  authOverlay?.classList.add("show");
  document.body.style.overflow = "hidden";

  switchAuthTab(tab);
}

function closeAuthModal() {
  authOverlay?.classList.remove("show");
  document.body.style.overflow = "";
}

function switchAuthTab(tab) {
  if (tab === "register" && isMobileAuth()) {
    window.location.href = "/register";
    return;
  }

  const isRegister = tab === "register";

  authTabs.forEach((button) => {
    button.classList.toggle("active", button.dataset.tab === tab);
  });

  authTabsBox?.classList.toggle("register-mode", isRegister);

  paneLogin?.classList.toggle("active", tab === "login");

  paneRegister?.classList.toggle("active", tab === "register");
}

// ══════════ بازکننده‌های Auth ══════════
document.querySelectorAll("[data-auth-open]").forEach((button) => {
  button.addEventListener("click", (event) => {
    event.preventDefault();

    closeDrawer();

    openAuthModal(button.dataset.authOpen || "login");
  });
});

// ══════════ تب‌ها ══════════
authTabs.forEach((button) => {
  button.addEventListener("click", (event) => {
    event.preventDefault();

    switchAuthTab(button.dataset.tab || "login");
  });
});

// ══════════ بستن مودال ══════════
authClose?.addEventListener("click", closeAuthModal);

authOverlay?.addEventListener("click", (event) => {
  if (event.target === authOverlay) {
    closeAuthModal();
  }
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") {
    closeDrawer();
    closeAuthModal();
  }
});

// ══════════ اعداد فارسی ══════════
const faToEn = (value) => {
  return String(value).replace(/[۰-۹]/g, (digit) =>
    "۰۱۲۳۴۵۶۷۸۹".indexOf(digit),
  );
};

const enToFa = (number) => {
  return String(number).replace(/\d/g, (digit) => "۰۱۲۳۴۵۶۷۸۹"[digit]);
};

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

// ══════════ پیام خطای فرم ══════════
function showFormError(element, message) {
  if (!element) return;

  element.textContent = message;
  element.style.display = "block";
}

function hideFormError(element) {
  if (!element) return;

  element.textContent = "";
  element.style.display = "none";
}

// ══════════ ورود ══════════
paneLogin?.addEventListener("submit", async (event) => {
  event.preventDefault();

  const button = document.getElementById("loginBtn");

  const error = document.getElementById("loginError");

  const mobileInput = document.getElementById("loginMobile");

  const passwordInput = document.getElementById("loginPassword");

  if (!button || !error || !mobileInput || !passwordInput) {
    return;
  }

  const mobile = faToEn(mobileInput.value.trim());

  const password = passwordInput.value;

  hideFormError(error);

  button.disabled = true;
  button.textContent = "در حال ورود...";

  try {
    const formData = new FormData();

    formData.append("mobile", mobile);

    formData.append("password", password);

    const response = await fetch("/api/login.php", {
      method: "POST",
      body: formData,
      headers: {
        Accept: "application/json",
      },
    });

    const raw = await response.text();

    let data;

    try {
      data = JSON.parse(raw);
    } catch {
      throw new Error("INVALID_LOGIN_RESPONSE");
    }

    if (!response.ok || !data.ok) {
      showFormError(error, data.error || "ورود ناموفق بود.");

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
    console.error("LOGIN_ERROR:", errorObject);

    showFormError(error, "ارتباط با سرویس ورود برقرار نشد.");

    button.disabled = false;
    button.textContent = "ورود";
  }
});

// ══════════ ثبت‌نام دسکتاپ ══════════
paneRegister?.addEventListener("submit", async (event) => {
  event.preventDefault();

  if (isMobileAuth()) {
    window.location.href = "/register";

    return;
  }

  const form = event.currentTarget;

  const button = document.getElementById("registerBtn");

  const error = document.getElementById("registerError");

  const fullNameInput = form.querySelector('[name="full_name"]');

  const companyInput = form.querySelector('[name="company_name"]');

  const mobileInput = form.querySelector('[name="mobile"]');

  const passwordInput = form.querySelector('[name="password"]');

  const password2Input = form.querySelector('[name="password2"]');

  if (
    !button ||
    !error ||
    !fullNameInput ||
    !mobileInput ||
    !passwordInput ||
    !password2Input
  ) {
    return;
  }

  const fullName = fullNameInput.value.trim();

  const companyName = companyInput?.value.trim() || "";

  const mobile = faToEn(mobileInput.value.trim());

  const password = passwordInput.value;

  const password2 = password2Input.value;

  hideFormError(error);

  if (!fullName) {
    showFormError(error, "نام و نام خانوادگی را وارد کنید.");

    return;
  }

  if (!/^09\d{9}$/.test(mobile)) {
    showFormError(error, "شماره موبایل صحیح نیست.");

    return;
  }

  if (password.length < 8) {
    showFormError(error, "رمز عبور باید حداقل ۸ کاراکتر باشد.");

    return;
  }

  if (password !== password2) {
    showFormError(error, "تکرار رمز عبور صحیح نیست.");

    return;
  }

  button.disabled = true;
  button.textContent = "در حال ایجاد حساب...";

  try {
    const formData = new FormData();

    formData.append("full_name", fullName);

    formData.append("company_name", companyName);

    formData.append("mobile", mobile);

    formData.append("password", password);

    formData.append("password2", password2);

    const response = await fetch("/api/register.php", {
      method: "POST",
      body: formData,
      headers: {
        Accept: "application/json",
      },
    });

    const raw = await response.text();

    let data;

    try {
      data = JSON.parse(raw);
    } catch {
      console.error("REGISTER_RAW_RESPONSE:", raw);

      throw new Error("INVALID_REGISTER_RESPONSE");
    }

    if (!response.ok || !data.ok) {
      showFormError(error, data.error || "ثبت‌نام انجام نشد.");

      button.disabled = false;
      button.textContent = "ایجاد حساب";

      return;
    }

    closeAuthModal();

    window.location.href = "/?registered=1";
  } catch (errorObject) {
    console.error("REGISTER_ERROR:", errorObject);

    showFormError(error, "ارتباط با سرویس ثبت‌نام برقرار نشد.");

    button.disabled = false;
    button.textContent = "ایجاد حساب";
  }
});
// ══════════ ثبت‌نام با AJAX ══════════
document
  .getElementById("pane-register")
  ?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const fd = new FormData(e.target);
    btn.disabled = true;
    btn.textContent = "در حال ثبت...";

    try {
      const res = await fetch("/api/register", { method: "POST", body: fd });
      const data = await res.json();

      if (!data.ok) {
        showToast("⚠️ " + (data.error || "ثبت‌نام ناموفق بود"));
        btn.disabled = false;
        btn.textContent = "ثبت درخواست عضویت";
        return;
      }

      closeAuthModal();
      showToast(
        "✅ ثبت‌نام شما ثبت شد — پس از تأیید کارشناس قیمت همکاری فعال می‌شود",
      );
      e.target.reset();
    } catch {
      showToast("خطای اتصال — دوباره تلاش کنید");
      btn.disabled = false;
      btn.textContent = "ثبت درخواست عضویت";
    }
  });
// ══════════ افزودن سریع واقعی به سبد ══════════
document.addEventListener("click", async (e) => {
  const btn = e.target.closest(".r6-add, .quick-add");
  if (!btn) return;

  const card = btn.closest(".r6-card, .card");
  const link = card?.querySelector('a[href*="/product/"]');
  if (!link) return;

  // استخراج id محصول از صفحه — از data attribute اگر بود، وگرنه از slug
  let pid = card.dataset.productId;
  if (!pid) {
    const slug = link.getAttribute("href").split("/").pop();
    pid = slug.replace(/^[a-z\-]+-/, "").replace(/\D/g, "") || 0;
  }

  // تعداد از Stepper اگر کنارش هست
  const stepper = card.querySelector(".step-num");
  const qty = stepper ? parseInt(faToEn(stepper.textContent), 10) || 1 : 1;

  btn.disabled = true;
  try {
    const fd = new FormData();
    fd.append("product_id", pid);
    fd.append("qty", qty);

    const res = await fetch("/api/cart", { method: "POST", body: fd });
    const data = await res.json();

    if (res.status === 401) {
      showToast("🔒 برای خرید ابتدا وارد شوید");
      document.getElementById("authModalOverlay")?.classList.add("show");
      document.body.style.overflow = "hidden";
      return;
    }

    if (!data.ok) {
      showToast("⚠️ " + data.error);
      return;
    }

    showToast(data.msg);
    // آپدیت badge ها
    document
      .getElementById("cartBadge")
      ?.replaceChildren(document.createTextNode(enToFa(data.count)));
    document.getElementById("cartBadge")?.style.removeProperty("display");
    document
      .getElementById("bnCartBadge")
      ?.replaceChildren(document.createTextNode(enToFa(data.count)));
    document.getElementById("bnCartBadge")?.style.removeProperty("display");
  } catch {
    showToast("خطای اتصال");
  } finally {
    btn.disabled = false;
  }
});
// ══════════ سبد: تغییر تعداد با Stepper ══════════
window.cartChange = function (itemId, delta) {
  const numEl = document
    .getElementById("qVal")
    .closest("form")
    .parentElement.querySelector(`[onsubmit] ~ * , *`); // placeholder
  // ساده: مقدار فعلی از DOM همان ردیف
  const row = document
    .querySelector(`button[onclick*="cartChange(${itemId}, -1)"]`)
    ?.closest(".cart-item-side");
  const num = row?.querySelector(".step-num");
  let val = parseInt(faToEn(num?.textContent || "1"), 10) || 1;
  val = Math.max(1, val + delta);

  document.getElementById("qItem").value = itemId;
  document.getElementById("qVal").value = val;
  document.getElementById("cartQtyForm").submit();
};
