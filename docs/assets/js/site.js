(function () {
  "use strict";

  var header = document.querySelector(".site-header");
  var toggle = document.getElementById("navToggle");
  var nav = document.getElementById("primaryNav");
  var dialog = document.getElementById("imageDialog");

  function updateHeader() {
    if (header) header.classList.toggle("is-scrolled", window.scrollY > 12);
  }

  function closeNav() {
    if (!nav || !toggle) return;
    nav.classList.remove("is-open");
    toggle.setAttribute("aria-expanded", "false");
  }

  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      var isOpen = nav.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", String(isOpen));
    });

    nav.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", closeNav);
    });

    document.addEventListener("click", function (event) {
      if (nav.classList.contains("is-open") && !nav.contains(event.target) && !toggle.contains(event.target)) closeNav();
    });

    window.addEventListener("resize", function () {
      if (window.innerWidth > 820) closeNav();
    });
  }

  var reveals = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window && !window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -40px" });
    reveals.forEach(function (element) { observer.observe(element); });
  } else {
    reveals.forEach(function (element) { element.classList.add("is-visible"); });
  }

  document.querySelectorAll(".preview-card").forEach(function (card) {
    card.addEventListener("click", function () {
      if (!dialog || typeof dialog.showModal !== "function") return;
      var image = dialog.querySelector("img");
      image.src = card.dataset.image;
      image.alt = card.dataset.alt || "Product screenshot";
      dialog.showModal();
    });
  });

  if (dialog) {
    dialog.querySelector(".dialog-close").addEventListener("click", function () { dialog.close(); });
    dialog.addEventListener("click", function (event) {
      if (event.target === dialog) dialog.close();
    });
  }

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") closeNav();
  });

  var year = document.getElementById("year");
  if (year) year.textContent = new Date().getFullYear();

  updateHeader();
  window.addEventListener("scroll", updateHeader, { passive: true });
})();
