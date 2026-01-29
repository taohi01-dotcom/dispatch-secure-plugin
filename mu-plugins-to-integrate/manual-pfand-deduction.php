<?php
/**
 * Plugin Name: Manual Pfand Deduction
 * Description: Allows drivers to manually deduct Pfand amounts for customers without history
 * Version: 1.0
 * Author: Klaus Arends
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inject Manual Pfand Deduction UI into driver dashboard
 */
add_action('wp_footer', 'manual_pfand_deduction_inject', 999);

function manual_pfand_deduction_inject() {
    // Only inject on driver dashboard pages
    if (!is_page() && strpos($_SERVER['REQUEST_URI'], 'dispatch') === false) {
        return;
    }

    // Check if user can access driver features
    if (!is_user_logged_in()) {
        return;
    }

    $ajax_url = admin_url('admin-ajax.php');
    ?>
    <style>
        /* Manual Pfand Deduction Styles */
        .manual-pfand-section {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d4a6f 100%);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 16px;
            margin-top: 16px;
        }

        .manual-pfand-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .manual-pfand-title {
            color: #F59E0B;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .manual-pfand-add-btn {
            background: #F59E0B;
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .manual-pfand-add-btn:hover {
            background: #D97706;
            transform: scale(1.1);
        }

        .manual-pfand-items {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 12px;
        }

        .manual-pfand-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.1);
            padding: 10px 12px;
            border-radius: 8px;
            animation: slideIn 0.2s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .manual-pfand-select {
            flex: 1;
            background: rgba(255,255,255,0.9);
            border: none;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
            cursor: pointer;
        }

        .manual-pfand-select:focus {
            outline: 2px solid #F59E0B;
        }

        .manual-pfand-remove {
            background: #EF4444;
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .manual-pfand-remove:hover {
            background: #DC2626;
        }

        .manual-pfand-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(245, 158, 11, 0.2);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .manual-pfand-total-label {
            color: rgba(255,255,255,0.8);
            font-size: 14px;
        }

        .manual-pfand-total-value {
            color: #F59E0B;
            font-size: 20px;
            font-weight: 700;
        }

        .manual-pfand-apply-btn {
            width: 100%;
            background: #F59E0B;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .manual-pfand-apply-btn:hover {
            background: #D97706;
        }

        .manual-pfand-apply-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .manual-pfand-empty {
            color: rgba(255,255,255,0.6);
            text-align: center;
            padding: 20px;
            font-size: 14px;
        }

        .manual-pfand-note {
            margin-top: 12px;
        }

        .manual-pfand-note textarea {
            width: 100%;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 6px;
            color: white;
            padding: 10px;
            font-size: 14px;
            resize: none;
            box-sizing: border-box;
        }

        .manual-pfand-note textarea::placeholder {
            color: rgba(255,255,255,0.5);
        }

        .manual-pfand-note label {
            display: block;
            color: rgba(255,255,255,0.7);
            font-size: 12px;
            margin-bottom: 6px;
        }
    </style>

    <script>
    (function() {
        'use strict';

        // Predefined Pfand values
        const PFAND_VALUES = [
            { value: 5.00, label: '5,00 € - Kleine Flasche' },
            { value: 5.50, label: '5,50 € - Standard Flasche' },
            { value: 6.50, label: '6,50 € - Große Flasche' },
            { value: 8.50, label: '8,50 € - Siphon klein' },
            { value: 10.00, label: '10,00 € - Siphon mittel' },
            { value: 12.50, label: '12,50 € - Kasten klein' },
            { value: 13.50, label: '13,50 € - Kasten standard' },
            { value: 25.00, label: '25,00 € - Kasten groß' },
            { value: 30.00, label: '30,00 € - Fass 30L' },
            { value: 60.00, label: '60,00 € - Fass 50L' }
        ];

        let manualPfandItems = [];
        let manualPfandItemCounter = 0;

        // Create Manual Pfand Section HTML
        window.createManualPfandSection = function(orderId, orderTotal) {
            return `
                <div class="manual-pfand-section" id="manualPfandSection">
                    <div class="manual-pfand-header">
                        <div class="manual-pfand-title">
                            <span>🍶</span>
                            <span>Manueller Pfand-Abzug</span>
                        </div>
                        <button type="button" class="manual-pfand-add-btn" onclick="addManualPfandItem()" title="Pfand hinzufügen">
                            +
                        </button>
                    </div>

                    <div class="manual-pfand-items" id="manualPfandItems">
                        <div class="manual-pfand-empty" id="manualPfandEmpty">
                            Klicken Sie auf + um Pfand hinzuzufügen
                        </div>
                    </div>

                    <div class="manual-pfand-total">
                        <span class="manual-pfand-total-label">Pfand-Abzug gesamt:</span>
                        <span class="manual-pfand-total-value" id="manualPfandTotal">€0,00</span>
                    </div>

                    <div class="manual-pfand-note">
                        <label>Notiz (optional):</label>
                        <textarea id="manualPfandNote" rows="2" placeholder="z.B. Kunde umgezogen, Pfand von alter Adresse"></textarea>
                    </div>

                    <button type="button" class="manual-pfand-apply-btn" id="manualPfandApplyBtn" onclick="applyManualPfand(${orderId}, ${orderTotal})" disabled>
                        <span>💰</span>
                        <span>Pfand abziehen</span>
                    </button>
                </div>
            `;
        };

        // Add a new Pfand item
        window.addManualPfandItem = function() {
            manualPfandItemCounter++;
            const itemId = 'manual-pfand-' + manualPfandItemCounter;

            // Hide empty message
            var emptyMsg = document.getElementById('manualPfandEmpty');
            if (emptyMsg) emptyMsg.style.display = 'none';

            // Create options HTML
            let optionsHtml = '<option value="">-- Pfand auswählen --</option>';
            PFAND_VALUES.forEach(function(pv) {
                optionsHtml += '<option value="' + pv.value + '">' + pv.label + '</option>';
            });

            // Create item HTML
            const itemHtml = `
                <div class="manual-pfand-item" id="${itemId}">
                    <select class="manual-pfand-select" onchange="updateManualPfandTotal()">
                        ${optionsHtml}
                    </select>
                    <button type="button" class="manual-pfand-remove" onclick="removeManualPfandItem('${itemId}')" title="Entfernen">
                        ×
                    </button>
                </div>
            `;

            var container = document.getElementById('manualPfandItems');
            if (container) {
                container.insertAdjacentHTML('beforeend', itemHtml);
            }

            updateManualPfandTotal();
        };

        // Remove a Pfand item
        window.removeManualPfandItem = function(itemId) {
            var item = document.getElementById(itemId);
            if (item) {
                item.remove();
            }

            // Show empty message if no items left
            var items = document.querySelectorAll('.manual-pfand-item');
            if (items.length === 0) {
                var emptyMsg = document.getElementById('manualPfandEmpty');
                if (emptyMsg) emptyMsg.style.display = 'block';
            }

            updateManualPfandTotal();
        };

        // Update total
        window.updateManualPfandTotal = function() {
            var total = 0;
            var selects = document.querySelectorAll('.manual-pfand-select');

            selects.forEach(function(select) {
                var value = parseFloat(select.value);
                if (!isNaN(value) && value > 0) {
                    total += value;
                }
            });

            var totalEl = document.getElementById('manualPfandTotal');
            if (totalEl) {
                totalEl.textContent = '€' + total.toFixed(2).replace('.', ',');
            }

            var applyBtn = document.getElementById('manualPfandApplyBtn');
            if (applyBtn) {
                applyBtn.disabled = total <= 0;
            }
        };

        // Apply manual Pfand deduction
        window.applyManualPfand = function(orderId, orderTotal) {
            var total = 0;
            var items = [];
            var selects = document.querySelectorAll('.manual-pfand-select');

            selects.forEach(function(select) {
                var value = parseFloat(select.value);
                if (!isNaN(value) && value > 0) {
                    total += value;
                    // Find label
                    var label = select.options[select.selectedIndex].text;
                    items.push({
                        amount: value,
                        label: label
                    });
                }
            });

            if (total <= 0) {
                alert('Bitte wählen Sie mindestens einen Pfand-Betrag aus.');
                return;
            }

            var noteEl = document.getElementById('manualPfandNote');
            var note = noteEl ? noteEl.value : '';

            // Build confirmation message
            var confirmMsg = 'Manueller Pfand-Abzug:\n\n';
            items.forEach(function(item) {
                confirmMsg += '• ' + item.label + '\n';
            });
            confirmMsg += '\nGesamt: €' + total.toFixed(2).replace('.', ',');
            if (note) {
                confirmMsg += '\nNotiz: ' + note;
            }
            confirmMsg += '\n\nPfand vom Bestellbetrag abziehen?';

            if (!confirm(confirmMsg)) {
                return;
            }

            var btn = document.getElementById('manualPfandApplyBtn');
            if (btn) {
                btn.innerHTML = '<span>⏳</span> <span>Wird verarbeitet...</span>';
                btn.disabled = true;
            }

            // Send AJAX request
            var formData = new URLSearchParams();
            formData.append('action', 'dispatch_manual_pfand_deduction');
            formData.append('order_id', orderId);
            formData.append('pfand_total', total);
            formData.append('pfand_items', JSON.stringify(items));
            formData.append('note', note);
            formData.append('nonce', (window.dispatch_ajax && dispatch_ajax.nonce) || '');

            fetch('<?php echo $ajax_url; ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    alert('Pfand-Abzug erfolgreich!\n\nNeuer Bestellbetrag: €' + data.data.new_total);

                    // Update order total display if exists
                    if (typeof updateOrderTotalDisplay === 'function') {
                        updateOrderTotalDisplay(data.data.new_total);
                    }

                    // Clear the form
                    var container = document.getElementById('manualPfandItems');
                    if (container) {
                        container.innerHTML = '<div class="manual-pfand-empty" id="manualPfandEmpty">Klicken Sie auf + um Pfand hinzuzufügen</div>';
                    }
                    var noteEl = document.getElementById('manualPfandNote');
                    if (noteEl) noteEl.value = '';
                    updateManualPfandTotal();

                    // Refresh order details if function exists
                    if (typeof refreshOrderDetails === 'function') {
                        refreshOrderDetails(orderId);
                    }
                } else {
                    alert('Fehler: ' + (data.data || 'Unbekannter Fehler'));
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                alert('Fehler beim Pfand-Abzug');
            })
            .finally(function() {
                if (btn) {
                    btn.innerHTML = '<span>💰</span> <span>Pfand abziehen</span>';
                    btn.disabled = false;
                }
                updateManualPfandTotal();
            });
        };

        console.log('Manual Pfand Deduction UI loaded');
    })();
    </script>
    <?php
}

/**
 * AJAX Handler for manual Pfand deduction
 */
add_action('wp_ajax_dispatch_manual_pfand_deduction', 'handle_manual_pfand_deduction');

function handle_manual_pfand_deduction() {
    // Get parameters
    $order_id = intval($_POST['order_id'] ?? 0);
    $pfand_total = floatval($_POST['pfand_total'] ?? 0);
    $pfand_items_json = sanitize_text_field($_POST['pfand_items'] ?? '[]');
    $note = sanitize_textarea_field($_POST['note'] ?? '');

    if (!$order_id || $pfand_total <= 0) {
        wp_send_json_error('Ungültige Parameter');
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Bestellung nicht gefunden');
        return;
    }

    $pfand_items = json_decode($pfand_items_json, true);
    if (!is_array($pfand_items)) {
        $pfand_items = [];
    }

    try {
        // Get current order total
        $current_total = floatval($order->get_total());
        $new_total = $current_total - $pfand_total;

        // Create a fee item for the Pfand deduction (negative amount)
        $fee = new WC_Order_Item_Fee();
        $fee->set_name('Pfand-Rückgabe (manuell)');
        $fee->set_amount(-$pfand_total);
        $fee->set_total(-$pfand_total);
        $fee->set_tax_status('none');
        $order->add_item($fee);

        // Build items description for note
        $items_description = [];
        foreach ($pfand_items as $item) {
            $items_description[] = $item['label'] ?? ('€' . number_format($item['amount'], 2, ',', '.'));
        }

        // Add order note
        $order_note = sprintf(
            'Manueller Pfand-Abzug: €%s\nArtikel: %s%s\nVerarbeitet von: %s',
            number_format($pfand_total, 2, ',', '.'),
            implode(', ', $items_description),
            $note ? "\nNotiz: " . $note : '',
            wp_get_current_user()->display_name
        );
        $order->add_order_note($order_note);

        // Store meta data
        $order->update_meta_data('_manual_pfand_deduction', $pfand_total);
        $order->update_meta_data('_manual_pfand_items', $pfand_items_json);
        $order->update_meta_data('_manual_pfand_note', $note);
        $order->update_meta_data('_manual_pfand_date', current_time('mysql'));
        $order->update_meta_data('_manual_pfand_driver', wp_get_current_user()->display_name);

        // Recalculate totals
        $order->calculate_totals();
        $order->save();

        wp_send_json_success([
            'message' => 'Pfand-Abzug erfolgreich',
            'pfand_total' => $pfand_total,
            'new_total' => $order->get_total(),
            'order_id' => $order_id
        ]);

    } catch (Exception $e) {
        wp_send_json_error('Fehler: ' . $e->getMessage());
    }
}
