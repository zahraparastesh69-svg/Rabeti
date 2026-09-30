/* ═══════════════════════════════════════════════════════════
   RABETI — app.js (نسخه نهایی)
   فهرست: ۱-کشو ۲-هدر ۳-توست ۴-ناوبری ۵-اعداد ۶-Stepper
   ۷-سبد ۸-آکاردئون ۹-مودال Auth ۱۰-ورود ۱۱-ثبت‌نام حقیقی/حقوقی
   ۱۲-فراموشی رمز
   ═══════════════════════════════════════════════════════════ */

// ══════════ ۱. کشوی منو ══════════
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

document
  .querySelectorAll(".r6-drawer-link")
  .forEach((l) => l.addEventListener("click", closeDrawer));

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    closeDrawer();
    closeAuthModal();
  }
});

// ══════════ ۲. هدر اسکرول + بازگشت بالا ══════════
const siteHeader = document.getElementById("siteHeader");
const backToTopEl = document.getElementById("backToTop");

window.addEventListener(
  "scroll",
  () => {
    siteHeader?.classList.toggle("is-scrolled", window.scrollY > 10);
    backToTopEl?.classList.toggle("show", window.scrollY > 500);
  },
  { passive: true },
);

backToTopEl?.addEventListener("click", () =>
  window.scrollTo({ top: 0, behavior: "smooth" }),
);

// ══════════ ۳. توست ══════════
let toastTimer;

window.showToast = function (message, duration = 2800) {
  const t = document.getElementById("toast");
  if (!t) return;
  t.textContent = message;
  t.classList.add("show");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove("show"), duration);
};

// ══════════ ۴. نوار پایین — هایلایت جاری ══════════
document.querySelectorAll(".bottom-nav .bn-item").forEach((item) => {
  const href = item.getAttribute("href");
  const path = window.location.pathname;
  const isHome = href === "/" && (path === "/" || path === "/index.php");
  if (isHome || (href && href !== "/" && path.startsWith(href))) {
    item.classList.add("active");
  }
});

// ══════════ ۵. اعداد فارسی ══════════
const faToEn = (v) =>
  String(v).replace(/[۰-۹]/g, (d) => "۰۱۲۳۴۵۶۷۸۹".indexOf(d));

const enToFa = (n) => String(n).replace(/\d/g, (d) => "۰۱۲۳۴۵۶۷۸۹"[d]);

// ══════════ ۶. Stepper — دکمه + ورود دستی ══════════
function stepperRead(numEl) {
  const raw = numEl.tagName === "INPUT" ? numEl.value : numEl.textContent;
  return parseInt(faToEn(raw), 10) || 1;
}

function stepperWrite(numEl, val) {
  if (numEl.tagName === "INPUT") numEl.value = enToFa(val);
  else numEl.textContent = enToFa(val);
}

document.addEventListener("click", (e) => {
  const btn = e.target.closest(".step, .r6-step");
  if (!btn) return;
  if (btn.closest(".cart-item")) return; // سبد هندلر اختصاصی دارد

  const st = btn.closest(".stepper, .r6-stepper");
  const num = st?.querySelector(".step-num, .r6-num");
  if (!num) return;

  const min = parseInt(st.dataset.min || "1", 10) || 1;
  let val = stepperRead(num);

  if (btn.classList.contains("plus")) val++;
  if (btn.classList.contains("minus")) val = Math.max(min, val - 1);

  stepperWrite(num, val);
});

document.addEventListener("input", (e) => {
  const inp = e.target.closest(".step-input");
  if (!inp) return;
  const v = faToEn(inp.value).replace(/[^\d]/g, "");
  inp.value = v ? enToFa(parseInt(v, 10)) : "";
});

document.addEventListener(
  "blur",
  (e) => {
    const inp = e.target.closest(".step-input");
    if (!inp) return;

    const st = inp.closest(".stepper, .r6-stepper");
    const min = parseInt(st?.dataset.min || "1", 10) || 1;
    let v = parseInt(faToEn(inp.value), 10);
    if (!v || v < min) v = min;

    stepperWrite(inp, v);
  },
  true,
);

// ══════════ ۷. افزودن سریع — کارت‌ها ══════════
document.addEventListener("click", async (e) => {
  const btn = e.target.closest(".r6-add, .quick-add");
  if (!btn) return;
  if (btn.classList.contains("pd-order-btn")) return; // صفحه محصول هندلر جدا دارد

  const card = btn.closest("[data-product-id]");
  if (!card) return;

  const pid = card.dataset.productId;
  const numEl = card.querySelector(".step-num, .r6-num");
  const qty = numEl ? stepperRead(numEl) : 1;

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

    const b = document.getElementById("cartBadge");
    if (b) {
      b.textContent = enToFa(data.count);
      b.style.removeProperty("display");
    }
  } catch (err) {
    console.error("CART_ERROR:", err);
    showToast("خطای ارتباط با سرور — جزئیات در کنسول");
  } finally {
    btn.disabled = false;
  }
});

// ══════════ افزودن به سبد — صفحه محصول ══════════
document
  .querySelector(".pd-order-btn[data-product-id]")
  ?.addEventListener("click", async function () {
    const btn = this;
    const pid = btn.dataset.productId;

    const stepper = document.querySelector(
      ".r6-stepper .r6-num, .stepper .r6-num, .stepper .step-num",
    );
    const qty = stepper ? stepperRead(stepper) : 1;

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
        showToast("⚠️ " + (data.error || "افزودن ناموفق"));
        return;
      }

      showToast(data.msg);

      const b = document.getElementById("cartBadge");
      if (b) {
        b.textContent = enToFa(data.count);
        b.style.removeProperty("display");
      }
    } catch (err) {
      console.error("CART_ERROR:", err);
      showToast("خطای ارتباط با سرور");
    } finally {
      btn.disabled = false;
    }
  });

// ══════════ سبد: تغییر تعداد → ذخیره خودکار ══════════
document.addEventListener("change", (e) => {
  const inp = e.target.closest(".step-input");
  if (!inp) return;

  const st = inp.closest(".stepper");
  if (!st?.dataset.item) return; // فقط سبد

  const fd = new FormData();
  fd.append("do", "update");
  fd.append("item_id", st.dataset.item);
  fd.append("qty", parseInt(faToEn(inp.value), 10) || 1);

  fetch("/cart", { method: "POST", body: fd }).then(() => location.reload());
});

// ══════════ آکاردئون دسته‌ها ══════════
document.querySelectorAll('.cat-card[role="button"]').forEach((card) => {
  card.addEventListener("click", () => {
    const wasOpen = card.classList.contains("active");
    document
      .querySelectorAll(".cat-card.active")
      .forEach((c) => c.classList.remove("active"));
    if (!wasOpen) card.classList.add("active");
  });
});

// ══════════ ۹. Modal ورود / ثبت‌نام / فراموشی ══════════
const authOverlay = document.getElementById("authModalOverlay");
const authClose = document.getElementById("authModalClose");
const authTabsBox = document.querySelector(".auth-tabs");
const paneLogin = document.getElementById("pane-login");
const paneRegister = document.getElementById("pane-register");

/* فقط تب‌های دارای data-tab — دکمه‌های حقیقی/حقوقی جدا هندل می‌شوند */
const authTabs = document.querySelectorAll(".auth-tab[data-tab]");
const utypeTabs = document.querySelectorAll(".auth-tab[data-utype]");

function isMobileAuth() {
  return window.matchMedia("(max-width: 760px)").matches;
}

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

  /* سوییچ نماها — کلید مشکل قبلی */
  document
    .getElementById("view-login")
    ?.classList.toggle("active", !isRegister);
  document
    .getElementById("view-register")
    ?.classList.toggle("active", isRegister);
  document.getElementById("view-forgot")?.classList.remove("active");

  /* نمایش دوباره تب‌ها اگر از فراموشی برگشتیم */
  const tabsBox = document.getElementById("authTabsBox");
  if (tabsBox) tabsBox.style.display = "";

  authTabs.forEach((b) => b.classList.toggle("active", b.dataset.tab === tab));

  authTabsBox?.classList.toggle("register-mode", isRegister);

  paneLogin?.classList.toggle("active", tab === "login");
  paneRegister?.classList.toggle("active", tab === "register");

  if (isRegister) switchUserType("real");
}

document.querySelectorAll("[data-auth-open]").forEach((b) => {
  b.addEventListener("click", (ev) => {
    ev.preventDefault();
    closeDrawer();
    openAuthModal(b.dataset.authOpen || "login");
  });
});

authTabs.forEach((b) =>
  b.addEventListener("click", () => switchAuthTab(b.dataset.tab || "login")),
);

authClose?.addEventListener("click", closeAuthModal);

authOverlay?.addEventListener("click", (e) => {
  if (e.target === authOverlay) closeAuthModal();
});

// ══════════ خطای فرم ══════════
function showFormError(el, msg) {
  if (el) {
    el.textContent = msg;
    el.style.display = "block";
  }
}

function hideFormError(el) {
  if (el) {
    el.textContent = "";
    el.style.display = "none";
  }
}

// ══════════ ۱۰. ورود ══════════
paneLogin?.addEventListener("submit", async (e) => {
  e.preventDefault();

  const btn = document.getElementById("loginBtn");
  const err = document.getElementById("loginError");
  const mobile = faToEn(document.getElementById("loginMobile")?.value.trim());
  const password = document.getElementById("loginPassword")?.value;

  hideFormError(err);
  btn.disabled = true;
  btn.textContent = "در حال ورود...";

  try {
    const fd = new FormData();
    fd.append("mobile", mobile);
    fd.append("password", password);

    const res = await fetch("/api/login.php", {
      method: "POST",
      body: fd,
      headers: { Accept: "application/json" },
    });

    const raw = await res.text();
    let data;
    try {
      data = JSON.parse(raw);
    } catch {
      throw new Error("INVALID_LOGIN_RESPONSE");
    }

    if (!res.ok || !data.ok) {
      showFormError(err, data.error || "ورود ناموفق بود.");
      btn.disabled = false;
      btn.textContent = "ورود";
      return;
    }

    if (data.role === "admin") {
      window.location.href = data.redirect || "/admin";
      return;
    }

    closeAuthModal();
    window.location.reload();
  } catch (errObj) {
    console.error("LOGIN_ERROR:", errObj);
    showFormError(err, "ارتباط با سرویس ورود برقرار نشد.");
    btn.disabled = false;
    btn.textContent = "ورود";
  }
});

// ══════════ ۱۱. ثبت‌نام — حقیقی / حقوقی ══════════
let currentUserType = "real";

function switchUserType(type) {
  currentUserType = type;
  const isLegal = type === "legal";

  /* نمایش/مخفی فیلدهای حقوقی */
  paneRegister
    ?.querySelector(".utype-legal")
    ?.style.setProperty("display", isLegal ? "block" : "none");

  /* اسلایدر تب نوع حساب */
  const slider = document.getElementById("utypeSlider");
  if (slider) slider.style.transform = isLegal ? "translateX(-100%)" : "none";

  utypeTabs.forEach((b) =>
    b.classList.toggle("active", b.dataset.utype === type),
  );
}

utypeTabs.forEach((b) =>
  b.addEventListener("click", () => switchUserType(b.dataset.utype)),
);

paneRegister?.addEventListener("submit", async (e) => {
  e.preventDefault();

  const form = e.currentTarget;
  const btn = document.getElementById("registerBtn");
  const err = document.getElementById("registerError");

  const fullName = form.querySelector('[name="full_name"]')?.value.trim() || "";
  const mobile = faToEn(form.querySelector('[name="mobile"]')?.value.trim());
  const email = form.querySelector('[name="email"]')?.value.trim() || "";
  const password = form.querySelector('[name="password"]')?.value;
  const password2 = form.querySelector('[name="password2"]')?.value;

  const isLegal = currentUserType === "legal";

  const companyName = isLegal
    ? form.querySelector('[name="company_name_legal"]')?.value.trim() || ""
    : form.querySelector('[name="company_name"]')?.value.trim() || "";

  const nationalCode = isLegal
    ? ""
    : faToEn(form.querySelector('[name="national_code"]')?.value.trim());

  const economicCode = isLegal
    ? faToEn(form.querySelector('[name="economic_code"]')?.value.trim())
    : "";

  const gender = isLegal
    ? ""
    : form.querySelector('[name="gender"]:checked')?.value || "";

  hideFormError(err);

  /* اعتبارسنجی سمت مشتری — منطق سرور جدا تأیید می‌کند */
  if (!fullName) {
    showFormError(err, "نام و نام خانوادگی را وارد کنید.");
    return;
  }
  if (!/^09\d{9}$/.test(mobile)) {
    showFormError(err, "شماره موبایل صحیح نیست.");
    return;
  }
  if (password.length < 8) {
    showFormError(err, "رمز عبور باید حداقل ۸ کاراکتر باشد.");
    return;
  }
  if (password !== password2) {
    showFormError(err, "تکرار رمز عبور صحیح نیست.");
    return;
  }
  if (!isLegal && !/^\d{10}$/.test(nationalCode)) {
    showFormError(err, "کد ملی باید ۱۰ رقم باشد.");
    return;
  }
  if (isLegal) {
    if (!companyName) {
      showFormError(err, "نام شرکت را وارد کنید.");
      return;
    }
    if (!/^\d{10,12}$/.test(economicCode)) {
      showFormError(err, "شناسه اقتصادی باید ۱۰ تا ۱۲ رقم باشد.");
      return;
    }
  }

  btn.disabled = true;
  btn.textContent = "در حال ایجاد حساب...";

  try {
    const fd = new FormData();
    fd.append("full_name", fullName);
    fd.append("company_name", companyName);
    fd.append("mobile", mobile);
    fd.append("email", email);
    fd.append("password", password);
    fd.append("password2", password2);
    fd.append("user_type", currentUserType);
    fd.append("national_code", nationalCode);
    fd.append("gender", gender);
    fd.append("economic_code", economicCode);

    const res = await fetch("/api/register.php", {
      method: "POST",
      body: fd,
      headers: { Accept: "application/json" },
    });

    const raw = await res.text();
    let data;
    try {
      data = JSON.parse(raw);
    } catch {
      console.error("REGISTER_RAW:", raw);
      throw new Error("INVALID_REGISTER_RESPONSE");
    }

    if (!res.ok || !data.ok) {
      showFormError(err, data.error || "ثبت‌نام انجام نشد.");
      btn.disabled = false;
      btn.textContent = "ایجاد حساب";
      return;
    }

    closeAuthModal();
    window.location.href = "/?registered=1";
  } catch (errObj) {
    console.error("REGISTER_ERROR:", errObj);
    showFormError(err, "ارتباط با سرویس ثبت‌نام برقرار نشد.");
    btn.disabled = false;
    btn.textContent = "ایجاد حساب";
  }
});

// ══════════ ۱۲. فراموشی رمز — سه مرحله ══════════
let forgotChannel = "sms";
let forgotTargetValue = "";

function showForgotStep(n) {
  ["forgot-step-1", "forgot-step-2", "forgot-step-3", "forgot-step-4"].forEach(
    (id, i) => {
      const el = document.getElementById(id);
      if (el) el.style.display = i + 1 === n ? "block" : "none";
    },
  );
}

/* رفتن به نمای فراموشی از لینک ورود */
document.getElementById("forgotLink")?.addEventListener("click", () => {
  document.getElementById("view-login")?.classList.remove("active");
  document.getElementById("view-register")?.classList.remove("active");
  document.getElementById("view-forgot")?.classList.add("active");

  showForgotStep(1);
  authOverlay?.classList.add("show");
  document.body.style.overflow = "hidden";
});

document.querySelectorAll(".forgot-channel").forEach((b) => {
  b.addEventListener("click", () => {
    forgotChannel = b.dataset.channel;
    const label = document.getElementById("forgotInputLabel");
    if (label) {
      label.textContent =
        forgotChannel === "sms" ? "شماره موبایل" : "آدرس ایمیل";
    }
    showForgotStep(2);
  });
});

document
  .getElementById("forgotBack1")
  ?.addEventListener("click", () => showForgotStep(1));

document
  .getElementById("forgotBack2")
  ?.addEventListener("click", () => showForgotStep(2));

document.getElementById("forgotDoneBtn")?.addEventListener("click", () => {
  closeAuthModal();
  setTimeout(() => {
    document.getElementById("view-forgot")?.classList.remove("active");
    document.getElementById("view-login")?.classList.add("active");
  }, 300);
});

/* ارسال کد */
document
  .getElementById("forgotSendBtn")
  ?.addEventListener("click", async () => {
    const btn = document.getElementById("forgotSendBtn");
    const target = document.getElementById("forgotTarget").value.trim();

    if (forgotChannel === "sms" && !/^09\d{9}$/.test(faToEn(target))) {
      showToast("⚠️ شماره موبایل صحیح نیست");
      return;
    }
    if (forgotChannel === "email" && !target.includes("@")) {
      showToast("⚠️ ایمیل صحیح نیست");
      return;
    }

    forgotTargetValue = target;
    btn.disabled = true;
    btn.textContent = "در حال ارسال...";

    try {
      const fd = new FormData();
      fd.append("step", "request");
      fd.append("channel", forgotChannel);
      fd.append("target", target);

      const res = await fetch("/api/forgot-password", {
        method: "POST",
        body: fd,
      });

      const data = await res.json();

      if (!data.ok) {
        showToast("⚠️ " + data.error);
        btn.disabled = false;
        btn.textContent = "ارسال کد بازیابی";
        return;
      }

      showToast("✅ " + (data.msg || "کد ارسال شد"));

      /* فاز فعلی: کد در پاسخ API هست برای تست —
       بعد از اتصال SMS/Email واقعی این بلاک حذف می‌شود */
      if (data.code) {
        console.log("🔑 کد بازیابی (تست):", data.code);
        showToast("🔑 کد تست: " + data.code);
      }

      showForgotStep(3);
      btn.disabled = false;
      btn.textContent = "ارسال کد بازیابی";
    } catch (err) {
      console.error("FORGOT_ERROR:", err);
      showToast("خطای اتصال");
      btn.disabled = false;
      btn.textContent = "ارسال کد بازیابی";
    }
  });

/* تأیید کد و رمز جدید */
document
  .getElementById("forgotResetBtn")
  ?.addEventListener("click", async () => {
    const btn = document.getElementById("forgotResetBtn");
    const code = document.getElementById("forgotCode").value.trim();
    const p1 = document.getElementById("forgotNewPass").value;
    const p2 = document.getElementById("forgotNewPass2").value;

    if (code.length !== 6) {
      showToast("⚠️ کد ۶ رقمی را وارد کنید");
      return;
    }
    if (p1.length < 8) {
      showToast("⚠️ رمز جدید حداقل ۸ کاراکتر");
      return;
    }
    if (p1 !== p2) {
      showToast("⚠️ تکرار رمز صحیح نیست");
      return;
    }

    btn.disabled = true;
    btn.textContent = "در حال ثبت...";

    try {
      const fd = new FormData();
      fd.append("step", "reset");
      fd.append("channel", forgotChannel);
      fd.append("target", forgotTargetValue);
      fd.append("code", code);
      fd.append("new_password", p1);
      fd.append("new_password2", p2);

      const res = await fetch("/api/forgot-password", {
        method: "POST",
        body: fd,
      });

      const data = await res.json();

      if (!data.ok) {
        showToast("⚠️ " + data.error);
        btn.disabled = false;
        btn.textContent = "تغییر رمز عبور";
        return;
      }

      showForgotStep(4);
    } catch (err) {
      console.error("RESET_ERROR:", err);
      showToast("خطای اتصال");
      btn.disabled = false;
      btn.textContent = "تغییر رمز عبور";
    }
  });
