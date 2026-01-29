<?php
/**
 * Saved Address Plus Code Sync
 *
 * Synchronisiert Plus Codes automatisch, wenn der Admin eine gespeicherte Lieferadresse wählt.
 *
 * @package Entregamos
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle Plus Code sync when saved addresses are selected
 */
class Saved_Address_PlusCode_Sync {

    /**
     * Initialize hooks
     */
    public function __construct() {
        // Hook on order meta updates (HPOS compatible)
        add_action('woocommerce_order_object_updated_props', [$this, 'onOrderPropsUpdated'], 10, 2);

        // Hook on legacy meta updates
        add_action('woocommerce_process_shop_order_meta', [$this, 'onOrderMetaSave'], 99, 1);

        // AJAX handler for syncing Plus Code when address changes
        add_action('wp_ajax_sync_pluscode_from_saved_address', [$this, 'ajaxSyncPlusCode']);

        // Add the saved addresses dropdown after shipping address in order edit
        add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'renderSavedAddressesDropdown'], 20);
    }

    /**
     * Render dropdown with all saved addresses for this customer
     */
    public function renderSavedAddressesDropdown($order) {
        if (!$order) return;

        $customer_id = $order->get_customer_id();
        if (!$customer_id) {
            echo '<p style="color: #999; font-style: italic;">Gast-Bestellung - keine gespeicherten Adressen</p>';
            return;
        }

        // Get saved delivery addresses from the custom meta key
        $saved_addresses = get_user_meta($customer_id, '_customer_delivery_addresses', true);

        // Also get the main plus_code from profile
        $main_plus_code = get_user_meta($customer_id, 'plus_code', true);
        $main_address = get_user_meta($customer_id, 'saved_delivery_address', true)
                        ?: get_user_meta($customer_id, 'billing_address_1', true) . ', ' . get_user_meta($customer_id, 'billing_city', true);

        $current_order_pluscode = $order->get_meta('plus_code', true);

        // Build dropdown options
        $options = [];

        // Add main profile address if it has a plus code
        if (!empty($main_plus_code)) {
            $options[] = [
                'label' => '🏠 Hauptadresse (Profil)',
                'address' => $main_address,
                'plus_code' => $main_plus_code,
            ];
        }

        // Add all saved delivery addresses
        if (is_array($saved_addresses) && !empty($saved_addresses)) {
            foreach ($saved_addresses as $addr) {
                if (!empty($addr['plus_code'])) {
                    $options[] = [
                        'label' => $addr['name'] ?: 'Gespeicherte Adresse',
                        'address' => $addr['address'] ?? '',
                        'plus_code' => $addr['plus_code'],
                    ];
                }
            }
        }

        if (empty($options)) {
            echo '<div style="margin-top: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">';
            echo '<p style="margin: 0; color: #856404;">⚠️ Keine gespeicherten Adressen mit Plus Code für diesen Kunden</p>';
            echo '</div>';
            return;
        }

        ?>
        <style>
            .saved-address-pluscode-sync {
                margin-top: 20px;
                margin-left: -42px;
                padding: 15px;
                background: #e3f2fd;
                border: 2px solid #2196F3;
                border-radius: 8px;
                min-width: 320px;
                max-width: 500px;
            }
            .saved-address-pluscode-sync h4 {
                margin: 0 0 15px 0;
                color: #1565C0;
                font-size: 15px;
                font-weight: 600;
            }
            .saved-address-pluscode-sync .current-pluscode {
                margin: 0 0 15px 0;
                font-size: 13px;
                color: #333;
                padding: 8px 10px;
                background: #fff;
                border-radius: 4px;
                border: 1px solid #90caf9;
            }
            .saved-address-pluscode-sync .current-pluscode code {
                background: #e8f5e9;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 13px;
                font-weight: 600;
                color: #2e7d32;
            }
            .saved-address-pluscode-sync select {
                width: 100%;
                padding: 12px 35px 12px 12px;
                margin-bottom: 12px;
                font-size: 13px;
                border: 1px solid #90caf9;
                border-radius: 6px;
                background: #fff url('data:image/svg+xml;charset=UTF-8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23666"><path d="M7 10l5 5 5-5z"/></svg>') no-repeat right 10px center;
                background-size: 18px;
                cursor: pointer;
                line-height: 1.4;
                -webkit-appearance: none;
                -moz-appearance: none;
                appearance: none;
            }
            .saved-address-pluscode-sync select option {
                padding: 10px;
                line-height: 1.6;
            }
            .saved-address-pluscode-sync .apply-btn {
                width: 100%;
                padding: 14px 20px;
                font-size: 14px;
                font-weight: 600;
                line-height: 1.4;
                border-radius: 6px;
                cursor: pointer;
                display: block;
                text-align: center;
            }
            .saved-address-pluscode-sync .result-box {
                margin-top: 12px;
                padding: 12px;
                border-radius: 6px;
                font-size: 13px;
                line-height: 1.5;
            }
        </style>
        <div class="saved-address-pluscode-sync">
            <h4>📍 Gespeicherte Lieferadressen (<?php echo count($options); ?>)</h4>

            <?php if (!empty($current_order_pluscode)): ?>
            <div class="current-pluscode">
                <strong>Aktueller Plus Code:</strong><br>
                <code><?php echo esc_html($current_order_pluscode); ?></code>
            </div>
            <?php endif; ?>

            <select id="saved_address_pluscode_select_<?php echo $order->get_id(); ?>">
                <option value="">-- Lieferadresse wählen --</option>
                <?php foreach ($options as $opt): ?>
                    <option value="<?php echo esc_attr($opt['plus_code']); ?>"
                            data-address="<?php echo esc_attr($opt['address']); ?>"
                            data-label="<?php echo esc_attr($opt['label']); ?>"
                            <?php echo ($opt['plus_code'] === $current_order_pluscode) ? 'selected' : ''; ?>>
                        <?php echo esc_html($opt['label']); ?>
                        (<?php echo esc_html($opt['plus_code']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="button" class="button button-primary apply-btn" id="apply_saved_pluscode_<?php echo $order->get_id(); ?>">
                ✅ Adresse übernehmen
            </button>

            <div id="pluscode_sync_result_<?php echo $order->get_id(); ?>" class="result-box" style="display: none;"></div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var orderId = <?php echo $order->get_id(); ?>;

            $('#apply_saved_pluscode_' + orderId).on('click', function() {
                var $select = $('#saved_address_pluscode_select_' + orderId);
                var selectedPlusCode = $select.val();
                var selectedAddress = $select.find(':selected').data('address') || '';
                var selectedLabel = $select.find(':selected').data('label') || '';
                var resultDiv = $('#pluscode_sync_result_' + orderId);

                if (!selectedPlusCode) {
                    resultDiv.html('<div style="background: #fff3cd; padding: 10px; border-radius: 4px; color: #856404;">⚠️ Bitte eine Adresse aus der Liste wählen</div>').show();
                    return;
                }

                resultDiv.html('<div style="background: #e3f2fd; padding: 10px; border-radius: 4px; color: #1565C0;">⏳ Speichere Adresse und Plus Code...</div>').show();

                $.post(ajaxurl, {
                    action: 'sync_pluscode_from_saved_address',
                    order_id: orderId,
                    plus_code: selectedPlusCode,
                    address: selectedAddress,
                    address_label: selectedLabel,
                    nonce: '<?php echo wp_create_nonce('sync_pluscode_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        resultDiv.html('<div style="background: #c8e6c9; padding: 10px; border-radius: 4px; color: #2e7d32;">✅ ' + response.data.message + '</div>');
                        // Reload after 1.5 seconds
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        resultDiv.html('<div style="background: #ffcdd2; padding: 10px; border-radius: 4px; color: #c62828;">❌ ' + (response.data.message || 'Fehler beim Speichern') + '</div>');
                    }
                }).fail(function() {
                    resultDiv.html('<div style="background: #ffcdd2; padding: 10px; border-radius: 4px; color: #c62828;">❌ Netzwerkfehler</div>');
                });
            });

            // Also update when selection changes to show preview
            $('#saved_address_pluscode_select_' + orderId).on('change', function() {
                var selectedPlusCode = $(this).val();
                var resultDiv = $('#pluscode_sync_result_' + orderId);

                if (selectedPlusCode) {
                    resultDiv.html('<div style="background: #e8f5e9; padding: 10px; border-radius: 4px; color: #388e3c;">📍 Ausgewählt: <strong>' + selectedPlusCode + '</strong> - Klicken Sie auf "Übernehmen" zum Speichern</div>').show();
                } else {
                    resultDiv.hide();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler to sync Plus Code from saved address
     */
    public function ajaxSyncPlusCode() {
        check_ajax_referer('sync_pluscode_nonce', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
            return;
        }

        $order_id = intval($_POST['order_id'] ?? 0);
        $plus_code = sanitize_text_field($_POST['plus_code'] ?? '');
        $address = sanitize_text_field($_POST['address'] ?? '');

        if (!$order_id || !$plus_code) {
            wp_send_json_error(['message' => 'Ungültige Daten']);
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Bestellung nicht gefunden']);
            return;
        }

        // Validate Plus Code format
        if (!preg_match('/^[23456789CFGHJMPQRVWX]{8}\+[23456789CFGHJMPQRVWX]{2,}$/i', $plus_code)) {
            wp_send_json_error(['message' => 'Ungültiges Plus Code Format']);
            return;
        }

        $plus_code = strtoupper($plus_code);

        // Decode Plus Code to coordinates
        $coordinates = $this->decodePlusCode($plus_code);

        // Update all relevant order meta fields
        $order->update_meta_data('plus_code', $plus_code);
        $order->update_meta_data('_billing_plus_code', $plus_code);
        $order->update_meta_data('lpac_plus_code', $plus_code);

        if ($coordinates) {
            $order->update_meta_data('delivery_coordinates', $coordinates);
            $order->update_meta_data('billing_latitude', $coordinates['lat']);
            $order->update_meta_data('billing_longitude', $coordinates['lng']);
            $order->update_meta_data('lpac_latitude', $coordinates['lat']);
            $order->update_meta_data('lpac_longitude', $coordinates['lng']);

            // Calculate distance from warehouse
            $warehouse_lat = floatval(get_option('dispatch_depot_latitude', '39.4887003'));
            $warehouse_lng = floatval(get_option('dispatch_depot_longitude', '2.8970119'));
            $distance = $this->haversineDistance($warehouse_lat, $warehouse_lng, $coordinates['lat'], $coordinates['lng']);

            $order->update_meta_data('lpac_customer_distance', round($distance, 3));
            $order->update_meta_data('lpac_customer_distance_unit', 'km');

            // Estimate duration (assuming average 40 km/h)
            $estimated_minutes = ceil(($distance / 40) * 60);
            $order->update_meta_data('lpac_customer_distance_duration', $estimated_minutes . ' mins');
        }

        // Update shipping address fields from saved address
        $address_label = sanitize_text_field($_POST['address_label'] ?? '');

        if (!empty($address) && $address !== $plus_code) {
            // Check if address looks like a street address (not a Plus Code)
            if (!preg_match('/^[23456789CFGHJMPQRVWX]{8}\+/i', $address)) {
                // Parse address: "Calle Murta 22, 07609 Son Veri Nou"
                $parsed = $this->parseAddressString($address);

                if ($parsed) {
                    // Update shipping fields
                    if (!empty($parsed['street'])) {
                        $order->set_shipping_address_1($parsed['street']);
                    }
                    if (!empty($parsed['postcode'])) {
                        $order->set_shipping_postcode($parsed['postcode']);
                    }
                    if (!empty($parsed['city'])) {
                        $order->set_shipping_city($parsed['city']);
                    }
                    // Use label as address_2 for reference (e.g., "Finca 4")
                    if (!empty($address_label) && $address_label !== $order->get_shipping_first_name()) {
                        $order->set_shipping_address_2($address_label);
                    }
                    // Set country to Spain if not set
                    if (empty($order->get_shipping_country())) {
                        $order->set_shipping_country('ES');
                    }
                    // Set state to Balearen
                    $order->set_shipping_state('PM');
                } else {
                    // Fallback: just set the whole string as address_1
                    $order->set_shipping_address_1($address);
                }
            }
        }

        $order->save();

        // Clear caches
        wp_cache_delete('order-' . $order_id, 'orders');
        clean_post_cache($order_id);

        error_log("Saved Address Plus Code Sync: Updated order #{$order_id} with Plus Code {$plus_code}");

        $message = "Adresse und Plus Code {$plus_code} wurden übernommen";
        if ($coordinates) {
            $message .= " (Entfernung: " . number_format($distance, 1) . " km)";
        }

        wp_send_json_success([
            'message' => $message,
            'plus_code' => $plus_code,
            'coordinates' => $coordinates,
            'distance' => isset($distance) ? round($distance, 1) : null,
            'address_updated' => !empty($address)
        ]);
    }

    /**
     * Decode Plus Code to coordinates
     */
    private function decodePlusCode($plus_code) {
        $plugin_dir = WP_PLUGIN_DIR . '/dispatch-secure-270/lib/OpenLocationCode/OpenLocationCode.php';

        if (!file_exists($plugin_dir)) {
            error_log("OpenLocationCode library not found at: {$plugin_dir}");
            return null;
        }

        try {
            require_once($plugin_dir);
            $olc = \OpenLocationCode\OpenLocationCode::createFromCode($plus_code);
            $decoded = $olc->decode();

            return [
                'lat' => $decoded->getCenterLatitude(),
                'lng' => $decoded->getCenterLongitude()
            ];
        } catch (Exception $e) {
            error_log('Plus Code decode error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Haversine distance calculation
     */
    private function haversineDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Parse address string into components
     * Expected format: "Street Number, Postcode City" or "Street Number, City"
     * Examples:
     *   "Calle Murta 22, 07609 Son Veri Nou"
     *   "Cami de Son Antelm, 07609 Cugulutx"
     */
    private function parseAddressString($address) {
        if (empty($address)) {
            return null;
        }

        $result = [
            'street' => '',
            'postcode' => '',
            'city' => ''
        ];

        // Split by comma
        $parts = array_map('trim', explode(',', $address));

        if (count($parts) >= 2) {
            // First part is street
            $result['street'] = $parts[0];

            // Second part contains postcode and city: "07609 Son Veri Nou"
            $location = trim($parts[1]);

            // Try to extract Spanish postcode (5 digits)
            if (preg_match('/^(\d{5})\s+(.+)$/', $location, $matches)) {
                $result['postcode'] = $matches[1];
                $result['city'] = trim($matches[2]);
            } else {
                // No postcode found, treat whole thing as city
                $result['city'] = $location;
            }
        } else {
            // No comma, try to parse as "Street Postcode City"
            if (preg_match('/^(.+?)\s+(\d{5})\s+(.+)$/', $address, $matches)) {
                $result['street'] = trim($matches[1]);
                $result['postcode'] = $matches[2];
                $result['city'] = trim($matches[3]);
            } else {
                // Can't parse, use whole string as street
                $result['street'] = $address;
            }
        }

        return $result;
    }

    /**
     * Hook when order props are updated (HPOS)
     */
    public function onOrderPropsUpdated($order, $updated_props) {
        // This can be used for future auto-sync functionality
    }

    /**
     * Hook on legacy order meta save
     */
    public function onOrderMetaSave($order_id) {
        // This can be used for future auto-sync functionality
    }
}

// Initialize the class
new Saved_Address_PlusCode_Sync();
