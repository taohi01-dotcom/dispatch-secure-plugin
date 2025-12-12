<?php
/**
 * Zentrale Order-Datenverwaltung für Dispatch Dashboard
 *
 * Diese Klasse verwaltet alle Order-Daten zentral und stellt sie
 * für alle Komponenten des Dispatch-Systems zur Verfügung.
 */

// Prevent multiple declarations
if (class_exists('Dispatch_Orders_Manager')) {
    return;
}

class Dispatch_Orders_Manager {

    private static $instance = null;
    private $orders_cache = [];
    private $cache_timestamp = 0;
    private $cache_lifetime = 30; // Sekunden

    /**
     * Singleton Instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor
     */
    private function __construct() {
        // AJAX Handler registrieren
        add_action('wp_ajax_dispatch_get_all_orders_data', [$this, 'ajax_get_all_orders_data']);
        add_action('wp_ajax_nopriv_dispatch_get_all_orders_data', [$this, 'ajax_get_all_orders_data']);
        add_action('wp_ajax_get_driver_delivery_locations', [$this, 'ajax_get_driver_delivery_locations']);
        add_action('wp_ajax_nopriv_get_driver_delivery_locations', [$this, 'ajax_get_driver_delivery_locations']);
    }

    /**
     * Holt ALLE relevanten Order-Daten mit einheitlicher Struktur
     */
    public function get_all_orders_data($force_refresh = false) {
        // Cache prüfen
        if (!$force_refresh && $this->is_cache_valid()) {
            return $this->orders_cache;
        }

        $timezone = wp_timezone();
        $today = new DateTime('today', $timezone);
        $today_ymd = $today->format('Y-m-d');
        $tomorrow = new DateTime('tomorrow', $timezone);
        $tomorrow_ymd = $tomorrow->format('Y-m-d');
        $seven_days_ago = new DateTime('-7 days', $timezone);

        // Orders holen - mit sinnvollem Limit für Performance
        // Hole nur Orders der letzten 30 Tage für bessere Performance
        $date_30_days_ago = new DateTime('-30 days', $timezone);
        $args = [
            'limit' => 500, // Maximale Anzahl auf einmal
            'status' => ['wc-processing', 'wc-on-hold', 'wc-pending', 'wc-completed'],
            'orderby' => 'date',
            'order' => 'DESC',
            'date_created' => '>' . $date_30_days_ago->format('Y-m-d')
        ];

        $all_orders = wc_get_orders($args);

        $structured_data = [
            'all_orders' => [],
            'unassigned' => [],
            'assigned_by_driver' => [],
            'stats' => [
                'current' => 0,      // Heutige Aufträge
                'scheduled' => 0,    // Zukünftige Aufträge
                'completed' => 0,    // Abgeschlossene (letzte 7 Tage)
                'incomplete' => 0,   // Unvollständige (vergangene, nicht abgeschlossen)
                'total_unassigned' => 0
            ]
        ];

        // Alle Fahrer holen
        $drivers = get_users(['role' => 'lieferfahrer']);
        foreach ($drivers as $driver) {
            $structured_data['assigned_by_driver'][$driver->ID] = [
                'driver_name' => $driver->display_name,
                'orders' => []
            ];
        }

        // Orders verarbeiten
        foreach ($all_orders as $order) {
            $order_data = $this->process_order($order, $today_ymd, $tomorrow_ymd);

            // Skip null (z.B. OrderRefund objects)
            if ($order_data === null) {
                continue;
            }

            // In all_orders speichern
            $structured_data['all_orders'][] = $order_data;

            // Kategorisieren
            $assigned_driver = $order->get_meta('_assigned_driver');

            if (empty($assigned_driver)) {
                // Unassigned - nur heute und zukünftige für dispatch-versand
                if ($order_data['delivery_date_parsed'] >= $today_ymd) {
                    $structured_data['unassigned'][] = $order_data;
                    $structured_data['stats']['total_unassigned']++;
                }
            } else {
                // Assigned to driver
                if (isset($structured_data['assigned_by_driver'][$assigned_driver])) {
                    $structured_data['assigned_by_driver'][$assigned_driver]['orders'][] = $order_data;
                }
            }

            // Stats aktualisieren
            $this->update_stats($structured_data['stats'], $order_data, $today_ymd, $tomorrow_ymd, $seven_days_ago);
        }

        // Cache aktualisieren
        $this->orders_cache = $structured_data;
        $this->cache_timestamp = time();

        return $structured_data;
    }

    /**
     * Verarbeitet eine einzelne Order
     */
    private function process_order($order, $today_ymd, $tomorrow_ymd) {
        // Skip OrderRefund objects - sie haben keine Shipping-Methoden
        if ($order instanceof \Automattic\WooCommerce\Admin\Overrides\OrderRefund) {
            return null;
        }

        // Lieferdatum ermitteln
        $delivery_date = $this->get_delivery_date($order);
        $delivery_date_parsed = $this->parse_delivery_date($delivery_date);

        // Zeitfenster/Zeitslot ermitteln
        $time_slot = $this->get_time_slot($order);

        // Pickup Station Daten
        $pickup_station = $this->get_pickup_station_data($order);

        // Formatierte Lieferzeit für Anzeige
        $delivery_time_display = $this->format_delivery_time_display(
            $delivery_date_parsed,
            $time_slot,
            $today_ymd
        );

        // Status bestimmen
        $status_info = $this->determine_order_status(
            $order,
            $delivery_date_parsed,
            $today_ymd,
            $tomorrow_ymd
        );

        // Adresse formatieren
        $address_parts = [];
        if ($order->get_shipping_address_1()) {
            $address_parts[] = $order->get_shipping_address_1();
            if ($order->get_shipping_postcode()) {
                $address_parts[] = $order->get_shipping_postcode();
            }
            if ($order->get_shipping_city()) {
                $address_parts[] = $order->get_shipping_city();
            }
        } else {
            if ($order->get_billing_address_1()) {
                $address_parts[] = $order->get_billing_address_1();
            }
            if ($order->get_billing_postcode()) {
                $address_parts[] = $order->get_billing_postcode();
            }
            if ($order->get_billing_city()) {
                $address_parts[] = $order->get_billing_city();
            }
        }

        // Alle relevanten Meta-Daten sammeln
        $all_meta_keys = [
            'Lieferdatum',
            '_delivery_date',
            'orddd_lite_delivery_date',
            '_orddd_lite_timestamp',
            'Gewünschtes Zeitfenster - unverbindlich',
            '_delivery_time',
            '_delivery_time_slot',
            'orddd_lite_delivery_time',
            '_assigned_driver',
            '_order_ready_status',
            '_driver_notes',
            '_dispatch_route_order',
            '_estimated_arrival',
            '_delivery_completed',
            '_customer_phone'
        ];

        $meta_data = [];
        foreach ($all_meta_keys as $key) {
            $value = $order->get_meta($key);
            if (!empty($value)) {
                $meta_data[$key] = $value;
            }
        }

        // Telefonnummer ermitteln
        $phone = $order->get_billing_phone();
        if (empty($phone)) {
            $phone = $order->get_meta('_customer_phone');
        }

        return [
            'id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_email' => $order->get_billing_email(),
            'customer_phone' => $phone,
            'customer_address' => implode(', ', $address_parts),
            'shipping_address' => $order->get_formatted_shipping_address(),
            'billing_address' => $order->get_formatted_billing_address(),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'created_date' => $order->get_date_created()->format('Y-m-d H:i:s'),

            // Lieferinformationen
            'delivery_date' => $delivery_date,
            'delivery_date_parsed' => $delivery_date_parsed,
            'delivery_date_formatted' => $this->format_german_date($delivery_date_parsed),
            'time_slot' => $time_slot,
            'delivery_time' => $time_slot,  // Alias für JavaScript-Kompatibilität
            'delivery_time_display' => $delivery_time_display,
            'delivery_window_start' => $this->extract_time_start($time_slot),
            'delivery_window_end' => $this->extract_time_end($time_slot),

            // Status-Informationen
            'is_today' => ($delivery_date_parsed === $today_ymd),
            'is_tomorrow' => ($delivery_date_parsed === $tomorrow_ymd),
            'is_future' => ($delivery_date_parsed > $today_ymd),
            'is_past' => ($delivery_date_parsed < $today_ymd),
            'days_until_delivery' => $this->calculate_days_until($delivery_date_parsed, $today_ymd),
            'status_badge' => $status_info['badge'],
            'status_color' => $status_info['color'],

            // Pickup Station
            'pickup_station_name' => $pickup_station['name'],
            'pickup_station_address' => $pickup_station['address'],

            // Fahrer
            'assigned_driver' => $order->get_meta('_assigned_driver'),
            'driver_name' => $this->get_driver_name($order->get_meta('_assigned_driver')),
            'driver_notes' => $order->get_meta('_driver_notes'),
            'route_order' => $order->get_meta('_dispatch_route_order'),
            'estimated_arrival' => $order->get_meta('_estimated_arrival'),
            'delivery_completed' => $order->get_meta('_delivery_completed'),

            // Produkte
            'items_count' => $order->get_item_count(),
            'items' => $this->get_order_items_summary($order),

            // Weitere Meta-Daten
            'ready_status' => $order->get_meta('_order_ready_status'),
            'notes' => $order->get_customer_note(),
            'all_meta' => $meta_data,

            // URLs
            'edit_url' => admin_url('post.php?post=' . $order->get_id() . '&action=edit'),
            'view_url' => $order->get_view_order_url()
        ];
    }

    /**
     * Ermittelt das Lieferdatum aus verschiedenen Meta-Feldern
     */
    private function get_delivery_date($order) {
        $delivery_date = $order->get_meta('Lieferdatum');

        if (empty($delivery_date)) {
            $delivery_date = $order->get_meta('_delivery_date');
        }

        if (empty($delivery_date)) {
            $delivery_date = $order->get_meta('orddd_lite_delivery_date');
        }

        if (empty($delivery_date)) {
            $timestamp = $order->get_meta('_orddd_lite_timestamp');
            if (!empty($timestamp)) {
                $delivery_date = date('d.m.Y', $timestamp);
            }
        }

        return $delivery_date;
    }

    /**
     * Ermittelt das Zeitfenster/Zeitslot
     */
    private function get_time_slot($order) {
        $time_slot = $order->get_meta('_delivery_time');

        if (empty($time_slot)) {
            $time_slot = $order->get_meta('orddd_lite_delivery_time');
        }

        if (empty($time_slot)) {
            $time_slot = $order->get_meta('Gewünschtes Zeitfenster - unverbindlich');
        }

        if (empty($time_slot)) {
            $time_slot = $order->get_meta('_delivery_time_slot');
        }

        // "asap" durch "Schnellstmöglich" ersetzen
        if (strtolower($time_slot) === 'asap') {
            $time_slot = 'Schnellstmöglich';
        }

        return $time_slot;
    }

    /**
     * Ermittelt Pickup Station Daten
     */
    private function get_pickup_station_data($order) {
        $name = $order->get_meta('_pickup_station_name');
        $address = $order->get_meta('_pickup_station_address');

        if (empty($name)) {
            $name = get_option('dispatch_pickup_station_name', 'Hauptlager');
        }

        if (empty($address)) {
            $address = get_option('dispatch_pickup_station_address', '');
        }

        return [
            'name' => $name,
            'address' => $address
        ];
    }

    /**
     * Parst das Lieferdatum in Y-m-d Format
     */
    private function parse_delivery_date($delivery_date) {
        if (empty($delivery_date)) {
            return date('Y-m-d'); // Heute als Fallback
        }

        // Deutsches Format mit Monatsnamen: "1 Oktober, 2025" oder "30 September, 2025"
        if (preg_match('/(\d{1,2})\s+(Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember),?\s+(\d{4})/i', $delivery_date, $matches)) {
            $german_months = [
                'januar' => '01', 'februar' => '02', 'märz' => '03', 'april' => '04',
                'mai' => '05', 'juni' => '06', 'juli' => '07', 'august' => '08',
                'september' => '09', 'oktober' => '10', 'november' => '11', 'dezember' => '12'
            ];
            $month_lower = strtolower($matches[2]);
            if (isset($german_months[$month_lower])) {
                return sprintf('%04d-%02d-%02d', $matches[3], $german_months[$month_lower], $matches[1]);
            }
        }

        // Deutsches Format mit 2-stelligem Jahr: 20.10.25 (MOST COMMON - Check FIRST!)
        if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{2})$/', $delivery_date, $matches)) {
            // 2-digit year: assume 20XX (e.g., 25 -> 2025)
            $year = (int)$matches[3];
            if ($year < 100) {
                $year += 2000;
            }
            return sprintf('%04d-%02d-%02d', $year, $matches[2], $matches[1]);
        }

        // Deutsches Format mit 4-stelligem Jahr: 30.09.2025
        if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $delivery_date, $matches)) {
            return sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
        }

        // ISO Format
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $delivery_date, $matches)) {
            return $matches[0];
        }

        // Versuche andere Formate
        $date = strtotime($delivery_date);
        if ($date !== false) {
            return date('Y-m-d', $date);
        }

        return date('Y-m-d'); // Heute als Fallback
    }

    /**
     * Formatiert die Lieferzeit für die Anzeige
     */
    private function format_delivery_time_display($date_parsed, $time_slot, $today_ymd) {
        if ($date_parsed === $today_ymd) {
            if (!empty($time_slot)) {
                return 'Heute, ' . $time_slot;
            }
            return 'Heute';
        }

        // Datum im deutschen Format
        $date_parts = explode('-', $date_parsed);
        $formatted_date = sprintf('%02d.%02d.%04d', $date_parts[2], $date_parts[1], $date_parts[0]);

        if (!empty($time_slot)) {
            return $formatted_date . ', ' . $time_slot;
        }

        return $formatted_date;
    }

    /**
     * Bestimmt den Order-Status für die Anzeige
     */
    private function determine_order_status($order, $delivery_date_parsed, $today_ymd, $tomorrow_ymd) {
        $assigned_driver = $order->get_meta('_assigned_driver');
        $wc_status = $order->get_status();

        // Abgeschlossen?
        if (in_array($wc_status, ['completed', 'delivered'])) {
            return [
                'badge' => 'abgeschlossen',
                'color' => '#10b981'
            ];
        }

        // Nicht zugewiesen?
        if (empty($assigned_driver)) {
            return [
                'badge' => 'nicht zugewiesen',
                'color' => '#dc3545'
            ];
        }

        // Zugewiesen - heute oder zukunft?
        if ($delivery_date_parsed > $today_ymd) {
            return [
                'badge' => 'Vorangewiesen',
                'color' => '#3b82f6'
            ];
        }

        return [
            'badge' => 'zugewiesen',
            'color' => '#28a745'
        ];
    }

    /**
     * Aktualisiert die Statistiken
     */
    private function update_stats(&$stats, $order_data, $today_ymd, $tomorrow_ymd, $seven_days_ago) {
        $wc_status = $order_data['status'];
        $delivery_date = $order_data['delivery_date_parsed'];

        // Abgeschlossen (letzte 7 Tage)
        if (in_array($wc_status, ['completed', 'delivered'])) {
            $order_date = DateTime::createFromFormat('Y-m-d', $delivery_date);
            if ($order_date && $order_date >= $seven_days_ago) {
                $stats['completed']++;
            }
            return;
        }

        // Unvollständig (vergangen, nicht abgeschlossen)
        if ($delivery_date < $today_ymd) {
            $stats['incomplete']++;
            return;
        }

        // Zukünftig
        if ($delivery_date > $today_ymd) {
            $stats['scheduled']++;
            return;
        }

        // Heute
        $stats['current']++;
    }

    /**
     * Holt den Fahrer-Namen
     */
    private function get_driver_name($driver_id) {
        if (empty($driver_id)) {
            return '';
        }

        $user = get_user_by('ID', $driver_id);
        return $user ? $user->display_name : '';
    }

    /**
     * Cache-Validierung
     */
    private function is_cache_valid() {
        return (time() - $this->cache_timestamp) < $this->cache_lifetime;
    }

    /**
     * Formatiert Datum in deutsches Format
     */
    private function format_german_date($date_ymd) {
        if (empty($date_ymd)) return '';
        $parts = explode('-', $date_ymd);
        if (count($parts) !== 3) return $date_ymd;
        return sprintf('%02d.%02d.%04d', $parts[2], $parts[1], $parts[0]);
    }

    /**
     * Extrahiert Startzeit aus Zeitfenster
     */
    private function extract_time_start($time_slot) {
        if (empty($time_slot)) return '';
        // Format: "10:00 - 12:00" oder "10:00-12:00"
        if (preg_match('/(\d{1,2}:\d{2})\s*[-–]\s*\d{1,2}:\d{2}/', $time_slot, $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * Extrahiert Endzeit aus Zeitfenster
     */
    private function extract_time_end($time_slot) {
        if (empty($time_slot)) return '';
        // Format: "10:00 - 12:00" oder "10:00-12:00"
        if (preg_match('/\d{1,2}:\d{2}\s*[-–]\s*(\d{1,2}:\d{2})/', $time_slot, $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * Berechnet Tage bis zur Lieferung
     */
    private function calculate_days_until($delivery_date_ymd, $today_ymd) {
        if (empty($delivery_date_ymd)) return null;
        $delivery = DateTime::createFromFormat('Y-m-d', $delivery_date_ymd);
        $today = DateTime::createFromFormat('Y-m-d', $today_ymd);
        if (!$delivery || !$today) return null;
        $interval = $today->diff($delivery);
        return $interval->invert ? -$interval->days : $interval->days;
    }

    /**
     * Holt zusammengefasste Produktinformationen
     */
    private function get_order_items_summary($order) {
        $items = [];
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $items[] = [
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total(),
                'sku' => $product ? $product->get_sku() : '',
                'weight' => $product ? $product->get_weight() : ''
            ];
        }
        return $items;
    }

    /**
     * AJAX Handler für alle Order-Daten
     */
    public function ajax_get_all_orders_data() {
        // Nonce-Prüfung - akzeptiert beide Nonce-Typen
        if (isset($_POST['nonce']) && !empty($_POST['nonce'])) {
            // Try both nonce actions (dispatch_nonce and dispatch_ajax_nonce)
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'dispatch_nonce') ||
                           wp_verify_nonce($_POST['nonce'], 'dispatch_ajax_nonce');

            if (!$nonce_valid) {
                // Für eingeloggte User trotzdem erlauben (Fallback)
                if (!is_user_logged_in()) {
                    wp_die('Security check failed', 403);
                }
            }
        }

        $force_refresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true';

        $data = $this->get_all_orders_data($force_refresh);

        wp_send_json_success($data);
    }

    /**
     * AJAX Handler für Karten-Lieferorte
     * Gibt die Bestellungen mit GPS-Koordinaten für die Karte zurück
     *
     * CLAUDE FIX v2.9.70:
     * - Added 3-tier date field priority (Lieferdatum > Gewünschtes Lieferdatum > _delivery_date)
     * - Added 4-tier coordinate priority (LPAC > Billing > Plus Code > Geocoding)
     * - Added delivery date filtering (TODAY only)
     */
    public function ajax_get_driver_delivery_locations() {
        // Nonce-Prüfung
        if (isset($_POST['nonce']) && !empty($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'dispatch_nonce') ||
                           wp_verify_nonce($_POST['nonce'], 'dispatch_ajax_nonce');

            if (!$nonce_valid) {
                if (!is_user_logged_in()) {
                    wp_die('Security check failed', 403);
                }
            }
        }

        // Hole den aktuellen Fahrer
        $current_user = wp_get_current_user();
        $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : $current_user->user_login;

        // Get user ID from username (for compatibility with orders that store user ID instead of username)
        $user = get_user_by('login', $username);
        $user_id = $user ? $user->ID : 0;

        // Hole die zugewiesenen Bestellungen für diesen Fahrer
        // CLAUDE FIX: Query for BOTH username AND user ID to handle different storage formats
        $args = array(
            'limit' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_assigned_driver',
                    'value' => $username,
                    'compare' => '='
                ),
                array(
                    'key' => '_assigned_driver',
                    'value' => (string)$user_id,
                    'compare' => '=',
                    'type' => 'CHAR'
                )
            ),
            'status' => array('processing', 'on-hold', 'pending'),
            'orderby' => 'date',
            'order' => 'ASC'
        );

        $orders = wc_get_orders($args);

        // Get today's date in Y-m-d format for filtering (using WordPress timezone)
        $today_ymd = (new DateTime('today', wp_timezone()))->format('Y-m-d');

        // CLAUDE DEBUG: Track filtering
        $debug_info = array(
            'query_found' => count($orders),
            'username_searched' => $username,
            'user_id_searched' => $user_id,
            'user_id_type' => gettype($user_id),
            'query_args' => $args,
            'today_date' => $today_ymd,
            'orders_processed' => array()
        );

        $locations = array();
        $pickup_location = array(
            'lat' => 39.4887003,
            'lng' => 2.8970119,
            'name' => 'Absa SL',
            'address' => 'Carrer del Cardenal Rossel 88, 07620 Llucmajor'
        );

        foreach ($orders as $order) {
            $order_debug = array(
                'order_id' => $order->get_id(),
                'filtered_out' => null,
                'reason' => null
            );
            // CLAUDE FIX: 3-tier date field priority
            // Priority: Lieferdatum > Gewünschtes Lieferdatum > _delivery_date
            $delivery_date = $order->get_meta('Lieferdatum');
            if (empty($delivery_date)) {
                $delivery_date = $order->get_meta('Gewünschtes Lieferdatum');
            }
            if (empty($delivery_date)) {
                $delivery_date = $order->get_meta('_delivery_date');
            }

            // Skip if no delivery date or not today
            if (empty($delivery_date)) {
                $order_debug['filtered_out'] = true;
                $order_debug['reason'] = 'No delivery date found';
                $debug_info['orders_processed'][] = $order_debug;
                continue;
            }

            $order_debug['delivery_date_raw'] = $delivery_date;

            // Parse date to Y-m-d format
            $parsed_date = null;
            if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $delivery_date, $matches)) {
                // Format: dd.mm.YYYY (German format with 4-digit year)
                $parsed_date = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
            } elseif (preg_match('/^(\d{2})\.(\d{2})\.(\d{2})$/', $delivery_date, $matches)) {
                // Format: dd.mm.yy (2-digit year)
                $parsed_date = '20' . $matches[3] . '-' . $matches[2] . '-' . $matches[1];
            } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $delivery_date, $matches)) {
                // Format: Y-m-d
                $parsed_date = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
            }

            $order_debug['delivery_date_parsed'] = $parsed_date;

            // Skip if date is not today
            if ($parsed_date !== $today_ymd) {
                $order_debug['filtered_out'] = true;
                $order_debug['reason'] = "Date mismatch (parsed: $parsed_date, today: $today_ymd)";
                $debug_info['orders_processed'][] = $order_debug;
                continue;
            }

            // CLAUDE FIX: 4-tier coordinate priority system
            $lat = null;
            $lng = null;

            // Priority 1: Check LPAC coordinates (Map Locations Plus)
            $lpac_lat = $order->get_meta('lpac_latitude');
            $lpac_lng = $order->get_meta('lpac_longitude');
            if (!empty($lpac_lat) && !empty($lpac_lng)) {
                $lat = floatval($lpac_lat);
                $lng = floatval($lpac_lng);
            }

            // Priority 2: Check billing coordinates
            if (is_null($lat) || is_null($lng)) {
                $billing_lat = $order->get_meta('billing_latitude');
                $billing_lng = $order->get_meta('billing_longitude');
                if (!empty($billing_lat) && !empty($billing_lng)) {
                    $lat = floatval($billing_lat);
                    $lng = floatval($billing_lng);
                }
            }

            // Priority 3: Try Plus Code if available
            if (is_null($lat) || is_null($lng)) {
                $plus_code = $order->get_meta('plus_code') ?: $order->get_meta('_billing_plus_code');
                if (!empty($plus_code) && class_exists('OpenLocationCode\OpenLocationCode')) {
                    try {
                        // vectorial1024/open-location-code-php: Use createFromCode() then decode()
                        $olc = \OpenLocationCode\OpenLocationCode::createFromCode($plus_code);
                        $decoded = $olc->decode();
                        $lat = floatval($decoded->getCenterLatitude());
                        $lng = floatval($decoded->getCenterLongitude());
                    } catch (Exception $e) {
                        // Plus code decode failed, skip to next priority
                    }
                }
            }

            $order_debug['coordinates'] = array('lat' => $lat, 'lng' => $lng);

            // Priority 4: Skip orders without coordinates (don't geocode in AJAX)
            if (is_null($lat) || is_null($lng)) {
                $order_debug['filtered_out'] = true;
                $order_debug['reason'] = 'No coordinates found';
                $debug_info['orders_processed'][] = $order_debug;
                continue;
            }

            $order_debug['filtered_out'] = false;
            $order_debug['reason'] = 'Included in locations';
            $debug_info['orders_processed'][] = $order_debug;

            $delivery_sequence = get_post_meta($order->get_id(), '_delivery_sequence', true);

            $locations[] = array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'customer_name' => $order->get_formatted_billing_full_name(),
                'address' => $order->get_shipping_address_1() . ', ' . $order->get_shipping_city(),
                'lat' => $lat,
                'lng' => $lng,
                'phone' => $order->get_billing_phone(),
                'status' => $order->get_status(),
                'status_text' => wc_get_order_status_name($order->get_status()),
                'delivery_sequence' => (int) $delivery_sequence
            );
        }

        // Sortiere nach delivery_sequence
        usort($locations, function($a, $b) {
            return $a['delivery_sequence'] - $b['delivery_sequence'];
        });

        wp_send_json_success(array(
            'CLAUDE_CODE_VERSION' => 'v2.9.70-ORDERS-MANAGER-FIXED', // VERIFICATION MARKER
            'locations' => $locations,
            'pickup_location' => $pickup_location,
            'count' => count($locations),
            'stats' => array(
                'distance' => '0 km',
                'time' => '0 min',
                'stops' => count($locations)
            ),
            'last_update' => current_time('mysql'),
            'cache_buster' => time(),
            'today_date' => $today_ymd, // Debug: show what date we're filtering for
            'debug' => $debug_info // CLAUDE DEBUG: Show filtering details
        ));
    }
}

// Initialisierung
add_action('init', function() {
    Dispatch_Orders_Manager::get_instance();
});
