/* =========================================================
   Watt'n Auftritt – Interaktionen
   - Mobile-Navigation
   - Sticky-Header-Schatten
   - Scroll-Spy (aktiver Menüpunkt)
   - Reveal-on-Scroll
   - Kontaktformular (mailto-Fallback)
   ========================================================= */
(() => {
  "use strict";

  const header = document.querySelector(".site-header");
  const toggle = document.querySelector(".nav__toggle");
  const menu = document.getElementById("nav-menu");

  /* ---- Mobile-Navigation ---- */
  const closeMenu = () => {
    menu.classList.remove("is-open");
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("aria-label", "Menü öffnen");
  };
  toggle?.addEventListener("click", () => {
    const open = menu.classList.toggle("is-open");
    toggle.setAttribute("aria-expanded", String(open));
    toggle.setAttribute("aria-label", open ? "Menü schließen" : "Menü öffnen");
  });
  menu?.querySelectorAll("a").forEach((a) => a.addEventListener("click", closeMenu));
  document.addEventListener("keydown", (e) => { if (e.key === "Escape") closeMenu(); });

  /* ---- Sticky-Header-Schatten ---- */
  const onScroll = () => header?.classList.toggle("is-scrolled", window.scrollY > 8);
  onScroll();
  window.addEventListener("scroll", onScroll, { passive: true });

  /* ---- Reveal-on-Scroll ---- */
  const reveals = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -8% 0px" });
    reveals.forEach((el) => io.observe(el));
  } else {
    reveals.forEach((el) => el.classList.add("is-visible"));
  }

  /* ---- Scroll-Spy ---- */
  const links = Array.from(menu?.querySelectorAll('a[href^="#"]') || []);
  const sections = links
    .map((l) => document.querySelector(l.getAttribute("href")))
    .filter(Boolean);
  if (sections.length && "IntersectionObserver" in window) {
    const spy = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const id = entry.target.id;
          links.forEach((l) => l.classList.toggle("is-active", l.getAttribute("href") === "#" + id));
        }
      });
    }, { rootMargin: "-45% 0px -50% 0px" });
    sections.forEach((s) => spy.observe(s));
  }

  /* ---- Kontaktformular (mailto-Fallback ohne Backend) ---- */
  const form = document.getElementById("contact-form");
  const hint = document.getElementById("form-hint");
  form?.addEventListener("submit", (e) => {
    e.preventDefault();
    const data = new FormData(form);
    const name = (data.get("name") || "").toString().trim();
    const email = (data.get("email") || "").toString().trim();
    const subject = (data.get("subject") || "").toString().trim();
    const message = (data.get("message") || "").toString().trim();

    if (!name || !email || !message) {
      hint.textContent = "Bitte füllen Sie Name, E-Mail und Nachricht aus.";
      hint.className = "form__hint is-err";
      return;
    }

    const body = `Name: ${name}\nE-Mail: ${email}\n\n${message}`;
    const mailto = `mailto:info@wattnauftritt.de?subject=${encodeURIComponent(subject || "Anfrage über die Website")}&body=${encodeURIComponent(body)}`;
    window.location.href = mailto;

    hint.textContent = "Ihr E-Mail-Programm öffnet sich – wir freuen uns auf Ihre Nachricht!";
    hint.className = "form__hint is-ok";
    form.reset();
  });

  /* ---- Jahr im Footer ---- */
  const year = document.getElementById("year");
  if (year) year.textContent = new Date().getFullYear();
})();
