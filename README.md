# Dispatch SECURE Plugin v2.9.74

WordPress/WooCommerce Plugin für Lieferfahrer-Management, Routing und Kundenbenachrichtigungen.

## Hauptfunktionen

### SMS & Benachrichtigungen (v2.9.71)
- SMS-Benachrichtigungen beim Markieren als "geladen"
- Keine Benachrichtigung bei reiner Fahrer-Zuweisung
- ETA-Berechnung mit konfigurierbarer Stopzeit
- Europäische Telefonnummer-Formatierung (ES/DE/UK/NL/FR)
- 30-Minuten-Proximity-Benachrichtigungen via OSRM

### Kostenersparnis (v2.9.63)
- **Google Directions API ersetzt durch OSRM** (OpenStreetMap Routing)
- **~100€/Monat Ersparnis**
- Unbegrenzte kostenlose Routing-Anfragen
- 15% Zeit-Buffer für Traffic-Kompensation

### Routing & Koordinaten (v2.9.69-72)
- 3-Tier-Koordinaten-Fallback (LPAC → Shipping → Billing)
- Plus Code Unterstützung
- Routing-Karte mit allen Bestellungen
- Customer Tracking mit Echtzeit-Position

### SMS-Unterdrückung (v2.9.51)
- Suppression-Flag blockiert nur SMS/WhatsApp
- E-Mails werden weiterhin versendet
- Automatischer Ablauf nach 60 Sekunden

## Installation

1. Upload nach `wp-content/plugins/dispatch-secure-270/`
2. Plugin aktivieren in WordPress Admin
3. Einstellungen konfigurieren unter WooCommerce → Dispatch Settings

## Anforderungen

- WordPress 6.0+
- WooCommerce 8.0+
- PHP 8.3+

## Version

Aktuelle Version: **2.9.74** (2025-11-20)

## Changelog

Siehe Plugin-Header in `dispatch-dashboard.php` für detaillierte Änderungshistorie.

## Autor

Klaus Arends (klaus.arends@yahoo.es)

## Auto-Commit Script

Das Repository enthält ein automatisches Backup-Script (`auto-commit.sh`), das:
- Die neueste Version vom FTP-Server herunterlädt
- Änderungen automatisch committed
- Zu GitHub pusht

### Einrichtung

1. Kopiere `.env.example` zu `.env`:
   ```bash
   cp .env.example .env
   ```

2. Bearbeite `.env` und trage deine Credentials ein:
   - FTP-Zugangsdaten
   - GitHub Personal Access Token

3. Führe das Script aus:
   ```bash
   ./auto-commit.sh
   ```

### Automatische Backups

Du kannst das Script über einen Cron-Job automatisch ausführen lassen, z.B. täglich um 2 Uhr:
```bash
0 2 * * * /tmp/dispatch-secure-plugin/auto-commit.sh >> /tmp/dispatch-backup.log 2>&1
```

## Lizenz

GPL v2 or later
