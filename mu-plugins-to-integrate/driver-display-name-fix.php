<?php
/**
 * Plugin Name: Driver Display Name Fix
 * Description: Automatically sets display_name to "Vorname Nachname" for Lieferfahrer
 * Version: 1.0
 */

// Fix display_name when a user is created or updated
add_action('user_register', 'fix_driver_display_name_on_register', 10, 1);
add_action('profile_update', 'fix_driver_display_name_on_update', 10, 2);

/**
 * Fix display name when user is registered
 */
function fix_driver_display_name_on_register($user_id) {
    // Small delay to ensure meta is saved
    add_action('shutdown', function() use ($user_id) {
        fix_driver_display_name($user_id);
    });
}

/**
 * Fix display name when user profile is updated
 */
function fix_driver_display_name_on_update($user_id, $old_user_data) {
    fix_driver_display_name($user_id);
}

/**
 * Main function to fix display name
 */
function fix_driver_display_name($user_id) {
    $user = get_user_by('ID', $user_id);

    if (!$user || !in_array('lieferfahrer', $user->roles)) {
        return; // Only fix Lieferfahrer
    }

    $first_name = get_user_meta($user_id, 'first_name', true);
    $last_name = get_user_meta($user_id, 'last_name', true);

    // Build expected display name
    $expected_display = trim($first_name . ' ' . $last_name);

    // If no first/last name, don't change anything
    if (empty($expected_display)) {
        return;
    }

    // Only update if different
    if ($user->display_name !== $expected_display) {
        wp_update_user([
            'ID' => $user_id,
            'display_name' => $expected_display
        ]);
    }
}

/**
 * Also fix when user role is changed to lieferfahrer
 */
add_action('set_user_role', 'fix_driver_display_name_on_role_change', 10, 3);

function fix_driver_display_name_on_role_change($user_id, $role, $old_roles) {
    if ($role === 'lieferfahrer') {
        fix_driver_display_name($user_id);
    }
}
