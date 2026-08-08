(function () {
  "use strict";

  var header = document.getElementById("siteHeader");
  var nav = document.getElementById("primaryNav");
  var navToggle = document.getElementById("navToggle");
  var toTopButton = document.getElementById("toTopButton");
  var dialog = document.getElementById("imageDialog");
  var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

  function closeNav() {
    if (!nav || !navToggle) return;
    nav.classList.remove("is-open");
    navToggle.setAttribute("aria-expanded", "false");
  }

  function updateScrollState() {
    if (header) header.classList.toggle("is-scrolled", window.scrollY > 12);
    if (toTopButton) toTopButton.classList.toggle("is-visible", window.scrollY > 600);
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
      if (window.innerWidth > 820) closeNav();
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
    }, { threshold: 0.1, rootMargin: "0px 0px -35px" });

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
    }, { rootMargin: "-35% 0px -55%", threshold: 0 });

    sections.forEach(function (section) { sectionObserver.observe(section); });
  }

  document.querySelectorAll(".shot").forEach(function (shot) {
    shot.addEventListener("click", function () {
      if (!dialog || typeof dialog.showModal !== "function") return;
      var image = dialog.querySelector("img");
      image.src = shot.dataset.image;
      image.alt = shot.dataset.alt || "Product screenshot";
      dialog.showModal();
      document.body.classList.add("dialog-open");
    });
  });

  if (dialog) {
    var closeDialog = function () {
      dialog.close();
      document.body.classList.remove("dialog-open");
    };

    dialog.querySelector(".dialog-close").addEventListener("click", closeDialog);
    dialog.addEventListener("click", function (event) {
      if (event.target === dialog) closeDialog();
    });
    dialog.addEventListener("close", function () { document.body.classList.remove("dialog-open"); });
  }

  if (toTopButton) {
    toTopButton.addEventListener("click", function () {
      if (window.location.hash) window.history.replaceState(null, "", window.location.pathname + window.location.search);
      window.scrollTo({ top: 0, behavior: reducedMotion.matches ? "auto" : "smooth" });
    });
  }

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") closeNav();
  });

  var year = document.getElementById("year");
  if (year) year.textContent = new Date().getFullYear();

  updateScrollState();
  window.addEventListener("scroll", updateScrollState, { passive: true });
})();
