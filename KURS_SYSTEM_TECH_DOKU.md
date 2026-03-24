# Technische Dokumentation: Kurs-System

## 🏗️ Architektur-Überblick

```
PUBLIC-WEBSITE
│
├── kurse.php (Hauptdatei)
│   ├── 📁 Funktionen zur Datenverwaltung
│   ├── 📁 HTML-Rendering (Übersicht + Detail)
│   ├── 📁 CSS (Moderne Cards + Responsive)
│   └── 📁 JavaScript (Form-Handling + WhatsApp)
│
└── inside/kurs_data/
    ├── demo.json (Beispielkurs)
    ├── bewegungsfluss.json
    ├── firmenfitness.json
    └── KURS_ANLEITUNG.md
```

## 🔄 PHP-Funktionen

### `loadAllKurse(): array`
Lädt alle Kurse aus `kurs_data/*.json`. Sortiert nach `created_at` (neueste zuerst).

```php
$allKurse = loadAllKurse();
// Gibt Array mit allen Kursen zurück
```

### `loadKurs(string $slug): ?array`
Lädt einen einzelnen Kurs nach Slug.

```php
$kurs = loadKurs('kraft-anfaenger');
// Gibt Kurs-Array oder null zurück
```

### `resolveKursImageUrl(string $imagePath): string`
Normalisiert Bildpfade (mit oder ohne `uploads/` Prefix).

```php
$url = resolveKursImageUrl('uploads/einblicke.jpg');
// Gibt 'inside/uploads/einblicke.jpg' zurück
```

### `formatZeitSlot(array $slot): string`
Formatiert Termine zu deutschem Format.

```php
$slot = ['datum' => '2026-03-25', 'von' => '17:30', 'bis' => '18:30'];
echo formatZeitSlot($slot);
// Output: "Mittwoch, 25.03.2026 · 17:30 bis 18:30 Uhr"
```

## 📱 HTML-Struktur (Semantisch)

### Kurs-Übersicht (`kurse.php` ohne slug)

```html
<main class="kurs-page">
  <!-- Hero-Sektion -->
  <section class="hero-shell">
    <div class="page-intro">
      <span class="eyebrow">Angebot</span>
      <h1>Unsere Kurse</h1>
      <p>...</p>
    </div>
    <div class="hero-metrics">
      <!-- Metriken -->
    </div>
  </section>

  <!-- Kurse-Grid -->
  <section class="grid">
    <article class="course-card">
      <img class="course-image" src="..." alt="...">
      <div class="course-card-head">
        <span class="niveau">Anfänger</span>
        <h2>Kursname</h2>
      </div>
      <div class="course-card-body">
        <p>Zielgruppe info</p>
        <p>Kurzbeschreibung</p>
        <div class="course-meta">
          <!-- Meta-Items (Termine, Ort, etc) -->
        </div>
      </div>
      <div class="course-card-footer">
        <span class="course-price">💶 89,00 €</span>
        <div class="button-group">
          <a href="..." class="button-small primary">Platz anfragen</a>
          <a href="wa.me/..." class="button-small whatsapp">WhatsApp</a>
        </div>
      </div>
    </article>
  </section>
</main>
```

### Kurs-Detail (`kurse.php?slug=NAME`)

```html
<main class="kurs-page">
  <!-- Zurück-Button + Titel -->
  <div class="page-intro detail-intro">
    <a href="kurse.php" class="detail-back">← Zurück</a>
    <h1>Kursname</h1>
  </div>

  <!-- Detail-Layout -->
  <section class="detail-layout">
    <!-- Hauptinhalt -->
    <article class="detail-main">
      <div class="detail-image-wrap">
        <img class="detail-image" ...>
      </div>
      <div class="detail-head">
        <h1>Kursname</h1>
        <p>Tagline</p>
      </div>
      <div class="detail-body">
        <!-- Info-Pills -->
        <!-- Zielgruppe -->
        <!-- Kursinfos (Fact-Grid) -->
        <!-- Ausführliche Beschreibung -->
      </div>
    </article>

    <!-- Sidebar: Anmeldung -->
    <aside class="detail-side">
      <div class="side-head">
        <h3>Jetzt Platz anfragen</h3>
      </div>
      <div class="side-body">
        <div class="price-box">💶 Preis</div>
        <ul class="side-points"><!-- Vorteile --></ul>
        <form id="anmeldeForm">
          <!-- Formularfelder -->
        </form>
        <!-- WhatsApp-Button optional -->
      </div>
    </aside>
  </section>
</main>
```

## 🎨 CSS-Struktur

### Basis-Layout
- **Kurs-Page:** Max-Width 1080px, responsive padding
- **Grid:** Auto-fill mit minmax(285px, 1fr) – 3-4 Spalten auf Desktop
- **Cards:** Flex-Layout, Hover-Effekte (translateY -3px)

### Color-Variablen (nutzen)
```css
--brand-color        /* Primär */
--accent-color       /* Sekundär */
--background-color   /* Hintergrund */
--text-color         /* Text */
--text-muted         /* Grauer Text */
--border-radius      /* Standard: 12px */
```

### Responsive Breakpoints
- **Desktop:** 1080px width
- **Tablet:** max-width 860px (Detail-Layout wird stapelbar)
- **Mobile:** Vollbreite mit Padding

### Card-Styling
```css
.course-card {
  border: 1px solid var(--card-border);
  border-radius: var(--border-radius);
  box-shadow: var(--card-shadow);
  transition: transform 0.22s, box-shadow 0.24s;
}

.course-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 22px 40px rgba(0,0,0,0.12);
}
```

## 📡 Datenfluss

```
URL-Parameter ?slug=NAME
    ↓
normalizeKursSlug()  ← Sanitize
    ↓
loadKurs(slug)  ← Aus JSON laden
    ↓
$kurs Array
    ↓
HTML-Template
    ↓
Browser rendered
```

## 🔐 Sicherheit

### XSS-Schutz
Alle Ausgaben werden mit `e()` escaped:
```php
<?= e($kurs['name']) ?>  ✅ Safe
<?= $kurs['name'] ?>     ❌ Nicht safe
```

### Input-Validierung
- Slug wird normalisiert (nur alphanumerisch + Bindestrich)
- JSON wird json_decode() mit `true` (array mode) geladen

### CORS/CSRF
Form nutzt FormData mit POST zu `kurs-anmeldung.php`

## 🔗 URL-Struktur

### Übersichtsseite
```
/kurse.php
```

### Detail-Seite
```
/kurse.php?slug=kraft-anfaenger
```

Query-Parameter wird automatisch normalisiert:
- `?slug=Kraft-Anfänger` → normalisiert zu `kraft-anfaenger`

## 📦 API-Endpoints

### Frontend Form Submit
```javascript
POST /kurs-anmeldung.php
Content-Type: application/x-www-form-urlencoded

kurs=Kursname&name=Max&email=max@example.com
```

**Response:**
```json
{
  "success": true,
  "message": "Danke für deine Anmeldung!"
}
```

## 🎯 Template-Tags

### Für HTML
- `<?= ?>` – Kurz-Echo
- `<?php if (): ?>` – Bedingungen
- `<?php foreach (): ?>` – Schleifen

### Code-Konventionen
```php
// Funktionen
static fn => Anonyme Funktionen
...(...array $x) => Spread Operator

// Arrays
$data[] = value;  // Append
$key => $value;   // Assoc

// Styles
display: grid;    // Modern Layout
color-mix()       /* CSS-Farben */
```

## 🚀 Performance-Tipps

1. **Bilder:** Max 500KB pro Bild, lazy-loading nutzen
2. **JSON:** Anzahl Kurse begrenzt (< 50 Kurse optimal)
3. **CSS:** Mit Variablen arbeiten (weniger Wiederholungen)
4. **JS:** Event-Delegation nutzen

## 🧪 Testing-Checkliste

- [ ] Neue Kurse werden angezeigt
- [ ] Kurse sind sortiert (neueste zuerst)
- [ ] /kurse.php?slug=demo lädt Detail-Seite
- [ ] /kurse.php?slug=nonexistent zeigt Error
- [ ] Formulare werden gesendet
- [ ] WhatsApp-Links funktionieren
- [ ] Responsive auf Mobile (< 600px)
- [ ] JSON-Validierung (Syntax-Fehler)

## 📝 Erweiterungsmöglichkeiten

1. **Filter/Suche:** Kurs-Filter nach Niveau, Ort, Preis
2. **Kalender:** Verwandte Events anzeigen
3. **Bewertungen:** Star-Ratings pro Kurs
4. **Bulk-Import:** CSV zu JSON Converter
5. **Buchungssystem:** Echte Reservierungen
6. **E-Mail-Template:** HTML-E-Mails mit Kursdaten

---

**Entwickler-Support:** Alle Dateien sind gut kommentiert. Bei Fragen: `kurse.php` durchlesen.
