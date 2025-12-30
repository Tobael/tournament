# Magic Tournament Software (MVP – Swiss-only)

Webbasierte Turnier-Software für eine Magic-the-Gathering-Spielgruppe  
gebaut mit **Laravel + Livewire**, optimiert für **mobile Nutzung** und **Live-Anzeige auf großen Displays**.

---

## 🎯 Ziel des Projekts

Die Software ermöglicht es einer Magic-Spielgruppe:

- Turniere zu organisieren
- Spieler zu verwalten
- Paarungen automatisch zu generieren (Swiss-System)
- Ergebnisse schnell per Smartphone einzutragen
- den aktuellen Turnierstand live (auch auf Beamer/TV) anzuzeigen

Das MVP fokussiert sich bewusst auf **Swiss-only**, ist aber von der Architektur her auf spätere Erweiterungen (Top Cut, KO-Baum, mehrere Gruppen) vorbereitet.

---

## 🧑‍🤝‍🧑 Nutzer & Rollen

### Spieler
- Registrierung & Login (Laravel Auth)
- Anmeldung zu Turnieren
- Angabe eines **Decknamens pro Turnier**
- Mobile Ergebnis-Meldung für eigene Matches
- Einsicht in aktuelle Runde & Rangliste

### Tournament Organizer (TO)
- Rolle wird **pro Turnier** vergeben
- Starten und Verwalten von Runden
- Automatische Generierung von Paarungen
- Korrigieren von Ergebnissen
- Beenden von Turnieren

### Admin
- Darf Turniere anlegen
- Hat alle TO-Rechte

### Zuschauer
- Kein Login notwendig
- Zugriff auf öffentliche Live-Ansichten

---

## 🏆 Turnier-Funktionen (MVP)

### Turnierform
- **Schweizer System (Swiss-only)**

### Turnier-Lebenszyklus
1. Turnier anlegen (Admin)
2. Spieler melden sich an
3. Turnierstart
4. Mehrere Swiss-Runden
5. Turnier beenden

### Paarungen
- Automatisch generiert
- Sortierung nach Punkten
- Unterstützung von **Byes** bei ungerader Spieleranzahl
- Keine doppelten Byes, wenn vermeidbar

---

## 🔁 Runden & Matches

- Jede Runde besteht aus mehreren Matches
- Match besteht aus:
  - Spieler A
  - Spieler B (oder Bye)
- Match-Status:
  - offen
  - gemeldet
  - bestätigt (optional)

---

## 📱 Ergebnis-Eingabe (Mobile First)

- Große, touch-optimierte Buttons:
  - 2–0
  - 2–1
  - 1–2
  - 0–2
  - Draw
- Ergebnis kann gemeldet werden von:
  - einem der beiden Spieler
- Optionale Ergebnisbestätigung:
  - konfigurierbar **pro Turnier**
- TO/Admin kann Ergebnisse jederzeit korrigieren

### Punktevergabe
- Sieg: 3 Punkte
- Unentschieden: 1 Punkt
- Niederlage: 0 Punkte
- Bye: Sieg

---

## 📊 Rangliste & Live-Ansicht

### Rangliste
- Sortierung nach Punkten
- Live-Updates via Livewire
- Vorbereitung für spätere Tie-Breaker

### Live-Ansicht (Beamer / TV)
- Öffentliche URL
- Kein Login erforderlich
- Vollbildmodus
- Große Schrift & klare Darstellung
- Automatische Aktualisierung
- Geeignet für:
  - Beamer
  - TV
  - Tablets

---

## 🧩 Technische Grundlagen

- **Backend:** Laravel
- **Frontend:** Livewire + Tailwind CSS
- **Auth:** Laravel Auth (Breeze)
- **Live Updates:** Livewire Polling
- **Mobile First Design**
- **Skalierbar** (10 → 100+ Spieler problemlos)

---

## 🗂️ Domänenmodell (vereinfacht)

- User
- Group (derzeit eine, später mehrere)
- Tournament
- TournamentUser (Teilnahme + Deckname)
- Round
- Match

---

## 🚀 Geplante Erweiterungen (nicht im MVP)

- Swiss + Top Cut (Top 4 / Top 8)
- KO-Turnierbaum mit Animationen
- Mehrere Gruppen / Organisationen
- Erweiterte Tie-Breaker (OMW%, GW%)
- Social Login
- Wiederholungsvermeidung bei Pairings
- Statistiken & Historie

---

## 🧪 Ziel des MVP

Ein stabiles, mobiles, live-fähiges Turniersystem,
das **sofort nutzbar** ist und als **saubere Basis** für zukünftige Magic-Turnierfeatures dient.

---

