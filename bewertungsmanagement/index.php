<?php require_once __DIR__ . '/inc/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Bewertungsmanagement – Unrechtmäßige Bewertungen entfernen | Watt'n Auftritt</title>
  <meta name="description" content="Unrechtmäßige Google-Bewertungen professionell prüfen und entfernen lassen. Objekt auswählen, Anfrage stellen – wir sichern Ihre Bewertungen und kümmern uns um den Rest." />
  <meta name="theme-color" content="#15201f" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
  <link rel="icon" href="/assets/logo-min.png" />
  <link rel="stylesheet" href="/assets/styles.css?v=3" />
  <link rel="stylesheet" href="/bewertungsmanagement/assets/public.css" />
</head>
<body>
  <header class="bm-header">
    <div class="container bm-header__in">
      <a class="bm-header__brand" href="/" aria-label="Watt'n Auftritt – Startseite">
        <img src="/assets/logo.webp" width="560" height="365" alt="Watt'n Auftritt" />
      </a>
      <nav class="bm-header__nav">
        <a href="/">Startseite</a>
        <a href="#ablauf">Ablauf</a>
        <a class="btn btn--primary" href="#anfrage">Anfrage starten</a>
      </nav>
    </div>
  </header>

  <main>
    <!-- HERO -->
    <section class="bm-hero">
      <div class="container bm-hero__inner">
        <p class="eyebrow">Bewertungsmanagement</p>
        <h1>Unrechtmäßige Bewertungen <span class="grad-text">erkennen &amp; entfernen</span></h1>
        <p>
          Falsche, beleidigende oder rechtswidrige Google-Bewertungen schaden Ihrem Ruf und Ihrem Umsatz.
          Wir sichern alle Bewertungen Ihres Objekts, prüfen sie und kümmern uns um die Entfernung der
          unrechtmäßigen – Sie behalten Ihren guten Ruf.
        </p>
        <a class="btn btn--primary" href="#anfrage">Kostenlos anfragen</a>
      </div>
    </section>

    <!-- ABLAUF -->
    <section class="section" id="ablauf">
      <div class="container">
        <header class="section__head">
          <p class="eyebrow eyebrow--dark">So läuft es ab</p>
          <h2 class="section__title">In drei Schritten zur sauberen Bewertung</h2>
        </header>
        <div class="bm-steps">
          <article class="bm-step">
            <div class="bm-step__num">1</div>
            <h3>Objekt auswählen</h3>
            <p>Geben Sie den Namen Ihres Hotels oder Ihrer Ferienwohnung ein und wählen Sie Ihr Google-Listing aus.</p>
          </article>
          <article class="bm-step">
            <div class="bm-step__num">2</div>
            <h3>Anfrage stellen</h3>
            <p>Sie hinterlassen Ihre Kontaktdaten. Wir sichern anschließend alle Bewertungen Ihres Objekts in unserer Datenbank.</p>
          </article>
          <article class="bm-step">
            <div class="bm-step__num">3</div>
            <h3>Wir werden aktiv</h3>
            <p>Wir prüfen die Bewertungen, leiten die Entfernung unrechtmäßiger ein und Sie verfolgen alles in Ihrem Kundenbereich.</p>
          </article>
        </div>
      </div>
    </section>

    <!-- ANFRAGE / WIZARD -->
    <section class="section section--alt" id="anfrage">
      <div class="container">
        <header class="section__head" style="text-align:center;margin-inline:auto;">
          <p class="eyebrow eyebrow--dark">Ihre Anfrage</p>
          <h2 class="section__title">Objekt finden &amp; Anfrage stellen</h2>
          <p class="section__sub">Die Suche greift live auf Google zu. Wählen Sie das passende Listing aus.</p>
        </header>

        <div id="bm-wizard">
          <div class="bm-card">
            <div class="bm-progress" aria-hidden="true">
              <span class="is-active" data-step="1"></span>
              <span data-step="2"></span>
              <span data-step="3"></span>
            </div>

            <!-- Schritt 1: Suche -->
            <div class="bm-stepview is-active" data-view="1">
              <div class="bm-field">
                <label for="bm-q">Name Ihres Objekts</label>
                <div class="bm-search-row">
                  <input type="text" id="bm-q" placeholder="z. B. Hotel Neptuns Ankerplatz Cuxhaven" autocomplete="off" />
                  <button type="button" class="btn btn--primary" id="bm-search">Suchen</button>
                </div>
                <p class="bm-status" id="bm-status" role="status" aria-live="polite"></p>
              </div>
              <ul class="bm-results" id="bm-results"></ul>
              <p class="bm-note">Tipp: Ort ergänzen (z. B. „… Cuxhaven"), wenn zu viele oder keine Treffer erscheinen.</p>
            </div>

            <!-- Schritt 2: Auswahl bestätigen + Kontakt -->
            <div class="bm-stepview" data-view="2">
              <div class="bm-chosen" id="bm-chosen">
                <span>✓</span>
                <div><strong id="bm-chosen-name">—</strong><div class="meta" id="bm-chosen-meta"></div></div>
              </div>

              <form id="bm-form" novalidate>
                <!-- versteckte Objektdaten -->
                <input type="hidden" name="property_name" id="f-name">
                <input type="hidden" name="property_token" id="f-token">
                <input type="hidden" name="property_type" id="f-type">
                <input type="hidden" name="property_rating" id="f-rating">
                <input type="hidden" name="property_reviews" id="f-reviews">
                <input type="hidden" name="property_lat" id="f-lat">
                <input type="hidden" name="property_lng" id="f-lng">
                <!-- Honeypot -->
                <div class="bm-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                <div class="bm-field">
                  <label for="c-name">Ihr Name *</label>
                  <input type="text" id="c-name" name="contact_name" required autocomplete="name">
                </div>
                <div class="bm-field">
                  <label for="c-email">E-Mail-Adresse *</label>
                  <input type="email" id="c-email" name="contact_email" required autocomplete="email">
                </div>
                <div class="bm-field">
                  <label for="c-phone">Telefon</label>
                  <input type="tel" id="c-phone" name="contact_phone" autocomplete="tel">
                </div>
                <div class="bm-field">
                  <label for="c-company">Firma</label>
                  <input type="text" id="c-company" name="company" autocomplete="organization">
                </div>
                <div class="bm-field">
                  <label for="c-message">Nachricht (optional)</label>
                  <textarea id="c-message" name="message" rows="3" placeholder="Worum geht es konkret?"></textarea>
                </div>

                <label class="bm-consent">
                  <input type="checkbox" name="consent" id="c-consent" required>
                  <span>Ich stimme zu, dass meine Angaben zur Bearbeitung der Anfrage gespeichert und die
                  öffentlich verfügbaren Bewertungen meines Objekts verarbeitet werden. *</span>
                </label>

                <p class="bm-status" id="bm-form-status" role="status" aria-live="polite"></p>
                <div class="bm-actions">
                  <button type="button" class="btn btn--ghost" id="bm-back" style="color:var(--ink);border-color:var(--line);">Zurück</button>
                  <button type="submit" class="btn btn--primary" id="bm-submit">Anfrage absenden</button>
                </div>
              </form>
            </div>

            <!-- Schritt 3: Fertig -->
            <div class="bm-stepview" data-view="3">
              <div class="bm-done">
                <div class="check">✓</div>
                <h3 style="color:var(--ink);font-family:var(--font-display);">Anfrage eingegangen!</h3>
                <p class="bm-status" id="bm-done-msg" style="color:var(--muted);"></p>
                <p class="bm-note">Sie erhalten von uns Zugangsdaten zu Ihrem Kundenbereich, sobald wir Ihre Bewertungen gesichert haben.</p>
                <a class="btn btn--primary" href="/">Zur Startseite</a>
              </div>
            </div>

          </div>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container footer-inner">
      <div class="footer__brand">
        <span class="brand__text">Watt'n <strong>Auftritt</strong></span>
        <p>Wir bringen Ihren Auftritt digital zu Ihren Kunden.</p>
      </div>
      <nav class="footer__nav" aria-label="Footer">
        <a href="/">Startseite</a>
        <a href="/#leistungen">Leistungen</a>
        <a href="/bewertungsmanagement/">Bewertungsmanagement</a>
        <a href="/bewertungsmanagement/kunde/login.php">Kundenbereich</a>
        <a href="/impressum.html">Impressum</a>
        <a href="/datenschutz.html">Datenschutz</a>
      </nav>
    </div>
    <div class="container footer__bottom">
      <p>© <span id="year"></span> Watt'n Auftritt. Alle Rechte vorbehalten.</p>
    </div>
  </footer>

  <script src="/bewertungsmanagement/assets/wizard.js" defer></script>
</body>
</html>
