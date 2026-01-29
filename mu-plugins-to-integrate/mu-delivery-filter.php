<?php
/**
 * Plugin Name: Shipping to Delivery Filter
 * Description: Replaces "shipping costs" with "delivery costs" for English
 */

// Only apply on English site
add_filter('gettext', 'replace_shipping_with_delivery', 20, 3);
add_filter('gettext_with_context', 'replace_shipping_with_delivery_context', 20, 4);
add_filter('ngettext', 'replace_shipping_with_delivery_n', 20, 5);

function replace_shipping_with_delivery($translated, $text, $domain) {
    // Only on English
    if (function_exists('icl_get_current_language')) {
        $lang = icl_get_current_language();
        if ($lang !== 'en') {
            return $translated;
        }
    }

    // Replace shipping with delivery
    $translated = str_ireplace(
        array('shipping costs', 'Shipping costs', 'shipping cost', 'Shipping cost'),
        array('delivery costs', 'Delivery costs', 'delivery cost', 'Delivery cost'),
        $translated
    );

    return $translated;
}

function replace_shipping_with_delivery_context($translated, $text, $context, $domain) {
    if (function_exists('icl_get_current_language')) {
        $lang = icl_get_current_language();
        if ($lang !== 'en') {
            return $translated;
        }
    }

    $translated = str_ireplace(
        array('shipping costs', 'Shipping costs', 'shipping cost', 'Shipping cost'),
        array('delivery costs', 'Delivery costs', 'delivery cost', 'Delivery cost'),
        $translated
    );

    return $translated;
}

function replace_shipping_with_delivery_n($translated, $single, $plural, $number, $domain) {
    if (function_exists('icl_get_current_language')) {
        $lang = icl_get_current_language();
        if ($lang !== 'en') {
            return $translated;
        }
    }

    $translated = str_ireplace(
        array('shipping costs', 'Shipping costs', 'shipping cost', 'Shipping cost'),
        array('delivery costs', 'Delivery costs', 'delivery cost', 'Delivery cost'),
        $translated
    );

    return $translated;
}
