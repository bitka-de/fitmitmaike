# 🎯 Kurs-System: Quick Start Guide

## ⚡ 30-Sekunden Überblick

Die **Kurse-Sektion** ist ein voll funktionsfähiges System für dynamische Kursdarstellung:

✅ **3 Kurse bereits vorhanden** (zum Testen)  
✅ **Moderne, responsive Cards**  
✅ **Automatische Sortiering** (neueste zuerst)  
✅ **WhatsApp-Integration** (optional)  
✅ **Unverbindliche Anfrage-Formulare**  
✅ **Einfach erweiterbar** (JSON-basiert)

---

## 🚀 Erste Schritte

### 1️⃣ Kurse anschauen
```
Öffne: /kurse.php
```
Du siehst die 3 Beispielkurse mit allen Infos.

### 2️⃣ Einen Kurs bearbeiten  
Öffne die JSON-Datei im Editor:
```
public/inside/kurs_data/demo.json
  ↓
Bearbeite Felder (Name, Preis, Beschreibung, etc.)
  ↓
Speichern
  ↓
Kurse.php neuladen → Änderungen sofort sichtbar!
```

### 3️⃣ Neuen Kurs hinzufügen
```
1. Neue Datei erstellen: public/inside/kurs_data/dein-kurs.json
2. Inhalt von demo.json kopieren und anpassen
3. Speichern → Kurs ist sofort online!
```

---

## 📋 Die 8 Felder (Checkliste)

Jeder Kurs braucht diese **8 Felder**:

```json
{
  "name": "Kursname",                          // ✅ Zeigt oben in der Card
  "slug": "kurs-url-name",                     // ✅ URL-safe Namen (keine Umlaute)
  "zielgruppe": "Für wen ist dieser Kurs?",    // ✅ Z.B. "Anfänger ab 16"
  "beschreibung": "Was lernst du hier...",     // ✅ Längere Beschreibung
  "ort": "Hamburg, Studio Zentrum",            // ✅ Trainingsort
  "termine": [...],                            // ✅ Mit Datum + Uhrzeit
  "max_teilnehmer": "15",                      // ✅ Als Text
  "preis": "89,00 €",                          // ✅ Mit Einheit
  "besonderheit": "Bonus/Highlight",           // ✅ Z.B. "Kostenlos Fitnessprofil"
  
  // Optional:
  "niveau": "Anfänger",
  "telefon": "+49 721 123456",  // Für WhatsApp
  "bild": "uploads/kurs.jpg"
}
```

---

## 🎨 Wie Kurse angezeigt werden

### 📱 Übersichtsseite (Alle Kurse)
```
┌─────────────────┐
│   HERO-HERO     │ ← "Unsere Kurse" Intro
│  (mit Metrics)  │
└─────────────────┘

┌──────┐ ┌──────┐ ┌──────┐
│Card 1│ │Card 2│ │Card 3│  ← 3-4 cards pro Reihe (responsive)
│      │ │      │ │      │
└──────┘ └──────┘ └──────┘

Jede Card zeigt:
  - Bild
  - Kursname + Level
  - Zielgruppe
  - Kurzbeschreibung
  - Wichtige Meta (Termine, Ort, Plätze, Besonderheit)
  - Preis
  - Buttons: [Platz anfragen] [WhatsApp]
```

### 📄 Detail-Seite (Ein Kurs)
```
← Zur Übersicht

┌──────────────────────┐ ┌─────────────────┐
│   KURS-DETAIL        │ │  ANMELDEFORMULAR│
│  (mit großem Bild)   │ │    (Sidebar)    │
│                      │ │                 │
│  Zielgruppe          │ │ [ ] Name        │
│  Beschreibung        │ │ [ ] Email       │
│  Kursinfos (Facts)   │ │ [Anfrage senden]│
│                      │ │                 │
│  Längerer Text...    │ │ oder WhatsApp   │
└──────────────────────┘ └─────────────────┘
```

---

## 💾 File-Struktur

```
public/
  kurse.php                        ← Hauptdatei (ändern nicht nötig!)
  inside/
    kurs_data/
      demo.json                    ← 📝 Beispielkurs - BEARBEITBAR
      bewegungsfluss.json          ← 📝 Beispiel 2
      firmenfitness.json           ← 📝 Beispiel 3
      KURS_ANLEITUNG.md           ← 📚 Detaillierte Anleitung
      
      [NEUE KURSE HIER HINZUFÜGEN] ← JSON-Dateien
```

---

## 🔥 Häufige Aufgaben

### Kurs-Name ändern?
```json
"name": "Neuer Kurs-Name"
// ↑ Save und fertig
```

### Kurs von der Website nehmen?
**Option 1 (Sofort weg):**
```
Datei löschen: demo.json
```

**Option 2 (Verstecken):**
```
Datei umbenennen: .DELETED-demo.json
```

### Neuen Termin hinzufügen?
```json
"termine": [
  { "datum": "2026-03-25", "von": "17:30", "bis": "18:30" },
  { "datum": "2026-04-01", "von": "17:30", "bis": "18:30" }  ← NEU
]
```

### Preis ändern?
```json
"preis": "99,00 €"  ← Immer mit Einheit schreiben
```

### WhatsApp einrichten?
```json
"telefon": "+49 721 123456"  ← Mit Ländercode!
// Das aktiviert den WhatsApp-Button automatisch
```

---

## ✅ Validierungs-Tipps

**Vor dem Speichern prüfen:**

- [ ] JSON valid? (keine roten Fehler im Editor)
- [ ] Alle 8 Felder vorhanden?
- [ ] `"slug"` ist einzigartig (nicht zweimal)?
- [ ] `"slug"` hat keine Umlaute: `kraft-anfaenger` ✅  (nicht: `kraft-anfänger`)
- [ ] `"termine"` hat Datumsformat: `2026-03-25`
- [ ] `"preis"` hat Einheit: `"89,00 €"`
- [ ] JSON-Kommas richtig? (letzte Feld KEINE Komma)

**🔴 Häufigster Fehler:**
```json
"besonderheit": "Bonus!"
}  ← Fehler: keine Komma nach Feld BEFORE dieser Zeile!
```

---

## 🎠 Live-Beispiel

**demo.json aktuelle**, Inhalt:
- **Name:** Krafttraining für Anfänger
- **Zielgruppe:** Anfänger ohne Erfahrung
- **Preis:** 89,00 €
- **Plätze:** Max 12 Personen
- **Zeiten:** Montag & Mittwoch, 17:30-18:30 Uhr
- **Besonderheit:** Inklusive kostenloses Fitnessprofil

**➜** Öffne `/kurse.php?slug=demo` und sehe die Detail-Seite!

---

## 🆘 Notfall-Tipps

| Problem | Lösung |
|---------|--------|
| **Kurs nicht sichtbar** | Slug in JSON prüfen, kurse.php F5 neuladen |
| **JSON-Fehler** | JSON prüfen auf Kommas/Klammern |
| **Bild nicht da** | Pfad prüfen: `uploads/Einblicke-ins-Training/file.jpg` |
| **WhatsApp-Button fehlt** | `"telefon": "+49..."` im JSON eintragen |
| **Termin-Format falsch** | `"datum": "YYYY-MM-DD"` verwenden |

---

## 📚 Weiterführende Docs

- **Detaillierte Anleitung:** `public/inside/kurs_data/KURS_ANLEITUNG.md`
- **Technische Doku:** `KURS_SYSTEM_TECH_DOKU.md` (für Entwickler)
- **Haupt-Datei:** `public/kurse.php` (Code-Referenz)

---

## 🎯 Nächste Schritte

1. **Öffne** `/kurse.php` und schau dir die Kurse an
2. **Bearbeite** `public/inside/kurs_data/demo.json` und ändere den Namen
3. **Erstelle** einen neuen Kurs: `public/inside/kurs_data/mein-kurs.json`
4. **Teste** alle Funktionen (Formulare, WhatsApp, Links)

**Viel Erfolg! 🚀**

---

*Hinweis: Diese Struktur ist produktiv und skalierbar bis ~50 Kurse. Für größere Kataloge ein CMS/Backend empfohlen.*
