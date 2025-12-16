# GPS Tracking & Plus Code System - Technische Dokumentation

> **Version:** 3.1.1 (2025-12-16)
> **Autor:** Claude Code / Klaus Arends
> **Letzte Aktualisierung:** 2025-12-16

---

## Inhaltsverzeichnis

1. [Systemübersicht](#1-systemübersicht)
2. [Plus Code System](#2-plus-code-system)
3. [GPS Tracking mit Traccar](#3-gps-tracking-mit-traccar)
4. [LPAC Integration](#4-lpac-integration)
5. [KM/ETA Berechnung](#5-kmeta-berechnung)
6. [Gespeicherte Adressen](#6-gespeicherte-adressen)
7. [Server-Konfiguration](#7-server-konfiguration)
8. [Troubleshooting](#8-troubleshooting)
9. [Meta-Keys Referenz](#9-meta-keys-referenz)

---

## 1. Systemübersicht

Das Dispatch System verwendet mehrere Komponenten für die präzise Lieferortbestimmung:

```
┌─────────────────────────────────────────────────────────────────┐
│                        KUNDE                                     │
│  - Gibt Adresse ein beim Checkout                               │
│  - Kann Plus Code im Profil hinterlegen                         │
│  - Kann mehrere Adressen speichern (LPAC)                       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    KOORDINATEN-QUELLEN                          │
│                                                                 │
│  1. LPAC Plugin    → Kunde wählt auf Karte (genaueste)         │
│  2. Plus Code      → 10-stelliger Code (z.B. 8FF4FR5M+75)      │
│  3. User Profile   → Gespeicherter Standard-Plus Code           │
│  4. Geocoding      → Adresse → Koordinaten (Fallback)           │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    BESTELLUNGS-META                             │
│                                                                 │
│  plus_code          → "8FF4FR5M+75"                            │
│  plus_code_source   → "user_profile" / "saved_address_finca1"  │
│  billing_latitude   → 39.4581875                               │
│  billing_longitude  → 2.8329375                                │
│  lpac_latitude      → 39.4581875                               │
│  lpac_longitude     → 2.8329375                                │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    KM/ETA BERECHNUNG                            │
│                                                                 │
│  1. OSRM (OpenStreetMap)  → Echte Straßenrouting               │
│  2. Haversine (Fallback)  → Luftlinie × 1.3                    │
│                                                                 │
│  Ergebnis:                                                      │
│  lpac_customer_distance          → "7.4"                       │
│  lpac_customer_distance_duration → "11 mins"                   │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    GPS TRACKING (TRACCAR)                       │
│                                                                 │
│  - Fahrer-App sendet Position alle 30 Sek                      │
│  - Server: tracking.entregamos-bebidas.es                      │
│  - Device ID: wp_driver_{user_id}                              │
│  - ETA-Aktualisierung in Echtzeit                              │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Plus Code System

### Was ist ein Plus Code?

Plus Codes (Open Location Codes) sind kurze Codes, die jeden Ort auf der Erde präzise identifizieren:

- **Format:** `8FF4FR5M+75` (8 Zeichen + "+" + 2 Zeichen)
- **Genauigkeit:** ~14m × 14m Quadrat
- **Vorteil:** Funktioniert auch ohne Straßenadresse (z.B. auf Fincas)

### Datei: `dispatch-pluscode-addon.php`

Diese Datei enthält das komplette Plus Code Handling:

```php
trait Dispatch_PlusCode_Addon {

    // Hooks registrieren
    public function registerPlusCodeAddonHooks(): void {
        // Plus Code Eingabe-Box im Order-Screen
        add_action('woocommerce_order_actions_start', [$this, 'renderPlusCodeSection'], 10, 1);

        // Gespeicherte Adressen anzeigen (NEU v3.1.1)
        add_action('woocommerce_order_actions_start', [$this, 'renderSavedAddressesSection'], 15, 1);

        // AJAX Handler
        add_action('wp_ajax_dispatch_save_pluscode', [$this, 'ajaxSavePlusCode']);
        add_action('wp_ajax_dispatch_apply_saved_address', [$this, 'ajaxApplySavedAddress']);
    }
}
```

### Plus Code Encoding/Decoding

```php
// Plus Code → Koordinaten
require_once(__DIR__ . '/lib/OpenLocationCode/OpenLocationCode.php');
$olc = \OpenLocationCode\OpenLocationCode::createFromCode('8FF4FR5M+75');
$decoded = $olc->decode();
$lat = $decoded->getCenterLatitude();  // 39.4581875
$lng = $decoded->getCenterLongitude(); // 2.8329375

// Koordinaten → Plus Code
$plus_code = \OpenLocationCode\OpenLocationCode::encode(39.4581875, 2.8329375, 10);
// Ergebnis: "8FF4FR5M+75"
```

### Plus Code Priorität bei neuer Bestellung

1. **LPAC Koordinaten** (wenn Kunde auf Karte geklickt hat)
2. **User Profile Plus Code** (wenn im Kundenprofil gespeichert)
3. **Geocoding** (Adresse → Koordinaten als Fallback)

---

## 3. GPS Tracking mit Traccar

### Server-Infrastruktur

```
┌──────────────────────────────────────────────────────────────┐
│  HETZNER SERVER (91.98.17.58)                                │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  CADDY (Reverse Proxy)                                 │ │
│  │  tracking.entregamos-bebidas.es                        │ │
│  │                                                        │ │
│  │  /         → Traccar Web UI (Port 8082)               │ │
│  │  /gps      → Traccar OsmAnd API (Port 5055)           │ │
│  │  /api/*    → Traccar REST API (Port 8082)             │ │
│  └────────────────────────────────────────────────────────┘ │
│                          │                                   │
│                          ▼                                   │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  TRACCAR SERVER (Docker Container)                     │ │
│  │                                                        │ │
│  │  Port 8082: Web UI + REST API                         │ │
│  │  Port 5055: OsmAnd Protocol (GPS-Daten empfangen)     │ │
│  │  Port 5056: OpenGTS Protocol                          │ │
│  └────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────┘
```

### Caddy Konfiguration

Pfad: `/etc/caddy/Caddyfile`

```caddyfile
tracking.entregamos-bebidas.es {
    # GPS-Daten von Fahrer-Apps (WICHTIG: Muss VOR reverse_proxy stehen!)
    handle_path /gps* {
        reverse_proxy localhost:5055
    }

    # Alles andere (Web UI, API)
    handle {
        reverse_proxy localhost:8082
    }
}
```

### Fahrer-App Konfiguration

Jeder Fahrer braucht die **Traccar Client App** (iOS/Android) mit diesen Einstellungen:

| Einstellung | Wert |
|-------------|------|
| Server URL | `https://tracking.entregamos-bebidas.es/gps` |
| Device ID | `wp_driver_{user_id}` (z.B. `wp_driver_42`) |
| Frequency | 30 Sekunden |
| Accuracy | High |

### QR-Code Generator

Für einfache Konfiguration gibt es einen QR-Code Generator:

**URL:** `https://entregamos-bebidas.es/traccar-qr-generator-admin.php?driver_id=42`

Dieser zeigt:
- QR-Code mit Server-URL und Device-ID
- Manuelle Konfigurationsanleitung
- Nur für eingeloggte Admins sichtbar

---

## 4. LPAC Integration

### Was ist LPAC?

**LPAC = Kikote Location Picker at Checkout** (früher: LPAC - Location Picker at Checkout)

Dieses WooCommerce-Plugin ermöglicht:
- Kartenansicht beim Checkout
- Kunde kann exakten Lieferort markieren
- Speichern mehrerer Adressen pro Kunde
- Automatische KM/ETA-Anzeige

### Gespeicherte Adressen

Kunden können mehrere Lieferadressen speichern (z.B. "Finca 1", "Büro", "Zuhause").

**User Meta Key:** `lpac_saved_addresses`

```php
// Struktur der gespeicherten Adressen
$saved_addresses = [
    'finca1_12345' => [
        'address_id'    => 12345,
        'address_name'  => 'Finca 1',
        'latitude'      => 39.4581875,
        'longitude'     => 2.8329375,
        'billing_address_1' => 'Camino Son Veri 123',
        'billing_city'      => 'Llucmajor',
        // ... weitere Felder
    ],
    'buro_12346' => [
        'address_name'  => 'Büro',
        'latitude'      => 39.5123,
        'longitude'     => 2.7456,
        // ...
    ]
];
```

### Gespeicherte Adresse auf Bestellung anwenden (NEU v3.1.1)

Im Order-Edit-Screen gibt es jetzt eine Box "📍 Gespeicherte Lieferadressen":

```
┌──────────────────────────────────────────────────────────┐
│  📍 Gespeicherte Lieferadressen                          │
│                                                          │
│  Klicken Sie auf eine Adresse, um deren Plus Code zu     │
│  verwenden und KM/ETA neu zu berechnen.                  │
│                                                          │
│  ┌────────────────────────────────────────────────────┐ │
│  │ ✓ Finca 1                                    [✓]  │ │
│  │   Camino Son Veri 123, Llucmajor                  │ │
│  │   8FF4FR5M+75                                     │ │
│  └────────────────────────────────────────────────────┘ │
│                                                          │
│  ┌────────────────────────────────────────────────────┐ │
│  │   Finca 2                                    [ ]  │ │
│  │   Camino Son Veri 456, Llucmajor                  │ │
│  │   8FF4FR5P+QH                                     │ │
│  └────────────────────────────────────────────────────┘ │
│                                                          │
│  ┌────────────────────────────────────────────────────┐ │
│  │   Büro                                       [ ]  │ │
│  │   Calle Principal 1, Palma                        │ │
│  │   8FF4JV2C+MX                                     │ │
│  └────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────┘
```

**Workflow:**
1. Admin öffnet Bestellung
2. Sieht alle gespeicherten Adressen des Kunden
3. Klickt auf die richtige Adresse (z.B. "Finca 1")
4. System aktualisiert automatisch:
   - `plus_code` → Plus Code der gewählten Adresse
   - `plus_code_source` → `saved_address_finca1_12345`
   - Koordinaten (lat/lng)
   - KM und ETA werden neu berechnet
5. Seite lädt neu mit korrekten Werten

---

## 5. KM/ETA Berechnung

### Berechnungsmethoden

**1. OSRM (OpenStreetMap Routing Machine)** - Primär
- Echtes Straßen-Routing
- Kostenlos und unbegrenzt
- Berücksichtigt Einbahnstraßen, Geschwindigkeitsbegrenzungen

**2. Haversine (Luftlinie)** - Fallback
- Wenn OSRM nicht verfügbar
- Luftlinie × 1.3 für geschätzte Straßenentfernung
- Geschwindigkeit: 40 km/h angenommen

### Code-Beispiel

```php
private function calculateAndSaveKmEta($order, $customer_coords): void {
    // Lager-Koordinaten
    $warehouse_lat = 39.4887003;  // Carrer del Cardenal Rossel 88
    $warehouse_lng = 2.8970119;   // 07620 Llucmajor

    // OSRM Routing versuchen
    if (method_exists($this, 'calculateRouteOSRM')) {
        $route = $this->calculateRouteOSRM(
            $warehouse_lat, $warehouse_lng,
            $customer_coords['lat'], $customer_coords['lng']
        );

        if ($route) {
            $distance_km = $route['distance_km'];
            $eta_minutes = $route['duration_min'];
        }
    }

    // Fallback: Haversine
    if (!isset($distance_km)) {
        $distance_km = $this->calculateDistance(
            $warehouse_lat, $warehouse_lng,
            $customer_coords['lat'], $customer_coords['lng']
        );
        $eta_minutes = round(($distance_km / 40) * 60);
    }

    // Meta-Daten speichern
    $order->update_meta_data('lpac_customer_distance', round($distance_km, 2));
    $order->update_meta_data('lpac_customer_distance_duration', $eta_minutes . ' mins');
    $order->save();
}
```

### Lager-Koordinaten

Das Lager/Abholort ist konfigurierbar in den Plugin-Settings:

| Option | Wert |
|--------|------|
| `dispatch_warehouse_latitude` | 39.4887003 |
| `dispatch_warehouse_longitude` | 2.8970119 |
| Adresse | Carrer del Cardenal Rossel 88, 07620 Llucmajor |

---

## 6. Gespeicherte Adressen

### Problem: Falsche Adresse verwendet

**Szenario:**
- Kunde hat mehrere gespeicherte Adressen (Finca 1, Finca 2, Büro)
- Neue Bestellung kommt rein
- System nimmt automatisch den Plus Code aus dem Benutzerprofil
- Aber Kunde will an "Finca 1" liefern, nicht an die Standard-Adresse

**Lösung (v3.1.1):**
1. Admin öffnet die Bestellung
2. Sieht die Box "📍 Gespeicherte Lieferadressen"
3. Klickt auf "Finca 1"
4. Plus Code wird aktualisiert
5. KM/ETA werden neu berechnet

### Technischer Ablauf

```
1. renderSavedAddressesSection($order_id)
   ├── Lade customer_id aus Bestellung
   ├── Lade gespeicherte Adressen: get_user_meta($customer_id, 'lpac_saved_addresses')
   ├── Für jede Adresse:
   │   ├── Generiere Plus Code aus lat/lng
   │   ├── Prüfe ob aktuell verwendet (grün markieren)
   │   └── Zeige Button mit Adressdetails
   └── JavaScript onclick ruft AJAX auf

2. ajaxApplySavedAddress()
   ├── Validiere Nonce
   ├── Lade Bestellung
   ├── Update Meta-Daten:
   │   ├── plus_code
   │   ├── plus_code_source
   │   ├── billing_latitude / billing_longitude
   │   ├── _shipping_latitude / _shipping_longitude
   │   └── lpac_latitude / lpac_longitude
   ├── Rufe calculateAndSaveKmEta() auf
   └── Sende JSON-Antwort mit neuem KM/ETA
```

---

## 7. Server-Konfiguration

### Hetzner Server

| Eigenschaft | Wert |
|-------------|------|
| IP | 91.98.17.58 |
| User | root |
| Passwort | viJkrvFp9gUk |
| OS | Ubuntu 22.04 |

### Traccar Container

```bash
# Container Status prüfen
docker ps | grep traccar

# Container Logs
docker logs traccar-server

# Container neu starten
docker restart traccar-server
```

### Caddy neu laden

```bash
# Konfiguration prüfen
caddy validate --config /etc/caddy/Caddyfile

# Neu laden (ohne Downtime)
systemctl reload caddy

# Status prüfen
systemctl status caddy
```

### Traccar API testen

```bash
# Alle Devices abrufen
curl -u admin:admin "https://tracking.entregamos-bebidas.es/api/devices"

# Letzte Position eines Devices
curl -u admin:admin "https://tracking.entregamos-bebidas.es/api/positions?deviceId=1"
```

---

## 8. Troubleshooting

### Problem: KM/ETA werden nicht angezeigt

**Symptome:**
- Bestellung zeigt keine Entfernung
- ETA ist leer

**Ursachen & Lösungen:**

1. **Keine Koordinaten**
   ```php
   // Prüfen in Order Meta
   $lat = $order->get_meta('billing_latitude');
   $lng = $order->get_meta('billing_longitude');
   // Wenn leer → Plus Code manuell eingeben
   ```

2. **Plus Code fehlt**
   - Im Order-Screen: Plus Code Feld ausfüllen
   - Oder gespeicherte Adresse anklicken

3. **LPAC nicht installiert**
   - Plugin prüfen: Kikote Location Picker at Checkout
   - Meta-Keys werden nicht angezeigt ohne LPAC

### Problem: Falsche Adresse/Plus Code

**Symptome:**
- KM/ETA stimmen nicht
- Karte zeigt falschen Ort

**Lösung:**
1. Order öffnen
2. In der Box "📍 Gespeicherte Lieferadressen" die richtige Adresse anklicken
3. System aktualisiert automatisch Plus Code und berechnet neu

### Problem: GPS-Tracking funktioniert nicht

**Symptome:**
- Keine Position vom Fahrer
- Alte/gecachte Daten

**Prüfen:**

1. **Fahrer-App konfiguriert?**
   - Server URL: `https://tracking.entregamos-bebidas.es/gps`
   - Device ID: `wp_driver_{user_id}`

2. **Traccar Server erreichbar?**
   ```bash
   curl -I https://tracking.entregamos-bebidas.es/gps
   # Sollte 200 OK oder 400 Bad Request zurückgeben
   ```

3. **Caddy Konfiguration korrekt?**
   ```bash
   # Auf Hetzner Server
   cat /etc/caddy/Caddyfile | grep -A5 "tracking"
   ```

4. **Device in Traccar registriert?**
   - https://tracking.entregamos-bebidas.es
   - Login: admin / admin
   - Unter "Devices" prüfen

### Problem: Gecachte GPS-Daten

**Symptome:**
- Position aktualisiert sich nicht
- Alte Daten werden angezeigt

**Ursache:**
- WordPress Object Cache
- Browser Cache
- CDN Cache

**Lösung:**
```php
// Cache-Buster in API-Antwort
wp_send_json_success([
    'position' => $position,
    'cache_buster' => time()
]);
```

---

## 9. Meta-Keys Referenz

### Order Meta (Bestellung)

| Key | Beschreibung | Beispiel |
|-----|--------------|----------|
| `plus_code` | Plus Code der Lieferadresse | `8FF4FR5M+75` |
| `plus_code_source` | Woher der Plus Code stammt | `user_profile`, `saved_address_finca1` |
| `billing_latitude` | Breitengrad | `39.4581875` |
| `billing_longitude` | Längengrad | `2.8329375` |
| `_shipping_latitude` | Versand-Breitengrad | `39.4581875` |
| `_shipping_longitude` | Versand-Längengrad | `2.8329375` |
| `lpac_latitude` | LPAC Breitengrad | `39.4581875` |
| `lpac_longitude` | LPAC Längengrad | `2.8329375` |
| `lpac_customer_distance` | Entfernung in km | `7.4` |
| `lpac_customer_distance_duration` | Fahrzeit | `11 mins` |
| `lpac_customer_distance_unit` | Einheit | `km` |
| `delivery_coordinates` | Koordinaten-Array | `['lat' => 39.45, 'lng' => 2.83]` |
| `distance_km` | Entfernung (Dispatch) | `7.4` |
| `eta_minutes` | ETA in Minuten | `11` |
| `_assigned_driver` | Zugewiesener Fahrer | `42` oder `momo` |

### User Meta (Kunde)

| Key | Beschreibung | Beispiel |
|-----|--------------|----------|
| `plus_code` | Standard Plus Code | `8FF4FR5J+QM` |
| `billing_latitude` | Standard Breitengrad | `39.4523` |
| `billing_longitude` | Standard Längengrad | `2.8312` |
| `lpac_saved_addresses` | Gespeicherte Adressen | (Array, siehe oben) |
| `delivery_coordinates` | Standard Koordinaten | `['lat' => 39.45, 'lng' => 2.83]` |

### User Meta (Fahrer)

| Key | Beschreibung | Beispiel |
|-----|--------------|----------|
| `traccar_device_id` | Traccar Device ID | `wp_driver_42` |
| `last_known_latitude` | Letzte bekannte Position | `39.4887` |
| `last_known_longitude` | Letzte bekannte Position | `2.8970` |
| `last_position_update` | Timestamp | `1702742400` |

---

## Changelog

### v3.1.1 (2025-12-16)
- **NEU:** Gespeicherte Lieferadressen Box im Order-Screen
- **NEU:** Klick auf Adresse aktualisiert Plus Code automatisch
- **NEU:** KM/ETA werden bei Adresswahl neu berechnet
- **FIX:** Plus Code Source wird korrekt gespeichert

### v3.1.0 (2025-11-21)
- LPAC-Kompatibilität für automatische KM/ETA-Anzeige
- Alle LPAC Meta-Keys werden automatisch gespeichert
- Kein MU-Plugin mehr erforderlich

### v2.9.72 (2025-11-20)
- Traccar GPS Integration
- QR-Code Generator für Fahrer
- Caddy Reverse Proxy Konfiguration

---

## Support

Bei Problemen:
1. Diese Dokumentation prüfen
2. Server-Logs prüfen: `docker logs traccar-server`
3. WordPress Debug Log: `wp-content/debug.log`
4. Kontakt: klaus.arends@yahoo.es
