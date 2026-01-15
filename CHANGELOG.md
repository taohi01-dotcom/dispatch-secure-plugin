# Changelog

Alle wichtigen Änderungen am Dispatch SECURE Plugin werden hier dokumentiert.

---

## [2.9.87] - 2026-01-15

### Navigation: Plus Code Priorisierung im Fahrer-Dashboard

#### Problem
- Navigation verwendete immer `customer_address` (Straßenadresse)
- Plus Codes wurden ignoriert, obwohl sie präziser sind
- Bei unvollständigen Adressen führte Navigation zu falschen Standorten
- Google Maps Intent auf Android hatte Probleme mit Plus Codes

#### Ursache
- `openNavigation()` Aufrufe verwendeten nur `order.customer_address`
- `openNavigationDirect()` unterschied nicht zwischen Adresse und Plus Code
- Android Intent `google.navigation:q=` funktioniert nicht zuverlässig mit Plus Codes

#### Lösung

**1. openNavigation Aufrufe (4 Stellen):**

```javascript
// ALT:
openNavigation('${order.customer_address}', depotAddress)

// NEU:
openNavigation('${order.plus_code || order.customer_address}', depotAddress)
```

**Geänderte Zeilen:**
- Zeile 3814: Bestellkarte Navigation-Icon
- Zeile 4311: Aktive Bestellung Button
- Zeile 8191: Order Details Modal
- Zeile 13147: Route-Ansicht Navigation

**2. openNavigationDirect() Funktion (Zeile 12591):**

- Plus Code Erkennung via Regex
- Bei Plus Code: URL-Format statt Android Intent
- Verbesserte Plattform-Erkennung (iOS/Android/Desktop)

#### Geänderte Dateien
- `templates/driver/dashboard.php`

---

## [2.9.86] - 2025-12-12

### 🐛 BUGFIX: OpenLocationCode Fatal Error in class-dispatch-orders-manager.php

#### ❌ Problem
- Fatal Error: `Non-static method OpenLocationCode\OpenLocationCode::decode() cannot be called statically`
- Trat bei `ajax_get_driver_delivery_locations()` auf (Zeile 757)
- Fehler seit 11. Dezember 2025 im debug.log

#### 🔍 Ursache
- Die Library `vectorial1024/open-location-code-php` hat einen **privaten Konstruktor**
- `decode()` ist eine **Instanz-Methode**, keine statische Methode
- Der alte Code versuchte statischen Aufruf: `OpenLocationCode::decode($plus_code)`

#### ✅ Lösung

**Alt (falsch):**
```php
$decoded = \OpenLocationCode\OpenLocationCode::decode($plus_code);
$lat = floatval($decoded->latitudeCenter);
$lng = floatval($decoded->longitudeCenter);
```

**Neu (korrekt):**
```php
$olc = \OpenLocationCode\OpenLocationCode::createFromCode($plus_code);
$decoded = $olc->decode();
$lat = floatval($decoded->getCenterLatitude());
$lng = floatval($decoded->getCenterLongitude());
```

#### 📚 Dokumentation
- Library: [vectorial1024/open-location-code-php](https://github.com/Vectorial1024/open-location-code-php)
- `createFromCode()` erstellt eine Instanz
- `decode()` gibt ein `CodeArea` Objekt zurück
- `getCenterLatitude()` / `getCenterLongitude()` sind Methoden, keine Properties

#### 📁 Geänderte Dateien
- `includes/class-dispatch-orders-manager.php` (Zeile 756-760)

---

## [2.9.85] - 2025-12-12

### 🔧 FIX: Auto KM/ETA Berechnung bei Order-Updates

#### ❌ Problem
- KM/ETA wurden nicht angezeigt, obwohl Plus Code im Kundenprofil vorhanden war
- Order #60924 hatte Plus Code `8FF4HJR5+27` aber keine Distanz-Berechnung
- Die Funktion `maybeCalculateDistanceForOrder()` aus v2.9.78 fehlte auf dem Server

#### 🔍 Ursache
- Der Fix aus GitHub v2.9.78 wurde nicht auf den Server deployed
- Server hatte v2.9.84 ohne die `maybeCalculateDistanceForOrder()` Funktion
- `ensurePlusCodeForOrder()` wurde nur bei `woocommerce_new_order` aufgerufen
- Bei nachträglicher Plus Code Eingabe im Kundenprofil → keine Neuberechnung

#### ✅ Lösung

**Neue Funktion `maybeCalculateDistanceForOrder()`:**
```php
private function maybeCalculateDistanceForOrder($order): void {
    // STEP 1: ZUERST Benutzerprofil prüfen (Plus Code)
    // STEP 2: Falls kein Plus Code → Lieferadresse prüfen
    // STEP 3: Falls keine Lieferadresse → Rechnungsadresse prüfen
    // STEP 4: Bei >100m Abweichung vom Profil-Plus Code → Warnung
    // OSRM Berechnung (Fallback: Haversine)
}
```

**Aufruf in `clearCacheForUpdatedOrder()`:**
```php
// AUTO-FIX: Calculate KM/ETA if missing but coordinates available
$this->maybeCalculateDistanceForOrder($order);
```

**Korrektur Funktionsname:**
- GitHub: `$this->decodePlusCode()` 
- Server: `$this->plusCodeToCoordinates()` ✅

#### 📊 Ergebnis für Order #60924
| Feld | Vorher | Nachher |
|------|--------|---------|
| lpac_customer_distance | LEER | **33.4 km** |
| lpac_customer_distance_duration | LEER | **27 mins** |
| _dispatch_distance_calculated | LEER | **osrm** |
| billing_latitude | LEER | **39.5900625** |
| billing_longitude | LEER | **2.6081875** |

#### 🧪 Getestet
```log
[12-Dec-2025 08:34:12 UTC] plusCodeToCoordinates: SUCCESS - 8FF4HJR5+27 → lat=39.5900625, lng=2.6081875
[12-Dec-2025 08:34:12 UTC] maybeCalculateDistanceForOrder: Order #60924 - STEP 1: Plus Code from user profile: 8FF4HJR5+27
[12-Dec-2025 08:34:12 UTC] ✅ AUTO-FIX (OSRM): Order #60924 - KM/ETA calculated: 33.4 km / 27 mins (source: user_profile)
```

#### 📁 Geänderte Dateien
- `dispatch-dashboard.php`:
  - Neue Funktion `maybeCalculateDistanceForOrder()` (Zeile 40220)
  - Aufruf in `clearCacheForUpdatedOrder()` (Zeile 40479)

---

## [2.9.79] - 2025-12-09

### 🔧 KRITISCHER BUGFIX: SMS/Email Benachrichtigungen wurden nicht gesendet

#### ❌ Problem
- Kunden erhielten **keine SMS und keine Email** wenn der Fahrer die Bestellung als "geladen" markierte
- Im debug.log erschien: `🚫 SMS/WhatsApp SUPPRESSED for order #XXXXX - flag is active`
- Der Suppression-Flag (zum Verhindern von Doppel-Benachrichtigungen) blockierte auch die **beabsichtigte** Benachrichtigung

#### 🔍 Ursache
Code-Flow war fehlerhaft:
1. Zeile 15229: Suppression-Flag wird für 60 Sek gesetzt
2. Zeile 15266: Order wird gespeichert
3. Zeile 15271: `do_action('dispatch_delivery_started_notification')` wird aufgerufen
4. SMS-Funktion prüft Flag → **NOCH AKTIV** → Return ohne SMS zu senden!

#### ✅ Lösung

**Fix 1: Suppression-Flag temporär löschen**
```php
// Vor der Benachrichtigung: Flag löschen
delete_transient('dispatch_suppress_notifications_' . $order_id);

// Benachrichtigung senden
do_action('dispatch_delivery_started_notification', $order_id, $order);

// Nach der Benachrichtigung: Flag wieder setzen (30 Sek)
set_transient('dispatch_suppress_notifications_' . $order_id, true, 30);
```

**Fix 2: Order Notes für Nachverfolgbarkeit**
- ✅ `📱 SMS "Fahrer unterwegs" gesendet an +49...`
- ❌ `❌ SMS-Versand fehlgeschlagen: [Fehler] (Nummer: +49...)`
- ℹ️ `ℹ️ SMS übersprungen: [Grund]`
- 📧 `📧 "Fahrer unterwegs" Email wurde getriggert`

#### 📊 Auswirkung
- ✅ SMS wird jetzt korrekt gesendet wenn Fahrer "geladen" markiert
- ✅ WooCommerce Email wird getriggert
- ✅ Alle Benachrichtigungen sind in Order Notes nachvollziehbar
- ✅ Doppelte Benachrichtigungen werden weiterhin verhindert

#### 📁 Geänderte Dateien
- `dispatch-dashboard.php`:
  - Zeile 15268-15279: Suppression-Flag Handling korrigiert
  - Zeile 16178-16214: Order Notes für SMS-Status hinzugefügt

---

## [2.9.78] - 2025-12-08

### 🔧 AUTO-FIX: Automatische KM/ETA Berechnung bei Order-Updates

#### ❌ Problem
- KM/ETA wurden nicht angezeigt, obwohl Plus Code im Kundenprofil vorhanden war
- Die Berechnung wurde nur bei **Bestellungserstellung** ausgeführt
- Wenn Admin den Plus Code **später** im Kundenprofil hinterlegt hat → keine Neuberechnung
- Bestellungen #60541, #60542 hatten Koordinaten, aber keine KM/ETA

#### 🔍 Ursache
- `ensurePlusCodeForOrder()` wurde nur über `woocommerce_new_order` Hook aufgerufen
- Bei nachträglicher Plus Code Eingabe im Kundenprofil wurde keine Berechnung ausgelöst
- Depot-Koordinaten wurden unter falschem Option-Namen gesucht (`dispatch_warehouse_*` statt `dispatch_depot_*`)

#### ✅ Lösung: Neue Funktion `maybeCalculateDistanceForOrder()`

**Logik bei jeder Bestellung:**

1. **STEP 1: ZUERST Benutzerprofil prüfen** → Plus Code dekodieren
2. **STEP 2: Falls kein Plus Code** → Lieferadresse (shipping) prüfen
3. **STEP 3: Falls keine Lieferadresse** → Rechnungsadresse (billing) prüfen
4. **STEP 4: Abweichungs-Check** → Bei >100m Abweichung vom Profil-Plus Code → Warnung an Admin
5. **Distanz/ETA berechnen** (IMMER wenn Koordinaten vorhanden)

**Technische Umsetzung:**

```php
private function maybeCalculateDistanceForOrder($order): void {
    // STEP 1: ZUERST Benutzerprofil prüfen (Plus Code)
    if ($customer_id > 0) {
        $user_plus_code = get_user_meta($customer_id, 'plus_code', true);
        if (!empty($user_plus_code) && strpos($user_plus_code, '+') !== false) {
            $coords = $this->decodePlusCode($user_plus_code);
            // Koordinaten aus Plus Code verwenden...
        }
    }

    // STEP 2: Falls kein Plus Code → Lieferadresse prüfen
    if (empty($customer_lat)) {
        $shipping_lat = $order->get_meta('_shipping_latitude');
        // ...
    }

    // STEP 3: Falls keine Lieferadresse → Rechnungsadresse prüfen
    if (empty($customer_lat)) {
        $billing_lat = $order->get_meta('billing_latitude');
        // ...
    }

    // STEP 4: Bei >100m Abweichung vom Profil-Plus Code → Warnung
    if ($deviation_m > 100) {
        $order->add_order_note('⚠️ Adressabweichung: ...');
    }

    // OSRM Berechnung (Fallback: Haversine)
}
```

**Aufruf:** In `clearCacheForUpdatedOrder()` eingefügt - wird bei JEDEM Order-Update ausgeführt.

#### 📊 Auswirkung
- ✅ KM/ETA wird automatisch berechnet wenn Plus Code später hinzugefügt wird
- ✅ Bei jedem Order-Update wird geprüft ob Berechnung fehlt
- ✅ Depot-Koordinaten werden aus `dispatch_depot_latitude/longitude` gelesen
- ✅ OSRM für genaue Straßenentfernung (Fallback: Haversine Luftlinie)
- ✅ Alle LPAC Meta-Keys werden gesetzt für Anzeige in WooCommerce

#### 🧪 Getestet mit
- Bestellung #60541: KM/ETA gelöscht → automatisch neu berechnet: 41,6 km / 46 mins
- Bestellung #60542: KM/ETA gelöscht → automatisch neu berechnet: 34 km / 29 mins
- `_dispatch_distance_calculated: osrm` ✅
- `_dispatch_distance_calculated_at: 2025-12-08 14:54:32` ✅

#### 📁 Geänderte Dateien
- `dispatch-dashboard.php`:
  - Neue Funktion `maybeCalculateDistanceForOrder()` (nach Zeile 40214)
  - Aufruf in `clearCacheForUpdatedOrder()` (Zeile 40429)

---

## [2.9.77] - 2025-12-06

### 🐛 KRITISCHER BUGFIX: Fatal Error `decodePlusCode()`

#### ❌ Problem
- **Fatal Error:** `Call to undefined method DispatchDashboard::decodePlusCode()`
- **Betroffene Zeilen:** 40029 und 40125 in `dispatch-dashboard.php`
- **Auswirkung:**
  - Bestellungen ohne Koordinaten/Distanz/ETA
  - Endlosschleifen mit Timeout (180-570 Sekunden)
  - Plus Code aus Kundenprofil konnte nicht decodiert werden

#### 🔍 Ursache
Die Methode `decodePlusCode()` wurde in `ensurePlusCodeForOrder()` aufgerufen, existierte aber nicht.
Eine ähnliche Methode `plusCodeToCoordinates()` war bereits vorhanden (Zeile 39825).

#### ✅ Lösung
Neue Alias-Methode `decodePlusCode()` hinzugefügt (nach Zeile 39862):

```php
/**
 * Decode Plus Code to coordinates (alias for plusCodeToCoordinates)
 *
 * @param string $plus_code Plus Code string
 * @return array|null Array with 'lat' and 'lng' keys, or null on failure
 */
private function decodePlusCode(string $plus_code): ?array {
    return $this->plusCodeToCoordinates($plus_code);
}
```

#### 📊 Auswirkung
- ✅ Plus Code aus Kundenprofil wird korrekt decodiert
- ✅ Koordinaten werden extrahiert
- ✅ OSRM berechnet echte Fahrstrecke und ETA
- ✅ `lpac_customer_distance`, `lpac_customer_distance_duration` werden gesetzt
- ✅ Distanz/ETA wird in WooCommerce Bestellungsübersicht angezeigt

#### 🧪 Getestet mit
- Bestellung #60444 (Stephan Elders)
- Plus Code aus Profil: `8FF5944H+8F`
- Ergebnis: 26,6 km / 30 mins (via OSRM)

#### 📁 Geänderte Dateien
- `dispatch-dashboard.php` (neue Methode nach Zeile 39862)

---

## ⚠️ TODO: Test-Dateien löschen

**Nach dem Testen bitte folgende Dateien vom Server entfernen:**

```
https://entregamos-bebidas.es/sumup-test.php
https://entregamos-bebidas.es/sumup-test-android.php
```

**Löschen per FTP oder mit diesem Befehl:**
```bash
curl -u f017dbe9:PASSWORD "ftp://w019704b.kasserver.com/" -Q "DELE sumup-test.php" -Q "DELE sumup-test-android.php"
```

Diese Test-Seiten wurden am 04.12.2025 erstellt, um die SumUp Android-Integration zu testen.

---

## [2.9.76] - 2025-12-04

### SMS Festnetz-Erkennung

#### 📱 Automatische Festnetz-Erkennung
- **Neue Funktion `isLandlineNumber()`** erkennt Festnetznummern vor SMS-Versand
- **Unterstützte Länder:**
  - 🇪🇸 Spanien: 8xx/9xx Vorwahlen (z.B. 971 Balearen)
  - 🇩🇪 Deutschland: Alle Nicht-Mobil +49 Nummern
  - 🇬🇧 Großbritannien: 01x/02x/03x Vorwahlen
  - 🇫🇷 Frankreich: 1-5 Vorwahlen

#### ✅ Verbesserungen
- **SMS wird automatisch übersprungen** wenn Festnetz erkannt
- **Klare Warnung im Log:** `⚠️ Übersprungen - Spanische Festnetznummer`
- **Keine Twilio-Fehler mehr:** "cannot be a landline" wird verhindert
- **Kosten gespart:** Keine unnötigen API-Aufrufe für Festnetznummern

#### 📁 Geänderte Dateien
- `dispatch-dashboard.php` (Zeilen 13798-13864)

---

## [2.9.75] - 2025-12-04

### SumUp Android Fix & Packliste Pfandtyp

#### 🔧 SumUp Tap to Pay - Android Kompatibilität
- **Plattform-Erkennung** hinzugefügt (iOS vs Android)
- **Android URL-Scheme korrigiert:**
  - `app-id=com.sumup.merchant` Parameter hinzugefügt
  - `total` statt `amount` für Beträge (Android-spezifisch)
  - Einzelner `callback` Parameter statt `callbacksuccess`/`callbackfail`
- **Android Callback-Handler:**
  - Verarbeitet `smp-status=success/failed` Parameter
  - Zeigt `smp-message` bei Fehlern an
- **Ergebnis:** SumUp Tap to Pay funktioniert jetzt auf Android-Geräten

#### ✨ Packliste - Pfandtyp Anzeige
- **Neues Badge** in der Fahrer-Packliste
  - Lila Hintergrund (#8B5CF6) mit ♻️ Symbol
  - Zeigt Pfandtyp wie "Mehrweg" an
- **Backend:**
  - Liest `pa_pfandtyp` Produktattribut aus
  - Unterstützt einfache Produkte und Variationen
  - Fallback auf Parent-Produkt wenn nötig
- **Badges in Packliste:**
  - 📏 Blau - Größe/Liter
  - 🍹 Grün - Geschmack
  - 📦 Orange - Menge
  - ♻️ Lila - Pfandtyp (NEU)

#### 📁 Geänderte Dateien
- `dispatch-dashboard.php` (Zeilen 14837-14883, 29670-29749, 31071-31174)

---

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
