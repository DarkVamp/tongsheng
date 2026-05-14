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
- `lessons` — `id, date, title, summary, homework_assigned, homework_types (JSON), created_by, created_at`
- `attendance` — `id, lesson_id, student_id, present`
- `homework_images` — `id, lesson_id, family_id, homework_type, file_path, original_filename, mime_type, uploaded_at`
- `homework_audio` — `id, lesson_id, family_id, homework_type, filename, mime_type, file_size, uploaded_at`
- `invitations` — noch in DB, Funktion aber entfernt

### Beziehungen
- `users.family_id → families.id ON DELETE CASCADE`
- `comments.author_id → users.id ON DELETE SET NULL`
- `comment_reactions.(comment_id, user_id)` unique
- `attendance.lesson_id → lessons.id ON DELETE CASCADE`
- `attendance.student_id → users.id ON DELETE CASCADE`
- `homework_images.lesson_id → lessons.id ON DELETE CASCADE`
- `homework_images.family_id → families.id ON DELETE CASCADE`
- `homework_audio.lesson_id → lessons.id ON DELETE CASCADE`
- `homework_audio.family_id → families.id ON DELETE CASCADE`

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
- `012_homework_types.sql` ⏳ (in phpMyAdmin einspielen)
- `013_homework_audio.sql` ⏳ (in phpMyAdmin einspielen)

## Tech Stack

### Backend
- PHP 8 / Symfony 8
- MySQL mit Doctrine ORM
- Custom `ApiTokenAuthenticator` (kein Lexik)
- Filesystem-Storage: `var/recordings/`, `var/homework/`, `var/homework_audio/`
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
- Lehrerin konfiguriert Typen pro Stunde im Hausaufgaben-Tab (Toggle-Pills)
- Hausaufgaben-Typen: `lesen` 🎙️, `schreiben` 📷, `schriftlich` 📝, `malen` 🎨, `sonstiges` 🎙️📷
  - Foto-Typen (`schreiben`, `schriftlich`, `malen`, `sonstiges`): `POST /api/lessons/{id}/homework`
  - Audio-Typen (`lesen`, `sonstiges`): `POST /api/lessons/{id}/homework-audio`
- Einreichungsansicht im Hausaufgaben-Tab: pro Stunde aufklappen → nach Typ gegliedert, pro Familie Badges + Thumbnails/Audio
- API-Endpunkte:
  - `GET /api/lessons/latest-homework` — letzte Stunde + eigene Bilder (Familie) oder alle Familien-Einreichungen (Lehrerin)
  - `POST /api/lessons/{id}/homework` — Bild hochladen (family_member)
  - `GET /api/homework/{id}/image` — Bild abrufen (auth)
  - `POST /api/homework/{id}/delete` — Bild löschen
  - `POST /api/lessons/{id}/homework-audio` — Audio hochladen (family_member)
  - `GET /api/homework-audio/{id}/stream` — Audio streamen (auth)
  - `POST /api/homework-audio/{id}/delete` — Audio löschen
  - `GET /api/lessons/{id}/homework/all` — alle Einreichungen (Bilder) pro Familie, nur Lehrerin
  - `GET /api/lessons/{id}/homework/by-type` — nach Typ gruppiert (Bilder + Audio), nur Lehrerin
- `homework_audio` ersetzt das alte Aufnahmen-System (recordings wird schrittweise abgelöst)

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
4. **Hausaufgaben** — pro Unterrichtsstunde konfigurieren welche Typen aufgegeben wurden (Lesen 🎙️, Schreiben 📷, Schriftlich 📝, Malen 🎨, Sonstiges 🎙️📷)

## PHPUnit Tests (195 Tests, alle grün)

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
`1.1.0` (frontend/package.json)

## Laufender Umbau (begonnen 2026-05-13)
Großer Umbau der App — schrittweise:
- ✅ **Unterricht-Tab:** 📚-Toggle entfernt, 📝-Zusammenfassung-Button eingebaut
- ✅ **Hausaufgaben-Tab (Lehrer):** neuer 4. Tab — Typen konfigurieren + Einreichungsansicht (aufklappbar, nach Typ, mit Bilder/Audio)
- ✅ **HomeworkAudio-System:** `homework_audio`-Tabelle, Controller, Tests (ersetzt Recordings)
- ✅ **FamilyDashboard umstrukturiert:** 3 Tabs — Hausaufgaben (pro Typ mit Foto-Upload), Report (Lektionsliste mit Zusammenfassungen), Kommunikation (Placeholder)
- ✅ **Hausaufgaben-Tab (Familie):** Audio-Aufnahme für lesen/sonstiges implementiert; latestHomework liefert jetzt auch Audio
- ⏳ **Eltern sehen Zusammenfassung** im FamilyDashboard (noch offen)
- ⏳ **Recordings-System abschalten** wenn homework_audio voll in Betrieb
- Weitere Umbau-Schritte folgen

## Offene Aufgaben
1. Cron Job auf All-Inkl.com für automatische Aufnahmen-Löschung
2. `frontend/src/api/invitations.js` entfernen (ungenutzt)
3. **FamilyDashboard:** Hausaufgaben-Einreichungen pro Typ (Audio für lesen/sonstiges, Foto für Rest)
4. Eltern-Ansicht für Zusammenfassung im FamilyDashboard
5. SQL 012 + 013 in phpMyAdmin auf Produktion einspielen
6. Recordings-System deprecaten/entfernen sobald homework_audio produktiv

## Accounts
- Lehrerin: ysong@song-kraus.com (role: teacher, locale: zh)
- Familie: rkraus@song-kraus.com (role: family_member, locale: de)
