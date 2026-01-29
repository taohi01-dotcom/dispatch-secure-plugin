<?php
/**
 * Plugin Name: Google Places Hausnummer Fix
 * Description: Extrahiert Hausnummer aus Google Places, positioniert Felder nebeneinander, formatiert Adresse in Bestellungen
 * Version: 1.1.2
 * Author: Entregamos
 *
 * v1.1.2 (2025-12-19):
 * - FIXED: Hausnummer wird nicht mehr doppelt angezeigt wenn bereits in Adresse enthalten
 * - ADDED: Prüfung ob Adresse bereits mit Hausnummer endet
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hausnummer in Adressformat integrieren (für Bestellanzeige)
 * Fügt Hausnummer direkt nach der Straße ein
 */
add_filter('woocommerce_order_formatted_billing_address', 'entregamos_format_billing_address_with_hausnummer', 10, 2);
add_filter('woocommerce_order_formatted_shipping_address', 'entregamos_format_shipping_address_with_hausnummer', 10, 2);

function entregamos_format_billing_address_with_hausnummer($address, $order) {
    // Hole die Hausnummer aus dem Meta
    $hausnummer = $order->get_meta('_billing_', true);
    if (empty($hausnummer)) {
        $hausnummer = $order->get_meta('_billing_house_number', true);
    }
    if (empty($hausnummer)) {
        $hausnummer = $order->get_meta('_billing_hausnummer', true);
    }

    if (!empty($hausnummer) && !empty($address['address_1'])) {
        // v1.1.2: Prüfe ob Hausnummer bereits in Adresse enthalten ist
        $addr = trim($address['address_1']);
        $hn = trim($hausnummer);

        // Prüfe ob Adresse bereits mit der Hausnummer endet (mit/ohne Leerzeichen)
        $already_has_number = preg_match('/[\s,]' . preg_quote($hn, '/') . '\s*$/', $addr)
                           || preg_match('/^' . preg_quote($hn, '/') . '\s/', $addr)
                           || $addr === $hn;

        if (!$already_has_number) {
            $address['address_1'] = $addr . ' ' . $hn;
        }
    }

    return $address;
}

function entregamos_format_shipping_address_with_hausnummer($address, $order) {
    $hausnummer = $order->get_meta('_shipping_', true);
    if (empty($hausnummer)) {
        $hausnummer = $order->get_meta('_shipping_house_number', true);
    }

    if (!empty($hausnummer) && !empty($address['address_1'])) {
        // v1.1.2: Prüfe ob Hausnummer bereits in Adresse enthalten ist
        $addr = trim($address['address_1']);
        $hn = trim($hausnummer);

        $already_has_number = preg_match('/[\s,]' . preg_quote($hn, '/') . '\s*$/', $addr)
                           || preg_match('/^' . preg_quote($hn, '/') . '\s/', $addr)
                           || $addr === $hn;

        if (!$already_has_number) {
            $address['address_1'] = $addr . ' ' . $hn;
        }
    }

    return $address;
}

/**
 * Auch für die My Account Seite (Kundenansicht)
 */
add_filter('woocommerce_my_account_my_address_formatted_address', 'entregamos_format_my_account_address', 10, 3);

function entregamos_format_my_account_address($address, $customer_id, $type) {
    $hausnummer = get_user_meta($customer_id, $type . '_', true);
    if (empty($hausnummer)) {
        $hausnummer = get_user_meta($customer_id, $type . '_house_number', true);
    }

    if (!empty($hausnummer) && !empty($address['address_1'])) {
        // v1.1.2: Prüfe ob Hausnummer bereits in Adresse enthalten ist
        $addr = trim($address['address_1']);
        $hn = trim($hausnummer);

        $already_has_number = preg_match('/[\s,]' . preg_quote($hn, '/') . '\s*$/', $addr)
                           || preg_match('/^' . preg_quote($hn, '/') . '\s/', $addr)
                           || $addr === $hn;

        if (!$already_has_number) {
            $address['address_1'] = $addr . ' ' . $hn;
        }
    }

    return $address;
}

/**
 * Für Admin Bestellansicht - Hausnummer aus separatem Feld in Adresse integrieren
 */
add_filter('woocommerce_admin_billing_fields', 'entregamos_admin_billing_fields_order', 20);
add_filter('woocommerce_admin_shipping_fields', 'entregamos_admin_shipping_fields_order', 20);

/**
 * Admin Billing Fields - Hausnummer-Feld nach address_1 einfügen
 */
function entregamos_admin_billing_fields_order($fields) {
    $new_fields = [];
    foreach ($fields as $key => $field) {
        $new_fields[$key] = $field;
        if ($key === 'address_1') {
            $new_fields['_'] = [
                'label' => 'Hausnr.',
                'show' => false, // Nicht separat anzeigen, wird in address_1 integriert
            ];
        }
    }
    return $new_fields;
}

/**
 * Admin Shipping Fields - Hausnummer-Feld nach address_1 einfügen
 * Fix: Diese Funktion fehlte und verursachte Fatal Error
 */
function entregamos_admin_shipping_fields_order($fields) {
    $new_fields = [];
    foreach ($fields as $key => $field) {
        $new_fields[$key] = $field;
        if ($key === 'address_1') {
            $new_fields['_'] = [
                'label' => 'Hausnr.',
                'show' => false, // Nicht separat anzeigen, wird in address_1 integriert
            ];
        }
    }
    return $new_fields;
}

/**
 * Ändert die Checkout-Feld-Positionen: Straße und Hausnummer nebeneinander
 */
add_filter('woocommerce_checkout_fields', 'entregamos_adjust_address_fields', 9999);

function entregamos_adjust_address_fields($fields) {

    // Billing Straße - links (70% Breite)
    if (isset($fields['billing']['billing_address_1'])) {
        $fields['billing']['billing_address_1']['class'] = ['form-row-first', 'address-field'];
        $fields['billing']['billing_address_1']['priority'] = 60;
    }

    // Billing Hausnummer - rechts (30% Breite)
    // Das Feld heißt "billing_" basierend auf der Analyse
    if (isset($fields['billing']['billing_'])) {
        $fields['billing']['billing_']['class'] = ['form-row-last', 'hausnummer-field'];
        $fields['billing']['billing_']['priority'] = 61;
    }

    // Falls das Feld anders heißt, auch diese Varianten prüfen
    $hausnummer_keys = ['billing_house_number', 'billing_hausnummer', 'billing_street_number'];
    foreach ($hausnummer_keys as $key) {
        if (isset($fields['billing'][$key])) {
            $fields['billing'][$key]['class'] = ['form-row-last', 'hausnummer-field'];
            $fields['billing'][$key]['priority'] = 61;
        }
    }

    // Shipping Straße - links
    if (isset($fields['shipping']['shipping_address_1'])) {
        $fields['shipping']['shipping_address_1']['class'] = ['form-row-first', 'address-field'];
        $fields['shipping']['shipping_address_1']['priority'] = 50;
    }

    // Shipping Hausnummer - rechts (falls vorhanden)
    if (isset($fields['shipping']['shipping_'])) {
        $fields['shipping']['shipping_']['class'] = ['form-row-last', 'hausnummer-field'];
        $fields['shipping']['shipping_']['priority'] = 51;
    }

    return $fields;
}

/**
 * CSS für die Feldbreiten
 */
add_action('wp_head', 'entregamos_hausnummer_css');

function entregamos_hausnummer_css() {
    if (!is_checkout()) return;
    ?>
    <style>
        /* Straße und Hausnummer nebeneinander */
        .woocommerce-checkout #billing_address_1_field.form-row-first,
        .woocommerce-checkout #shipping_address_1_field.form-row-first {
            width: 68% !important;
            clear: both !important;
        }

        .woocommerce-checkout .hausnummer-field.form-row-last,
        .woocommerce-checkout #billing__field.form-row-last {
            width: 30% !important;
            clear: none !important;
        }

        /* Mobile Anpassung */
        @media (max-width: 768px) {
            .woocommerce-checkout #billing_address_1_field.form-row-first,
            .woocommerce-checkout #shipping_address_1_field.form-row-first {
                width: 65% !important;
            }

            .woocommerce-checkout .hausnummer-field.form-row-last,
            .woocommerce-checkout #billing__field.form-row-last {
                width: 33% !important;
            }
        }

        @media (max-width: 480px) {
            .woocommerce-checkout #billing_address_1_field.form-row-first,
            .woocommerce-checkout #shipping_address_1_field.form-row-first,
            .woocommerce-checkout .hausnummer-field.form-row-last,
            .woocommerce-checkout #billing__field.form-row-last {
                width: 100% !important;
                clear: both !important;
            }
        }
    </style>
    <?php
}

/**
 * JavaScript für Google Places Hausnummer-Extraktion
 */
add_action('wp_footer', 'entregamos_google_places_hausnummer_js', 999);

function entregamos_google_places_hausnummer_js() {
    if (!is_checkout()) return;
    ?>
    <script>
    (function() {
        'use strict';

        // Warte bis Google Places geladen ist
        var checkGooglePlaces = setInterval(function() {
            if (typeof google !== 'undefined' && google.maps && google.maps.places) {
                clearInterval(checkGooglePlaces);
                initHausnummerExtraction();
            }
        }, 500);

        // Timeout nach 10 Sekunden
        setTimeout(function() {
            clearInterval(checkGooglePlaces);
        }, 10000);

        function initHausnummerExtraction() {
            console.log('Google Places Hausnummer-Extraktion initialisiert');

            // Finde die Adressfelder
            var billingAddressField = document.getElementById('billing_address_1');
            var shippingAddressField = document.getElementById('shipping_address_1');

            // Hausnummer-Felder (verschiedene mögliche IDs)
            var hausnummerSelectors = [
                '#billing_',
                '#billing_house_number',
                '#billing_hausnummer',
                '#billing_street_number',
                'input[name="billing_"]',
                'input[name="billing_house_number"]'
            ];

            var billingHausnummerField = null;
            for (var i = 0; i < hausnummerSelectors.length; i++) {
                billingHausnummerField = document.querySelector(hausnummerSelectors[i]);
                if (billingHausnummerField) break;
            }

            if (billingAddressField) {
                setupPlaceChangedListener(billingAddressField, billingHausnummerField, 'billing');
            }

            if (shippingAddressField) {
                var shippingHausnummerField = document.querySelector('#shipping_house_number, #shipping_hausnummer, #shipping_');
                setupPlaceChangedListener(shippingAddressField, shippingHausnummerField, 'shipping');
            }
        }

        function setupPlaceChangedListener(addressField, hausnummerField, type) {
            // Überwache Änderungen am Adressfeld
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                        tryExtractHausnummer(addressField, hausnummerField);
                    }
                });
            });

            observer.observe(addressField, { attributes: true });

            // Auch bei Input-Events prüfen
            addressField.addEventListener('change', function() {
                setTimeout(function() {
                    tryExtractHausnummer(addressField, hausnummerField);
                }, 100);
            });

            // Google Places Autocomplete Event abfangen
            if (window.lpac_autocomplete || window.kikote_autocomplete) {
                var autocomplete = window.lpac_autocomplete || window.kikote_autocomplete;
                if (autocomplete && autocomplete[type + '_address_1']) {
                    google.maps.event.addListener(autocomplete[type + '_address_1'], 'place_changed', function() {
                        var place = autocomplete[type + '_address_1'].getPlace();
                        if (place && place.address_components) {
                            extractAndFillHausnummer(place.address_components, hausnummerField, addressField);
                        }
                    });
                }
            }

            // Globalen Event-Listener für place_changed
            document.addEventListener('lpac_place_changed', function(e) {
                if (e.detail && e.detail.place && e.detail.place.address_components) {
                    extractAndFillHausnummer(e.detail.place.address_components, hausnummerField, addressField);
                }
            });

            // Fallback: Überwache das Feld auf Autocomplete-Befüllung
            var lastValue = addressField.value;
            setInterval(function() {
                if (addressField.value !== lastValue) {
                    lastValue = addressField.value;
                    tryExtractHausnummer(addressField, hausnummerField);
                }
            }, 500);
        }

        function extractAndFillHausnummer(addressComponents, hausnummerField, addressField) {
            if (!hausnummerField) {
                console.log('Hausnummer-Feld nicht gefunden');
                return;
            }

            var streetNumber = '';
            var route = '';

            for (var i = 0; i < addressComponents.length; i++) {
                var component = addressComponents[i];
                var types = component.types;

                if (types.indexOf('street_number') !== -1) {
                    streetNumber = component.long_name;
                }
                if (types.indexOf('route') !== -1) {
                    route = component.long_name;
                }
            }

            if (streetNumber) {
                console.log('Hausnummer aus Google Places extrahiert:', streetNumber);
                hausnummerField.value = streetNumber;

                // Event auslösen für WooCommerce
                var event = new Event('change', { bubbles: true });
                hausnummerField.dispatchEvent(event);

                // Straßenfeld nur auf Straßenname setzen (ohne Hausnummer)
                if (route && addressField) {
                    // Prüfe ob die aktuelle Adresse die Hausnummer enthält
                    var currentAddress = addressField.value;
                    if (currentAddress.includes(streetNumber)) {
                        // Entferne die Hausnummer aus dem Straßenfeld
                        var cleanedAddress = currentAddress
                            .replace(new RegExp(',?\\s*' + streetNumber + '\\s*,?', 'g'), '')
                            .replace(new RegExp(streetNumber + '\\s*,?\\s*', 'g'), '')
                            .replace(new RegExp(',?\\s*' + streetNumber, 'g'), '')
                            .trim();

                        // Nur Route setzen wenn sinnvoll
                        if (cleanedAddress.length > 3) {
                            addressField.value = cleanedAddress;
                        } else {
                            addressField.value = route;
                        }
                    }
                }
            }
        }

        function tryExtractHausnummer(addressField, hausnummerField) {
            if (!hausnummerField || !addressField) return;

            var address = addressField.value;
            if (!address) return;

            // Bereits gefülltes Hausnummer-Feld nicht überschreiben
            if (hausnummerField.value && hausnummerField.value.trim() !== '') return;

            // Versuche Hausnummer aus dem Adressstring zu extrahieren
            // Spanische/Deutsche Muster: "Calle Mayor 12" oder "Calle Mayor, 12" oder "12 Calle Mayor"

            var patterns = [
                // Hausnummer am Ende: "Calle Mayor 12" oder "Calle Mayor, 12"
                /,?\s*(\d+[a-zA-Z]?)\s*$/,
                // Hausnummer am Anfang: "12 Calle Mayor"
                /^(\d+[a-zA-Z]?)\s+/,
                // Mit Komma getrennt: "Calle Mayor, 12"
                /,\s*(\d+[a-zA-Z]?)/
            ];

            for (var i = 0; i < patterns.length; i++) {
                var match = address.match(patterns[i]);
                if (match && match[1]) {
                    var number = match[1];
                    // Validierung: Hausnummern sind typischerweise 1-4 Ziffern
                    if (number.length <= 5 && /^\d+[a-zA-Z]?$/.test(number)) {
                        console.log('Hausnummer aus Adresse extrahiert:', number);
                        hausnummerField.value = number;

                        // Event auslösen
                        var event = new Event('change', { bubbles: true });
                        hausnummerField.dispatchEvent(event);

                        // Optional: Hausnummer aus Adressfeld entfernen
                        var cleanedAddress = address.replace(patterns[i], '').trim();
                        if (cleanedAddress.length > 3) {
                            addressField.value = cleanedAddress;
                        }
                        break;
                    }
                }
            }
        }
    })();
    </script>
    <?php
}
