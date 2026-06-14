/* Bewertungsmanagement – Anfrage-Assistent (Suche -> Auswahl -> Kontakt). */
(function () {
  "use strict";

  const API = "/bewertungen/api";
  const $ = (id) => document.getElementById(id);

  const q = $("bm-q"), searchBtn = $("bm-search"), status = $("bm-status"), results = $("bm-results");
  const form = $("bm-form"), formStatus = $("bm-form-status"), submitBtn = $("bm-submit");
  const chosenName = $("bm-chosen-name"), chosenMeta = $("bm-chosen-meta");
  const views = document.querySelectorAll(".bm-stepview");
  const bars = document.querySelectorAll(".bm-progress span");

  function esc(s) {
    return String(s ?? "").replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));
  }

  function goStep(n) {
    views.forEach((v) => v.classList.toggle("is-active", v.dataset.view === String(n)));
    bars.forEach((b) => b.classList.toggle("is-active", Number(b.dataset.step) <= n));
    window.scrollTo({ top: document.getElementById("anfrage").offsetTop - 60, behavior: "smooth" });
  }

  async function search() {
    const term = q.value.trim();
    if (term.length < 3) { status.textContent = "Bitte mindestens 3 Zeichen eingeben."; return; }
    results.innerHTML = ""; searchBtn.disabled = true; status.textContent = "Suche läuft …";
    try {
      const res = await fetch(API + "/lookup.php?q=" + encodeURIComponent(term));
      const data = await res.json();
      if (!data.ok) { status.textContent = "Fehler: " + data.error; return; }
      if (!data.count) { status.textContent = "Keine Treffer. Bitte Suchbegriff präzisieren."; return; }
      status.textContent = data.count + " Treffer – Ihr Objekt anklicken:";
      data.properties.forEach((p) => {
        const li = document.createElement("li");
        const meta = [p.type, p.rating ? "★ " + p.rating : null, p.reviews ? p.reviews + " Bewertungen" : null]
          .filter(Boolean).join(" · ");
        li.innerHTML = '<div class="name">' + esc(p.name) + "</div>" + (meta ? '<div class="meta">' + esc(meta) + "</div>" : "");
        li.addEventListener("click", () => choose(p));
        results.appendChild(li);
      });
    } catch (e) {
      status.textContent = "Netzwerkfehler. Bitte erneut versuchen.";
    } finally {
      searchBtn.disabled = false;
    }
  }

  function choose(p) {
    $("f-name").value = p.name || "";
    $("f-token").value = p.token || "";
    $("f-type").value = p.type || "";
    $("f-rating").value = p.rating != null ? p.rating : "";
    $("f-reviews").value = p.reviews != null ? p.reviews : "";
    $("f-lat").value = p.lat != null ? p.lat : "";
    $("f-lng").value = p.lng != null ? p.lng : "";
    chosenName.textContent = p.name || "—";
    chosenMeta.textContent = [p.type, p.rating ? "★ " + p.rating : null, p.reviews ? p.reviews + " Bewertungen" : null]
      .filter(Boolean).join(" · ");
    goStep(2);
  }

  async function submit(ev) {
    ev.preventDefault();
    if (!form.contact_name.value.trim() || !form.contact_email.value.trim()) {
      formStatus.textContent = "Bitte Name und E-Mail ausfüllen."; return;
    }
    if (!form.consent.checked) { formStatus.textContent = "Bitte der Verarbeitung zustimmen."; return; }
    submitBtn.disabled = true; formStatus.textContent = "Wird gesendet …";
    try {
      const res = await fetch(API + "/submit.php", { method: "POST", body: new FormData(form) });
      const data = await res.json();
      if (!data.ok) { formStatus.textContent = "Fehler: " + data.error; submitBtn.disabled = false; return; }
      $("bm-done-msg").textContent = data.message || "Vielen Dank für Ihre Anfrage!";
      goStep(3);
    } catch (e) {
      formStatus.textContent = "Netzwerkfehler. Bitte erneut versuchen."; submitBtn.disabled = false;
    }
  }

  searchBtn.addEventListener("click", search);
  q.addEventListener("keydown", (e) => { if (e.key === "Enter") { e.preventDefault(); search(); } });
  form.addEventListener("submit", submit);
  $("bm-back").addEventListener("click", () => goStep(1));

  const year = document.getElementById("year");
  if (year) year.textContent = new Date().getFullYear();
})();
