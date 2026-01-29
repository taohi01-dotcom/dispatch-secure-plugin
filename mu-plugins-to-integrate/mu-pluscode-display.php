<?php
/**
 * Display Plus Code in My Account Address Section
 * Shows the customer's Plus Code under their shipping address
 *
 * @package WooCommerce_PlusCode_Display
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register strings with WPML for translation
 */
add_action('init', 'register_pluscode_strings_wpml');

function register_pluscode_strings_wpml() {
    if (function_exists('icl_register_string')) {
        icl_register_string('pluscode-display', 'Plus Code', 'Plus Code');
        icl_register_string('pluscode-display', 'Your Plus Code for deliveries', 'Ihr Plus Code für Lieferungen');
        icl_register_string('pluscode-display', 'This code helps us find your delivery address precisely.', 'Dieser Code hilft uns, Ihre Lieferadresse präzise zu finden.');
        icl_register_string('pluscode-display', 'No Plus Code registered', 'Kein Plus Code hinterlegt');
        icl_register_string('pluscode-display', 'Our team will determine a Plus Code for you with your next order.', 'Unser Team wird bei Ihrer nächsten Bestellung einen Plus Code für Sie ermitteln.');
        icl_register_string('pluscode-display', 'With this code we find your address precisely - even without an exact house number.', 'Mit diesem Code finden wir Ihre Adresse präzise - auch ohne genaue Hausnummer.');
    }
}

/**
 * Helper function to get translated string
 */
function pluscode_translate($name, $default) {
    if (function_exists('icl_t')) {
        return icl_t('pluscode-display', $name, $default);
    }
    return $default;
}

/**
 * Add Plus Code to the formatted shipping address in My Account
 */
add_filter('woocommerce_my_account_my_address_formatted_address', 'add_pluscode_to_my_account_address', 10, 3);

function add_pluscode_to_my_account_address($address, $customer_id, $address_type) {
    // Only add to shipping address
    if ($address_type !== 'shipping') {
        return $address;
    }

    // Get the Plus Code from user meta
    $plus_code = get_user_meta($customer_id, 'plus_code', true);

    if (!empty($plus_code)) {
        $address['plus_code'] = $plus_code;
    }

    return $address;
}

/**
 * Add Plus Code replacement to address format
 */
add_filter('woocommerce_localisation_address_formats', 'add_pluscode_address_format', 10, 1);

function add_pluscode_address_format($formats) {
    foreach ($formats as $country => $format) {
        // Add Plus Code at the end of the address format
        $formats[$country] = $format . "\n{plus_code}";
    }
    return $formats;
}

/**
 * Add Plus Code replacement value
 */
add_filter('woocommerce_formatted_address_replacements', 'add_pluscode_replacement', 10, 2);

function add_pluscode_replacement($replacements, $args) {
    if (!empty($args['plus_code'])) {
        $label = __('Plus Code', 'woocommerce');
        $replacements['{plus_code}'] = '<span class="plus-code-display" style="display: inline-block; margin-top: 8px; padding: 4px 8px; background: #e8f4f8; border: 1px solid #00a8e8; border-radius: 4px; font-family: monospace; font-size: 13px; color: #005f73;"><strong>' . esc_html($label) . ':</strong> ' . esc_html($args['plus_code']) . '</span>';
    } else {
        $replacements['{plus_code}'] = '';
    }
    return $replacements;
}

/**
 * Display Plus Code prominently in My Account Addresses page
 * This adds a visible info box when viewing addresses
 */
add_action('woocommerce_after_edit_address_form_shipping', 'show_pluscode_info_in_edit_form');

function show_pluscode_info_in_edit_form() {
    $customer_id = get_current_user_id();
    $plus_code = get_user_meta($customer_id, 'plus_code', true);

    if (!empty($plus_code)) {
        ?>
        <div class="plus-code-info-box" style="margin: 20px 0; padding: 15px; background: linear-gradient(135deg, #e8f4f8 0%, #d4edda 100%); border: 2px solid #28a745; border-radius: 8px;">
            <h4 style="margin: 0 0 10px 0; color: #155724; font-size: 16px;">
                <span style="font-size: 20px;">📍</span> <?php echo esc_html(pluscode_translate('Your Plus Code for deliveries', 'Ihr Plus Code für Lieferungen')); ?>
            </h4>
            <p style="margin: 0 0 10px 0; font-family: monospace; font-size: 18px; font-weight: bold; color: #005f73; background: white; padding: 10px; border-radius: 4px; text-align: center;">
                <?php echo esc_html($plus_code); ?>
            </p>
            <p style="margin: 0; font-size: 13px; color: #666;">
                <?php echo esc_html(pluscode_translate('This code helps us find your delivery address precisely.', 'Dieser Code hilft uns, Ihre Lieferadresse präzise zu finden.')); ?>
            </p>
        </div>
        <?php
    } else {
        ?>
        <div class="plus-code-info-box" style="margin: 20px 0; padding: 15px; background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px;">
            <h4 style="margin: 0 0 10px 0; color: #856404; font-size: 16px;">
                <span style="font-size: 20px;">📍</span> <?php echo esc_html(pluscode_translate('No Plus Code registered', 'Kein Plus Code hinterlegt')); ?>
            </h4>
            <p style="margin: 0; font-size: 13px; color: #856404;">
                <?php echo esc_html(pluscode_translate('Our team will determine a Plus Code for you with your next order.', 'Unser Team wird bei Ihrer nächsten Bestellung einen Plus Code für Sie ermitteln.')); ?>
            </p>
        </div>
        <?php
    }
}

/**
 * Also show Plus Code on the main addresses overview page
 */
add_action('woocommerce_my_account_after_my_address', 'show_pluscode_on_address_overview');

function show_pluscode_on_address_overview() {
    $customer_id = get_current_user_id();
    $plus_code = get_user_meta($customer_id, 'plus_code', true);

    if (!empty($plus_code)) {
        ?>
        <div class="plus-code-overview" style="margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #e8f4f8 0%, #d4edda 100%); border: 2px solid #28a745; border-radius: 10px; text-align: center;">
            <h3 style="margin: 0 0 15px 0; color: #155724; font-size: 18px;">
                <span style="font-size: 24px;">📍</span> <?php echo esc_html(pluscode_translate('Your Plus Code for deliveries', 'Ihr Plus Code für Lieferungen')); ?>
            </h3>
            <p style="margin: 0 0 15px 0; font-family: monospace; font-size: 22px; font-weight: bold; color: #005f73; background: white; padding: 12px 20px; border-radius: 6px; display: inline-block; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <?php echo esc_html($plus_code); ?>
            </p>
            <p style="margin: 0; font-size: 13px; color: #666;">
                <?php echo esc_html(pluscode_translate('With this code we find your address precisely - even without an exact house number.', 'Mit diesem Code finden wir Ihre Adresse präzise - auch ohne genaue Hausnummer.')); ?>
            </p>
        </div>
        <?php
    }
}
