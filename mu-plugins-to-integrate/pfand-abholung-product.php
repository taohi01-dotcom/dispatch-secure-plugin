<?php
/**
 * Plugin Name: Pfand-Abholung Produkte
 * Description: Erstellt automatisch zwei virtuelle Pfand-Produkte mit korrekter MwSt (10% Wasser, 21% Sonstiges)
 * Version: 2.1
 * Author: Klaus Arends
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Find the correct tax class slug for a given rate percentage
 */
function find_tax_class_for_rate($target_rate) {
    global $wpdb;

    // Search for tax rate matching the target percentage
    $rate = $wpdb->get_row($wpdb->prepare(
        "SELECT tax_rate_class FROM {$wpdb->prefix}woocommerce_tax_rates
         WHERE tax_rate >= %f AND tax_rate <= %f
         LIMIT 1",
        $target_rate - 0.5,
        $target_rate + 0.5
    ));

    if ($rate) {
        error_log("Found tax class for {$target_rate}%: '" . $rate->tax_rate_class . "'");
        return $rate->tax_rate_class; // Empty string means 'standard'
    }

    error_log("No tax class found for {$target_rate}%");
    return null;
}

/**
 * Create the Pfand-Abholung products if they don't exist
 * v2.1: Automatische Erkennung der korrekten Tax Class Slugs
 */
add_action('init', 'ensure_pfand_abholung_products_exist', 20);

function ensure_pfand_abholung_products_exist() {
    if (!function_exists('WC')) {
        return;
    }

    // Only run once per day (performance)
    $last_check = get_transient('pfand_abholung_products_check_v2_1');
    if ($last_check && !isset($_GET['force_pfand_products'])) {
        return;
    }

    // Finde die korrekten Tax Class Slugs für 10% und 21%
    $tax_class_10 = find_tax_class_for_rate(10);
    $tax_class_21 = find_tax_class_for_rate(21);

    error_log("Pfand Products: Tax class for 10% = '" . ($tax_class_10 ?? 'NOT FOUND') . "'");
    error_log("Pfand Products: Tax class for 21% = '" . ($tax_class_21 ?? 'NOT FOUND') . "'");

    // Produkt 1: Pfand mit 10% MwSt (Wasser)
    $product_id_10 = wc_get_product_id_by_sku('PFAND-10');

    if (!$product_id_10) {
        $product = new WC_Product_Simple();
        $product->set_name('Pfand Wasser (10% MwSt)');
        $product->set_slug('pfand-wasser-10-mwst');
        $product->set_sku('PFAND-10');
        $product->set_regular_price('0');
        $product->set_price('0');
        $product->set_status('private');
        $product->set_catalog_visibility('hidden');
        $product->set_virtual(true);
        $product->set_sold_individually(false);
        $product->set_description('Pfand-Rückerstattung für Wasserprodukte mit 10% MwSt.');
        $product->set_short_description('Pfand Wasser - 10% MwSt');
        $product->set_tax_status('taxable');

        // Verwende die gefundene Tax Class für 10%
        if ($tax_class_10 !== null) {
            $product->set_tax_class($tax_class_10);
        } else {
            // Fallback: Versuche gängige Slugs
            $product->set_tax_class('reduced-rate');
        }

        $product_id_10 = $product->save();
        error_log('Pfand Wasser (10% MwSt) erstellt mit ID: ' . $product_id_10 . ', Tax Class: ' . $product->get_tax_class());
    } else {
        // Update existing product's tax class if needed
        $product = wc_get_product($product_id_10);
        if ($product && $tax_class_10 !== null && $product->get_tax_class() !== $tax_class_10) {
            $product->set_tax_class($tax_class_10);
            $product->save();
            error_log('Pfand Wasser Tax Class aktualisiert auf: ' . $tax_class_10);
        }
    }

    // Produkt 2: Pfand mit 21% MwSt (Sonstiges)
    $product_id_21 = wc_get_product_id_by_sku('PFAND-21');

    if (!$product_id_21) {
        $product = new WC_Product_Simple();
        $product->set_name('Pfand Sonstiges (21% MwSt)');
        $product->set_slug('pfand-sonstiges-21-mwst');
        $product->set_sku('PFAND-21');
        $product->set_regular_price('0');
        $product->set_price('0');
        $product->set_status('private');
        $product->set_catalog_visibility('hidden');
        $product->set_virtual(true);
        $product->set_sold_individually(false);
        $product->set_description('Pfand-Rückerstattung für sonstige Produkte mit 21% MwSt.');
        $product->set_short_description('Pfand Sonstiges - 21% MwSt');
        $product->set_tax_status('taxable');

        // Verwende die gefundene Tax Class für 21% (Standard = '')
        if ($tax_class_21 !== null) {
            $product->set_tax_class($tax_class_21);
        } else {
            $product->set_tax_class(''); // Standard
        }

        $product_id_21 = $product->save();
        error_log('Pfand Sonstiges (21% MwSt) erstellt mit ID: ' . $product_id_21 . ', Tax Class: ' . $product->get_tax_class());
    } else {
        // Update existing product's tax class if needed
        $product = wc_get_product($product_id_21);
        if ($product && $tax_class_21 !== null && $product->get_tax_class() !== $tax_class_21) {
            $product->set_tax_class($tax_class_21);
            $product->save();
            error_log('Pfand Sonstiges Tax Class aktualisiert auf: ' . $tax_class_21);
        }
    }

    // Altes Produkt deaktivieren falls vorhanden
    $old_product_id = wc_get_product_id_by_sku('PFAND-ABHOLUNG');
    if ($old_product_id) {
        $old_product = wc_get_product($old_product_id);
        if ($old_product && $old_product->get_status() !== 'draft') {
            $old_product->set_status('draft');
            $old_product->set_name('Pfand-Abholung (VERALTET)');
            $old_product->save();
            error_log('Altes Pfand-Abholung Produkt deaktiviert');
        }
    }

    set_transient('pfand_abholung_products_check_v2_1', true, DAY_IN_SECONDS);
}

/**
 * Force recreation/update of products
 */
add_action('admin_init', 'pfand_abholung_force_check');

function pfand_abholung_force_check() {
    if (isset($_GET['force_pfand_products']) && current_user_can('manage_woocommerce')) {
        delete_transient('pfand_abholung_products_check_v2_1');

        // Delete existing products to force recreation with correct tax class
        $pfand_10 = wc_get_product_id_by_sku('PFAND-10');
        $pfand_21 = wc_get_product_id_by_sku('PFAND-21');

        if ($pfand_10) {
            wp_delete_post($pfand_10, true);
            error_log('PFAND-10 gelöscht für Neuanlage');
        }
        if ($pfand_21) {
            wp_delete_post($pfand_21, true);
            error_log('PFAND-21 gelöscht für Neuanlage');
        }

        // Recreate products
        ensure_pfand_abholung_products_exist();

        add_action('admin_notices', function() {
            global $wpdb;

            // Show found tax rates
            $rates = $wpdb->get_results("SELECT tax_rate_class, tax_rate, tax_rate_name FROM {$wpdb->prefix}woocommerce_tax_rates ORDER BY tax_rate");

            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>Pfand-Produkte wurden neu erstellt!</strong></p>';
            echo '<p>Gefundene Steuersätze:</p><ul>';
            foreach ($rates as $rate) {
                echo '<li>' . esc_html($rate->tax_rate_name) . ': ' . esc_html($rate->tax_rate) . '% (Class: "' . esc_html($rate->tax_rate_class) . '")</li>';
            }
            echo '</ul>';

            $p10 = wc_get_product(wc_get_product_id_by_sku('PFAND-10'));
            $p21 = wc_get_product(wc_get_product_id_by_sku('PFAND-21'));

            if ($p10) {
                echo '<p>PFAND-10 Tax Class: "' . esc_html($p10->get_tax_class()) . '"</p>';
            }
            if ($p21) {
                echo '<p>PFAND-21 Tax Class: "' . esc_html($p21->get_tax_class()) . '"</p>';
            }
            echo '</div>';
        });
    }
}

/**
 * Prevent customers from purchasing these products directly
 */
add_filter('woocommerce_is_purchasable', 'pfand_produkte_not_purchasable', 10, 2);

function pfand_produkte_not_purchasable($purchasable, $product) {
    $pfand_skus = ['PFAND-10', 'PFAND-21', 'PFAND-ABHOLUNG'];

    if (in_array($product->get_sku(), $pfand_skus)) {
        if (!current_user_can('manage_woocommerce')) {
            return false;
        }
    }
    return $purchasable;
}

/**
 * Helper function to get Pfand product IDs
 */
function get_pfand_product_ids() {
    return [
        '10' => wc_get_product_id_by_sku('PFAND-10'),
        '21' => wc_get_product_id_by_sku('PFAND-21'),
    ];
}
