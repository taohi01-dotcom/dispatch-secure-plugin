<?php
/**
 * Pfand-Tagesstatistik - mu-plugin
 * Version 2.1.3 - HPOS Compatible + delivered Status
 *
 * FIX v2.1.0 (2026-01-26):
 * - Suche nach _pfand_processing_complete statt _pfand_refunded
 * - Dies zeigt die AKTUELLE Bestellung an (wo Pfand zurückgegeben wird)
 * - Nicht mehr die ursprüngliche Bestellung (woher das Pfand stammt)
 *
 * Speicherort: /wp-content/mu-plugins/pfand-daily-statistics.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Pfand_Daily_Statistics {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_submenu'], 99);
        add_action('wp_ajax_get_pfand_statistics', [$this, 'ajax_get_statistics']);
    }

    public function add_submenu() {
        add_submenu_page(
            'dispatch-dashboard',
            'Pfand-Tagesstatistik',
            '📊 Pfand-Statistik',
            'manage_options',
            'pfand-statistik',
            [$this, 'render_page']
        );
    }

    public function render_page() {
        $today = date('Y-m-d');
        echo '<div class="wrap">';
        echo '<h1>📊 Pfand-Tagesstatistik</h1>';
        echo '<p>Tägliche Übersicht der Pfand-Rückerstattungen mit Artikeldetails.</p>';

        echo '<div style="margin: 20px 0; display: flex; align-items: center; gap: 10px;">';
        echo '<label for="pfand-date"><strong>Datum wählen:</strong></label> ';
        echo '<input type="date" id="pfand-date" value="' . esc_attr($today) . '" style="padding: 10px 12px; font-size: 14px; border: 1px solid #ccc; border-radius: 4px; height: 42px; box-sizing: border-box;">';
        echo '<button id="load-pfand-stats" class="button button-primary" style="padding: 10px 20px; font-size: 14px; height: 42px; box-sizing: border-box; display: flex; align-items: center; justify-content: center; border: 2px solid white; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">Laden</button>';
        echo '</div>';

        echo '<div id="pfand-stats-container" style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">';
        echo '<p><em>Wähle ein Datum und klicke auf "Laden"...</em></p>';
        echo '</div>';

        echo '<script>
        jQuery(document).ready(function($) {
            function loadStats() {
                var date = $("#pfand-date").val();
                $("#pfand-stats-container").html("<p><em>Lade Daten...</em></p>");

                $.ajax({
                    url: ajaxurl,
                    method: "POST",
                    data: {
                        action: "get_pfand_statistics",
                        date: date,
                        nonce: "' . wp_create_nonce('pfand_stats') . '"
                    },
                    success: function(response) {
                        if (response.success) {
                            $("#pfand-stats-container").html(response.data.html);
                        } else {
                            $("#pfand-stats-container").html("<p style=\"color:red;\">Fehler: " + response.data + "</p>");
                        }
                    },
                    error: function() {
                        $("#pfand-stats-container").html("<p style=\"color:red;\">AJAX-Fehler</p>");
                    }
                });
            }

            $("#load-pfand-stats").on("click", loadStats);
            loadStats();
        });
        </script>';

        echo '</div>';
    }

    public function ajax_get_statistics() {
        check_ajax_referer('pfand_stats', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
        }

        $date = sanitize_text_field($_POST['date'] ?? date('Y-m-d'));
        $refunds = $this->get_refunds_hpos_compatible($date);
        $html = $this->render_statistics($refunds, $date);

        wp_send_json_success(['html' => $html]);
    }

    /**
     * HPOS-compatible method to get refunds using wc_get_orders
     * v2.1.3: Lade alle kürzlichen Bestellungen und prüfe Meta manuell (HPOS-Workaround)
     */
    private function get_refunds_hpos_compatible($date) {
        $refunds = [];

        $search_date = $date; // Format: Y-m-d

        error_log('Pfand Stats v2.1.3: Searching for date ' . $date);

        // v2.1.3: HPOS-Workaround - lade kürzliche Bestellungen ohne Meta-Filter
        // und prüfe die Meta-Daten manuell
        $args = [
            'limit' => 200,
            'status' => ['processing', 'completed', 'on-hold', 'refunded', 'delivered', 'wc-completed', 'wc-delivered'],
            'orderby' => 'date',
            'order' => 'DESC',
            'date_created' => '>' . date('Y-m-d', strtotime('-60 days')) // Letzte 60 Tage
        ];

        $all_orders = wc_get_orders($args);
        error_log('Pfand Stats v2.1.3: Loaded ' . count($all_orders) . ' recent orders');

        // Filtere nach Pfand-Verarbeitung UND Datum
        $orders = [];
        foreach ($all_orders as $order) {
            // Prüfe ob _pfand_processing_complete = yes
            if ($order->get_meta('_pfand_processing_complete') !== 'yes') {
                continue;
            }

            $processing_date = $order->get_meta('_pfand_processing_date');
            $refund_date = $order->get_meta('_pfand_refund_date');

            // Prüfe ob eines der Daten zum gesuchten Tag passt
            $processing_day = $processing_date ? substr($processing_date, 0, 10) : '';
            $refund_day = $refund_date ? substr($refund_date, 0, 10) : '';

            if ($processing_day === $search_date || $refund_day === $search_date) {
                $orders[] = $order;
                error_log('Pfand Stats v2.1.3: Found matching order #' . $order->get_id());
            }
        }

        error_log('Pfand Stats v2.1.3: After filter: ' . count($orders) . ' orders for ' . $search_date);

        foreach ($orders as $order) {
            $order_id = $order->get_id();

            // Get refund details from meta
            $refund_items = $order->get_meta('_pfand_refund_items');
            $refund_amount = floatval($order->get_meta('_pfand_refund_amount'));
            $refund_driver = $order->get_meta('_pfand_refund_driver');
            $processing_date = $order->get_meta('_pfand_processing_date');

            // v2.0.1: Get missing bottles and deductions
            $missing_bottles = $order->get_meta('_pfand_missing_bottles');
            $deduction_amount = floatval($order->get_meta('_pfand_deduction_amount'));

            if (empty($processing_date)) {
                $processing_date = $order->get_meta('_pfand_refund_date');
            }

            error_log('Pfand Stats v2: Order #' . $order_id . ' - Driver: ' . ($refund_driver ?: 'NONE') . ', Items: ' . (is_array($refund_items) ? count($refund_items) : 'NONE') . ', Missing: ' . (is_array($missing_bottles) ? json_encode($missing_bottles) : 'NONE'));

            // Parse items if available
            $items = [];
            if (!empty($refund_items) && is_array($refund_items)) {
                $items = $refund_items;
            }

            // If no amount, try to calculate from items
            if ($refund_amount <= 0 && !empty($items)) {
                foreach ($items as $item) {
                    $refund_amount += floatval($item['amount'] ?? $item['total_pfand'] ?? 0);
                }
            }

            // If still no amount, try to get from order notes (fallback)
            if ($refund_amount <= 0) {
                $refund_amount = $this->get_amount_from_notes($order_id);
            }

            if ($refund_amount > 0) {
                // v2.1.0: Get cash payout amount (when pfand > order total)
                $cash_payout = floatval($order->get_meta('_pfand_cash_payout'));
                $order_offset = floatval($order->get_meta('_pfand_order_offset'));

                // If no explicit values saved, try to calculate from order
                if ($cash_payout <= 0 && $order_offset <= 0) {
                    $order_total = floatval($order->get_total());
                    // If order total is 0 or negative after refund, driver paid out cash
                    if ($order_total <= 0) {
                        // Get the WooCommerce refund amount
                        $wc_refunds = $order->get_refunds();
                        $wc_refund_total = 0;
                        foreach ($wc_refunds as $wc_refund) {
                            $wc_refund_total += abs($wc_refund->get_total());
                        }
                        $order_offset = $wc_refund_total;
                        $cash_payout = $refund_amount - $wc_refund_total;
                        if ($cash_payout < 0) $cash_payout = 0;
                    } else {
                        $order_offset = $refund_amount;
                        $cash_payout = 0;
                    }
                }

                $refunds[] = [
                    'order_id' => $order_id,
                    'items' => $items,
                    'total' => $refund_amount,
                    'driver' => $refund_driver ?: $this->get_driver_from_notes($order_id),
                    'time' => $processing_date ?: current_time('mysql'),
                    'missing_bottles' => $missing_bottles ?: [],
                    'deduction' => $deduction_amount,
                    'cash_payout' => $cash_payout,
                    'order_offset' => $order_offset
                ];
            }
        }

        error_log('Pfand Stats v2: Returning ' . count($refunds) . ' refunds');
        return $refunds;
    }

    private function get_amount_from_notes($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return 0;

        $notes = wc_get_order_notes(['order_id' => $order_id, 'limit' => 50]);

        foreach ($notes as $note) {
            if (preg_match('/Pfand zurückerstattet:\s*€?([\d,\.]+)/i', $note->content, $m)) {
                return floatval(str_replace(',', '.', $m[1]));
            }
            if (preg_match('/Pfand-Rückerstattung.*?€([\d,\.]+)/i', $note->content, $m)) {
                return floatval(str_replace(',', '.', $m[1]));
            }
        }

        return 0;
    }

    private function get_driver_from_notes($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return 'Unbekannt';

        $notes = wc_get_order_notes(['order_id' => $order_id, 'limit' => 50]);

        foreach ($notes as $note) {
            if (preg_match('/Fahrer:\s*([^,\n<]+)/i', $note->content, $m)) {
                return trim($m[1]);
            }
        }

        return 'Unbekannt';
    }

    private function render_statistics($refunds, $date) {
        $date_formatted = date('d.m.Y', strtotime($date));

        if (empty($refunds)) {
            return '<div style="text-align: center; padding: 40px; color: #666;">
                <span style="font-size: 48px;">📭</span>
                <h3>Keine Pfand-Rückerstattungen am ' . esc_html($date_formatted) . '</h3>
                <p>An diesem Tag wurden keine Pfand-Artikel zurückerstattet.</p>
            </div>';
        }

        // Calculate totals
        $total_amount = 0;
        $total_items = 0;
        $items_count = [];
        $by_driver = [];

        foreach ($refunds as $refund) {
            $total_amount += $refund['total'];
            $driver = $refund['driver'] ?: 'Unbekannt';

            if (!isset($by_driver[$driver])) {
                $by_driver[$driver] = ['amount' => 0, 'count' => 0, 'items' => []];
            }
            $by_driver[$driver]['amount'] += $refund['total'];
            $by_driver[$driver]['count']++;

            foreach ($refund['items'] as $item) {
                $qty = intval($item['quantity'] ?? $item['qty'] ?? 1);
                $name = $item['item_name'] ?? $item['name'] ?? 'Unbekannt';
                $total_items += $qty;

                $short_name = $this->shorten_product_name($name);

                if (!isset($items_count[$short_name])) {
                    $items_count[$short_name] = 0;
                }
                $items_count[$short_name] += $qty;

                if (!isset($by_driver[$driver]['items'][$short_name])) {
                    $by_driver[$driver]['items'][$short_name] = 0;
                }
                $by_driver[$driver]['items'][$short_name] += $qty;
            }
        }

        arsort($items_count);

        $html = '';

        // Summary cards
        $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">';

        $html .= '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">
            <div style="font-size: 32px; font-weight: bold;">€' . number_format($total_amount, 2, ',', '.') . '</div>
            <div style="opacity: 0.9; margin-top: 8px;">Gesamt-Rückerstattung</div>
        </div>';

        $html .= '<div style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">
            <div style="font-size: 32px; font-weight: bold;">' . count($refunds) . '</div>
            <div style="opacity: 0.9; margin-top: 8px;">Rückerstattungen</div>
        </div>';

        $html .= '<div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">
            <div style="font-size: 32px; font-weight: bold;">📦 ' . $total_items . '</div>
            <div style="opacity: 0.9; margin-top: 8px;">Kästen/Artikel zurück</div>
        </div>';

        $html .= '</div>';

        // Items breakdown (only show if we have item details)
        if (!empty($items_count)) {
            $html .= '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
            $html .= '<h3 style="margin-top: 0;">📦 Artikelübersicht (erwartete Rückgaben)</h3>';
            $html .= '<table style="width: 100%; border-collapse: collapse;">';
            $html .= '<tr style="background: #e9ecef;"><th style="padding: 10px; text-align: left;">Artikel</th><th style="padding: 10px; text-align: right;">Anzahl</th></tr>';

            foreach ($items_count as $name => $qty) {
                $html .= '<tr style="border-bottom: 1px solid #dee2e6;">';
                $html .= '<td style="padding: 10px;">' . esc_html($name) . '</td>';
                $html .= '<td style="padding: 10px; text-align: right; font-weight: bold;">' . $qty . 'x</td>';
                $html .= '</tr>';
            }

            $html .= '</table>';
            $html .= '</div>';
        } else {
            $html .= '<div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #856404;">';
            $html .= '<strong>Hinweis:</strong> Artikeldetails sind erst für neue Rückerstattungen (ab v2.9.95) verfügbar.';
            $html .= '</div>';
        }

        // By Driver
        $html .= '<h3>👤 Nach Fahrer</h3>';

        foreach ($by_driver as $driver => $data) {
            $html .= '<div style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px;">';
            $html .= '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">';
            $html .= '<strong style="font-size: 16px;">👤 ' . esc_html($driver) . '</strong>';
            $html .= '<span style="background: #28a745; color: white; padding: 5px 12px; border-radius: 20px; font-weight: bold;">€' . number_format($data['amount'], 2, ',', '.') . '</span>';
            $html .= '</div>';

            if (!empty($data['items'])) {
                $html .= '<div style="font-size: 13px; color: #666;">';
                $item_strings = [];
                foreach ($data['items'] as $item_name => $item_qty) {
                    $item_strings[] = $item_qty . 'x ' . $item_name;
                }
                $html .= implode(' | ', $item_strings);
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        // Detail list
        $html .= '<h3>📋 Einzelne Rückerstattungen</h3>';
        $html .= '<table style="width: 100%; border-collapse: collapse; background: #fff;">';
        $html .= '<thead><tr style="background: #343a40; color: white;">';
        $html .= '<th style="padding: 12px; text-align: left;">Zeit</th>';
        $html .= '<th style="padding: 12px; text-align: left;">Bestellung</th>';
        $html .= '<th style="padding: 12px; text-align: left;">Fahrer</th>';
        $html .= '<th style="padding: 12px; text-align: left;">Artikel</th>';
        $html .= '<th style="padding: 12px; text-align: center;">Pfand</th>';
        $html .= '<th style="padding: 12px; text-align: right;">Verrechnet</th>';
        $html .= '<th style="padding: 12px; text-align: right;">Ausgezahlt</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($refunds as $refund) {
            $time_display = date('H:i', strtotime($refund['time']));

            $items_display = [];
            if (!empty($refund['items'])) {
                foreach ($refund['items'] as $item) {
                    $qty = intval($item['quantity'] ?? $item['qty'] ?? 1);
                    $name = $this->shorten_product_name($item['item_name'] ?? $item['name'] ?? 'Unbekannt');
                    $amount = floatval($item['amount'] ?? $item['total_pfand'] ?? 0);

                    // v2.0.1: Check if this is a manual item
                    $is_manual = isset($item['manual']) && $item['manual'];
                    $prefix = $is_manual ? '💰 ' : '';

                    $items_display[] = $prefix . $qty . 'x ' . esc_html($name) . ' <span style="color:#059669;">(+€' . number_format($amount, 2) . ')</span>';
                }
            }

            // v2.0.1: Show missing bottles as deductions
            $missing_bottles = $refund['missing_bottles'] ?? [];
            if (!empty($missing_bottles) && is_array($missing_bottles)) {
                $pfand_config = get_option('dispatch_pfand_items', [
                    ['id' => 'water', 'name' => 'Wasserflasche', 'amount' => 0.50],
                    ['id' => 'beer', 'name' => 'Bierflasche', 'amount' => 0.25]
                ]);
                $pfand_prices = [];
                foreach ($pfand_config as $cfg) {
                    $pfand_prices[$cfg['id']] = ['name' => $cfg['name'], 'amount' => $cfg['amount']];
                    $pfand_prices[$cfg['name']] = ['name' => $cfg['name'], 'amount' => $cfg['amount']];
                }

                foreach ($missing_bottles as $bottle_type => $qty) {
                    $qty = intval($qty);
                    if ($qty > 0) {
                        $bottle_info = $pfand_prices[$bottle_type] ?? ['name' => $bottle_type, 'amount' => 0.50];
                        $deduction = $qty * $bottle_info['amount'];
                        $items_display[] = '<span style="color:#dc2626;">⚠️ ' . $qty . 'x Fehlende ' . esc_html($bottle_info['name']) . ' (-€' . number_format($deduction, 2) . ')</span>';
                    }
                }
            }

            if (empty($items_display)) {
                $items_display[] = '<em style="color:#999;">Keine Details</em>';
            }

            // v2.1.0: Get cash payout and order offset values
            $cash_payout = floatval($refund['cash_payout'] ?? 0);
            $order_offset = floatval($refund['order_offset'] ?? $refund['total']);

            $html .= '<tr style="border-bottom: 1px solid #dee2e6;">';
            $html .= '<td style="padding: 10px;">' . esc_html($time_display) . '</td>';
            $html .= '<td style="padding: 10px;"><a href="' . admin_url('post.php?post=' . $refund['order_id'] . '&action=edit') . '" target="_blank">#' . $refund['order_id'] . '</a></td>';
            $html .= '<td style="padding: 10px;">' . esc_html($refund['driver']) . '</td>';
            $html .= '<td style="padding: 10px; font-size: 12px;">' . implode('<br>', $items_display) . '</td>';
            $html .= '<td style="padding: 10px; text-align: center; font-weight: bold;">€' . number_format($refund['total'], 2, ',', '.') . '</td>';
            // Verrechnet (offset against order)
            $html .= '<td style="padding: 10px; text-align: right; color: #059669;">' . ($order_offset > 0 ? '€' . number_format($order_offset, 2, ',', '.') : '-') . '</td>';
            // Ausgezahlt (cash paid by driver)
            if ($cash_payout > 0) {
                $html .= '<td style="padding: 10px; text-align: right; color: #dc2626; font-weight: bold;">💸 €' . number_format($cash_payout, 2, ',', '.') . '</td>';
            } else {
                $html .= '<td style="padding: 10px; text-align: right; color: #9ca3af;">-</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private function shorten_product_name($name) {
        $name = preg_replace('/\s*-\s*Botella.*$/i', '', $name);
        $name = preg_replace('/\s*-\s*Agua.*$/i', '', $name);
        $name = preg_replace('/\s*\d+x[\d,\.]+L$/i', '', $name);
        $name = preg_replace('/\s*\(\d+\s*Tamaños.*\)$/i', '', $name);
        $name = preg_replace('/\s*Botellas?\s*Retornables?$/i', '', $name);
        $name = preg_replace('/\s*Botellas?\s*de\s*Cristal$/i', '', $name);
        $name = preg_replace('/\s*Botellas?\s*de\s*Vidrio\s*Retornable?$/i', '', $name);

        if (strlen($name) > 40) {
            $name = substr($name, 0, 37) . '...';
        }

        return trim($name);
    }
}

new Pfand_Daily_Statistics();
