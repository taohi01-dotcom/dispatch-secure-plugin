<?php
/**
 * Plugin Name: Nachlieferung Driver UI
 * Description: Adds partial delivery UI to driver dashboard
 * Version: 1.0
 * Author: Klaus Arends
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inject Nachlieferung CSS and JS into driver dashboard
 */
add_action('wp_footer', 'nachlieferung_driver_ui_inject', 999);

function nachlieferung_driver_ui_inject() {
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
        /* Nachlieferung Driver UI Styles */
        .item-quantity-select {
            margin-right: 12px;
        }

        .delivered-qty-select {
            background: #007AFF;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            min-width: 50px;
        }

        .delivered-qty-select.partial {
            background: #F59E0B;
            animation: nachlieferung-pulse 1s infinite;
        }

        @keyframes nachlieferung-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .nachlieferung-section {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid #F59E0B;
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
        }

        .nachlieferung-warning {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .warning-icon {
            font-size: 20px;
        }

        .warning-text {
            color: #F59E0B;
            font-weight: 600;
            font-size: 14px;
        }

        .nachlieferung-comment label {
            display: block;
            color: rgba(255,255,255,0.7);
            font-size: 12px;
            margin-bottom: 6px;
        }

        .nachlieferung-comment textarea {
            width: 100%;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 6px;
            color: white;
            padding: 10px;
            font-size: 14px;
            resize: none;
            margin-bottom: 12px;
            box-sizing: border-box;
        }

        .nachlieferung-btn {
            background: #F59E0B !important;
            color: white !important;
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .nachlieferung-btn:hover {
            background: #D97706 !important;
        }

        .nachlieferung-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>

    <script>
    (function() {
        'use strict';

        // Nachlieferung Functions - will be called from driver dashboard
        window.checkNachlieferung = function(orderId) {
            var itemCards = document.querySelectorAll('.item-card[data-ordered-qty]');
            var hasPartialDelivery = false;

            itemCards.forEach(function(card) {
                var orderedQty = parseInt(card.dataset.orderedQty);
                var select = card.querySelector('.delivered-qty-select');
                if (!select) return;
                var deliveredQty = parseInt(select.value);

                if (deliveredQty < orderedQty) {
                    hasPartialDelivery = true;
                    select.classList.add('partial');
                } else {
                    select.classList.remove('partial');
                }
            });

            var nachlieferungSection = document.getElementById('nachlieferungSection');
            if (nachlieferungSection) {
                nachlieferungSection.style.display = hasPartialDelivery ? 'block' : 'none';
            }
        };

        window.createNachlieferung = function(orderId) {
            var itemCards = document.querySelectorAll('.item-card[data-ordered-qty]');
            var missingItems = [];

            itemCards.forEach(function(card) {
                var orderedQty = parseInt(card.dataset.orderedQty);
                var select = card.querySelector('.delivered-qty-select');
                if (!select) return;
                var deliveredQty = parseInt(select.value);
                var missingQty = orderedQty - deliveredQty;

                if (missingQty > 0) {
                    missingItems.push({
                        product_id: card.dataset.itemId,
                        name: card.dataset.itemName,
                        sku: card.dataset.itemSku,
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

            var commentEl = document.getElementById('nachlieferungComment');
            var comment = commentEl ? commentEl.value : '';

            var confirmMsg = 'Nachlieferung erstellen fuer ' + missingItems.length + ' Artikel?\n\n';
            confirmMsg += missingItems.map(function(item) {
                return '- ' + item.missing_qty + 'x ' + item.name;
            }).join('\n');
            confirmMsg += '\n\nKommentar: ' + (comment || '(kein Kommentar)');

            if (!confirm(confirmMsg)) {
                return;
            }

            var btn = document.querySelector('.nachlieferung-btn');
            if (!btn) return;
            var originalText = btn.innerHTML;
            btn.innerHTML = '⏳ Wird erstellt...';
            btn.disabled = true;

            var formData = new URLSearchParams();
            formData.append('action', 'dispatch_create_nachlieferung');
            formData.append('order_id', orderId);
            formData.append('missing_items', JSON.stringify(missingItems));
            formData.append('comment', comment);

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
                    alert('Nachlieferung #' + data.data.new_order_id + ' wurde erstellt!\n\nDer Kunde wurde per E-Mail benachrichtigt.');
                    document.querySelectorAll('.delivered-qty-select').forEach(function(select) {
                        var card = select.closest('.item-card');
                        if (card) {
                            select.value = card.dataset.orderedQty;
                            select.classList.remove('partial');
                        }
                    });
                    var section = document.getElementById('nachlieferungSection');
                    if (section) section.style.display = 'none';
                    if (commentEl) commentEl.value = '';
                } else {
                    alert('Fehler: ' + (data.data && data.data.message ? data.data.message : 'Unbekannter Fehler'));
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                alert('Fehler beim Erstellen der Nachlieferung');
            })
            .finally(function() {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        };

        // Utility function to escape strings for JavaScript
        window.escapeJs = function(str) {
            if (!str) return '';
            return String(str)
                .replace(/\\/g, '\\\\')
                .replace(/'/g, "\\'")
                .replace(/"/g, '\\"')
                .replace(/\n/g, '\\n')
                .replace(/\r/g, '\\r');
        };

        console.log('Nachlieferung Driver UI loaded');
    })();
    </script>
    <?php
}
