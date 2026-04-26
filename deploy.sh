#!/usr/bin/env bash
# Tongsheng Deployment Script
# Voraussetzung: lftp installieren  →  sudo pacman -S lftp  /  sudo apt install lftp

set -e

FTP_HOST="w0095185.kasserver.com"
FTP_USER="f0184253"
FTP_PASS="${TONGSHENG_FTP_PASS:?Bitte TONGSHENG_FTP_PASS setzen: export TONGSHENG_FTP_PASS=...}"

echo "▶ 1/3  React-Frontend bauen…"
cd "$(dirname "$0")/frontend"
npm run build

echo "▶ 2/3  Symfony Autoloader optimieren…"
cd "../backend"
APP_ENV=prod composer install --no-dev --optimize-autoloader --no-interaction

echo "▶ 3/3  Upload per FTP…"
cd ".."

lftp -u "$FTP_USER","$FTP_PASS" "$FTP_HOST" <<EOF
set ssl:verify-certificate false
set ftp:passive-mode true

# Symfony Prod-Cache leeren (damit neue Routes/Container aktiv werden)
rm -r /backend/var/cache/prod
mkdir -p /backend/var/cache/prod

# React-Build hochladen (Web-Root, backend/ vom Delete ausschließen)
mirror --reverse --delete --verbose \
  --exclude-glob backend/ \
  frontend/dist/ \
  /

# Symfony-Backend hochladen (ohne vendor und var)
mirror --reverse --verbose \
  --exclude-glob vendor/ \
  --exclude-glob var/ \
  --exclude-glob .env.local \
  --exclude-glob .env.local.example \
  --exclude-glob public/.user.ini \
  backend/ \
  /backend/

# vendor/ separat (nur Size-Vergleich wegen Timestamps)
mirror --reverse --verbose \
  backend/vendor/ \
  /backend/vendor/

# var-Verzeichnisse nach den Mirrors anlegen (nicht vorher!)
mkdir -p /backend/var/cache
mkdir -p /backend/var/log
mkdir -p /backend/var/sessions

bye
EOF

echo ""
echo "✓ Deployment abgeschlossen → https://tongsheng.app/"
echo ""
echo "⚠ NICHT VERGESSEN: backend/.env.local per SFTP auf den Server hochladen!"
echo "  → /www/htdocs/w0095185/tongsheng.app/backend/.env.local"
