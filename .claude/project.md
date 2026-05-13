# 童声 — Projektbeschreibung

## Zweck
App für Sprachaufnahmen von Kindern beim Chinesisch-Unterricht. Familienmitglieder laden täglich Aufnahmen hoch, die Lehrerin kann alles einsehen, kommentieren und die Anwesenheit verwalten.

## App-Name
童声 (Kinderstimme) — umbenannt von 同声 am 2026-05-13.

## Rollen

### Lehrerin (`teacher`)
- Sieht alle Aufnahmen aller Familien, kann kommentieren und löschen
- Verwaltet Familien und Mitglieder direkt (kein Einladungs-Workflow mehr)
- Markiert Familienmitglieder als Schüler (`is_student`)
- Legt Unterrichtsstunden an, pflegt Anwesenheitsliste
- Schreibt Zusammenfassung pro Unterrichtsstunde (📝)

### Familienmitglied (`family_member`)
- Maximal 1 Sprachaufnahme pro Tag hochladen
- Sieht alle Aufnahmen der eigenen Familie (`family_id`)
- Kann kommentieren, auf Kommentare reagieren (👍 ❤️ 👎)
- Lädt Hausaufgaben-Bilder hoch

## Datenmodell

### Tabellen
- `users` — `id, email, family_name, role, family_id, is_student, password, api_token, locale`
- `families` — `id, name, created_at`
- `recordings` — `id, user_id, filename, mime_type, file_size, recorded_at, delete_at`
- `comments` — `id, recording_id, author_id (nullable), content, created_at`
- `comment_reactions` — `id, comment_id, user_id, type` (unique je comment+user)
- `lessons` — `id, date, title, summary, homework_assigned, created_by, created_at`
- `attendance` — `id, lesson_id, student_id, present`
- `homework_images` — `id, lesson_id, family_id, file_path, original_filename, mime_type, uploaded_at`
- `invitations` — noch in DB, Funktion aber entfernt

### Beziehungen
- `users.family_id → families.id ON DELETE CASCADE`
- `comments.author_id → users.id ON DELETE SET NULL`
- `comment_reactions.(comment_id, user_id)` unique
- `attendance.lesson_id → lessons.id ON DELETE CASCADE`
- `attendance.student_id → users.id ON DELETE CASCADE`
- `homework_images.lesson_id → lessons.id ON DELETE CASCADE`
- `homework_images.family_id → families.id ON DELETE CASCADE`

### SQL-Migrationen (in phpMyAdmin auszuführen)
- `001_initial_schema.sql` ✅
- `002_initial_teacher.sql` ✅
- `003_family_user_ralf.sql` ✅
- `004_add_locale.sql` ✅
- `005_family_member.sql` ✅
- `006_lessons_attendance.sql` ✅
- `007_users_eva_joshua.sql` ✅
- `008_families_refactor.sql` ✅
- `009_comment_author_reactions.sql` ✅
- `010_homework.sql` ✅
- `011_lesson_summary.sql` ✅

## Tech Stack

### Backend
- PHP 8 / Symfony 8
- MySQL mit Doctrine ORM
- Custom `ApiTokenAuthenticator` (kein Lexik)
- Filesystem-Storage: `var/recordings/`, `var/homework/`
- Apache blockt HTTP DELETE → Workaround: `POST /resource/{id}/delete`

### Frontend
- React + Vite, PWA mit `registerType: 'prompt'` (UpdatePrompt.jsx)
- lucide-react für Aktionsicons
- `npm install` benötigt `--legacy-peer-deps` (vite-plugin-pwa@1.2.0 vs Vite 8)
- Audio-Requests via Axios als Blob (kein direkter `<audio src>` wegen Auth)
- `fix-webm-duration` korrigiert WebM-Metadaten vor Upload

### Icons (PWA)
- `public/icon-192.png`, `public/icon-512.png`, `public/apple-touch-icon.png`
- Quell-PNGs in `Code/tongsheng-icons/` (außerhalb Repo)
- Weiße Ränder trimmen: `magick -fuzz 5% -trim`, dann auf Quadrat mit `#5bbef5` erweitern

## Features

### Kommentare & Reaktionen
- Autor + Rolle-Badge pro Kommentar
- Reaktionen: 👍 ❤️ 👎 — toggle (same type = remove, different = change)
- `author_id` nullable (ON DELETE SET NULL)

### Hausaufgaben
- Lehrerin sieht Einreichungen pro Familie im Unterrichts-Tab
- Familien laden Bilder hoch (`POST /api/lessons/{id}/homework`)
- Bilder als Blob via Axios geladen, Lightbox bei Klick
- `homework_assigned`-Flag bleibt in DB, Toggle-Button entfernt (es gibt immer Hausaufgaben)
- Eigener Hausaufgaben-Bereich ist geplant

### Zusammenfassung (seit SQL 011)
- Lehrerin schreibt Zusammenfassung pro Unterrichtsstunde
- 📝-Button in Lesson-Actions → Inline-Textarea klappt auf
- Speichern via `PATCH /api/lessons/{id}` mit `{ summary }`
- Eltern-Ansicht folgt beim großen Umbau

### Glücksrad
- Drehdauer 3–4 Sekunden, 5–7 Umdrehungen

### i18n
- de + zh, `LocaleContext` mit `t()`-Hook, `frontend/src/i18n.js`
- Locale per `PATCH /api/me/locale` in DB gespeichert

## Teacher-Dashboard Tabs
1. **Aufnahmen** — gefilterte Liste aller Aufnahmen, Kommentarfunktion
2. **Familien** — Familien anlegen/löschen, Mitglieder verwalten, Schüler markieren
3. **Unterricht** — Stunden anlegen/löschen, Anwesenheit, Zusammenfassung (📝), Hausaufgaben-Einreichungen

## PHPUnit Tests (172 Tests, alle grün)

```bash
cd backend
PHP_INI_SCAN_DIR=/home/ralfk/.php/conf.d php bin/phpunit --no-coverage
```

- Test-DB: `test_tongsheng_test` (MariaDB lokal)
- Schema wird beim ersten Run automatisch angelegt
- Basis-Klasse: `tests/ApiTestCase.php`
- **Regel:** Jede Backend-Änderung muss mit Test abgesichert sein

## Deployment
- All-Inkl.com Shared Hosting (kein SSH, nur FTP/SFTP)
- FTP-Host: `w0095185.kasserver.com`, User: `f0184253`
- Domain: https://tongsheng.app/
- `export TONGSHENG_FTP_PASS=... && ./deploy.sh`
- `.env.local` per SFTP manuell hochladen
- SQL-Migrationen in phpMyAdmin einspielen
- Nach Deploy: PWA-Cache im Browser leeren

## Aufbewahrung
- Aufnahmen werden nach 5 Wochen automatisch gelöscht (Cron Job + `delete_at`-Feld)
- Cron Job auf All-Inkl.com noch nicht eingerichtet

## Aktuelle Version
`1.0.6` (frontend/package.json)

## Laufender Umbau (begonnen 2026-05-13)
Großer Umbau der App — schrittweise:
- ✅ **Unterricht-Tab:** 📚-Toggle entfernt, 📝-Zusammenfassung-Button eingebaut
- ⏳ **Hausaufgaben:** bekommt eigenen Bereich (noch offen)
- ⏳ **Eltern sehen Zusammenfassung** im FamilyDashboard (noch offen)
- Weitere Umbau-Schritte folgen

## Offene Aufgaben
1. Cron Job auf All-Inkl.com für automatische Aufnahmen-Löschung
2. `frontend/src/api/invitations.js` entfernen (ungenutzt)
3. Eigener Hausaufgaben-Bereich (neuer Tab oder eigene Seite)
4. Eltern-Ansicht für Zusammenfassung im FamilyDashboard
5. SQL 011 in phpMyAdmin auf Produktion einspielen (falls noch nicht done)

## Accounts
- Lehrerin: ysong@song-kraus.com (role: teacher, locale: zh)
- Familie: rkraus@song-kraus.com (role: family_member, locale: de)
