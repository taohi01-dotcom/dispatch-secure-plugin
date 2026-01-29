<?php
/**
 * Auto-Assign Guest Orders to Registered Customers
 *
 * When a guest places an order with an email that matches a registered customer,
 * this plugin automatically assigns the order to that customer account.
 *
 * @package Dispatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Auto-assign guest order to registered customer if email matches
 *
 * Runs when a new order is created
 */
add_action('woocommerce_new_order', 'dispatch_auto_assign_guest_order', 10, 2);

function dispatch_auto_assign_guest_order($order_id, $order = null) {
    if (!$order) {
        $order = wc_get_order($order_id);
    }

    if (!$order) {
        return;
    }

    // Skip if order already has a customer assigned
    if ($order->get_customer_id() > 0) {
        return;
    }

    // Get billing email
    $billing_email = $order->get_billing_email();
    if (empty($billing_email)) {
        return;
    }

    // Search for existing user with this email
    $user = get_user_by('email', $billing_email);

    if ($user && $user->ID > 0) {
        // Assign order to this customer
        $order->set_customer_id($user->ID);
        $order->save();

        // Add order note
        $order->add_order_note(sprintf(
            '🔗 Gastbestellung automatisch dem registrierten Kundenkonto zugewiesen (User: %s, ID: %d)',
            $user->display_name,
            $user->ID
        ));

        error_log(sprintf(
            'Auto-Assign: Order #%d assigned to registered customer %s (ID: %d) based on email match',
            $order_id,
            $user->display_name,
            $user->ID
        ));
    }
}

/**
 * Also check when order status changes (backup for orders that slipped through)
 */
add_action('woocommerce_order_status_changed', 'dispatch_check_guest_order_assignment', 5, 4);

function dispatch_check_guest_order_assignment($order_id, $from_status, $to_status, $order) {
    // Only check on first status change from pending
    if ($from_status !== 'pending') {
        return;
    }

    dispatch_auto_assign_guest_order($order_id, $order);
}

/**
 * Admin tool: Bulk assign all unassigned guest orders
 * Access via: /wp-admin/admin.php?page=dispatch-dashboard&bulk_assign_guests=1
 */
add_action('admin_init', 'dispatch_bulk_assign_guest_orders');

function dispatch_bulk_assign_guest_orders() {
    if (!isset($_GET['bulk_assign_guests']) || $_GET['bulk_assign_guests'] !== '1') {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die('Keine Berechtigung');
    }

    // Get all guest orders (customer_id = 0)
    $guest_orders = wc_get_orders([
        'customer_id' => 0,
        'limit' => 100,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);

    $assigned = 0;
    $skipped = 0;

    foreach ($guest_orders as $order) {
        $billing_email = $order->get_billing_email();
        if (empty($billing_email)) {
            $skipped++;
            continue;
        }

        $user = get_user_by('email', $billing_email);
        if ($user && $user->ID > 0) {
            $order->set_customer_id($user->ID);
            $order->save();
            $order->add_order_note(sprintf(
                '🔗 Gastbestellung nachträglich dem Kundenkonto zugewiesen (User: %s, ID: %d)',
                $user->display_name,
                $user->ID
            ));
            $assigned++;
        } else {
            $skipped++;
        }
    }

    // Redirect with message
    wp_redirect(admin_url('admin.php?page=dispatch-dashboard&bulk_assigned=' . $assigned . '&bulk_skipped=' . $skipped));
    exit;
}

/**
 * Show admin notice after bulk assignment
 */
add_action('admin_notices', 'dispatch_bulk_assign_notice');

function dispatch_bulk_assign_notice() {
    if (isset($_GET['bulk_assigned'])) {
        $assigned = intval($_GET['bulk_assigned']);
        $skipped = intval($_GET['bulk_skipped'] ?? 0);
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>Gastbestellungen zugewiesen:</strong> ' . $assigned . ' Bestellungen wurden Kundenkonten zugewiesen. ' . $skipped . ' übersprungen.</p>';
        echo '</div>';
    }
}
