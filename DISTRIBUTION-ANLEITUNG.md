# 📦 Distribution-Anleitung für Plus Code KM/ETA System

## ✅ **WAS MUSS MIT?**

### **1. dispatch-pluscode-addon.php**
- **Pfad**: `/wp-content/plugins/dispatch-secure-270/dispatch-pluscode-addon.php`
- **Version**: v3.1 mit LPAC-Kompatibilität
- **Funktion**:
  - Fügt Plus Code-Eingabefeld zu Bestellungen hinzu
  - Berechnet automatisch KM/ETA nach Plus Code-Eingabe
  - **Speichert ALLE benötigten LPAC Meta-Keys**

### **Gespeicherte Meta-Keys:**
```php
// Standard Meta-Keys
- distance_km
- _delivery_distance_km
- eta_minutes
- _estimated_arrival

// LPAC-Plugin Meta-Keys (für Anzeige auf Orders-Seite)
- lpac_customer_distance        // z.B. "16.73"
- lpac_customer_distance_duration // z.B. "25 mins"
- lpac_customer_distance_unit     // "km"
- lpac_latitude                   // GPS Koordinaten
- lpac_longitude                  // GPS Koordinaten
- lpac_plus_code                  // Plus Code
```

---

## ❌ **WAS MUSS NICHT MIT?**

### **1. MU-Plugin: wc-km-eta-columns.php**
- **Pfad**: `/wp-content/mu-plugins/wc-km-eta-columns.php`
- **Grund**: Das LPAC-Plugin zeigt bereits KM/ETA-Spalten auf der Orders-Seite an
- **Status**: Kann gelöscht werden

---

## 🔧 **VORAUSSETZUNGEN BEIM KUNDEN**

### **Erforderliche Plugins:**
1. **WooCommerce** (Standard)
2. **LPAC - Kikote Location Picker at Checkout**
   - Wird für die Anzeige der Distance/Duration-Spalten benötigt
   - Falls NICHT installiert: KM/ETA werden in Order-Meta gespeichert, aber nicht auf Orders-Seite angezeigt

---

## ✨ **WIE ES FUNKTIONIERT**

### **Workflow:**
1. Admin öffnet eine Bestellung
2. Gibt Plus Code ein: z.B. `8FF4JVQP+HH`
3. Klickt auf "💾 Speichern & KM/ETA berechnen"
4. System konvertiert Plus Code → GPS-Koordinaten
5. System berechnet Route via OSRM (oder Fallback: Haversine)
6. System speichert KM/ETA in **alle** Meta-Keys (Standard + LPAC)
7. LPAC-Plugin zeigt die Werte automatisch auf der Orders-Seite an

### **Anzeige auf Orders-Seite:**
```
Bestellung    |  Status  |  Location  |  Distance
#56377        |  Pending |  [View]    |  16,73 km (25 mins)
```

---

## 🎯 **ZUSAMMENFASSUNG**

**Nur eine Datei nötig:**
```
dispatch-secure-270/
  └── dispatch-pluscode-addon.php  ✅ MUSS MIT
```

**MU-Plugin:**
```
mu-plugins/
  └── wc-km-eta-columns.php  ❌ NICHT NÖTIG (kann gelöscht werden)
```

**Kunde braucht:**
- WooCommerce
- LPAC-Plugin (für Anzeige auf Orders-Seite)
- Plus Codes von Kunden

---

## 📝 **NOTIZEN**

- **Version-Update**: dispatch-pluscode-addon.php ist jetzt v3.1
- **Änderung**: Speichert jetzt ALLE LPAC Meta-Keys automatisch
- **Kompatibilität**: Funktioniert mit und ohne LPAC-Plugin
  - **Mit LPAC**: KM/ETA werden auf Orders-Seite angezeigt
  - **Ohne LPAC**: KM/ETA werden nur in Order-Meta gespeichert

---

✅ **FERTIG!**
Mit diesem Setup funktioniert das Plus Code KM/ETA System vollständig.
