<?php
/**
 * Dispatch Plus Code Addon v3.1 - WITH AUTO KM/ETA CALCULATION
 * Adds Plus Code input field - HPOS Compatible
 * Works for BOTH registered customers AND guest customers
 * CLAUDE FIX: Now triggers KM/ETA calculation after saving coordinates
 *
 * @package Dispatch_Dashboard
 * @since 2.9.145
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plus Code Addon Trait
 */
trait Dispatch_PlusCode_Addon {

    /**
     * Register Plus Code addon hooks
     */
    public function registerPlusCodeAddonHooks(): void {
        // Add Plus Code section after order actions (works in both Legacy and HPOS)
        add_action('woocommerce_order_actions_start', [$this, 'renderPlusCodeSection'], 10, 1);

        // Add Saved Addresses section (LPAC integration)
        add_action('woocommerce_order_actions_start', [$this, 'renderSavedAddressesSection'], 15, 1);

        // AJAX handler for saving Plus Code
        add_action('wp_ajax_dispatch_save_pluscode', [$this, 'ajaxSavePlusCode']);

        // AJAX handler for applying saved address
        add_action('wp_ajax_dispatch_apply_saved_address', [$this, 'ajaxApplySavedAddress']);
    }

    /**
     * Render Plus Code section in order edit screen
     */
    public function renderPlusCodeSection($order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $customer_id = $order->get_customer_id();
        $is_guest = empty($customer_id);

        // Check if customer has Plus Code in profile
        $user_plus_code = '';
        if (!$is_guest) {
            $user_plus_code = get_user_meta($customer_id, 'plus_code', true);
        }

        // Check if order already has Plus Code
        $order_plus_code = $order->get_meta('plus_code', true);

        // Get order address for plus.codes link
        $address_parts = [
            $order->get_billing_address_1(),
            $order->get_billing_address_2(),
            $order->get_billing_postcode(),
            $order->get_billing_city(),
        ];
        $full_address = trim(implode(', ', array_filter($address_parts)));

        // Determine what to show
        $show_input_field = false;
        $status_message = '';
        $current_plus_code = '';
        $box_color = '#e8f4f8';
        $border_color = '#00a8e8';

        if (!empty($user_plus_code)) {
            // Registered customer with Plus Code in profile
            $status_message = '✅ Kunde hat Plus Code im Profil: <strong>' . esc_html($user_plus_code) . '</strong>';
            $current_plus_code = $user_plus_code;
            $box_color = '#d4edda';
            $border_color = '#28a745';
        } elseif (!empty($order_plus_code)) {
            // Order has Plus Code (but customer doesn't have one in profile)
            $status_message = 'ℹ️ Plus Code für diese Bestellung: <strong>' . esc_html($order_plus_code) . '</strong>';
            $current_plus_code = $order_plus_code;
            $show_input_field = true;
            $box_color = '#d1ecf1';
            $border_color = '#17a2b8';
        } else {
            // No Plus Code anywhere
            if ($is_guest) {
                $status_message = '⚠️ <strong>Gast-Bestellung ohne Plus Code</strong>';
            } else {
                $status_message = '⚠️ <strong>Stammkunde ohne Plus Code im Profil</strong>';
            }
            $show_input_field = true;
            $box_color = '#fff3cd';
            $border_color = '#ffc107';
        }

        ?>
        <div class="dispatch-pluscode-section" style="background: <?php echo $box_color; ?>; border: 2px solid <?php echo $border_color; ?>; border-radius: 6px; padding: 15px; margin: 15px 0;">
            <h3 style="color: #005f73; margin: 0 0 10px 0; font-size: 16px;">📍 Plus Code für Lieferung</h3>

            <p style="margin: 0 0 10px 0; color: #333; font-size: 14px;">
                <?php echo $status_message; ?>
            </p>

            <?php if ($show_input_field): ?>
            <div style="margin: 10px 0; padding: 12px; background: white; border: 1px solid #ddd; border-radius: 4px;">
                <?php if ($is_guest): ?>
                    <p style="margin: 0 0 10px 0; font-size: 12px; color: #666;">
                        <strong>Hinweis:</strong> Bei Gast-Bestellungen wird der Plus Code nur für diese Bestellung gespeichert.
                    </p>
                <?php else: ?>
                    <p style="margin: 0 0 10px 0; font-size: 12px; color: #666;">
                        Der Plus Code wird im Kundenprofil gespeichert und bei zukünftigen Bestellungen automatisch verwendet.
                    </p>
                <?php endif; ?>

                <label for="pluscode_input_<?php echo $order_id; ?>" style="display: block; font-weight: bold; margin-bottom: 5px;">
                    Plus Code eingeben:
                </label>
                <input type="text"
                       id="pluscode_input_<?php echo $order_id; ?>"
                       value="<?php echo esc_attr($current_plus_code); ?>"
                       placeholder="z.B. 8FF4JVQP+HH"
                       style="width: 100%; font-family: monospace; padding: 8px; font-size: 14px; border: 1px solid #ddd; border-radius: 3px; margin-bottom: 8px; box-sizing: border-box;">
                <button type="button"
                        class="button button-primary"
                        onclick="dispatchSavePlusCode_<?php echo $order_id; ?>()"
                        style="width: 100%; text-align: center;">
                    💾 Speichern & KM/ETA berechnen
                </button>
            </div>
            <?php endif; ?>

            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(0,0,0,0.1);">
                <button type="button"
                        class="button"
                        onclick="copyAddressAndOpenPlusCode_<?php echo $order_id; ?>()"
                        style="width: 100%; text-align: center;">
                    🗺️ Plus Code finden
                </button>
                <div id="copy_notification_<?php echo $order_id; ?>" style="margin-top: 8px; padding: 6px; background: #d4edda; border: 1px solid #28a745; border-radius: 3px; font-size: 12px; color: #155724; text-align: center; display: none;">
                    ✅ Adresse kopiert! Auf plus.codes einfügen
                </div>
            </div>

            <div id="pluscode_save_result_<?php echo $order_id; ?>" style="margin-top: 10px; display: none;"></div>

            <script>
            function copyAddressAndOpenPlusCode_<?php echo $order_id; ?>() {
                const address = "<?php echo esc_js($full_address); ?>";
                const notification = document.getElementById('copy_notification_<?php echo $order_id; ?>');

                // Copy address to clipboard
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(address).then(function() {
                        // Show notification
                        notification.style.display = 'block';

                        // Open plus.codes/map in new tab
                        window.open('https://plus.codes/map', '_blank');

                        // Hide notification after 5 seconds
                        setTimeout(function() {
                            notification.style.display = 'none';
                        }, 5000);
                    }).catch(function(err) {
                        alert('Fehler beim Kopieren: ' + err);
                    });
                } else {
                    // Fallback for older browsers
                    alert('Adresse: ' + address + '\n\nBitte manuell kopieren.');
                    window.open('https://plus.codes/map', '_blank');
                }
            }
            </script>
        </div>

        <script>
        function dispatchSavePlusCode_<?php echo $order_id; ?>() {
            const plusCode = document.getElementById('pluscode_input_<?php echo $order_id; ?>').value.trim();
            const resultDiv = document.getElementById('pluscode_save_result_<?php echo $order_id; ?>');

            if (!plusCode) {
                resultDiv.innerHTML = '<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 8px; border-radius: 4px; font-size: 13px; color: #856404;">⚠️ Bitte Plus Code eingeben</div>';
                resultDiv.style.display = 'block';
                return;
            }

            // Validate format
            if (!/^[23456789CFGHJMPQRVWX]{8}\+[23456789CFGHJMPQRVWX]{2,}$/i.test(plusCode)) {
                resultDiv.innerHTML = '<div style="background: #f8d7da; border: 1px solid #dc3545; padding: 8px; border-radius: 4px; font-size: 13px; color: #721c24;">❌ Ungültiges Plus Code Format (Beispiel: 8FF4JVQP+HH)</div>';
                resultDiv.style.display = 'block';
                return;
            }

            resultDiv.innerHTML = '<div style="background: #d1ecf1; border: 1px solid #17a2b8; padding: 8px; border-radius: 4px; font-size: 13px; color: #0c5460;">⏳ Speichere...</div>';
            resultDiv.style.display = 'block';

            jQuery.post(ajaxurl, {
                action: 'dispatch_save_pluscode',
                order_id: <?php echo $order_id; ?>,
                customer_id: <?php echo $is_guest ? 0 : $customer_id; ?>,
                plus_code: plusCode.toUpperCase(),
                is_guest: <?php echo $is_guest ? 'true' : 'false'; ?>,
                nonce: '<?php echo wp_create_nonce('dispatch_pluscode_action'); ?>'
            }, function(response) {
                if (response.success) {
                    resultDiv.innerHTML = '<div style="background: #d4edda; border: 1px solid #28a745; padding: 8px; border-radius: 4px; font-size: 13px; color: #155724;">✅ ' + response.data.message + '</div>';
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    resultDiv.innerHTML = '<div style="background: #f8d7da; border: 1px solid #dc3545; padding: 8px; border-radius: 4px; font-size: 13px; color: #721c24;">❌ ' + (response.data.message || 'Fehler beim Speichern') + '</div>';
                }
            }).fail(function() {
                resultDiv.innerHTML = '<div style="background: #f8d7da; border: 1px solid #dc3545; padding: 8px; border-radius: 4px; font-size: 13px; color: #721c24;">❌ Netzwerkfehler</div>';
            });
        }
        </script>
        <?php
    }

    /**
     * AJAX Handler: Save Plus Code
     * CLAUDE FIX v2: Now triggers KM/ETA calculation after saving coordinates
     */
    public function ajaxSavePlusCode(): void {
        check_ajax_referer('dispatch_pluscode_action', 'nonce');

        $order_id = intval($_POST['order_id'] ?? 0);
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $plus_code = sanitize_text_field($_POST['plus_code'] ?? '');
        $is_guest = filter_var($_POST['is_guest'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$order_id || !$plus_code) {
            wp_send_json_error(['message' => 'Ungültige Daten']);
            return;
        }

        // Validate Plus Code format
        if (!preg_match('/^[23456789CFGHJMPQRVWX]{8}\+[23456789CFGHJMPQRVWX]{2,}$/i', $plus_code)) {
            wp_send_json_error(['message' => 'Ungültiges Plus Code Format']);
            return;
        }

        $plus_code = strtoupper($plus_code);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Bestellung nicht gefunden']);
            return;
        }

        // Decode Plus Code to coordinates
        $coordinates = null;
        try {
            require_once(__DIR__ . '/lib/OpenLocationCode/OpenLocationCode.php');
            $olc = \OpenLocationCode\OpenLocationCode::createFromCode($plus_code);
            $decoded = $olc->decode();

            $coordinates = [
                'lat' => $decoded->getCenterLatitude(),
                'lng' => $decoded->getCenterLongitude()
            ];
        } catch (Exception $e) {
            error_log('Plus Code decode error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Plus Code konnte nicht dekodiert werden']);
            return;
        }

        if ($is_guest) {
            // Guest: Save to order meta only
            $order->update_meta_data('plus_code', $plus_code);
            if ($coordinates) {
                $order->update_meta_data('delivery_coordinates', $coordinates);
                $order->update_meta_data('billing_latitude', $coordinates['lat']);
                $order->update_meta_data('billing_longitude', $coordinates['lng']);
                $order->update_meta_data('_shipping_latitude', $coordinates['lat']);
                $order->update_meta_data('_shipping_longitude', $coordinates['lng']);
            }
            $order->save();

            // CLAUDE FIX: Trigger KM/ETA calculation
            $this->calculateAndSaveKmEta($order, $coordinates);

            wp_send_json_success([
                'message' => "Plus Code $plus_code wurde gespeichert und KM/ETA berechnet."
            ]);
        } else {
            // Registered customer: Save to profile AND order
            if (!$customer_id) {
                wp_send_json_error(['message' => 'Kunden-ID fehlt']);
                return;
            }

            // Save to user profile
            update_user_meta($customer_id, 'plus_code', $plus_code);
            if ($coordinates) {
                update_user_meta($customer_id, 'delivery_coordinates', $coordinates);
                update_user_meta($customer_id, 'billing_latitude', $coordinates['lat']);
                update_user_meta($customer_id, 'billing_longitude', $coordinates['lng']);
            }

            // Also save to order
            $order->update_meta_data('plus_code', $plus_code);
            if ($coordinates) {
                $order->update_meta_data('delivery_coordinates', $coordinates);
                $order->update_meta_data('billing_latitude', $coordinates['lat']);
                $order->update_meta_data('billing_longitude', $coordinates['lng']);
                $order->update_meta_data('_shipping_latitude', $coordinates['lat']);
                $order->update_meta_data('_shipping_longitude', $coordinates['lng']);
            }
            $order->save();

            // CLAUDE FIX: Trigger KM/ETA calculation
            $this->calculateAndSaveKmEta($order, $coordinates);

            $user = get_userdata($customer_id);
            $customer_name = $user ? $user->display_name : "Kunde #$customer_id";

            wp_send_json_success([
                'message' => "Plus Code $plus_code wurde gespeichert und KM/ETA berechnet."
            ]);
        }
    }

    /**
     * CLAUDE FIX: Calculate and save KM/ETA using OSRM
     * 
     * @param WC_Order $order
     * @param array $customer_coords ['lat' => float, 'lng' => float]
     */
    private function calculateAndSaveKmEta($order, $customer_coords): void {
        if (!$customer_coords || !isset($customer_coords['lat']) || !isset($customer_coords['lng'])) {
            error_log("CLAUDE: Cannot calculate KM/ETA - no customer coordinates for order #{$order->get_id()}");
            return;
        }

        // Get warehouse/pickup location
        $warehouse_lat = floatval(get_option('dispatch_warehouse_latitude', 39.4887003));
        $warehouse_lng = floatval(get_option('dispatch_warehouse_longitude', 2.8970119));

        error_log("CLAUDE: Calculating KM/ETA for order #{$order->get_id()} from Plus Code");
        error_log("CLAUDE: Warehouse: $warehouse_lat, $warehouse_lng");
        error_log("CLAUDE: Customer: {$customer_coords['lat']}, {$customer_coords['lng']}");

        // Check if we have a calculateRouteOSRM method
        if (!method_exists($this, 'calculateRouteOSRM')) {
            error_log("CLAUDE: calculateRouteOSRM method not found - using fallback distance calculation");
            
            // Fallback: Calculate straight-line distance (Haversine formula)
            $distance_km = $this->calculateDistance(
                $warehouse_lat, 
                $warehouse_lng,
                $customer_coords['lat'],
                $customer_coords['lng']
            );
            
            // Estimate ETA: assume 40 km/h average speed
            $eta_minutes = round(($distance_km / 40) * 60);
            
            error_log("CLAUDE: Fallback calculation - Distance: {$distance_km} km, ETA: {$eta_minutes} min");
            
            // Save to order meta
            $order->update_meta_data('distance_km', round($distance_km, 2));
            $order->update_meta_data('_delivery_distance_km', round($distance_km, 2));
            $order->update_meta_data('eta_minutes', $eta_minutes);
            $order->update_meta_data('_estimated_arrival', $eta_minutes);

            // LPAC compatibility - all required meta keys for display
            $order->update_meta_data('lpac_customer_distance', round($distance_km, 2));
            $order->update_meta_data('lpac_customer_distance_duration', $eta_minutes . ' mins');
            $order->update_meta_data('lpac_customer_distance_unit', 'km');
            $order->update_meta_data('lpac_latitude', $customer_coords['lat']);
            $order->update_meta_data('lpac_longitude', $customer_coords['lng']);

            // Save Plus Code to LPAC meta if available
            $plus_code = $order->get_meta('plus_code');
            if ($plus_code) {
                $order->update_meta_data('lpac_plus_code', $plus_code);
            }

            $order->save();
            
            return;
        }

        // Use OSRM for accurate routing
        $route = $this->calculateRouteOSRM(
            $warehouse_lat,
            $warehouse_lng,
            $customer_coords['lat'],
            $customer_coords['lng']
        );

        if ($route && isset($route['distance_km']) && isset($route['duration_min'])) {
            error_log("CLAUDE: OSRM calculation successful - Distance: {$route['distance_km']} km, ETA: {$route['duration_min']} min");
            
            // Save to order meta
            $order->update_meta_data('distance_km', $route['distance_km']);
            $order->update_meta_data('_delivery_distance_km', $route['distance_km']);
            $order->update_meta_data('eta_minutes', $route['duration_min']);
            $order->update_meta_data('_estimated_arrival', $route['duration_min']);

            // LPAC compatibility - all required meta keys for display
            $order->update_meta_data('lpac_customer_distance', $route['distance_km']);
            $order->update_meta_data('lpac_customer_distance_duration', $route['duration_min'] . ' mins');
            $order->update_meta_data('lpac_customer_distance_unit', 'km');
            $order->update_meta_data('lpac_latitude', $customer_coords['lat']);
            $order->update_meta_data('lpac_longitude', $customer_coords['lng']);

            // Save Plus Code to LPAC meta if available
            $plus_code = $order->get_meta('plus_code');
            if ($plus_code) {
                $order->update_meta_data('lpac_plus_code', $plus_code);
            }

            $order->save();
        } else {
            error_log("CLAUDE: OSRM calculation failed - route is null or incomplete");
        }
    }

    /**
     * Calculate straight-line distance using Haversine formula
     *
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return float Distance in kilometers
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2): float {
        $earth_radius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earth_radius * $c;

        return $distance;
    }

    /**
     * Render Saved Addresses section in order edit screen
     * Shows customer's saved addresses from LPAC plugin
     * CLAUDE FIX: Allows admin to click on a saved address to apply its Plus Code
     */
    public function renderSavedAddressesSection($order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $customer_id = $order->get_customer_id();
        if (empty($customer_id)) {
            // Guest order - no saved addresses
            return;
        }

        // Get saved addresses from LPAC plugin
        $saved_addresses = get_user_meta($customer_id, 'lpac_saved_addresses', true);

        if (empty($saved_addresses) || !is_array($saved_addresses)) {
            return; // No saved addresses
        }

        // Get current order Plus Code for comparison
        $current_plus_code = $order->get_meta('plus_code', true);

        ?>
        <div class="dispatch-saved-addresses-section" style="background: #f0f7ff; border: 2px solid #3b82f6; border-radius: 6px; padding: 15px; margin: 15px 0;">
            <h3 style="color: #1e40af; margin: 0 0 10px 0; font-size: 16px;">📍 Gespeicherte Lieferadressen</h3>
            <p style="margin: 0 0 10px 0; font-size: 12px; color: #64748b;">
                Klicken Sie auf eine Adresse, um deren Plus Code zu verwenden und KM/ETA neu zu berechnen.
            </p>

            <div class="saved-addresses-list" style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach ($saved_addresses as $address_key => $address):
                    $address_name = $address['address_name'] ?? $address_key;
                    $lat = floatval($address['latitude'] ?? 0);
                    $lng = floatval($address['longitude'] ?? 0);

                    // Skip if no coordinates
                    if ($lat == 0 && $lng == 0) continue;

                    // Generate Plus Code from coordinates
                    $address_plus_code = '';
                    try {
                        require_once(__DIR__ . '/lib/OpenLocationCode/OpenLocationCode.php');
                        $address_plus_code = \OpenLocationCode\OpenLocationCode::encode($lat, $lng, 10);
                    } catch (Exception $e) {
                        error_log("Failed to encode Plus Code for address $address_key: " . $e->getMessage());
                        continue;
                    }

                    // Check if this address matches current order Plus Code
                    $is_active = ($current_plus_code === $address_plus_code);

                    // Build address display
                    $display_parts = [];
                    if (!empty($address['billing_address_1'] ?? $address['shipping_address_1'] ?? '')) {
                        $display_parts[] = $address['billing_address_1'] ?? $address['shipping_address_1'];
                    }
                    if (!empty($address['billing_city'] ?? $address['shipping_city'] ?? '')) {
                        $display_parts[] = $address['billing_city'] ?? $address['shipping_city'];
                    }
                    $address_display = !empty($display_parts) ? implode(', ', $display_parts) : "Lat: $lat, Lng: $lng";
                    ?>
                    <button type="button"
                            class="button saved-address-btn <?php echo $is_active ? 'button-primary' : ''; ?>"
                            onclick="dispatchApplySavedAddress_<?php echo $order_id; ?>('<?php echo esc_js($address_key); ?>', '<?php echo esc_js($address_plus_code); ?>', <?php echo $lat; ?>, <?php echo $lng; ?>)"
                            style="text-align: left; padding: 10px 15px; <?php echo $is_active ? 'background: #22c55e; border-color: #16a34a;' : ''; ?>">
                        <strong style="display: block; color: <?php echo $is_active ? '#fff' : '#1e40af'; ?>;">
                            <?php echo $is_active ? '✓ ' : ''; ?><?php echo esc_html($address_name); ?>
                        </strong>
                        <span style="font-size: 11px; color: <?php echo $is_active ? '#dcfce7' : '#64748b'; ?>;">
                            <?php echo esc_html($address_display); ?>
                        </span>
                        <code style="display: block; font-size: 10px; margin-top: 3px; background: <?php echo $is_active ? 'rgba(255,255,255,0.2)' : '#e2e8f0'; ?>; padding: 2px 4px; border-radius: 3px;">
                            <?php echo esc_html($address_plus_code); ?>
                        </code>
                    </button>
                <?php endforeach; ?>
            </div>

            <div id="saved_address_result_<?php echo $order_id; ?>" style="margin-top: 10px; display: none;"></div>
        </div>

        <script>
        function dispatchApplySavedAddress_<?php echo $order_id; ?>(addressKey, plusCode, lat, lng) {
            const resultDiv = document.getElementById('saved_address_result_<?php echo $order_id; ?>');

            if (!confirm('Möchten Sie den Plus Code "' + plusCode + '" für diese Bestellung verwenden?\n\nDies wird die KM/ETA-Berechnung aktualisieren.')) {
                return;
            }

            resultDiv.innerHTML = '<div style="background: #d1ecf1; border: 1px solid #17a2b8; padding: 8px; border-radius: 4px; font-size: 13px; color: #0c5460;">⏳ Wende Adresse an...</div>';
            resultDiv.style.display = 'block';

            jQuery.post(ajaxurl, {
                action: 'dispatch_apply_saved_address',
                order_id: <?php echo $order_id; ?>,
                address_key: addressKey,
                plus_code: plusCode,
                latitude: lat,
                longitude: lng,
                nonce: '<?php echo wp_create_nonce('dispatch_saved_address_action'); ?>'
            }, function(response) {
                if (response.success) {
                    resultDiv.innerHTML = '<div style="background: #d4edda; border: 1px solid #28a745; padding: 8px; border-radius: 4px; font-size: 13px; color: #155724;">✅ ' + response.data.message + '</div>';
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    resultDiv.innerHTML = '<div style="background: #f8d7da; border: 1px solid #dc3545; padding: 8px; border-radius: 4px; font-size: 13px; color: #721c24;">❌ ' + (response.data.message || 'Fehler beim Anwenden') + '</div>';
                }
            }).fail(function() {
                resultDiv.innerHTML = '<div style="background: #f8d7da; border: 1px solid #dc3545; padding: 8px; border-radius: 4px; font-size: 13px; color: #721c24;">❌ Netzwerkfehler</div>';
            });
        }
        </script>
        <?php
    }

    /**
     * AJAX Handler: Apply saved address Plus Code to order
     * CLAUDE FIX: Updates order with selected saved address coordinates and recalculates KM/ETA
     */
    public function ajaxApplySavedAddress(): void {
        check_ajax_referer('dispatch_saved_address_action', 'nonce');

        $order_id = intval($_POST['order_id'] ?? 0);
        $address_key = sanitize_text_field($_POST['address_key'] ?? '');
        $plus_code = sanitize_text_field($_POST['plus_code'] ?? '');
        $lat = floatval($_POST['latitude'] ?? 0);
        $lng = floatval($_POST['longitude'] ?? 0);

        if (!$order_id || !$plus_code || ($lat == 0 && $lng == 0)) {
            wp_send_json_error(['message' => 'Ungültige Daten']);
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Bestellung nicht gefunden']);
            return;
        }

        $plus_code = strtoupper($plus_code);

        // Save Plus Code and coordinates to order
        $order->update_meta_data('plus_code', $plus_code);
        $order->update_meta_data('plus_code_source', 'saved_address_' . $address_key);
        $order->update_meta_data('delivery_coordinates', ['lat' => $lat, 'lng' => $lng]);
        $order->update_meta_data('billing_latitude', $lat);
        $order->update_meta_data('billing_longitude', $lng);
        $order->update_meta_data('_shipping_latitude', $lat);
        $order->update_meta_data('_shipping_longitude', $lng);
        $order->update_meta_data('lpac_latitude', $lat);
        $order->update_meta_data('lpac_longitude', $lng);

        $order->save();

        // Trigger KM/ETA calculation
        $this->calculateAndSaveKmEta($order, ['lat' => $lat, 'lng' => $lng]);

        // Get the calculated values for display
        $distance = $order->get_meta('lpac_customer_distance');
        $duration = $order->get_meta('lpac_customer_distance_duration');

        wp_send_json_success([
            'message' => "Plus Code $plus_code wurde angewendet. Entfernung: {$distance} km, ETA: {$duration}"
        ]);
    }
}
