# Changelog

Alle wichtigen Änderungen am Dispatch SECURE Plugin werden hier dokumentiert.

## [3.1.0] - 2025-11-21

### Plus Code LPAC-Kompatibilität

#### ✨ Hinzugefügt
- **Vollständige LPAC-Integration** in `dispatch-pluscode-addon.php`
  - Automatisches Speichern aller LPAC-spezifischen Meta-Keys
  - `lpac_customer_distance` - Distanz in km
  - `lpac_customer_distance_duration` - Fahrtzeit (z.B. "25 mins")
  - `lpac_customer_distance_unit` - Einheit ("km")
  - `lpac_latitude` / `lpac_longitude` - GPS-Koordinaten
  - `lpac_plus_code` - Plus Code Adresse

- **Dual-Path LPAC-Support**
  - OSRM-Routing-Pfad speichert LPAC Meta-Keys
  - Haversine-Fallback-Pfad speichert LPAC Meta-Keys
  - Garantiert konsistente Datenspeicherung in beiden Modi

#### 🔧 Geändert
- Plus Code KM/ETA-Berechnung jetzt vollständig LPAC-kompatibel
- KM/ETA-Werte werden automatisch auf WooCommerce Orders-Seite angezeigt (wenn LPAC installiert)

#### ❌ Entfernt
- **MU-Plugin `wc-km-eta-columns.php` nicht mehr benötigt**
  - LPAC-Plugin übernimmt die Spalten-Anzeige
  - Löst "headers already sent" Warnings auf
  - Reduziert Plugin-Abhängigkeiten

#### 📚 Dokumentation
- `DISTRIBUTION-ANLEITUNG.md` hinzugefügt
  - Klare Anleitung für Plugin-Distribution
  - Voraussetzungen beim Kunden dokumentiert
  - Workflow-Erklärung

### 🐛 Bugfixes
- Behoben: Plus Code wurde gespeichert, aber KM/ETA nicht auf Orders-Seite angezeigt
- Behoben: PHP Warning "Cannot modify header information" durch MU-Plugin

### 🎯 Voraussetzungen beim Kunden
- **WooCommerce** (Standard)
- **LPAC - Kikote Location Picker at Checkout** (empfohlen für Orders-Seite Anzeige)
  - Falls nicht installiert: KM/ETA werden in Order-Meta gespeichert, aber nicht angezeigt

---

## [2.9.74] - 2025-11-20

### Allgemeine Verbesserungen
- Plus Code Unterstützung
- Routing-Karte mit allen Bestellungen
- Customer Tracking mit Echtzeit-Position

---

## [2.9.71] - 2025-11-15

### SMS & Benachrichtigungen
- SMS-Benachrichtigungen beim Markieren als "geladen"
- Keine Benachrichtigung bei reiner Fahrer-Zuweisung
- ETA-Berechnung mit konfigurierbarer Stopzeit
- Europäische Telefonnummer-Formatierung (ES/DE/UK/NL/FR)
- 30-Minuten-Proximity-Benachrichtigungen via OSRM

---

## [2.9.63] - 2025-11-10

### Kostenersparnis
- **Google Directions API ersetzt durch OSRM**
- **~100€/Monat Ersparnis**
- Unbegrenzte kostenlose Routing-Anfragen
- 15% Zeit-Buffer für Traffic-Kompensation

---

## [2.9.51] - 2025-11-05

### SMS-Unterdrückung
- Suppression-Flag blockiert nur SMS/WhatsApp
- E-Mails werden weiterhin versendet
- Automatischer Ablauf nach 60 Sekunden

---

## Format

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).
