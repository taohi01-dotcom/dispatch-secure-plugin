# Changelog

Alle wichtigen Änderungen am Dispatch SECURE Plugin werden hier dokumentiert.

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
