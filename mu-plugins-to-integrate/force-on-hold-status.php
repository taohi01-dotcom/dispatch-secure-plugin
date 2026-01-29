<?php
/**
 * Plugin Name: Force On-Hold Status for All Orders
 * Description: Setzt ALLE neuen Bestellungen auf "on-hold" (Wartestellung)
 * Version: 3.0
 * 
 * Verwendet mehrere Hooks um alle Szenarien abzudecken:
 * - Classic Checkout
 * - Block Checkout  
 * - REST API
 * - Admin-erstellte Bestellungen
 */

// 1. Standard-Status für neue Bestellungen auf on-hold setzen
add_filter('woocommerce_default_order_status', 'wlg_default_order_status');
function wlg_default_order_status($status) {
    return 'on-hold';
}

// 2. Payment Complete Status überschreiben (für alle Zahlungsmethoden)
add_filter('woocommerce_payment_complete_order_status', 'wlg_payment_complete_status', 999, 3);
function wlg_payment_complete_status($status, $order_id, $order) {
    return 'on-hold';
}

// 3. Classic Checkout - nach Bestellverarbeitung
add_action('woocommerce_checkout_order_processed', 'wlg_force_on_hold_classic', 999, 3);
function wlg_force_on_hold_classic($order_id, $posted_data, $order) {
    if ($order && $order->get_status() !== 'on-hold') {
        $order->set_status('on-hold', 'Automatisch auf Wartestellung gesetzt.');
        $order->save();
    }
}

// 4. Block Checkout (Store API) - nach Bestellverarbeitung
add_action('woocommerce_store_api_checkout_order_processed', 'wlg_force_on_hold_block', 999, 1);
function wlg_force_on_hold_block($order) {
    if ($order && $order->get_status() !== 'on-hold') {
        $order->set_status('on-hold', 'Automatisch auf Wartestellung gesetzt (Block Checkout).');
        $order->save();
    }
}

// 5. Fallback: Abfangen wenn Status auf "processing" gesetzt wird
add_action('woocommerce_order_status_processing', 'wlg_intercept_processing', 1, 2);
function wlg_intercept_processing($order_id, $order = null) {
    if (!$order) {
        $order = wc_get_order($order_id);
    }
    if ($order) {
        // Nur bei neuen Bestellungen (< 2 Minuten alt)
        $created = $order->get_date_created();
        if ($created) {
            $age = time() - $created->getTimestamp();
            if ($age < 120) {
                $order->set_status('on-hold', 'Von Processing auf Wartestellung geändert.');
                $order->save();
            }
        }
    }
}

// 6. COD-spezifisch: Verhindert dass COD direkt auf completed geht
add_filter('woocommerce_cod_process_payment_order_status', 'wlg_cod_status', 999);
function wlg_cod_status($status) {
    return 'on-hold';
}
