<?php
if (!isset($_GET['check_address'])) {
    return;
}

add_action('init', function() {
    if (!current_user_can('manage_options')) {
        return;
    }

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');

    $order_id = intval($_GET['check_address']);
    echo "=== Address Meta für Order #$order_id ===\n\n";

    global $wpdb;

    // Get all address related meta
    $meta = $wpdb->get_results($wpdb->prepare("
        SELECT meta_key, meta_value
        FROM {$wpdb->prefix}wc_orders_meta
        WHERE order_id = %d
        AND (meta_key LIKE '%%billing%%' OR meta_key LIKE '%%shipping%%' OR meta_key LIKE '%%address%%' OR meta_key LIKE '%%haus%%' OR meta_key LIKE '%%number%%')
        ORDER BY meta_key
    ", $order_id));

    echo "--- WC Orders Meta (HPOS) ---\n";
    foreach ($meta as $m) {
        echo "{$m->meta_key}: {$m->meta_value}\n";
    }

    // Also check wc_orders table directly
    echo "\n--- WC Orders Table ---\n";
    $order_data = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}wc_orders WHERE id = %d
    ", $order_id));

    if ($order_data) {
        echo "billing_email: {$order_data->billing_email}\n";
    }

    // Check order addresses table
    echo "\n--- WC Order Addresses ---\n";
    $addresses = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}wc_order_addresses WHERE order_id = %d
    ", $order_id));

    foreach ($addresses as $addr) {
        echo "\n{$addr->address_type}:\n";
        echo "  first_name: {$addr->first_name}\n";
        echo "  last_name: {$addr->last_name}\n";
        echo "  address_1: {$addr->address_1}\n";
        echo "  address_2: {$addr->address_2}\n";
        echo "  city: {$addr->city}\n";
        echo "  postcode: {$addr->postcode}\n";
    }

    // Try to load order via WC
    echo "\n--- Via wc_get_order() ---\n";
    $order = wc_get_order($order_id);
    if ($order) {
        echo "Billing Address 1: " . $order->get_billing_address_1() . "\n";
        echo "Billing Address 2: " . $order->get_billing_address_2() . "\n";
        echo "Shipping Address 1: " . $order->get_shipping_address_1() . "\n";
        echo "Shipping Address 2: " . $order->get_shipping_address_2() . "\n";

        echo "\nHausnummer Meta Keys:\n";
        $keys = ['_billing_', '_billing_house_number', '_billing_hausnummer',
                 '_shipping_', '_shipping_house_number', '_shipping_hausnummer'];
        foreach ($keys as $key) {
            $val = $order->get_meta($key);
            if ($val) echo "  $key: $val\n";
        }
    } else {
        echo "Order nicht ladbar via wc_get_order()\n";
    }

    die();
}, 1);
