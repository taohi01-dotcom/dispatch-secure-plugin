<?php
/**
 * Plugin Name: Fix Nachlieferung Pfand
 * Description: Setzt Pfand und Preise bei Nachlieferungen automatisch auf 0
 * Version: 2.0
 * Author: Klaus Arends
 *
 * v2.0 (2026-01-20):
 * - Greift bei Admin-erstellten UND System-erstellten Nachlieferungen
 * - Hooks: order_status_changed, before_save, after_calculate_totals
 * - Entfernt Pfand-Fees komplett
 * - Setzt Item-Preise auf 0
 * - Setzt Deposit-Meta auf 0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Fix_Nachlieferung_Pfand {

    private static $instance = null;
    private static $processing = [];

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Hook 1: When order status changes to nachlieferung
        add_action('woocommerce_order_status_changed', [$this, 'onStatusChange'], 10, 4);

        // Hook 2: Before order is saved (catches manual admin edits)
        add_action('woocommerce_before_order_object_save', [$this, 'beforeOrderSave'], 99, 1);

        // Hook 3: After totals are calculated
        add_action('woocommerce_order_after_calculate_totals', [$this, 'afterCalculateTotals'], 999, 2);

        // Hook 4: When new order is created
        add_action('woocommerce_new_order', [$this, 'onNewOrder'], 99, 2);

        // Hook 5: When order item is added (catches German Market Pfand)
        add_action('woocommerce_new_order_item', [$this, 'onNewOrderItem'], 99, 3);
    }

    /**
     * Check if order is a Nachlieferung
     */
    private function isNachlieferung($order): bool {
        if (!$order) return false;

        // Check status
        if ($order->get_status() === 'nachlieferung') {
            return true;
        }

        // Check meta
        if ($order->get_meta('_nachlieferung_original_order')) {
            return true;
        }

        if ($order->get_meta('_already_paid') === 'yes') {
            return true;
        }

        return false;
    }

    /**
     * Fix Nachlieferung order - remove Pfand and set prices to 0
     */
    private function fixNachlieferungOrder($order): bool {
        if (!$order) return false;

        $order_id = $order->get_id();

        // Prevent infinite loops
        if (isset(self::$processing[$order_id])) {
            return false;
        }
        self::$processing[$order_id] = true;

        $changed = false;

        // 1. Remove Pfand fees
        foreach ($order->get_fees() as $fee_id => $fee) {
            $name = strtolower($fee->get_name());
            if (strpos($name, 'pfand') !== false ||
                strpos($name, 'deposit') !== false ||
                strpos($name, 'mehrweg') !== false) {
                $order->remove_item($fee_id);
                $changed = true;
                error_log("Nachlieferung #{$order_id}: Pfand-Fee '{$fee->get_name()}' entfernt");
            }
        }

        // 2. Set item prices and deposit to 0
        foreach ($order->get_items() as $item) {
            $item_changed = false;

            // Set line totals to 0
            if ($item->get_total() != 0) {
                $item->set_subtotal(0);
                $item->set_total(0);
                $item_changed = true;
            }

            // Set deposit meta to 0
            if ($item->get_meta('_deposit_amount')) {
                $item->update_meta_data('_deposit_amount', 0);
                $item->update_meta_data('_deposit_amount_per_unit', 0);
                $item->update_meta_data('_deposit_quantity', 0);
                $item_changed = true;
            }

            if ($item_changed) {
                $item->save();
                $changed = true;
            }
        }

        // 3. Set order total to 0
        if ($order->get_total() != 0) {
            $order->set_total(0);
            $changed = true;
        }

        // 4. Ensure _already_paid is set
        if ($order->get_meta('_already_paid') !== 'yes') {
            $order->update_meta_data('_already_paid', 'yes');
            $changed = true;
        }

        unset(self::$processing[$order_id]);

        return $changed;
    }

    /**
     * Hook: Status changed to nachlieferung
     */
    public function onStatusChange($order_id, $old_status, $new_status, $order): void {
        if ($new_status === 'nachlieferung') {
            if ($this->fixNachlieferungOrder($order)) {
                $order->add_order_note('✅ Nachlieferung: Pfand und Preise automatisch auf €0 gesetzt');
                $order->save();
            }
        }
    }

    /**
     * Hook: Before order save
     */
    public function beforeOrderSave($order): void {
        if ($this->isNachlieferung($order)) {
            $this->fixNachlieferungOrder($order);
        }
    }

    /**
     * Hook: After totals calculated
     */
    public function afterCalculateTotals($and_taxes, $order): void {
        if ($this->isNachlieferung($order)) {
            if ($order->get_total() > 0) {
                $this->fixNachlieferungOrder($order);
            }
        }
    }

    /**
     * Hook: New order created
     */
    public function onNewOrder($order_id, $order = null): void {
        if (!$order) {
            $order = wc_get_order($order_id);
        }

        if ($this->isNachlieferung($order)) {
            if ($this->fixNachlieferungOrder($order)) {
                $order->save();
            }
        }
    }

    /**
     * Hook: New order item added (catches Pfand fees from German Market)
     */
    public function onNewOrderItem($item_id, $item, $order_id): void {
        $order = wc_get_order($order_id);

        if (!$this->isNachlieferung($order)) {
            return;
        }

        // Check if this is a Pfand fee
        if ($item instanceof WC_Order_Item_Fee) {
            $name = strtolower($item->get_name());
            if (strpos($name, 'pfand') !== false ||
                strpos($name, 'deposit') !== false ||
                strpos($name, 'mehrweg') !== false) {
                // Remove immediately
                $order->remove_item($item_id);
                $order->save();
                error_log("Nachlieferung #{$order_id}: Pfand-Fee sofort entfernt");
            }
        }

        // Check if this is a product item with deposit
        if ($item instanceof WC_Order_Item_Product) {
            $item->set_subtotal(0);
            $item->set_total(0);
            $item->update_meta_data('_deposit_amount', 0);
            $item->update_meta_data('_deposit_amount_per_unit', 0);
            $item->save();
        }
    }
}

// Initialize
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        Fix_Nachlieferung_Pfand::getInstance();
    }
});
