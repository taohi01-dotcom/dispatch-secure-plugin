<?php
/**
 * Plugin Name: Nachlieferung System
 * Description: Erstellt Nachlieferungen (Follow-up deliveries) fuer fehlende Artikel
 * Version: 1.1
 * Author: Klaus Arends
 *
 * v1.1 (2026-01-19):
 * - FIX: Pfand-Fees werden jetzt explizit entfernt (German Market Problem)
 * - FIX: Varianten-Attribute werden korrekt angezeigt
 * - FIX: Item-Level Deposit-Meta wird auf 0 gesetzt
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Nachlieferung System Class
 */
class Dispatch_Nachlieferung_System {

    private static $instance = null;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // AJAX Actions
        add_action('wp_ajax_dispatch_create_nachlieferung', [$this, 'ajaxCreateNachlieferung']);

        // Admin Metabox
        add_action('add_meta_boxes', [$this, 'addNachlieferungMetabox']);
    }

    /**
     * Register Nachlieferung Metabox for WooCommerce Orders
     */
    public function addNachlieferungMetabox(): void {
        $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')
            && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'dispatch_nachlieferung_metabox',
            '📦 Nachlieferung erstellen',
            [$this, 'renderNachlieferungMetabox'],
            $screen,
            'side',
            'default'
        );
    }

    /**
     * Render Nachlieferung Metabox
     */
    public function renderNachlieferungMetabox($post_or_order): void {
        // Get order object (HPOS compatible)
        if ($post_or_order instanceof WC_Order) {
            $order = $post_or_order;
        } else {
            $order = wc_get_order($post_or_order->ID);
        }

        if (!$order) {
            echo '<p>Bestellung nicht gefunden.</p>';
            return;
        }

        $order_id = $order->get_id();
        $items = $order->get_items();

        if (empty($items)) {
            echo '<p>Keine Artikel in dieser Bestellung.</p>';
            return;
        }

        // Check if this is already a Nachlieferung
        $is_nachlieferung = $order->get_meta('_nachlieferung_original_order');
        if ($is_nachlieferung) {
            echo '<p style="color: #666; font-style: italic;">Dies ist bereits eine Nachlieferung zu Bestellung #' . esc_html($is_nachlieferung) . '</p>';
            return;
        }

        // Check for existing Nachlieferungen
        $existing_nachlieferungen = wc_get_orders([
            'meta_key' => '_nachlieferung_original_order',
            'meta_value' => $order_id,
            'return' => 'ids',
            'limit' => -1,
        ]);

        if (!empty($existing_nachlieferungen)) {
            echo '<div style="background: #fff3cd; padding: 8px; border-radius: 4px; margin-bottom: 10px; font-size: 12px;">';
            echo '<strong>Vorhandene Nachlieferungen:</strong><br>';
            foreach ($existing_nachlieferungen as $nl_id) {
                $edit_url = admin_url('admin.php?page=wc-orders&action=edit&id=' . $nl_id);
                echo '<a href="' . esc_url($edit_url) . '">#' . $nl_id . '</a> ';
            }
            echo '</div>';
        }
        ?>
        <style>
            .nachlieferung-metabox-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 6px 0;
                border-bottom: 1px solid #eee;
                font-size: 12px;
            }
            .nachlieferung-metabox-item:last-child {
                border-bottom: none;
            }
            .nachlieferung-item-name {
                flex: 1;
                margin-right: 8px;
                word-break: break-word;
            }
            .nachlieferung-qty-wrapper {
                display: flex;
                align-items: center;
                gap: 4px;
                white-space: nowrap;
            }
            .nachlieferung-qty-input {
                width: 50px;
                text-align: center;
                padding: 4px;
            }
            .nachlieferung-qty-input.partial {
                background: #fff3cd;
                border-color: #ffc107;
            }
            .nachlieferung-comment {
                width: 100%;
                margin: 10px 0;
                padding: 8px;
                font-size: 12px;
            }
            .nachlieferung-btn {
                width: 100%;
                padding: 10px;
                background: #2271b1;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 13px;
                font-weight: 500;
            }
            .nachlieferung-btn:hover {
                background: #135e96;
            }
            .nachlieferung-btn:disabled {
                background: #ccc;
                cursor: not-allowed;
            }
            .nachlieferung-btn.hidden {
                display: none;
            }
            .nachlieferung-info {
                font-size: 11px;
                color: #666;
                margin-bottom: 10px;
                padding: 8px;
                background: #f0f0f1;
                border-radius: 4px;
            }
        </style>

        <div class="nachlieferung-info">
            Reduziere die gelieferte Menge bei Artikeln, die nicht vollstaendig geliefert wurden.
        </div>

        <div id="nachlieferung-items-<?php echo $order_id; ?>">
            <?php foreach ($items as $item_id => $item):
                $product = $item->get_product();
                $qty = $item->get_quantity();
                $name = $item->get_name();
                $product_id = $item->get_product_id();
                $variation_id = $item->get_variation_id();
                $sku = $product ? $product->get_sku() : '';
            ?>
            <div class="nachlieferung-metabox-item"
                 data-item-id="<?php echo $variation_id ?: $product_id; ?>"
                 data-item-name="<?php echo esc_attr($name); ?>"
                 data-item-sku="<?php echo esc_attr($sku); ?>"
                 data-ordered-qty="<?php echo $qty; ?>">
                <span class="nachlieferung-item-name"><?php echo esc_html($name); ?></span>
                <div class="nachlieferung-qty-wrapper">
                    <input type="number"
                           class="nachlieferung-qty-input"
                           min="0"
                           max="<?php echo $qty; ?>"
                           value="<?php echo $qty; ?>"
                           onchange="checkNachlieferungAdmin<?php echo $order_id; ?>()" />
                    <span>/ <?php echo $qty; ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <textarea id="nachlieferung-comment-<?php echo $order_id; ?>"
                  class="nachlieferung-comment"
                  placeholder="Kommentar (optional)..."
                  rows="2"></textarea>

        <button type="button"
                id="nachlieferung-btn-<?php echo $order_id; ?>"
                class="nachlieferung-btn hidden"
                onclick="createNachlieferungAdmin<?php echo $order_id; ?>()">
            Nachlieferung erstellen
        </button>

        <script>
        function checkNachlieferungAdmin<?php echo $order_id; ?>() {
            const container = document.getElementById('nachlieferung-items-<?php echo $order_id; ?>');
            const items = container.querySelectorAll('.nachlieferung-metabox-item');
            let hasPartial = false;

            items.forEach(item => {
                const orderedQty = parseInt(item.dataset.orderedQty);
                const input = item.querySelector('.nachlieferung-qty-input');
                const deliveredQty = parseInt(input.value);

                if (deliveredQty < orderedQty) {
                    hasPartial = true;
                    input.classList.add('partial');
                } else {
                    input.classList.remove('partial');
                }
            });

            const btn = document.getElementById('nachlieferung-btn-<?php echo $order_id; ?>');
            if (hasPartial) {
                btn.classList.remove('hidden');
            } else {
                btn.classList.add('hidden');
            }
        }

        function createNachlieferungAdmin<?php echo $order_id; ?>() {
            const container = document.getElementById('nachlieferung-items-<?php echo $order_id; ?>');
            const items = container.querySelectorAll('.nachlieferung-metabox-item');
            const missingItems = [];

            items.forEach(item => {
                const orderedQty = parseInt(item.dataset.orderedQty);
                const input = item.querySelector('.nachlieferung-qty-input');
                const deliveredQty = parseInt(input.value);
                const missingQty = orderedQty - deliveredQty;

                if (missingQty > 0) {
                    missingItems.push({
                        product_id: item.dataset.itemId,
                        name: item.dataset.itemName,
                        sku: item.dataset.itemSku,
                        ordered_qty: orderedQty,
                        delivered_qty: deliveredQty,
                        missing_qty: missingQty
                    });
                }
            });

            if (missingItems.length === 0) {
                alert('Keine fehlenden Artikel gefunden.');
                return;
            }

            const comment = document.getElementById('nachlieferung-comment-<?php echo $order_id; ?>').value;
            const summary = missingItems.map(i => '- ' + i.missing_qty + 'x ' + i.name).join('\n');

            if (!confirm('Nachlieferung erstellen fuer ' + missingItems.length + ' Artikel?\n\n' + summary + '\n\nKommentar: ' + (comment || '(kein Kommentar)'))) {
                return;
            }

            const btn = document.getElementById('nachlieferung-btn-<?php echo $order_id; ?>');
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Wird erstellt...';
            btn.disabled = true;

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'dispatch_create_nachlieferung',
                    order_id: '<?php echo $order_id; ?>',
                    missing_items: JSON.stringify(missingItems),
                    comment: comment
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Nachlieferung #' + data.data.new_order_id + ' wurde erstellt!\n\nDer Kunde wurde per E-Mail benachrichtigt.');
                    location.reload();
                } else {
                    alert('Fehler: ' + (data.data?.message || 'Unbekannter Fehler'));
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Erstellen der Nachlieferung');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        </script>
        <?php
    }

    /**
     * AJAX: Create Nachlieferung (Follow-up delivery) for missing items
     * Creates a new WooCommerce order with status 'nachlieferung' and 0 EUR total
     * Sends customer notification email
     */
    public function ajaxCreateNachlieferung(): void {
        // Get parameters
        $order_id = intval($_POST['order_id'] ?? 0);
        $missing_items_json = stripslashes($_POST['missing_items'] ?? '[]');
        $comment = sanitize_textarea_field($_POST['comment'] ?? '');

        // Validate order ID
        if (!$order_id) {
            wp_send_json_error(['message' => 'Ungueltige Bestell-ID']);
            return;
        }

        // Get original order
        $original_order = wc_get_order($order_id);
        if (!$original_order) {
            wp_send_json_error(['message' => 'Originalbestellung nicht gefunden']);
            return;
        }

        // Parse missing items
        $missing_items = json_decode($missing_items_json, true);
        if (empty($missing_items) || !is_array($missing_items)) {
            wp_send_json_error(['message' => 'Keine fehlenden Artikel angegeben']);
            return;
        }

        try {
            // Create new order for Nachlieferung
            $new_order = wc_create_order([
                'customer_id' => $original_order->get_customer_id(),
                'status' => 'nachlieferung',
            ]);

            if (is_wp_error($new_order)) {
                wp_send_json_error(['message' => 'Fehler beim Erstellen der Bestellung: ' . $new_order->get_error_message()]);
                return;
            }

            // Copy billing and shipping addresses from original order
            $new_order->set_billing_first_name($original_order->get_billing_first_name());
            $new_order->set_billing_last_name($original_order->get_billing_last_name());
            $new_order->set_billing_company($original_order->get_billing_company());
            $new_order->set_billing_address_1($original_order->get_billing_address_1());
            $new_order->set_billing_address_2($original_order->get_billing_address_2());
            $new_order->set_billing_city($original_order->get_billing_city());
            $new_order->set_billing_state($original_order->get_billing_state());
            $new_order->set_billing_postcode($original_order->get_billing_postcode());
            $new_order->set_billing_country($original_order->get_billing_country());
            $new_order->set_billing_email($original_order->get_billing_email());
            $new_order->set_billing_phone($original_order->get_billing_phone());

            $new_order->set_shipping_first_name($original_order->get_shipping_first_name());
            $new_order->set_shipping_last_name($original_order->get_shipping_last_name());
            $new_order->set_shipping_company($original_order->get_shipping_company());
            $new_order->set_shipping_address_1($original_order->get_shipping_address_1());
            $new_order->set_shipping_address_2($original_order->get_shipping_address_2());
            $new_order->set_shipping_city($original_order->get_shipping_city());
            $new_order->set_shipping_state($original_order->get_shipping_state());
            $new_order->set_shipping_postcode($original_order->get_shipping_postcode());
            $new_order->set_shipping_country($original_order->get_shipping_country());

            // Copy delivery date if exists
            $delivery_date = $original_order->get_meta('_delivery_date');
            if ($delivery_date) {
                $new_order->update_meta_data('_delivery_date', $delivery_date);
            }

            $delivery_time = $original_order->get_meta('_delivery_time');
            if ($delivery_time) {
                $new_order->update_meta_data('_delivery_time', $delivery_time);
            }

            // Add missing items to new order (with 0 EUR price)
            $items_summary = [];
            foreach ($missing_items as $item) {
                $product_id = intval($item['product_id']);
                $quantity = intval($item['missing_qty']);
                $product = wc_get_product($product_id);

                if ($product) {
                    // v1.1: Handle variations properly
                    $add_product_args = [
                        'subtotal' => 0,
                        'total' => 0,
                    ];

                    // If this is a variation, explicitly set variation data
                    if ($product->is_type('variation')) {
                        $add_product_args['variation'] = $product->get_variation_attributes();
                        $add_product_args['variation_id'] = $product->get_id();
                    }

                    // Add product with 0 price
                    $item_id = $new_order->add_product($product, $quantity, $add_product_args);

                    // Add nachlieferung quantity (missing items to deliver)
                    if ($item_id) {
                        // Set qty values for THIS nachlieferung order
                        wc_add_order_item_meta($item_id, '_nachlieferung_original_qty', $item['missing_qty']);
                        wc_add_order_item_meta($item_id, '_nachlieferung_delivered_qty', 0);
                        // Store reference to source order quantities for tracking
                        wc_add_order_item_meta($item_id, '_source_order_qty', $item['ordered_qty']);
                        wc_add_order_item_meta($item_id, '_source_delivered_qty', $item['delivered_qty']);

                        // v1.1: Ensure variation attributes are stored for display
                        if ($product->is_type('variation')) {
                            $order_item = $new_order->get_item($item_id);
                            if ($order_item) {
                                foreach ($product->get_variation_attributes() as $attr_key => $attr_value) {
                                    $order_item->add_meta_data($attr_key, $attr_value, true);
                                }
                                $order_item->save();
                            }
                        }
                    }

                    $items_summary[] = $quantity . 'x ' . $product->get_name();
                } else {
                    // Product not found, add as custom line item
                    $order_item = new WC_Order_Item_Product();
                    $order_item->set_name($item['name'] ?? 'Unbekanntes Produkt');
                    $order_item->set_quantity($quantity);
                    $order_item->set_subtotal(0);
                    $order_item->set_total(0);
                    $new_order->add_item($order_item);
                    $order_item->save();

                    $items_summary[] = $quantity . 'x ' . ($item['name'] ?? 'Unbekanntes Produkt');
                }
            }

            // v1.1: Explizit alle Pfand-Fees entfernen (German Market fügt diese bei add_product hinzu)
            foreach ($new_order->get_fees() as $fee_id => $fee) {
                $name = strtolower($fee->get_name());
                if (strpos($name, 'pfand') !== false ||
                    strpos($name, 'deposit') !== false ||
                    strpos($name, 'mehrweg') !== false) {
                    $new_order->remove_item($fee_id);
                }
            }

            // v1.1: Item-Level Deposit-Meta auf 0 setzen
            foreach ($new_order->get_items() as $item) {
                $item->update_meta_data('_deposit_amount', 0);
                $item->update_meta_data('_deposit_amount_per_unit', 0);
                $item->update_meta_data('_deposit_quantity', 0);
                $item->save();
            }

            // Set order totals to 0
            $new_order->set_total(0);

            // Link to original order
            $new_order->update_meta_data('_nachlieferung_original_order', $order_id);
            $new_order->update_meta_data('_nachlieferung_reason', $comment);
            $new_order->update_meta_data('_already_paid', 'yes');

            // Add order notes
            $user = get_userdata(get_current_user_id());
            $user_name = $user ? $user->display_name : 'System';

            $new_order->add_order_note(sprintf(
                'Nachlieferung erstellt von %s fuer Bestellung #%d. Fehlende Artikel: %s. %s',
                $user_name,
                $order_id,
                implode(', ', $items_summary),
                $comment ? 'Kommentar: ' . $comment : ''
            ));

            // Add note to original order
            $original_order->add_order_note(sprintf(
                'Nachlieferung #%d erstellt. Fehlende Artikel: %s. %s',
                $new_order->get_id(),
                implode(', ', $items_summary),
                $comment ? 'Kommentar: ' . $comment : ''
            ));
            $original_order->save();

            // Save new order
            $new_order->save();

            // Send customer email notification (multilingual via WooCommerce Email class)
            if (class_exists('WLG_Nachlieferung_Email_Loader')) {
                WLG_Nachlieferung_Email_Loader::sendEmail($new_order, $original_order, $missing_items, $comment);
            } else {
                // Fallback to old method if email class not loaded
                $this->sendNachlieferungEmail($new_order, $original_order, $missing_items, $comment);
            }

            wp_send_json_success([
                'message' => 'Nachlieferung erfolgreich erstellt',
                'new_order_id' => $new_order->get_id(),
                'items_count' => count($missing_items)
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
        }
    }

    /**
     * Send email notification for Nachlieferung
     */
    private function sendNachlieferungEmail($new_order, $original_order, $missing_items, $comment): void {
        $to = $original_order->get_billing_email();
        if (empty($to)) {
            return;
        }

        $customer_name = $original_order->get_billing_first_name();
        $original_order_number = $original_order->get_order_number();
        $new_order_number = $new_order->get_order_number();

        // Build items table
        $items_html = '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
        $items_html .= '<tr style="background: #f5f5f5;"><th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Artikel</th><th style="padding: 10px; text-align: center; border: 1px solid #ddd;">Menge</th></tr>';

        foreach ($missing_items as $item) {
            $items_html .= sprintf(
                '<tr><td style="padding: 10px; border: 1px solid #ddd;">%s</td><td style="padding: 10px; text-align: center; border: 1px solid #ddd;">%d</td></tr>',
                esc_html($item['name']),
                intval($item['missing_qty'])
            );
        }
        $items_html .= '</table>';

        $subject = sprintf('Nachlieferung zu Ihrer Bestellung #%s', $original_order_number);

        $message = sprintf('
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <h2 style="color: #333;">Hallo %s,</h2>

                <p>bei der Lieferung Ihrer Bestellung <strong>#%s</strong> konnten leider nicht alle Artikel geliefert werden.</p>

                <p>Wir haben daher eine <strong>kostenlose Nachlieferung</strong> fuer Sie erstellt:</p>

                <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 15px; margin: 20px 0;">
                    <strong style="color: #856404;">Nachlieferung #%s</strong><br>
                    <span style="color: #28a745; font-weight: bold;">Bereits bezahlt - Keine weiteren Kosten</span>
                </div>

                <h3 style="color: #333;">Folgende Artikel werden nachgeliefert:</h3>
                %s

                %s

                <p>Wir werden Sie kontaktieren, um einen Liefertermin zu vereinbaren.</p>

                <p>Wir entschuldigen uns fuer die Unannehmlichkeiten und danken Ihnen fuer Ihr Verstaendnis.</p>

                <p>Mit freundlichen Gruessen,<br>
                <strong>Ihr Lieferteam</strong></p>
            </div>
        ',
            esc_html($customer_name),
            esc_html($original_order_number),
            esc_html($new_order_number),
            $items_html,
            $comment ? '<p><strong>Hinweis vom Fahrer:</strong> ' . esc_html($comment) . '</p>' : ''
        );

        // Get WooCommerce email header and footer
        $mailer = WC()->mailer();
        $email_heading = 'Nachlieferung zu Ihrer Bestellung';

        $wrapped_message = $mailer->wrap_message($email_heading, $message);

        // Send email
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        wp_mail($to, $subject, $wrapped_message, $headers);
    }
}

// Initialize
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        Dispatch_Nachlieferung_System::getInstance();
    }
});
