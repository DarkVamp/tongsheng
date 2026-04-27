# Tongsheng — Projektbeschreibung

## Zweck
App für Sprachaufnahmen von Kindern beim Chinesisch-Unterricht. Familienmitglieder laden täglich Aufnahmen hoch, die Lehrerin kann alles einsehen, kommentieren und die Anwesenheit verwalten.

## Rollen

### Lehrerin (`teacher`)
- Sieht alle Aufnahmen aller Familien, kann kommentieren und löschen
- Verwaltet Familien: anlegen, löschen (Löschen cascadet alle Mitglieder + Aufnahmen)
- Lädt Familienmitglieder per tokenbasiertem Einladungslink ein (7 Tage gültig, kein E-Mail-Versand — Link wird kopiert)
- Markiert Familienmitglieder als Schüler (`is_student`)
- Legt Unterrichtsstunden an und pflegt die Anwesenheitsliste

### Familienmitglied (`family_member`)
- Maximal 1 Sprachaufnahme pro Tag hochladen
- Sieht alle Aufnahmen der eigenen Familie (`family_id`)
- Kann kommentieren (eigene und fremde Aufnahmen der Familie)

## Datenmodell

### Tabellen
- `users` — `id, email, family_name, role, family_id, is_student, password, api_token, locale`
- `families` — `id, name, created_at`
- `invitations` — `id, email, role, family_id, token, invited_by, created_at, expires_at`
- `recordings` — `id, user_id, filename, mime_type, file_size, recorded_at, delete_at`
- `comments` — `id, recording_id, user_id, content, created_at`
- `lessons` — `id, date, title, created_by, created_at`
- `attendance` — `id, lesson_id, student_id, present`

### Beziehungen
- `users.family_id → families.id ON DELETE CASCADE`
- `invitations.family_id → families.id ON DELETE CASCADE`
- `attendance.lesson_id → lessons.id ON DELETE CASCADE`
- `attendance.student_id → users.id ON DELETE CASCADE`

### SQL-Migrationen (in phpMyAdmin auszuführen)
- `001_initial_schema.sql` ✅
- `002_initial_teacher.sql` ✅
- `003_family_user_ralf.sql` ✅
- `004_add_locale.sql` ✅
- `005_family_member.sql` ✅
- `006_lessons_attendance.sql` ✅
- `007_users_eva_joshua.sql` ✅
- `008_families_refactor.sql` — **noch ausstehend**

## Tech Stack

### Backend
- PHP 8 / Symfony 8
- MySQL mit Doctrine ORM
- Custom `ApiTokenAuthenticator` (kein Lexik)
- Filesystem-Storage für Audiodateien (`%app.recordings_dir%`)

### Frontend
- React + Vite, PWA (autoUpdate Service Worker)
- lucide-react für Aktionsicons
- Mono Icons SVG (Icon.jsx) für Chevron-Toggles und Tab-Icons
- `npm install` benötigt `--legacy-peer-deps` (vite-plugin-pwa@1.2.0 vs Vite 8)

## Teacher-Dashboard Tabs
1. **Aufnahmen** — gefilterte Liste aller Aufnahmen gruppiert nach Familie, Kommentarfunktion, Einladungen verwalten
2. **Schüler** — Familien anlegen/löschen, Mitglieder als Schüler markieren
3. **Unterricht** — Unterrichtsstunden anlegen/löschen, Anwesenheit pro Stunde erfassen

## Deployment
- All-Inkl.com Shared Hosting (kein SSH, nur FTP/SFTP)
- FTP-Host: `w0095185.kasserver.com`, User: `f0184253`
- Domain: https://tongsheng.app/
- Apache blockiert HTTP DELETE → alle Lösch-Operationen via `POST /resource/{id}/delete`
- Nach Deployment: App komplett schließen + neu öffnen wegen PWA-Cache

## Aufbewahrung
- Aufnahmen werden nach 5 Wochen automatisch gelöscht (Cron Job + `delete_at`-Feld)
