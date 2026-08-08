(function () {
  "use strict";

  var header = document.getElementById("siteHeader");
  var nav = document.getElementById("primaryNav");
  var navToggle = document.getElementById("navToggle");
  var dialog = document.getElementById("imageDialog");
  var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
  var lastShot = null;

  function closeNav() {
    if (!nav || !navToggle) return;
    nav.classList.remove("is-open");
    navToggle.setAttribute("aria-expanded", "false");
  }

  function updateHeader() {
    if (header) header.classList.toggle("is-scrolled", window.scrollY > 10);
  }

  if (nav && navToggle) {
    navToggle.addEventListener("click", function () {
      var isOpen = nav.classList.toggle("is-open");
      navToggle.setAttribute("aria-expanded", String(isOpen));
    });

    nav.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", closeNav);
    });

    document.addEventListener("click", function (event) {
      if (nav.classList.contains("is-open") && !nav.contains(event.target) && !navToggle.contains(event.target)) closeNav();
    });

    window.addEventListener("resize", function () {
      if (window.innerWidth > 900) closeNav();
    });
  }

  var revealItems = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window && !reducedMotion.matches) {
    var revealObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("is-visible");
        revealObserver.unobserve(entry.target);
      });
    }, { threshold: 0.08, rootMargin: "0px 0px -30px" });

    revealItems.forEach(function (item) { revealObserver.observe(item); });
  } else {
    revealItems.forEach(function (item) { item.classList.add("is-visible"); });
  }

  var sectionLinks = document.querySelectorAll('.primary-nav a[href^="#"]');
  var sections = Array.from(sectionLinks).map(function (link) {
    return document.querySelector(link.getAttribute("href"));
  }).filter(Boolean);

  if ("IntersectionObserver" in window && sections.length) {
    var sectionObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        sectionLinks.forEach(function (link) {
          link.classList.toggle("is-active", link.getAttribute("href") === "#" + entry.target.id);
        });
      });
    }, { rootMargin: "-38% 0px -54%", threshold: 0 });

    sections.forEach(function (section) { sectionObserver.observe(section); });
  }

  document.querySelectorAll(".open-shot").forEach(function (shot) {
    shot.addEventListener("click", function () {
      if (!dialog || typeof dialog.showModal !== "function") {
        window.open(shot.dataset.image, "_blank", "noopener");
        return;
      }

      lastShot = shot;
      var image = dialog.querySelector("img");
      image.src = shot.dataset.image;
      image.alt = shot.dataset.alt || "Product screenshot";
      dialog.showModal();
      document.body.classList.add("dialog-open");
    });
  });

  if (dialog) {
    function closeDialog() {
      if (dialog.open) dialog.close();
    }

    dialog.querySelector(".dialog-close").addEventListener("click", closeDialog);
    dialog.addEventListener("click", function (event) {
      if (event.target === dialog) closeDialog();
    });
    dialog.addEventListener("close", function () {
      document.body.classList.remove("dialog-open");
      if (lastShot) lastShot.focus();
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
