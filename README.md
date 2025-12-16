# Dispatch SECURE Plugin v3.1.0

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

### Plus Code LPAC-Kompatibilität (v3.1.0)
- **Vollständige LPAC-Integration** für automatische KM/ETA-Anzeige
- Speichert alle LPAC-spezifischen Meta-Keys automatisch
- KM/ETA werden auf WooCommerce Orders-Seite angezeigt
- Dual-Path Support (OSRM + Haversine Fallback)
- **Kein MU-Plugin mehr erforderlich**

### SMS-Unterdrückung (v2.9.51)
- Suppression-Flag blockiert nur SMS/WhatsApp
- E-Mails werden weiterhin versendet
- Automatischer Ablauf nach 60 Sekunden


### GPS Tracking & Plus Code System (v3.1.1)
- **Traccar GPS Integration** für Echtzeit-Fahrer-Tracking
- **Plus Code Support** für präzise Lieferortbestimmung
- **Gespeicherte Adressen** - Admin kann LPAC-Adressen mit einem Klick anwenden
- **Automatische KM/ETA-Berechnung** via OSRM oder Haversine
- **QR-Code Generator** für einfache Fahrer-App-Konfiguration

📖 **[Ausführliche Dokumentation: GPS Tracking & Plus Code](DOCS-GPS-TRACKING-PLUSCODE.md)**

## Installation

1. Upload nach `wp-content/plugins/dispatch-secure-270/`
2. Plugin aktivieren in WordPress Admin
3. Einstellungen konfigurieren unter WooCommerce → Dispatch Settings

## Anforderungen

- WordPress 6.0+
- WooCommerce 8.0+
- PHP 8.3+
- **LPAC - Kikote Location Picker at Checkout** (optional, für KM/ETA-Anzeige auf Orders-Seite)

## Distribution

Siehe [DISTRIBUTION-ANLEITUNG.md](DISTRIBUTION-ANLEITUNG.md) für Details zur Plugin-Weitergabe an Kunden.

## Version

Aktuelle Version: **3.1.1** (2025-12-16) - Gespeicherte Adressen Plus Code Integration

## Changelog

Siehe [CHANGELOG.md](CHANGELOG.md) für detaillierte Änderungshistorie.

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
