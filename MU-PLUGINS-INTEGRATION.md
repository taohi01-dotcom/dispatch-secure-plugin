# Dispatch Plugin - mu-plugins Integration Plan

> Stand: 29.01.2026 | Version: 2.10.x

## Übersicht

Diese Dokumentation listet alle mu-plugins auf, die in das Dispatch Secure Plugin integriert werden sollen, um eine zentrale, wartbare Codebasis zu schaffen.

---

## 🚚 Dispatch-relevante mu-plugins (zur Integration)

### Pfand-System
| Datei | Größe | Beschreibung | Ziel |
|-------|-------|--------------|------|
| `pfand-daily-statistics.php` | 21.8 KB | Tagesstatistik für Pfand-Bewegungen | `includes/Pfand/DailyStatistics.php` |
| `manual-pfand-deduction.php` | 18 KB | Manueller Pfand-Abzug durch Fahrer | `includes/Pfand/ManualDeduction.php` |
| `fix-nachlieferung-pfand.php` | 6.8 KB | Pfand-Korrektur bei Nachlieferungen | `includes/Pfand/NachlieferungFix.php` |
| `pfand-abholung-product.php`* | 8.3 KB | Virtuelle Pfand-Abholprodukte | `includes/Pfand/AbholungProduct.php` |

### Nachlieferung-System
| Datei | Größe | Beschreibung | Ziel |
|-------|-------|--------------|------|
| `nachlieferung-system.php` | 25 KB | Haupt-Nachlieferungslogik | `includes/Nachlieferung/System.php` |
| `nachlieferung-driver-ui.php` | 8.5 KB | Fahrer-UI für Nachlieferungen | `includes/Nachlieferung/DriverUI.php` |
| `register-nachlieferung-status.php` | 2.3 KB | WooCommerce Status registrieren | `includes/Nachlieferung/Status.php` |

### PlusCode & Adressen
| Datei | Größe | Beschreibung | Ziel |
|-------|-------|--------------|------|
| `saved-address-pluscode-sync.php` | 19 KB | Plus Code Sync bei Adresswahl | `includes/PlusCode/AddressSync.php` |
| `mu-pluscode-display.php` | 7 KB | Plus Code Anzeige im Admin | `includes/PlusCode/Display.php` |
| `google-places-hausnummer-fix.php` | 17 KB | Hausnummer-Extraktion aus Google Places | `includes/Address/GooglePlacesFix.php` |
| `check-address-meta.php` | 2.7 KB | Adress-Meta-Validierung | `includes/Address/MetaCheck.php` |

### Bestellungen & Fahrer
| Datei | Größe | Beschreibung | Ziel |
|-------|-------|--------------|------|
| `auto-assign-guest-orders.php` | 4.2 KB | Gast-Bestellungen automatisch zuweisen | `includes/Orders/AutoAssign.php` |
| `force-on-hold-status.php` | 2.4 KB | Status auf "on-hold" erzwingen | `includes/Orders/ForceOnHold.php` |
| `mu-delivery-filter.php` | 2 KB | Lieferfilter für Dashboard | `includes/Delivery/Filter.php` |
| `driver-display-name-fix.php` | 1.9 KB | Fahrer-Anzeigename korrigieren | `includes/Driver/DisplayNameFix.php` |
| `customer-number-system.php` | 25.6 KB | Kundennummern-System | `includes/Customer/NumberSystem.php` |

---

## 📁 Geplante Plugin-Struktur

```
dispatch-secure-plugin/
├── dispatch-dashboard.php          (Hauptdatei)
├── autoload.php                    (PSR-4 Autoloader)
├── includes/
│   ├── Ajax/                       (✅ 12 Handler - FERTIG)
│   │   ├── PfandHandler.php
│   │   ├── OrdersHandler.php
│   │   ├── DriverHandler.php
│   │   ├── RoutingHandler.php
│   │   ├── MobileAppHandler.php
│   │   ├── StatisticsHandler.php
│   │   ├── NotificationHandler.php
│   │   ├── DeliveryHandler.php
│   │   ├── GeocodingHandler.php
│   │   ├── SettingsHandler.php
│   │   ├── PaymentHandler.php
│   │   └── PickupStationHandler.php
│   │
│   ├── Pfand/                      (🔄 ZU INTEGRIEREN)
│   │   ├── DailyStatistics.php
│   │   ├── ManualDeduction.php
│   │   ├── NachlieferungFix.php
│   │   └── AbholungProduct.php
│   │
│   ├── Nachlieferung/              (🔄 ZU INTEGRIEREN)
│   │   ├── System.php
│   │   ├── DriverUI.php
│   │   └── Status.php
│   │
│   ├── PlusCode/                   (🔄 ZU INTEGRIEREN)
│   │   ├── AddressSync.php
│   │   └── Display.php
│   │
│   ├── Address/                    (🔄 ZU INTEGRIEREN)
│   │   ├── GooglePlacesFix.php
│   │   └── MetaCheck.php
│   │
│   ├── Orders/                     (🔄 ZU INTEGRIEREN)
│   │   ├── AutoAssign.php
│   │   └── ForceOnHold.php
│   │
│   ├── Delivery/                   (🔄 ZU INTEGRIEREN)
│   │   └── Filter.php
│   │
│   ├── Driver/                     (🔄 ZU INTEGRIEREN)
│   │   └── DisplayNameFix.php
│   │
│   └── Customer/                   (🔄 ZU INTEGRIEREN)
│       └── NumberSystem.php
│
├── assets/
│   ├── css/
│   │   ├── driver-dashboard.css    (🔄 EXTRAHIEREN)
│   │   └── admin-style.css
│   └── js/
│       ├── driver-dashboard.js     (🔄 EXTRAHIEREN)
│       └── admin-script.js
│
└── templates/
    ├── driver/
    ├── admin/
    └── emails/
```

---

## ❌ NICHT zu integrieren (bleiben als mu-plugins)

### WPML/Übersetzung
- `mu-wpml-string-registration.php`
- `multilingual-product-creator.php`
- `sync-product-translations.php`
- `translate-attribute-filter.php`
- `mu-aws-wpml-filter.php`

### SEO
- `seo-h1-fix.php`
- `hide-auto-h1.php`
- `rankmath-breadcrumb-translate.php`
- `fix-breadcrumb-wpml.php`

### Sonstige
- `storeabill-custom-shortcodes.php`
- `mu-local-google-fonts.php`
- `mu-old-slug-redirects.php`
- `mu-kikote-complianz-fix.php`
- `fix-invalid-cart-items.php`
- `wc-search-limit-fix.php`
- `woocommerce-analytics-proxy-speed-module.php`
- `wpml-sso-fix.php`
- `mu-hide-order-meta.php`

---

## 🔧 Migrations-Schritte

### Phase 1: Ajax Handler (✅ FERTIG)
- 88 AJAX-Methoden in 12 Trait-Dateien modularisiert

### Phase 2: mu-plugins Integration (🔄 AKTUELL)
1. Namespace-Struktur erstellen (`Dispatch\Pfand\`, `Dispatch\Nachlieferung\`, etc.)
2. Klassen aus mu-plugins extrahieren
3. Autoloader anpassen
4. mu-plugins durch Loader ersetzen (für Rückwärtskompatibilität)

### Phase 3: JS/CSS Extraktion
1. JavaScript aus `renderDriverDashboard()` extrahieren
2. CSS aus Inline-Styles extrahieren
3. Webpack/Build-Pipeline einrichten (optional)

### Phase 4: Template-Refactoring
1. HTML aus PHP-Methoden in Template-Dateien auslagern
2. Template-Loader implementieren

---

## 📊 Zusammenfassung

| Kategorie | Dateien | Größe | Status |
|-----------|---------|-------|--------|
| Ajax Handler | 12 | ~220 KB | ✅ Fertig |
| Pfand | 4 | ~55 KB | 🔄 Ausstehend |
| Nachlieferung | 3 | ~36 KB | 🔄 Ausstehend |
| PlusCode | 2 | ~26 KB | 🔄 Ausstehend |
| Address | 2 | ~20 KB | 🔄 Ausstehend |
| Orders | 2 | ~7 KB | 🔄 Ausstehend |
| Driver | 1 | ~2 KB | 🔄 Ausstehend |
| Customer | 1 | ~26 KB | 🔄 Ausstehend |
| **Gesamt** | **27** | **~392 KB** | |

---

*Letzte Aktualisierung: 29.01.2026*
