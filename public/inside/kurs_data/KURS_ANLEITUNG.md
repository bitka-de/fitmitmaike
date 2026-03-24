# Kurs-Verwaltung Anleitung

## 📋 Überblick

Die Kurse werden als **JSON-Dateien** im Verzeichnis `public/inside/kurs_data/` verwaltet. Jede JSON-Datei repräsentiert einen Kurs und wird automatisch auf der Kursseite angezeigt.

## 🆕 Neuen Kurs erstellen

### Schritt 1: JSON-Datei erstellen

1. Im Editor ein neues File erstellen: `public/inside/kurs_data/NAME_DEINES_KURSES.json`
2. Beispiel-Template kopieren (siehe unten)
3. Mit Daten aus dem Formular füllen

### Schritt 2: Gültige Slugs verwenden

Der **Slug** ist ein eindeutiger Identifikator:
- Nur Kleinbuchstaben: `bewegungsfluss`
- Keine Umlaute (werden zu: ä→ae, ö→oe, ü→ue, ß→ss)
- Nur Bindestriche, keine Unterstriche
- Wird automatisch normalisiert, daher wird dieser exakte Name in der URL verwendet

**Gültige Slugs:**
- ✅ `kraft-anfaenger`
- ✅ `bewegungsfluss`
- ✅ `firmenfitness`

**Ungültige Slugs:**
- ❌ `Kraft Anfänger` (Umlaute + Leerzeichen)
- ❌ `kraft_anfaenger` (Unterstriche statt Bindestriche)

## 📋 JSON-Struktur (mit allen 8 Feldern)

```json
{
  "name": "Krafttraining für Anfänger",
  "slug": "kraft-anfaenger",
  "zielgruppe": "Anfänger ohne Trainingserfahrung, die sich fit und stark fühlen möchten",
  "beschreibung": "Ein umfassendes Anfänger-Trainingsprogramm mit persönlicher Begleitung. Du lernst die richtigen Techniken...",
  "ort": "Karlsruhe",
  "termine": [
    {
      "datum": "2026-03-25",
      "von": "17:30",
      "bis": "18:30"
    }
  ],
  "max_teilnehmer": "12",
  "preis": "89,00 €",
  "besonderheit": "Inklusive kostenloses Fitnessprofil & individuelle Trainingsplanung",
  "niveau": "Anfänger",
  "telefon": "+49 721 123456",
  "bild": "uploads/Einblicke-ins-Training/einblicke-1.jpeg",
  "created_at": "2026-03-24 12:00:00",
  "updated_at": "2026-03-24 12:00:00"
}
```

## 📝 Feld-Beschreibungen

| Feld | Typ | Pflicht? | Beschreibung |
|------|-----|---------|-------------|
| **name** | Text | ✅ | Der Kursname (wird in Überschrift + Browser-Tab angezeigt) |
| **slug** | Text | ✅ | Eindeutige URL (nur Kleinbuchstaben, Bindestriche) |
| **zielgruppe** | Text | ✅ | Für wen ist dieser Kurs? (z.B. "Anfänger ohne Erfahrung") |
| **beschreibung** | Langtext | ✅ | Ausführliche Kursbeschreibung mit Nutzen |
| **ort** | Text | ✅ | Wo findet der Kurs statt? |
| **termine** | Array | ✅ | Liste mit Datum, Uhrzeit (von/bis) |
| **max_teilnehmer** | Text | ✅ | Maximale Teilnehmerzahl als Text ("12" oder "Max. 12") |
| **preis** | Text | ✅ | Preis mit Einheit (z.B. "89,00 €" oder "Auf Anfrage") |
| **besonderheit** | Text | ✅ | Besonderheit/Bonus (z.B. "Inklusive Fitnessprofil") |
| **niveau** | Text | ✅ | Anfänger / Fortgeschrittene / Alle Levels |
| **telefon** | Text | - | Telefon für WhatsApp-Integration ("+49 721 123456") |
| **bild** | Text | - | Pfad zu Kursbild (uploads/Einblicke-ins-Training/einblicke-1.jpeg) |
| **created_at** | Datetime | - | Erstellt am (AutoFill: YYYY-MM-DD HH:MM:SS) |
| **updated_at** | Datetime | - | Zuletzt aktualisiert (AutoFill: YYYY-MM-DD HH:MM:SS) |

## 📅 Termine-Format

**Mehrere Termine pro Kurs:**

```json
"termine": [
  {
    "datum": "2026-03-25",
    "von": "17:30",
    "bis": "18:30"
  },
  {
    "datum": "2026-03-27",
    "von": "17:30",
    "bis": "18:30"
  },
  {
    "datum": "2026-04-01",
    "von": "17:30",
    "bis": "18:30"
  }
]
```

**Datumsformat:** `YYYY-MM-DD` (ISO 8601)  
**Zeitformat:** `HH:MM` (24er Format)

## 🎨 Empfohlene Icon-Nutzung

Die Icons werden automatisch in der Kurs-Card angezeigt (können bei Bedarf angepasst werden):

- 🗓 Termine
- 📍 Ort
- 👥 Teilnehmerzahl
- ✨ Besonderheit
- 💶 Preis
- 📊 Level
- 💬 WhatsApp

## 📸 Bilder einbinden

Bilder sollten im Ordner `public/inside/uploads/` liegen:

```
public/inside/uploads/
├── Angebote/
├── Beiträge/
├── Einblicke-ins-Training/
└── Hero/
```

**Bildpfad im JSON:**
```json
"bild": "uploads/Einblicke-ins-Training/einblicke-1.jpeg"
```

**Empfehlung:** Bilder mindestens 800x500px, optimiert für Web (~200-400KB)

## 🔄 Kurs-Verwaltung (Operationen)

### Kurs hinzufügen
1. Neue `.json` Datei in `public/inside/kurs_data/` erstellen
2. Mit Daten füllen
3. Speichern → wird sofort angezeigt

### Kurs bearbeiten
1. Entsprechende `.json` datei öffnen
2. Felder anpassen
3. `updated_at` aktualisieren (optional)
4. Speichern → wird sofort aktualisiert

### Kurs veröffentlichung steuern
Kurse werden nach `created_at` sortiert (neueste zuerst):
- Neue Kurse zuerst platzieren: `created_at` auf heutiges Datum setzen

### Kurs löschen/verstecken
- **Komplett löschen:** JSON-Datei löschen
- **Verstecken:** Das Dateiname-Präfix mit `.DELETED-` beginnen (z.B. `.DELETED-kurs.json`)

## ✅ Checkliste vor dem Speichern

- [ ] Slug ist eindeutig (keine Duplikate)
- [ ] Slug aus dem Namen abgleitet (keine Umlaute, nur Kleinbuchstaben)
- [ ] Alle 8 Pflichtfelder ausgefüllt
- [ ] Termine im Format `YYYY-MM-DD` + `HH:MM`
- [ ] Mindestens 1 Termin
- [ ] Telefonnummer mit `+49` beginnt (für WhatsApp)
- [ ] JSON-Syntax valid (keine Fehler beim Speichern)
- [ ] Bild existiert unter `public/inside/uploads/`

## 🐛 Häufige Fehler

| Problem | Lösung |
|---------|--------|
| Kurs wird nicht angezeigt | Slug prüfen, JSON-Syntax prüfen (JSON-Validator nutzen) |
| Umlaute erscheinen falsch | Dateim UTF-8 abspeichern |
| WhatsApp-Button ist inaktiv | Telefonnummer im Format "+49 721 123456" eintragen |
| Bild wird nicht angezeigt | Dateipfad korrekt? Dateiendung prüfen? |
| Datum wird falsch dargestellt | Format `YYYY-MM-DD` verwenden (z.B. `2026-03-24`) |

## 💡 Best Practices

1. **Zielgruppe konkret:** Statt "Alle" besser "Anfänger ab 16 Jahren"
2. **Beschreibung verlockend:** Vorteile hervorheben, konkrete Beispiele
3. **Besonderheit einzigartig:** Z.B. "Kostenlose Trainingsplanung" statt generisch
4. **Preise klar:** "89,00 €" oder "Auf Anfrage" (nie mehrdeutig)
5. **Bilder hochwertig:** Repräsentativ für den Kurs
6. **Telefon aktuell:** Für WhatsApp-Support nötig

## 📊 Beispiel einer kompletten JSON

```json
{
  "name": "Yoga für Kraft & Ausdauer",
  "slug": "yoga-kraft",
  "zielgruppe": "Alle, die körperliche und mentale Kraft aufbauen möchten",
  "beschreibung": "Ein funktionales Yoga-Programm, das Kraft und Ausdauer trainiert. Perfekt als Ergänzung zum Krafttraining oder als eigenständiges Trainingsprogramm.",
  "ort": "Karlsruhe, Yoga Studio Mitte",
  "termine": [
    { "datum": "2026-04-01", "von": "17:00", "bis": "18:00" },
    { "datum": "2026-04-08", "von": "17:00", "bis": "18:00" }
  ],
  "max_teilnehmer": "15",
  "preis": "39,00 € pro Monat",
  "besonderheit": "Mit professionellem Yoga-Matte & online Zugang für zuhause",
  "niveau": "Anfänger",
  "telefon": "+49 721 123456",
  "bild": "uploads/Einblicke-ins-Training/yoga.jpg",
  "created_at": "2026-03-24 10:00:00",
  "updated_at": "2026-03-24 10:00:00"
}
```

---

**Fragen oder Probleme?** Alle Kurse werden durch die Funktionen in `public/kurse.php` automatisch geladen und dargestellt.
