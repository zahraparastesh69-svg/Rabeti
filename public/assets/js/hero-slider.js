(function () {
  "use strict";

  var hero = document.getElementById("r6Hero");
  if (!hero) return;

  var slides = Array.from(hero.querySelectorAll(".r6-hero-slide"));
  var dots = Array.from(hero.querySelectorAll(".r6-hero-dot"));
  var prevButton = hero.querySelector(".r6-hero-prev");
  var nextButton = hero.querySelector(".r6-hero-next");

  if (slides.length <= 1) {
    // اگر فقط یک بنر هست، همان را نشان بده و بقیه را مخفی کن
    slides.forEach(function (s, i) {
      s.style.opacity = i === 0 ? "1" : "0";
      s.style.visibility = i === 0 ? "visible" : "hidden";
      s.style.position = i === 0 ? "relative" : "absolute";
    });
    return;
  }

  var current = 0;
  var timer = null;

  function showSlide(index) {
    current = (index + slides.length) % slides.length;

    slides.forEach(function (slide, i) {
      var active = i === current;
      slide.classList.toggle("is-active", active);
      slide.style.opacity = active ? "1" : "0";
      slide.style.visibility = active ? "visible" : "hidden";
      slide.style.pointerEvents = active ? "auto" : "none";
      slide.style.zIndex = active ? "2" : "1";
      slide.style.position = "absolute";
      slide.style.top = "0";
      slide.style.left = "0";
      slide.style.width = "100%";
      slide.style.transition = "opacity 0.5s ease";
    });

    dots.forEach(function (dot, i) {
      dot.classList.toggle("is-active", i === current);
    });
  }

  function nextSlide() {
    showSlide(current + 1);
  }
  function prevSlide() {
    showSlide(current - 1);
  }

  function restartAutoPlay() {
    clearInterval(timer);
    timer = setInterval(nextSlide, 5000);
  }

  if (nextButton)
    nextButton.addEventListener("click", function (e) {
      e.preventDefault();
      nextSlide();
      restartAutoPlay();
    });

  if (prevButton)
    prevButton.addEventListener("click", function (e) {
      e.preventDefault();
      prevSlide();
      restartAutoPlay();
    });

  dots.forEach(function (dot, index) {
    dot.addEventListener("click", function (e) {
      e.preventDefault();
      showSlide(index);
      restartAutoPlay();
    });
  });

  hero.addEventListener("mouseenter", function () {
    clearInterval(timer);
  });
  hero.addEventListener("mouseleave", restartAutoPlay);

  // اولین اسلاید را نشان بده، بقیه را مخفی کن
  showSlide(0);
  restartAutoPlay();
})();
