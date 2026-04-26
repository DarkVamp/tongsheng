# Tongsheng — Projektbeschreibung

## Zweck
App für Sprachaufnahmen von Kindern. Eltern laden täglich Aufnahmen hoch, die Lehrerin kann alles einsehen und kommentieren.

## Rollen

### Familie (Eltern)
- Ein Account pro Familie
- Maximal 1 Sprachaufnahme pro Tag hochladen
- Kann nur eigene Aufnahmen sehen und abspielen

### Lehrerin
- Höherwertiger Account
- Kann alle Aufnahmen aller Familien sehen und abspielen
- Kann zu jeder Aufnahme einen Kommentar hinterlassen

## Tech Stack

### Backend
- PHP 8.x (Symfony)
- MySQL (Doctrine ORM)
- Filesystem-Storage auf All-Inkl.com
- Custom ApiTokenAuthenticator (kein Lexik)

### Frontend
- React JS (SPA, PWA — läuft auf iOS & Android)
- Vite als Build-Tool

## Hosting
- All-Inkl.com (Shared Hosting)
- React-Build als statische Dateien deployen
- PHP REST API im gleichen Webspace
- Cron Job für automatische Dateilöschung

## Datenaufbewahrung
- Aufnahmen werden nach 4–6 Wochen automatisch gelöscht (Cron Job)

## Offene Punkte (noch zu klären)
- Einladungssystem oder Selbstregistrierung?
- Wie viele Familien / Klassen ungefähr?
- DSGVO-Anforderungen?
- Exakte Aufbewahrungsdauer (4 oder 6 Wochen)?

## Deployment
- Hosting auf all-inkl.com
- FTP Upload für Deployment (User landet direkt im korrekten ROOT, kann direkt deployed werden)
- Host: w0095185.kasserver.com
- User: f0184253
- Domain: https://tongsheng.app/
