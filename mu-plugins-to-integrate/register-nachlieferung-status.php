<?php
/**
 * Plugin Name: Nachlieferung Status Registration
 * Description: Registriert den "Nachlieferung" Bestellstatus für WooCommerce
 * Version: 1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register 'nachlieferung' order status
 */
add_action('init', function() {
    register_post_status('wc-nachlieferung', [
        'label'                     => _x('Nachlieferung', 'Order status', 'woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop(
            'Nachlieferung <span class="count">(%s)</span>',
            'Nachlieferung <span class="count">(%s)</span>',
            'woocommerce'
        ),
    ]);
});

/**
 * Add status to WooCommerce order statuses
 */
add_filter('wc_order_statuses', function($order_statuses) {
    $new_statuses = [];

    foreach ($order_statuses as $key => $status) {
        $new_statuses[$key] = $status;

        // Add nachlieferung after 'on-hold'
        if ($key === 'wc-on-hold') {
            $new_statuses['wc-nachlieferung'] = _x('Nachlieferung', 'Order status', 'woocommerce');
        }
    }

    return $new_statuses;
});

/**
 * Add status color in admin
 */
add_action('admin_head', function() {
    ?>
    <style>
        .order-status.status-nachlieferung {
            background: #f0ad4e;
            color: #fff;
        }
        .widefat .column-order_status mark.nachlieferung,
        .woocommerce-page .woocommerce-order-status mark.nachlieferung {
            background: #f0ad4e;
            color: #fff;
        }
        .widefat .column-order_status mark.nachlieferung::after,
        .woocommerce-page .woocommerce-order-status mark.nachlieferung::after {
            content: "\e011";
            color: #fff;
        }
    </style>
    <?php
});

/**
 * Make status editable in bulk actions
 */
add_filter('bulk_actions-edit-shop_order', function($bulk_actions) {
    $bulk_actions['mark_nachlieferung'] = 'Status ändern: Nachlieferung';
    return $bulk_actions;
});

add_filter('bulk_actions-woocommerce_page_wc-orders', function($bulk_actions) {
    $bulk_actions['mark_nachlieferung'] = 'Status ändern: Nachlieferung';
    return $bulk_actions;
});
