#!/bin/bash

# Auto-Commit Script für Dispatch SECURE Plugin
# Lädt automatisch die neueste Version vom Server, committed und pusht zu GitHub

echo "=== Dispatch SECURE Plugin - Auto Commit ==="
echo ""

# Lade Credentials aus .env Datei
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env"

if [ ! -f "$ENV_FILE" ]; then
    echo "❌ Fehler: .env Datei nicht gefunden!"
    echo "Bitte erstelle eine .env Datei mit FTP und GitHub Credentials"
    exit 1
fi

# Lade Umgebungsvariablen
source "$ENV_FILE"

# Gehe ins Repository-Verzeichnis
cd "$SCRIPT_DIR" || exit 1

# Lade aktuelle Version vom Server
echo "📥 Lade aktuelle Version vom Server..."
curl --ssl-reqd --user "$FTP_USER:$FTP_PASS" "ftp://$FTP_HOST/$FTP_PATH" -o dispatch-dashboard.php 2>/dev/null

if [ $? -ne 0 ]; then
    echo "❌ Fehler beim Download!"
    exit 1
fi

echo "✓ Download erfolgreich ($(ls -lh dispatch-dashboard.php | awk '{print $5}'))"
echo ""

# Prüfe ob es Änderungen gibt
if git diff --quiet dispatch-dashboard.php; then
    echo "ℹ️  Keine Änderungen gefunden"
    exit 0
fi

# Zeige Änderungen
echo "📝 Änderungen gefunden:"
git diff --stat dispatch-dashboard.php
echo ""

# Extrahiere Version aus Plugin-Header
VERSION=$(head -20 dispatch-dashboard.php | grep "Plugin Name:" | sed 's/.*v/v/')
if [ -z "$VERSION" ]; then
    VERSION="v2.9.74"
fi

# Erstelle automatische Commit-Message
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
COMMIT_MSG="Update Dispatch SECURE Plugin $VERSION - $TIMESTAMP

Auto-committed via script
Changes detected in dispatch-dashboard.php"

# Commit
echo "💾 Committing..."
git add dispatch-dashboard.php
git commit -m "$COMMIT_MSG"

if [ $? -ne 0 ]; then
    echo "❌ Commit fehlgeschlagen!"
    exit 1
fi

echo "✓ Commit erfolgreich"
echo ""

# Push zu GitHub
echo "🚀 Push zu GitHub..."
git push origin main 2>&1

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ ERFOLGREICH! Änderungen sind auf GitHub gesichert."
    echo "🔗 https://github.com/taohi01-dotcom/dispatch-secure-plugin"
else
    echo "❌ Push fehlgeschlagen!"
    exit 1
fi
