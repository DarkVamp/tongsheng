# Deployment auf All-Inkl.com

## Einrichtung

### 1. Dateien hochladen
Alle Dateien außer `var/`, `vendor/` per FTP/SFTP hochladen.
Das `public/`-Verzeichnis muss das Webroot sein (in All-Inkl.com einstellen).

### 2. Composer auf dem Server ausführen (per SSH)
```bash
composer install --no-dev --optimize-autoloader
```

### 3. .env.local anlegen (nur auf dem Server, NICHT committen)
```
APP_ENV=prod
APP_SECRET=<langen_zufaelligen_wert_generieren>
DATABASE_URL="mysql://DB_USER:DB_PASS@localhost:3306/DB_NAME?serverVersion=8.0&charset=utf8mb4"
CORS_ALLOW_ORIGIN='^https://deine-domain\.de$'
```

### 4. Datenbank anlegen
In All-Inkl.com Verwaltung eine MySQL-Datenbank anlegen, dann:
```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Recordings-Verzeichnis anlegen
```bash
mkdir -p var/recordings
chmod 755 var/recordings
```

### 6. Ersten Lehrer-Account anlegen
```bash
php bin/console app:user:create
```

### 7. Cron Job einrichten (All-Inkl.com Verwaltung → Cron Jobs)
```
0 2 * * *   php /pfad/zum/projekt/bin/console app:recordings:delete-expired
```

## API-Übersicht
| Methode | Pfad                                    | Beschreibung                         |
|---------|-----------------------------------------|--------------------------------------|
| POST    | /api/login                              | Login → gibt Token zurück            |
| POST    | /api/logout                             | Token löschen                        |
| GET     | /api/me                                 | Aktueller Benutzer                   |
| GET     | /api/recordings                         | Eigene (Familie) / alle (Lehrerin)   |
| POST    | /api/recordings                         | Aufnahme hochladen (max. 1/Tag)      |
| GET     | /api/recordings/{id}/audio              | Audiodatei abrufen/streamen          |
| GET     | /api/recordings/{recordingId}/comments  | Kommentare einer Aufnahme            |
| POST    | /api/recordings/{recordingId}/comments  | Kommentar hinzufügen (nur Lehrerin)  |
