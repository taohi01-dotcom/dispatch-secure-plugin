<?php
/**
 * Kundennummer System für WooCommerce
 *
 * Features:
 * - Automatische Kundennummer-Generierung bei Registrierung
 * - Manuelle Eingabe im Benutzerprofil
 * - Anzeige in WCPDF Rechnungen und Lieferscheinen
 * - Anzeige in Germanized/StoreaBill Dokumenten
 * - Admin-Einstellungsseite für Startnummer und Format
 * - NUR für registrierte Kunden (Gäste erhalten keine Nummer)
 *
 * @version 1.2.0
 *
 * CHANGELOG v1.2.0 (2025-11-27):
 * - NEW: Option "Nur bei Registrierung" - bestehende Kunden erhalten keine automatische Nummer
 * - FIX: Profiländerungen werden sofort in Bestellliste angezeigt
 * - FIX: Startnummer kann jetzt frei gewählt werden (mit Duplikat-Warnung)
 * - FIX: Auto-Vergabe Checkbox speichert korrekt beim Deaktivieren
 * - IMPROVED: Klarere Beschreibungen in Einstellungen
 */

if (!defined('ABSPATH')) exit;

// ============================================
// 1. ADMIN SETTINGS PAGE
// ============================================

/**
 * Add admin menu for customer number settings
 */
add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'Kundennummer Einstellungen',
        'Kundennummer',
        'manage_woocommerce',
        'customer-number-settings',
        'render_customer_number_settings_page'
    );
});

/**
 * Register settings
 */
add_action('admin_init', function() {
    register_setting('customer_number_settings', 'cn_start_number', [
        'type' => 'integer',
        'default' => 10001,
        'sanitize_callback' => 'absint'
    ]);

    register_setting('customer_number_settings', 'cn_prefix', [
        'type' => 'string',
        'default' => '',
        'sanitize_callback' => 'sanitize_text_field'
    ]);

    register_setting('customer_number_settings', 'cn_suffix', [
        'type' => 'string',
        'default' => '',
        'sanitize_callback' => 'sanitize_text_field'
    ]);

    // Checkbox: use custom sanitize to handle unchecked state
    register_setting('customer_number_settings', 'cn_auto_generate', [
        'type' => 'string',
        'default' => 'yes',
        'sanitize_callback' => function($value) {
            return $value === 'yes' ? 'yes' : 'no';
        }
    ]);

    register_setting('customer_number_settings', 'cn_show_in_account', [
        'type' => 'string',
        'default' => 'yes',
        'sanitize_callback' => function($value) {
            return $value === 'yes' ? 'yes' : 'no';
        }
    ]);

    // Only on registration (not on first order)
    register_setting('customer_number_settings', 'cn_only_registration', [
        'type' => 'string',
        'default' => 'no',
        'sanitize_callback' => function($value) {
            return $value === 'yes' ? 'yes' : 'no';
        }
    ]);
});

/**
 * Render settings page
 */
function render_customer_number_settings_page() {
    $start_number = get_option('cn_start_number', 10001);
    $prefix = get_option('cn_prefix', '');
    $suffix = get_option('cn_suffix', '');
    $auto_generate = get_option('cn_auto_generate', 'yes');
    $show_in_account = get_option('cn_show_in_account', 'yes');
    $only_registration = get_option('cn_only_registration', 'no');

    // Get current highest customer number
    global $wpdb;
    $highest = $wpdb->get_var("
        SELECT MAX(CAST(meta_value AS UNSIGNED))
        FROM {$wpdb->usermeta}
        WHERE meta_key = 'customer_number'
        AND meta_value REGEXP '^[0-9]+$'
    ");

    // Count customers with/without numbers
    $total_customers = $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->users} u
        INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
        WHERE um.meta_key = 'wpc1a_capabilities' AND um.meta_value LIKE '%customer%'
    ");

    $customers_with_number = $wpdb->get_var("
        SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
        INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
        INNER JOIN {$wpdb->usermeta} cn ON u.ID = cn.user_id
        WHERE um.meta_key = 'wpc1a_capabilities' AND um.meta_value LIKE '%customer%'
        AND cn.meta_key = 'customer_number' AND cn.meta_value != ''
    ");

    $customers_without = $total_customers - $customers_with_number;
    $auto_checked = $auto_generate === 'yes' ? ' checked' : '';
    $account_checked = $show_in_account === 'yes' ? ' checked' : '';
    $only_reg_checked = $only_registration === 'yes' ? ' checked' : '';

    echo '<div class="wrap">';
    echo '<h1>Kundennummer Einstellungen</h1>';

    // Statistics box
    echo '<div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin: 20px 0; border-radius: 4px;">';
    echo '<h3 style="margin-top: 0;">Statistiken</h3>';
    echo '<table class="widefat" style="max-width: 400px;">';
    echo '<tr><td><strong>Höchste vergebene Nummer:</strong></td><td>' . ($highest ?: 'Keine') . '</td></tr>';
    echo '<tr><td><strong>Kunden gesamt:</strong></td><td>' . $total_customers . '</td></tr>';
    echo '<tr><td><strong>Kunden mit Nummer:</strong></td><td>' . $customers_with_number . '</td></tr>';
    echo '<tr><td><strong>Kunden ohne Nummer:</strong></td><td>' . $customers_without . '</td></tr>';
    echo '</table>';
    echo '</div>';

    // Settings form
    echo '<form method="post" action="options.php">';
    settings_fields('customer_number_settings');

    echo '<table class="form-table">';

    // Start number
    echo '<tr>';
    echo '<th scope="row"><label for="cn_start_number">Nächste Nummer</label></th>';
    echo '<td>';
    echo '<input type="number" id="cn_start_number" name="cn_start_number" value="' . esc_attr($start_number) . '" min="1" class="regular-text">';
    echo '<p class="description">Diese Nummer wird dem nächsten neuen Kunden zugewiesen.</p>';
    if ($highest && $start_number <= $highest) {
        echo '<p style="color: #d63638; margin-top: 5px;"><strong>⚠️ Warnung:</strong> Diese Nummer ist niedriger als die höchste vergebene (' . $highest . '). Duplikate sind möglich!</p>';
    } elseif ($highest) {
        echo '<p style="color: #00a32a; margin-top: 5px;"><strong>✓</strong> Höchste vergebene: ' . $highest . '</p>';
    }
    echo '</td></tr>';

    // Prefix
    echo '<tr>';
    echo '<th scope="row"><label for="cn_prefix">Präfix</label></th>';
    echo '<td>';
    echo '<input type="text" id="cn_prefix" name="cn_prefix" value="' . esc_attr($prefix) . '" class="regular-text" placeholder="z.B. KD-">';
    echo '<p class="description">Optional: Text vor der Nummer (z.B. "KD-" für KD-10001)</p>';
    echo '</td></tr>';

    // Suffix
    echo '<tr>';
    echo '<th scope="row"><label for="cn_suffix">Suffix</label></th>';
    echo '<td>';
    echo '<input type="text" id="cn_suffix" name="cn_suffix" value="' . esc_attr($suffix) . '" class="regular-text" placeholder="z.B. -ES">';
    echo '<p class="description">Optional: Text nach der Nummer (z.B. "-ES" für 10001-ES)</p>';
    echo '</td></tr>';

    // Preview
    echo '<tr>';
    echo '<th scope="row">Vorschau</th>';
    echo '<td><code style="font-size: 16px; padding: 5px 10px; background: #f0f0f1;">' . esc_html($prefix . $start_number . $suffix) . '</code></td>';
    echo '</tr>';

    // Auto generate
    echo '<tr>';
    echo '<th scope="row">Automatische Vergabe</th>';
    echo '<td>';
    echo '<label><input type="checkbox" name="cn_auto_generate" value="yes"' . $auto_checked . '> Kundennummer automatisch vergeben</label>';
    echo '<p class="description">';
    echo '<strong>Aktiviert:</strong> Kunden erhalten automatisch eine Kundennummer.<br>';
    echo '<strong>Deaktiviert:</strong> Kundennummern werden nur manuell im Benutzerprofil vergeben.';
    echo '</p>';
    echo '</td>';
    echo '</tr>';

    // Only on registration
    echo '<tr>';
    echo '<th scope="row">Nur bei Registrierung</th>';
    echo '<td>';
    echo '<label><input type="checkbox" name="cn_only_registration" value="yes"' . $only_reg_checked . '> Nur NEU registrierte Kunden erhalten eine Nummer</label>';
    echo '<p class="description">';
    echo '<strong>Aktiviert:</strong> Kundennummer wird NUR bei Neu-Registrierung vergeben. Bestehende Kunden ohne Nummer erhalten keine automatisch.<br>';
    echo '<strong>Deaktiviert:</strong> Auch bestehende Kunden erhalten bei ihrer ersten Bestellung eine Nummer (falls noch keine vorhanden).';
    echo '</p>';
    echo '</td>';
    echo '</tr>';

    // Show in account
    echo '<tr>';
    echo '<th scope="row">Im Kundenkonto anzeigen</th>';
    echo '<td><label><input type="checkbox" name="cn_show_in_account" value="yes"' . $account_checked . '> Kundennummer im "Mein Konto" Bereich anzeigen</label></td>';
    echo '</tr>';

    echo '</table>';
    submit_button('Einstellungen speichern');
    echo '</form>';

    // Bulk assignment
    echo '<hr>';
    echo '<h2>Massenzuweisung</h2>';
    echo '<p>Kundennummern für alle bestehenden Kunden ohne Nummer vergeben:</p>';
    echo '<form method="post" action="">';
    wp_nonce_field('cn_bulk_assign');
    echo '<input type="hidden" name="cn_action" value="bulk_assign">';
    echo '<button type="submit" class="button button-secondary" onclick="return confirm(\'Wirklich allen ' . $customers_without . ' Kunden ohne Nummer eine Kundennummer zuweisen?\');">';
    echo 'Kundennummern zuweisen (' . $customers_without . ' Kunden)';
    echo '</button>';
    echo '</form>';
    echo '</div>';
}

/**
 * Handle bulk assignment
 */
add_action('admin_init', function() {
    if (isset($_POST['cn_action']) && $_POST['cn_action'] === 'bulk_assign') {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'cn_bulk_assign')) {
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        global $wpdb;

        // Get all customers without a customer number
        $customers = $wpdb->get_col("
            SELECT DISTINCT u.ID
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            LEFT JOIN {$wpdb->usermeta} cn ON u.ID = cn.user_id AND cn.meta_key = 'customer_number'
            WHERE um.meta_key = 'wpc1a_capabilities'
            AND um.meta_value LIKE '%customer%'
            AND (cn.meta_value IS NULL OR cn.meta_value = '')
            ORDER BY u.ID ASC
        ");

        $count = 0;
        foreach ($customers as $user_id) {
            $number = generate_customer_number();
            if ($number) {
                update_user_meta($user_id, 'customer_number', $number);
                $count++;
            }
        }

        add_action('admin_notices', function() use ($count) {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 sprintf('%d Kundennummern wurden erfolgreich vergeben.', $count) .
                 '</p></div>';
        });
    }
});

// ============================================
// 2. CUSTOMER NUMBER GENERATION
// ============================================

/**
 * Generate new customer number
 * Uses the configured start number directly (admin can set any number they want)
 */
function generate_customer_number() {
    $start_number = get_option('cn_start_number', 10001);
    $prefix = get_option('cn_prefix', '');
    $suffix = get_option('cn_suffix', '');

    // Use the start number directly (admin controls this completely)
    $next_number = $start_number;

    // Update start number for next time (increment by 1)
    update_option('cn_start_number', $next_number + 1);

    return $prefix . $next_number . $suffix;
}

/**
 * Auto-assign customer number on registration
 */
add_action('user_register', function($user_id) {
    if (get_option('cn_auto_generate', 'yes') !== 'yes') {
        return;
    }

    // Check if user is/will be a customer
    $user = get_user_by('id', $user_id);
    if (!$user) return;

    // Only for customers or users without specific roles (new registrations)
    $roles = $user->roles;
    if (!empty($roles) && !in_array('customer', $roles) && !in_array('subscriber', $roles)) {
        return;
    }

    // Check if already has a number
    $existing = get_user_meta($user_id, 'customer_number', true);
    if (!empty($existing)) {
        return;
    }

    // Generate and save
    $number = generate_customer_number();
    update_user_meta($user_id, 'customer_number', $number);
}, 10, 1);

/**
 * Also assign on first order if missing
 */
add_action('woocommerce_checkout_order_processed', function($order_id, $posted_data, $order) {
    $user_id = $order->get_customer_id();
    if (!$user_id) return; // Guest order - no customer number

    $existing = get_user_meta($user_id, 'customer_number', true);
    if (!empty($existing)) {
        // Save existing customer number to order meta
        $order->update_meta_data('_customer_number', $existing);
        $order->save();
        return;
    }

    // No existing customer number - should we auto-generate?
    if (get_option('cn_auto_generate', 'yes') !== 'yes') {
        return; // Auto-generate is disabled
    }

    // Check if we should only assign on registration (not on first order)
    if (get_option('cn_only_registration', 'no') === 'yes') {
        return; // Only new registrations get numbers, not existing users at checkout
    }

    // Generate and assign customer number
    $number = generate_customer_number();
    update_user_meta($user_id, 'customer_number', $number);
    $order->update_meta_data('_customer_number', $number);
    $order->save();
}, 10, 3);

// ============================================
// 3. USER PROFILE FIELDS
// ============================================

/**
 * Add customer number field to user profile (admin)
 */
add_action('show_user_profile', 'add_customer_number_profile_field');
add_action('edit_user_profile', 'add_customer_number_profile_field');

function add_customer_number_profile_field($user) {
    $customer_number = get_user_meta($user->ID, 'customer_number', true);

    echo '<h3>Kundennummer</h3>';
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th><label for="customer_number">Kundennummer</label></th>';
    echo '<td>';
    echo '<input type="text" name="customer_number" id="customer_number" value="' . esc_attr($customer_number) . '" class="regular-text">';
    echo '<p class="description">Die Kundennummer erscheint auf Rechnungen und Lieferscheinen.';
    if (empty($customer_number) && get_option('cn_auto_generate', 'yes') === 'yes') {
        $new_number = generate_customer_number();
        echo '<br><a href="#" class="button button-small" onclick="document.getElementById(\'customer_number\').value=\'' . esc_attr($new_number) . '\'; return false;">Automatisch generieren</a>';
    }
    echo '</p>';
    echo '</td>';
    echo '</tr>';
    echo '</table>';
}

/**
 * Save customer number field
 */
add_action('personal_options_update', 'save_customer_number_profile_field');
add_action('edit_user_profile_update', 'save_customer_number_profile_field');

function save_customer_number_profile_field($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return;
    }

    if (isset($_POST['customer_number'])) {
        update_user_meta($user_id, 'customer_number', sanitize_text_field($_POST['customer_number']));
    }
}

// ============================================
// 4. DISPLAY IN MY ACCOUNT
// ============================================

/**
 * Show customer number in My Account dashboard
 */
add_action('woocommerce_account_dashboard', function() {
    if (get_option('cn_show_in_account', 'yes') !== 'yes') {
        return;
    }

    $user_id = get_current_user_id();
    $customer_number = get_user_meta($user_id, 'customer_number', true);

    if (!empty($customer_number)) {
        echo '<p style="margin: 15px 0; padding: 15px; background: #f8f8f8; border-left: 4px solid #2271b1; border-radius: 4px;">';
        echo '<strong>Ihre Kundennummer:</strong> ' . esc_html($customer_number);
        echo '</p>';
    }
}, 5);

// ============================================
// 5. WCPDF INTEGRATION (PDF Invoices & Packing Slips)
// ============================================

/**
 * Helper function to get customer number for an order
 * IMPORTANT: Always use current user's customer number (not historical order meta)
 * This ensures profile changes are reflected immediately in orders list
 */
function cn_get_customer_number_for_order($order) {
    if (!$order) return '';

    // ALWAYS get from user first (so profile changes show immediately)
    $user_id = $order->get_customer_id();
    if ($user_id) {
        $customer_number = get_user_meta($user_id, 'customer_number', true);
        if (!empty($customer_number)) {
            return $customer_number;
        }
    }

    // Fallback: Get from order meta (for historical records or guest orders converted to accounts)
    $customer_number = $order->get_meta('_customer_number');

    return $customer_number ?: '';
}

/**
 * Output customer number HTML for WCPDF
 * Uses static tracking to prevent duplicate output
 */
function cn_output_wcpdf_customer_number($order, $format = 'div') {
    static $shown_orders = [];

    $order_id = $order->get_id();

    // Prevent duplicate output for the same order
    if (isset($shown_orders[$order_id])) {
        return;
    }

    $customer_number = cn_get_customer_number_for_order($order);

    if (!empty($customer_number)) {
        $shown_orders[$order_id] = true;

        if ($format === 'table') {
            echo '<tr class="customer-number">';
            echo '<th>Kundennummer:</th>';
            echo '<td>' . esc_html($customer_number) . '</td>';
            echo '</tr>';
        } else {
            echo '<div class="customer-number" style="margin-top: 10px; margin-bottom: 10px;">';
            echo '<strong>Kundennummer:</strong> ' . esc_html($customer_number);
            echo '</div>';
        }
    }
}

/**
 * Add customer number after billing address (for invoices)
 */
add_action('wpo_wcpdf_after_billing_address', function($document_type, $order) {
    cn_output_wcpdf_customer_number($order);
}, 10, 2);

/**
 * Add customer number after shipping address (for packing slips/Lieferscheine)
 * The static tracking in cn_output_wcpdf_customer_number prevents duplicates
 */
add_action('wpo_wcpdf_after_shipping_address', function($document_type, $order) {
    cn_output_wcpdf_customer_number($order);
}, 10, 2);

/**
 * Add customer number as WCPDF placeholder
 */
add_filter('wpo_wcpdf_templates_replace_document_data_shortcodes', function($replacement, $shortcode, $document, $order) {
    if ($shortcode === 'customer_number' || $shortcode === 'kundennummer') {
        return cn_get_customer_number_for_order($order) ?: '';
    }
    return $replacement;
}, 10, 4);

// ============================================
// 6. GERMANIZED / STOREABILL INTEGRATION
// ============================================

/**
 * Add customer number to StoreaBill documents
 */
add_filter('storeabill_document_shortcodes', function($shortcodes, $document) {
    if (!$document) return $shortcodes;

    $order = null;
    if (method_exists($document, 'get_order')) {
        $order = $document->get_order();
    } elseif (method_exists($document, 'get_reference')) {
        $order = $document->get_reference();
    }

    if (!$order || !is_a($order, 'WC_Order')) {
        $shortcodes['customer_number'] = '';
        $shortcodes['kundennummer'] = '';
        return $shortcodes;
    }

    $customer_number = cn_get_customer_number_for_order($order) ?: '';
    $shortcodes['customer_number'] = $customer_number;
    $shortcodes['kundennummer'] = $customer_number;

    return $shortcodes;
}, 10, 2);

/**
 * Register custom StoreaBill placeholder
 */
add_filter('storeabill_get_document_placeholders', function($placeholders, $document_type) {
    $placeholders['customer_number'] = [
        'title' => 'Kundennummer',
        'description' => 'Die Kundennummer des Kunden'
    ];

    return $placeholders;
}, 10, 2);

/**
 * Add to Germanized invoice template data
 */
add_filter('woocommerce_gzd_invoice_template_data', function($data, $invoice) {
    if (!isset($data['order']) || !$data['order']) {
        return $data;
    }

    $data['customer_number'] = cn_get_customer_number_for_order($data['order']) ?: '';

    return $data;
}, 10, 2);

// ============================================
// 7. ORDER ADMIN DISPLAY
// ============================================

/**
 * Show customer number in order admin
 */
add_action('woocommerce_admin_order_data_after_billing_address', function($order) {
    $customer_number = cn_get_customer_number_for_order($order);
    echo '<p><strong>Kundennummer:</strong> ' . esc_html($customer_number ?: '—') . '</p>';
});

/**
 * Add customer number column to orders list
 */
add_filter('manage_woocommerce_page_wc-orders_columns', function($columns) {
    $new_columns = [];
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        if ($key === 'order_number') {
            $new_columns['customer_number'] = 'Kundennr.';
        }
    }
    return $new_columns;
});

add_action('manage_woocommerce_page_wc-orders_custom_column', function($column, $order) {
    if ($column === 'customer_number') {
        $customer_number = cn_get_customer_number_for_order($order);
        echo esc_html($customer_number ?: '—');
    }
}, 10, 2);

// ============================================
// 8. REST API SUPPORT
// ============================================

/**
 * Add customer number to REST API response
 */
add_filter('woocommerce_rest_prepare_shop_order_object', function($response, $order, $request) {
    $data = $response->get_data();
    $data['customer_number'] = cn_get_customer_number_for_order($order) ?: '';
    $response->set_data($data);
    return $response;
}, 10, 3);

// ============================================
// 9. EMAIL INTEGRATION
// ============================================

/**
 * Add customer number to WooCommerce emails
 */
add_action('woocommerce_email_after_order_table', function($order, $sent_to_admin, $plain_text, $email) {
    $customer_number = cn_get_customer_number_for_order($order);
    if ($customer_number) {
        if ($plain_text) {
            echo "\nKundennummer: " . $customer_number . "\n";
        } else {
            echo '<p><strong>Kundennummer:</strong> ' . esc_html($customer_number) . '</p>';
        }
    }
}, 10, 4);

// ============================================
// 10. SHORTCODE FOR TEMPLATES
// ============================================

/**
 * Shortcode [customer_number] for use in Germanized templates or elsewhere
 */
add_shortcode('customer_number', function($atts) {
    // If we're in a Germanized/StoreaBill context
    if (function_exists('sab_get_current_invoice')) {
        $invoice = sab_get_current_invoice();
        if ($invoice && method_exists($invoice, 'get_order')) {
            $order = $invoice->get_order();
            if ($order) {
                $customer_number = cn_get_customer_number_for_order($order);
                if ($customer_number) {
                    return '<strong>Kundennummer:</strong> ' . esc_html($customer_number);
                }
            }
        }
    }

    // Fallback for other contexts
    global $post;
    if (isset($post->ID)) {
        $order = wc_get_order($post->ID);
        if ($order) {
            $customer_number = cn_get_customer_number_for_order($order);
            if ($customer_number) {
                return '<strong>Kundennummer:</strong> ' . esc_html($customer_number);
            }
        }
    }

    return '';
});

// ============================================
// 11. LEGACY SHOP ORDER COLUMNS (non-HPOS)
// ============================================

/**
 * Add customer number column to legacy shop_order list
 */
add_filter('manage_edit-shop_order_columns', function($columns) {
    $new_columns = [];
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        if ($key === 'order_number') {
            $new_columns['customer_number'] = 'Kundennr.';
        }
    }
    return $new_columns;
});

/**
 * Display customer number in legacy shop_order column
 */
add_action('manage_shop_order_posts_custom_column', function($column, $post_id) {
    if ($column === 'customer_number') {
        $order = wc_get_order($post_id);
        if ($order) {
            $customer_number = cn_get_customer_number_for_order($order);
            echo $customer_number ? esc_html($customer_number) : '—';
        }
    }
}, 10, 2);

/**
 * Make customer number column sortable (legacy)
 */
add_filter('manage_edit-shop_order_sortable_columns', function($columns) {
    $columns['customer_number'] = 'customer_number';
    return $columns;
});

/**
 * Make customer number column sortable (HPOS)
 */
add_filter('manage_woocommerce_page_wc-orders_sortable_columns', function($columns) {
    $columns['customer_number'] = 'customer_number';
    return $columns;
});

// ============================================
// 12. GERMANIZED STOREABILL CUSTOM FIELDS
// ============================================

/**
 * Add customer number to StoreaBill invoice custom fields
 */
add_filter('storeabill_invoice_data_custom_fields', function($data, $document) {
    if (method_exists($document, 'get_order')) {
        $order = $document->get_order();
        if ($order) {
            $customer_number = cn_get_customer_number_for_order($order);
            if ($customer_number) {
                $data['customer_number'] = $customer_number;
            }
        }
    }
    return $data;
}, 10, 2);

// End of file - no closing PHP tag