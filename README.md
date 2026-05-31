# Watt'n Auftritt – moderne Version

Eine moderne, schlanke Neuauflage der Website [wattnauftritt.de](https://wattnauftritt.de).
Die Originalseite läuft auf WordPress mit dem Divi-Theme; diese Version ist eine
**statische Single-Page-Site** ohne Build-Step, Datenbank oder Plugins – schnell,
sicher und überall hostbar.

## Was ist neu?

| | Original (WordPress/Divi) | Diese Version |
|---|---|---|
| Technik | WordPress + Divi + Plugins | reines HTML / CSS / JS |
| Ladezeit | viele Requests, schwer | minimal, ein paar KB |
| Wartung | Updates, Sicherheitslücken | praktisch wartungsfrei |
| Design | Standard-Theme | individuelles, modernes UI |
| Hosting | PHP-Server nötig | jedes Static-Hosting (Netlify, Pages, S3 …) |

## Inhalte

Alle Texte und Leistungen wurden aus der Originalseite übernommen:

- **Hero** – „Ihr Auftritt!" + Claim
- **Leistungen** – Branding, Web Design, SEO, Marketingstrategie, Hard- & Software, IT-Beratung
- **Warum wir** – „Mehr Zeit fürs Wichtige" & „Und wenn die Technik streikt?"
- **Referenzen** – TTS-Baudienstleistungen (+ Platzhalter)
- **Kontakt** – Formular mit `mailto`-Fallback

## Features

- Responsives Design (Mobile-First) mit Hamburger-Menü
- Sticky-Header mit Glass-Effekt, Scroll-Spy für aktive Navigation
- Reveal-Animationen via `IntersectionObserver`
- Wasser-/Watt-Wellenmotiv passend zum Namen
- Barrierearm: Skip-Link, ARIA-Labels, `prefers-reduced-motion`
- SEO: Meta-Description, Open-Graph-Tags, semantisches HTML

## Lokal ansehen

Einfach `index.html` im Browser öffnen, oder einen kleinen Server starten:

```bash
python3 -m http.server 8000
# → http://localhost:8000
```

## Struktur

```
.
├── index.html        # gesamte Seite (Single-Page)
├── assets/
│   ├── styles.css    # Design-System & Layout
│   └── main.js       # Navigation, Scroll-Spy, Reveal, Formular
└── README.md
```

## Anpassen

- **Farben/Abstände:** CSS-Custom-Properties oben in `assets/styles.css` (`:root`)
- **Kontakt-E-Mail:** `info@wattnauftritt.de` in `index.html` und `assets/main.js`
- **Formular mit Backend:** `mailto`-Fallback in `main.js` durch einen `fetch()`-Aufruf
  an einen Form-Dienst (z. B. Formspree, eigenes Endpoint) ersetzen
