<?php
/**
 * Driver Dashboard Template
 * Rendered by renderDriverDashboard()
 * 
 * @package Dispatch_Dashboard
 * @since 2.9.60
 * 
 * Variables available:
 * - $this: DispatchDashboard instance
 * - $current_user: WP_User object
 */

if (!defined('ABSPATH')) {
    exit;
}
    private function renderDriverDashboard(): void {
        // Start output buffering to prevent any unexpected PHP output before HTML
        ob_start();
        
        try {
            // Check if user is logged in
            $current_user = null;
            
            if (is_user_logged_in()) {
                $current_user = wp_get_current_user();
            } else {
                // Check for Safari-compatible driver cookie as fallback
                if (isset($_COOKIE['driver_logged_in']) || isset($_COOKIE['driver_user_id'])) {
                    // Try to get user from session or cookie
                    $user_id = isset($_COOKIE['driver_user_id']) ? intval($_COOKIE['driver_user_id']) : null;
                    
                    // For Safari compatibility, also check WordPress auth cookie
                    if (!$user_id) {
                        $cookie_elements = wp_parse_auth_cookie();
                        if ($cookie_elements) {
                            $user_id = $cookie_elements['username'] ? 
                                get_user_by('login', $cookie_elements['username'])->ID : null;
                        }
                    }
                    
                    if ($user_id) {
                        $current_user = get_userdata($user_id);
                        if ($current_user && (in_array('lieferfahrer', $current_user->roles) || in_array('driver', $current_user->roles))) {
                            wp_set_current_user($user_id);
                        } else {
                            $current_user = null;
                        }
                    }
                }
            }
            
            if (!$current_user) {
                ob_end_clean();
                wp_redirect(home_url('/fahrer-login/'));
                exit;
            }
            
            // Check if user is a driver (supports both 'lieferfahrer' and 'driver' roles)
            $is_driver = in_array('lieferfahrer', $current_user->roles) || in_array('driver', $current_user->roles);
            if (!$is_driver) {
                ob_end_clean();
                wp_die('Zugriff verweigert. Nur für Lieferfahrer.');
            }

            // Check if driver is online
            $driver_status = get_user_meta($current_user->ID, 'driver_online_status', true);
            // If no status is set, consider driver as offline (they need to explicitly go online)
            $is_online = ($driver_status === 'online');

        // Get driver's orders only if online
        if ($is_online) {
            // Get driver's CURRENT orders for "Bestellungen"
            $current_orders = $this->getDriverOrders($current_user->ID);
            // Get driver's SCHEDULED orders for "Warten"
            $scheduled_orders = $this->getDriverScheduledOrders($current_user->ID);
        } else {
            // Empty arrays when offline - orders will be blocked
            $current_orders = [];
            $scheduled_orders = [];
        }

        // Default to current orders for initial display
        $orders = $current_orders;
        
        // Get driver initials for avatar
        $name_parts = explode(' ', $current_user->display_name);
        $initials = '';
        foreach($name_parts as $part) {
            if(!empty($part)) {
                $initials .= strtoupper(substr($part, 0, 1));
            }
        }
        if(empty($initials)) {
            $initials = strtoupper(substr($current_user->display_name, 0, 2));
        }
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>Fahrer Dashboard - <?php echo esc_html($current_user->display_name); ?></title>
            <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
            <meta http-equiv="Pragma" content="no-cache">
            <meta http-equiv="Expires" content="0">

            <!-- PWA Meta Tags -->
            <meta name="theme-color" content="#ffffff">
            <meta name="apple-mobile-web-app-capable" content="yes">
            <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
            <meta name="apple-mobile-web-app-title" content="<?php echo esc_attr(get_option('dispatch_default_depot_name', 'Dispatch')); ?>">
            <meta name="mobile-web-app-capable" content="yes">
            <meta name="format-detection" content="telephone=no">

            <!-- jQuery -->
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

            <!-- PWA Manifest -->
            <link rel="manifest" href="<?php echo plugin_dir_url(__FILE__); ?>pwa/manifest.php">

            <!-- Firebase Messaging Service Worker Script -->
            <!-- DISABLED: Using firebase-messaging-sw.js registered by FCM initialization instead -->
            <!-- This prevents conflicts between PWA SW and Firebase SW -->
            <!--
            <script>
                if ('serviceWorker' in navigator) {
                    window.addEventListener('load', function() {
                        navigator.serviceWorker.register('<?php echo plugin_dir_url(__FILE__); ?>pwa/service-worker.js')
                            .then(function(registration) {
                                console.log('Service Worker registered with scope:', registration.scope);
                            })
                            .catch(function(error) {
                                console.error('Service Worker registration failed:', error);
                            });
                    });
                }
            </script>
            -->

            <!-- iOS Icons -->
            <link rel="apple-touch-icon" href="<?php echo plugin_dir_url(__FILE__); ?>pwa/icons/apple-touch-icon.png">
            <link rel="apple-touch-icon" sizes="152x152" href="<?php echo plugin_dir_url(__FILE__); ?>pwa/icons/icon-152x152.png">
            <link rel="apple-touch-icon" sizes="180x180" href="<?php echo plugin_dir_url(__FILE__); ?>pwa/icons/apple-touch-icon.png">
            <link rel="apple-touch-icon" sizes="167x167" href="<?php echo plugin_dir_url(__FILE__); ?>pwa/icons/apple-touch-icon.png">

            <!-- Favicon -->
            <link rel="icon" type="image/png" sizes="32x32" href="<?php echo plugin_dir_url(__FILE__); ?>pwa/icons/favicon.png">
            <link rel="icon" type="image/png" sizes="96x96" href="<?php echo plugin_dir_url(__FILE__); ?>pwa/icons/icon-96x96.png">

            <!-- Leaflet CSS -->
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>

            <!-- Leaflet JS -->
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

            <!-- Firebase SDK - DISABLED, using Web Push (VAPID) instead -->
            <!--
            <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-app-compat.js"></script>
            <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-messaging-compat.js"></script>

            <script>
                // Firebase Configuration (will be populated from WordPress options)
                const firebaseConfig = {
                    apiKey: "<?php echo esc_js($this->getSettings()['dispatch_firebase_api_key']); ?>",
                    authDomain: "<?php echo esc_js($this->getSettings()['dispatch_firebase_auth_domain']); ?>",
                    projectId: "<?php echo esc_js($this->getSettings()['dispatch_firebase_project_id']); ?>",
                    storageBucket: "<?php echo esc_js($this->getSettings()['dispatch_firebase_storage_bucket']); ?>",
                    messagingSenderId: "<?php echo esc_js($this->getSettings()['dispatch_firebase_messaging_sender_id']); ?>",
                    appId: "<?php echo esc_js($this->getSettings()['dispatch_firebase_app_id']); ?>"
                };

                // Initialize Firebase globally
                let messaging = null;
                let fcmActive = false; // Flag to disable polling notifications when FCM is active

                // Check if Firebase config is valid
                if (firebaseConfig.apiKey && firebaseConfig.projectId && firebaseConfig.messagingSenderId) {
                    console.log('Firebase Config found, initializing...');
                    console.log('Project ID:', firebaseConfig.projectId);

                    try {
                        // STEP 1: Unregister old PWA service workers that might conflict
                        if ('serviceWorker' in navigator) {
                            navigator.serviceWorker.getRegistrations().then((registrations) => {
                                registrations.forEach((registration) => {
                                    // Unregister any SW that's not the Firebase SW
                                    if (!registration.active || !registration.active.scriptURL.includes('firebase-messaging-sw.js')) {
                                        console.log('🗑️ Unregistering old service worker:', registration.scope);
                                        registration.unregister();
                                    }
                                });
                            });
                        }

                        // STEP 2: Initialize Firebase
                        firebase.initializeApp(firebaseConfig);
                        messaging = firebase.messaging();
                        console.log('Firebase Messaging initialized successfully');

                        // STEP 3: Register Firebase Service Worker for FCM
                        // Using firebase-messaging-sw.js which handles FCM background messages
                        if ('serviceWorker' in navigator) {
                            navigator.serviceWorker.register('/firebase-messaging-sw.js', {
                                scope: '/'
                            })
                                .then((registration) => {
                                    console.log('✅ FCM Service Worker registered successfully:', registration.scope);
                                    return registration;
                                })
                                .catch((err) => {
                                    console.error('❌ Service Worker registration for FCM failed:', err);
                                });
                        }
                    } catch (error) {
                        console.error('Firebase initialization error:', error);
                    }
                } else {
                    console.warn('Firebase not configured. FCM will not be available.');
                    console.log('Config values:', firebaseConfig);
                }

                // Request permission and get token
                async function requestPermissionAndGetToken() {
                    try {
                        // Check if Firebase is initialized
                        if (!messaging) {
                            console.warn('Firebase Messaging not initialized, cannot get FCM token');
                            return;
                        }

                        // Check if Notification API is available (not available in iOS Safari, only in installed PWAs)
                        if (typeof Notification === 'undefined') {
                            console.warn('Notification API not available. On iOS, please install the app to your home screen.');

                            // Show installation prompt for iOS users
                            if (/iPad|iPhone|iPod/.test(navigator.userAgent) && !window.navigator.standalone) {
                                showNotificationToast('📱 Push-Benachrichtigungen: Bitte installieren Sie die App auf Ihrem Homescreen', 'warning', 10000);
                            }
                            return;
                        }

                        // Check current permission status first
                        if (Notification.permission === 'granted') {
                            console.log('Notification permission already granted, getting FCM token...');

                            // IMPORTANT: Get the firebase-messaging-sw.js registration specifically
                            // Don't use navigator.serviceWorker.ready as it might return the wrong SW
                            let registration = await navigator.serviceWorker.getRegistration('/firebase-messaging-sw.js');

                            if (!registration) {
                                console.log('Firebase SW not registered, registering now...');
                                registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js', {
                                    scope: '/'
                                });
                                await navigator.serviceWorker.ready;
                                console.log('✅ Firebase SW registered:', registration.scope);
                            } else {
                                console.log('✅ Firebase SW already registered:', registration.scope);
                            }

                            // Get FCM token with explicit SW registration
                            console.log('Requesting FCM token with Firebase SW...');
                            const token = await messaging.getToken({
                                serviceWorkerRegistration: registration,
                                vapidKey: firebaseConfig.vapidKey || undefined
                            });

                            if (token) {
                                console.log('✅ FCM Token obtained:', token.substring(0, 20) + '...');
                                // Send token to your WordPress backend
                                sendTokenToServer(token);
                            } else {
                                console.log('❌ No FCM token available.');
                            }
                        } else if (Notification.permission === 'default') {
                            // Only request permission if called from user interaction
                            console.log('Permission needed - this must be triggered by user action');

                            const permission = await Notification.requestPermission();
                            if (permission === 'granted') {
                                console.log('Notification permission granted.');

                                // Get the firebase-messaging-sw.js registration specifically
                                let registration = await navigator.serviceWorker.getRegistration('/firebase-messaging-sw.js');

                                if (!registration) {
                                    console.log('Firebase SW not registered, registering now...');
                                    registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js', {
                                        scope: '/'
                                    });
                                    await navigator.serviceWorker.ready;
                                    console.log('✅ Firebase SW registered:', registration.scope);
                                } else {
                                    console.log('✅ Firebase SW already registered:', registration.scope);
                                }

                                // Get FCM token with explicit SW registration
                                console.log('Requesting FCM token with Firebase SW...');
                                const token = await messaging.getToken({
                                    serviceWorkerRegistration: registration,
                                    vapidKey: firebaseConfig.vapidKey || undefined
                                });

                                if (token) {
                                    console.log('✅ FCM Token obtained:', token.substring(0, 20) + '...');
                                    sendTokenToServer(token);
                                } else {
                                    console.log('❌ No FCM token available.');
                                }
                            } else {
                                console.warn('Notification permission denied.');
                            }
                        } else {
                            console.warn('Notification permission already denied.');
                        }
                    } catch (error) {
                        console.error('Error getting FCM token:', error);
                    }
                }

                // Function to send token to WordPress backend (AJAX)
                function sendTokenToServer(token) {
                    console.log('Sending FCM Token to server...', token.substring(0, 20) + '...');
                    jQuery.post(dispatch_ajax.ajax_url, {
                        action: 'dispatch_save_fcm_token',
                        nonce: dispatch_ajax.nonce,
                        fcm_token: token
                    }, function(response) {
                        if (response.success) {
                            console.log('✅ FCM Token saved to server:', response.data);
                        } else {
                            console.error('❌ Failed to save FCM Token:', response.data);
                        }
                    }).fail(function(xhr, status, error) {
                        console.error('❌ AJAX Error saving FCM Token:', status, error, xhr.responseText);
                    });
                }

                // Call this function when the user goes online
                window.requestFCMToken = requestPermissionAndGetToken;

                // Also check on page load if driver is already online
                document.addEventListener('DOMContentLoaded', function() {
                    // Wait a bit for service worker to register
                    setTimeout(() => {
                        const isOnline = localStorage.getItem('driver_online_status') === 'true';
                        if (isOnline && messaging) {
                            console.log('Driver is online, requesting FCM token...');
                            requestPermissionAndGetToken();
                        }
                    }, 2000);
                });

                // FCM Token button removed - handled automatically

                // Handle incoming messages when app is in foreground
                // NOTE: With notification block, onMessage is NOT called
                // Instead, browser shows notification automatically
                // We use Service Worker messages to handle foreground notifications
                if (messaging) {
                    console.log('[FCM] Setting up message handlers');

                    // Listen for messages from Service Worker
                    if ('serviceWorker' in navigator) {
                        navigator.serviceWorker.addEventListener('message', (event) => {
                            console.log('[FCM] ✅ Message from Service Worker:', event.data);

                            // Check for FCM notification message
                            if (event.data && (event.data.type === 'FCM_NOTIFICATION' || event.data.notification)) {
                                const notification = event.data.notification || {};
                                const data = event.data.data || {};

                                console.log('[FCM] Processing foreground notification');
                                console.log('[FCM] Notification:', notification);
                                console.log('[FCM] Data:', data);

                                // Mark FCM as active to suppress polling notifications
                                fcmActive = true;

                                // Show in-app toast banner
                                if (typeof showNotificationToast === 'function') {
                                    const toastMessage = notification.body || data.body || 'Neue Bestellung zugewiesen!';
                                    console.log('[FCM] Showing toast banner:', toastMessage);
                                    showNotificationToast('🚚 ' + toastMessage, 'success', 5000);
                                } else {
                                    console.warn('[FCM] showNotificationToast function not available');
                                }

                                // Play notification sound
                                console.log('[FCM] Attempting to play sound via SW message');
                                if (window.notificationSound) {
                                    console.log('[FCM] notificationSound exists');
                                    console.log('[FCM] notificationSound.initialized:', window.notificationSound.initialized);
                                    console.log('[FCM] notificationSound.unlocked:', window.notificationSound.unlocked);

                                    // Force init if not initialized
                                    if (!window.notificationSound.initialized) {
                                        console.log('[FCM] Initializing sound on demand');
                                        window.notificationSound.init();
                                    }

                                    // Play directly - play() method handles unlock check
                                    console.log('[FCM] Playing sound');
                                    window.notificationSound.play();
                                } else {
                                    console.warn('[FCM] notificationSound not available');
                                }

                                // Refresh orders list
                                if (typeof loadDriverOrders === 'function') {
                                    console.log('[FCM] Refreshing orders list');
                                    loadDriverOrders();
                                } else {
                                    console.warn('[FCM] loadDriverOrders function not available');
                                }
                            }
                        });
                        console.log('[FCM] Service Worker message listener registered');
                    }

                    // ALSO setup onMessage as backup for foreground notifications
                    // On some browsers/platforms, onMessage fires when app is open
                    // This ensures we always have sound + toast even if Service Worker message doesn't arrive
                    messaging.onMessage((payload) => {
                        console.log('[FCM] ✅ onMessage received (app in foreground):', payload);

                        const notification = payload.notification || {};
                        const data = payload.data || {};

                        // Mark FCM as active to suppress polling notifications
                        fcmActive = true;

                        // Show in-app toast banner
                        if (typeof showNotificationToast === 'function') {
                            const toastMessage = notification.body || data.body || 'Neue Bestellung zugewiesen!';
                            console.log('[FCM onMessage] Showing toast banner:', toastMessage);
                            showNotificationToast('🚚 ' + toastMessage, 'success', 5000);
                        } else {
                            console.warn('[FCM onMessage] showNotificationToast function not available');
                        }

                        // Play notification sound
                        console.log('[FCM onMessage] Attempting to play sound');
                        if (window.notificationSound) {
                            console.log('[FCM onMessage] notificationSound exists');
                            console.log('[FCM onMessage] notificationSound.initialized:', window.notificationSound.initialized);
                            console.log('[FCM onMessage] notificationSound.unlocked:', window.notificationSound.unlocked);

                            // Force init if not initialized
                            if (!window.notificationSound.initialized) {
                                console.log('[FCM onMessage] Initializing sound on demand');
                                window.notificationSound.init();
                            }

                            // Play directly - play() method handles unlock check
                            console.log('[FCM onMessage] Playing sound');
                            const playPromise = window.notificationSound.play();
                            if (playPromise && playPromise.catch) {
                                playPromise.catch(err => {
                                    console.warn('[FCM onMessage] Could not play sound:', err);
                                });
                            }
                        } else {
                            console.warn('[FCM onMessage] notificationSound not available');
                        }

                        // Refresh orders list
                        if (typeof loadDriverOrders === 'function') {
                            console.log('[FCM onMessage] Refreshing orders list');
                            loadDriverOrders();
                        } else {
                            console.warn('[FCM onMessage] loadDriverOrders function not available');
                        }
                    });

                    console.log('[FCM] Both onMessage and Service Worker message handlers registered');
                } else {
                    console.warn('[FCM] Messaging not initialized - foreground handler not registered');
                }

            </script>
            -->

            <style>
                /* Dashboard v2.0 - Verbesserte Anordnung */
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #1a1a1a;
                    color: #ffffff;
                    height: 100vh;
                    overflow-x: hidden;
                }
                
                /* Header */
                .header {
                    background: #1a1a1a;
                    padding: 15px 20px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    border-bottom: 1px solid #333;
                    position: relative;
                }
                
                .hamburger-menu {
                    background: none;
                    border: none;
                    color: #fff;
                    font-size: 20px;
                    cursor: pointer;
                    padding: 5px;
                }
                
                .header-title {
                    color: #fff;
                    font-size: 18px;
                    font-weight: 600;
                    position: absolute;
                    left: 50%;
                    transform: translateX(-50%);
                    z-index: 1;
                }
                
                .header-right {
                    width: 30px; /* Balance space */
                }
                
                /* Main Content */
                .main-content {
                    padding: 20px;
                    padding-bottom: 56px; /* Space for bottom nav */
                    max-width: 400px;
                    margin: 0 auto;
                }
                
                /* Full width content for orders page */
                .main-content.orders-page {
                    padding: 0;
                    padding-bottom: 56px; /* Keep space for bottom nav */
                    max-width: none;
                    margin: 0;
                    width: 100%;
                }
                
                .welcome-section {
                    background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%);
                    padding: 25px;
                    border-radius: 20px;
                    display: flex;
                    align-items: center;
                    margin-bottom: 25px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                }
                
                .driver-avatar {
                    width: 60px;
                    height: 60px;
                    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #fff;
                    font-weight: 700;
                    font-size: 20px;
                    margin-right: 20px;
                    box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
                }
                
                .welcome-text h2 {
                    font-size: 22px;
                    margin-bottom: 8px;
                    font-weight: 600;
                }
                
                .welcome-text p {
                    color: #aaa;
                    font-size: 15px;
                }
                
                .dashboard-grid {
                    display: grid;
                    grid-template-columns: 1fr;
                    gap: 20px;
                    margin-bottom: 30px;
                }
                
                .card {
                    background: linear-gradient(135deg, #2a2a2a 0%, #1f1f1f 100%);
                    padding: 25px;
                    border-radius: 20px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                    border: 1px solid #333;
                }
                
                .card-header {
                    display: flex;
                    align-items: center;
                    margin-bottom: 20px;
                }
                
                .card-icon {
                    width: 45px;
                    height: 45px;
                    background: #4CAF50;
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-right: 15px;
                    font-size: 20px;
                }
                
                .card-title {
                    font-size: 18px;
                    font-weight: 600;
                    color: #fff;
                }
                
                .online-button {
                    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
                    color: #fff;
                    border: none;
                    padding: 18px 30px;
                    border-radius: 25px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    width: 100%;
                    transition: all 0.3s ease;
                    box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
                    position: relative;
                    overflow: hidden;
                }
                
                .online-button::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: -100%;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
                    transition: left 0.5s;
                }
                
                .online-button:hover::before {
                    left: 100%;
                }
                
                .online-button:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 25px rgba(76, 175, 80, 0.5);
                }
                
                .online-button.offline {
                    background: linear-gradient(135deg, #666 0%, #555 100%);
                    box-shadow: 0 4px 15px rgba(102, 102, 102, 0.4);
                }
                
                .online-button.offline:hover {
                    box-shadow: 0 6px 25px rgba(102, 102, 102, 0.5);
                }
                
                .stats-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 15px;
                }
                
                .stat-item {
                    text-align: center;
                    padding: 15px;
                    background: rgba(76, 175, 80, 0.1);
                    border-radius: 15px;
                    border: 1px solid rgba(76, 175, 80, 0.2);
                }
                
                .stat-number {
                    font-size: 28px;
                    font-weight: 700;
                    color: #4CAF50;
                    margin-bottom: 5px;
                }
                
                .stat-label {
                    font-size: 12px;
                    color: #aaa;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                /* Mobile Home Screen Styles */
                .mobile-home-screen {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    min-height: calc(100vh - 120px);
                    padding: 20px;
                    text-align: center;
                }
                
                .welcome-section-mobile {
                    margin-bottom: 60px;
                }
                
                .driver-avatar-large {
                    width: 150px;
                    height: 150px;
                    border-radius: 50%;
                    background: #10b981;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 60px;
                    font-weight: bold;
                    color: #ffffff;
                    margin-bottom: 30px;
                }
                
                .welcome-title {
                    font-size: 28px;
                    font-weight: 600;
                    color: #ffffff;
                    margin: 0 0 10px 0;
                }
                
                .welcome-subtitle {
                    font-size: 18px;
                    color: #888;
                    margin: 0;
                }
                
                .start-delivery-section {
                    width: 100%;
                    max-width: 350px;
                }
                
                .section-title {
                    font-size: 20px;
                    font-weight: 500;
                    color: #ffffff;
                    margin-bottom: 30px;
                }
                
                .online-button-large {
                    width: 100%;
                    padding: 20px;
                    background: #10b981;
                    border: none;
                    border-radius: 50px;
                    color: white;
                    font-size: 18px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
                }
                
                .online-button-large:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 25px rgba(16, 185, 129, 0.5);
                }
                
                .online-button-large.offline {
                    background: #10b981;
                }
                
                .online-button-large.online {
                    background: #ef4444;
                }
                
                .status-indicator {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-top: 15px;
                    padding: 10px;
                    background: rgba(255, 193, 7, 0.1);
                    border-radius: 12px;
                    border: 1px solid rgba(255, 193, 7, 0.3);
                }
                
                .status-dot {
                    width: 8px;
                    height: 8px;
                    background: #ffc107;
                    border-radius: 50%;
                    margin-right: 8px;
                    animation: pulse 2s infinite;
                }
                
                @keyframes pulse {
                    0% { opacity: 1; }
                    50% { opacity: 0.5; }
                    100% { opacity: 1; }
                }
                
                .status-text {
                    color: #ffc107;
                    font-size: 14px;
                    font-weight: 500;
                }
                
                /* Loading Screen Styles */
                .loading-screen-stats {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    min-height: calc(100vh - 120px);
                    padding: 40px 20px;
                    text-align: center;
                    background: #1a1a1a;
                    color: #ffffff;
                }

                .loading-spinner-stats {
                    width: 40px;
                    height: 40px;
                    border: 3px solid #333;
                    border-top: 3px solid #10b981;
                    border-radius: 50%;
                    animation: spin-stats 1s linear infinite;
                    margin-bottom: 20px;
                }

                @keyframes spin-stats {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }

                .loading-text {
                    color: #9CA3AF;
                    font-size: 16px;
                }

                /* Empty State Styles */
                .empty-state-screen {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    min-height: calc(100vh - 120px);
                    padding: 40px 20px;
                    text-align: center;
                }
                
                .empty-state-icon {
                    width: 120px;
                    height: 120px;
                    border-radius: 50%;
                    background: #10b981;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-bottom: 40px;
                }
                
                .empty-state-icon svg {
                    width: 60px;
                    height: 60px;
                    fill: #ffffff;
                }
                
                .empty-state-message {
                    font-size: 18px;
                    color: #ffffff;
                    font-weight: 500;
                }
                
                /* Bottom Navigation */
                .bottom-navigation {
                    position: fixed;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    background: #2D3748;
                    border-top: 1px solid #4A5568;
                    display: flex;
                    justify-content: space-around;
                    height: 56px;
                    align-items: center;
                }
                
                .nav-item {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    text-decoration: none;
                    color: #48BB78;
                    transition: color 0.3s ease;
                    padding: 5px 10px;
                }
                
                .nav-item.active {
                    color: #48BB78;
                }
                
                .nav-item:hover {
                    color: #48BB78;
                }
                
                .nav-item .icon {
                    font-size: 20px;
                    margin-bottom: 3px;
                }
                
                .nav-item .label {
                    font-size: 11px;
                }
                
                .nav-item.active {
                    color: #48BB78 !important;
                }
                
                .nav-item.active .icon {
                    transform: scale(1.1);
                }
                
                .nav-item.active .label {
                    color: #48BB78;
                    font-weight: 600;
                }
                
                /* Side Menu */
                /* Hamburger Menu - Schlicht & Modern */
                .side-menu {
                    position: fixed;
                    left: -300px;
                    top: 0;
                    width: 300px;
                    height: 100vh;
                    background: #1e293b;
                    transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    z-index: 1000;
                    display: flex;
                    flex-direction: column;
                    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.3);
                }

                .side-menu.open {
                    left: 0;
                }

                .side-menu-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.6);
                    opacity: 0;
                    visibility: hidden;
                    transition: all 0.3s ease;
                    z-index: 999;
                    backdrop-filter: blur(2px);
                }

                .side-menu-overlay.open {
                    opacity: 1;
                    visibility: visible;
                }

                /* User Section */
                .menu-user-section {
                    padding: 24px 20px;
                    background: #0f172a;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    position: relative;
                }

                .menu-avatar {
                    width: 48px;
                    height: 48px;
                    border-radius: 12px;
                    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-weight: 700;
                    font-size: 18px;
                    flex-shrink: 0;
                }

                .menu-user-info {
                    flex: 1;
                    min-width: 0;
                }

                .menu-user-name {
                    color: #f1f5f9;
                    font-size: 16px;
                    font-weight: 600;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }

                .menu-user-status {
                    color: #10b981;
                    font-size: 13px;
                    margin-top: 2px;
                }

                .menu-close-btn {
                    background: transparent;
                    border: none;
                    color: #94a3b8;
                    padding: 8px;
                    cursor: pointer;
                    border-radius: 6px;
                    transition: all 0.2s;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .menu-close-btn:hover {
                    background: rgba(148, 163, 184, 0.1);
                    color: #f1f5f9;
                }

                /* Navigation */
                .menu-nav {
                    flex: 1;
                    padding: 12px 0;
                    overflow-y: auto;
                }

                .menu-link {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    padding: 14px 20px;
                    color: #cbd5e1;
                    text-decoration: none;
                    font-size: 15px;
                    font-weight: 500;
                    transition: all 0.2s;
                    border-left: 3px solid transparent;
                }

                .menu-link:hover {
                    background: rgba(59, 130, 246, 0.1);
                    color: #60a5fa;
                    border-left-color: #3b82f6;
                }

                .menu-link svg {
                    opacity: 0.8;
                    flex-shrink: 0;
                }

                .menu-link:hover svg {
                    opacity: 1;
                }

                .menu-divider {
                    height: 1px;
                    background: #334155;
                    margin: 12px 20px;
                }

                /* Bottom Actions */
                .menu-bottom {
                    padding: 16px 20px 24px;
                    background: #0f172a;
                    border-top: 1px solid #334155;
                }

                .menu-offline-btn {
                    width: 100%;
                    padding: 14px 16px;
                    background: #ef4444;
                    border: none;
                    border-radius: 10px;
                    color: white;
                    font-size: 15px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
                }

                .menu-offline-btn:hover {
                    background: #dc2626;
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
                }

                .menu-offline-btn:active {
                    transform: translateY(0);
                }

                .menu-logout-link {
                    display: block;
                    text-align: center;
                    padding: 12px;
                    margin-top: 8px;
                    color: #94a3b8;
                    text-decoration: none;
                    font-size: 14px;
                    font-weight: 500;
                    border-radius: 8px;
                    transition: all 0.2s;
                }

                .menu-logout-link:hover {
                    background: rgba(148, 163, 184, 0.1);
                    color: #f1f5f9;
                }

                /* Legacy support - kann später entfernt werden */
                .driver-info-menu {
                    padding: 15px 0 !important;
                    display: flex !important;
                    align-items: center !important;
                    gap: 15px !important;
                    background: #374151 !important;
                    border-radius: 8px !important;
                    margin-bottom: 20px !important;
                    padding-left: 15px !important;
                    padding-right: 15px !important;
                }
                
                .driver-avatar-small {
                    width: 45px;
                    height: 45px;
                    border-radius: 50%;
                    background: #10b981;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #ffffff;
                    font-weight: bold;
                    font-size: 16px;
                    flex-shrink: 0;
                }
                
                .driver-details-menu {
                    display: flex;
                    flex-direction: column;
                    gap: 2px;
                }
                
                .driver-name-menu {
                    color: #ffffff;
                    font-weight: 600;
                    font-size: 16px;
                    margin: 0;
                }
                
                .driver-status-online {
                    color: #10b981;
                    font-size: 13px;
                    font-weight: 500;
                    margin: 0;
                }
                
                /* Toast Notification Animations */
                @keyframes slideInToast {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                
                @keyframes slideOutToast {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                }
                
                .toast-content {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                
                .toast-close {
                    background: none;
                    border: none;
                    color: white;
                    font-size: 18px;
                    cursor: pointer;
                    padding: 0;
                    width: 20px;
                    height: 20px;
                }
                
                .empty-orders-state {
                    text-align: center;
                    padding: 60px 20px;
                    color: #6b7280;
                }
                
                .empty-orders-state h3 {
                    margin: 20px 0 10px 0;
                    color: #374151;
                }
                
                .empty-orders-state p {
                    margin: 0;
                    font-size: 14px;
                }
                
                /* Mobile Orders List */
                .orders-list-mobile {
                    padding: 0;
                    margin: 0;
                    padding-bottom: 56px; /* Space for bottom navigation */
                    width: 100%;
                }
                
                .order-card-mobile {
                    background: #374151;
                    border-radius: 0;
                    padding: 16px 20px;
                    margin: 0;
                    border: none;
                    border-bottom: 1px solid #4B5563;
                    width: 100%;
                    box-sizing: border-box;
                }
                
                .order-header-mobile {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 12px;
                }
                
                .order-status-badge {
                    padding: 4px 12px;
                    border-radius: 20px;
                    color: #ffffff;
                    font-size: 12px;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                
                .order-time-mobile {
                    color: #D1D5DB;
                    font-size: 12px;
                    font-weight: 500;
                }
                
                .order-details-mobile {
                    display: flex;
                    flex-direction: column;
                    gap: 8px;
                }
                
                .order-number-mobile {
                    color: #9CA3AF;
                    font-size: 14px;
                    font-weight: 500;
                }
                
                .customer-info-mobile {
                    display: flex;
                    flex-direction: column;
                    gap: 2px;
                }
                
                .customer-name-mobile {
                    color: #ffffff;
                    font-size: 16px;
                    font-weight: 600;
                }
                
                .customer-address-mobile {
                    color: #D1D5DB;
                    font-size: 14px;
                    line-height: 1.3;
                }
                
                /* Current Order Card - Exact Screenshot Style */
                .current-order-card {
                    background: #1a1a1a;
                    border-radius: 16px;
                    margin: 16px;
                    padding: 20px;
                    position: relative;
                    transition: all 0.2s ease;
                }

                .current-order-card.drag-over {
                    border: 2px dashed #48BB78;
                    background: #1f2937;
                }

                .current-order-card:active {
                    cursor: grabbing !important;
                }

                .drag-handle {
                    touch-action: none;
                }

                .drag-handle:active {
                    cursor: grabbing !important;
                }
                
                .order-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 16px;
                }

                .order-header-left {
                    display: flex;
                    align-items: center;
                }
                
                .status-badges {
                    display: flex;
                    gap: 10px;
                }
                
                .status-badge {
                    padding: 6px 14px;
                    border-radius: 6px;
                    font-size: 13px;
                    font-weight: 600;
                }

                .status-badge.zugewiesen {
                    background: #D4A017;
                    color: #000000;
                }

                .status-badge.started {
                    background: #10B981;
                    color: white;
                }
                
                .action-icons {
                    display: flex;
                    gap: 16px;
                }
                
                .action-icon {
                    background: transparent;
                    border: none;
                    padding: 8px;
                    cursor: pointer;
                    transition: opacity 0.2s ease;
                }
                
                .action-icon:hover {
                    opacity: 0.7;
                }
                
                .order-number-section {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 24px;
                    padding-left: 4px;
                }
                
                .order-number {
                    color: #9CA3AF;
                    font-size: 17px;
                    font-weight: 400;
                }
                
                .order-total {
                    color: #9CA3AF;
                    font-size: 17px;
                    font-weight: 400;
                }
                
                .order-locations {
                    margin-bottom: 24px;
                }

                .location-item {
                    display: flex;
                    align-items: flex-start;
                    margin-bottom: 20px;
                    position: relative;
                }

                .location-marker {
                    width: 24px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-right: 12px;
                    position: relative;
                    padding-top: 2px;
                }

                .location-marker::after {
                    content: '';
                    position: absolute;
                    top: 24px;
                    left: 50%;
                    width: 1px;
                    height: 40px;
                    background: #374151;
                    transform: translateX(-50%);
                }

                .location-item:last-child .location-marker::after {
                    display: none;
                }

                .marker-dot {
                    width: 12px;
                    height: 12px;
                    border-radius: 50%;
                    background: #ffffff;
                    border: 2px solid #374151;
                }

                .location-info {
                    flex: 1;
                }

                .location-name {
                    color: #ffffff;
                    font-size: 16px;
                    font-weight: 500;
                    margin-bottom: 4px;
                }

                .location-address {
                    color: #6B7280;
                    font-size: 14px;
                    line-height: 1.3;
                }

                .location-time {
                    color: #ffffff;
                    font-size: 15px;
                    font-weight: 400;
                    margin-left: auto;
                    white-space: nowrap;
                }
                
                .pickup-button {
                    background: #F97316;
                    color: white;
                    border: none;
                    border-radius: 50px;
                    padding: 16px 24px;
                    font-size: 17px;
                    font-weight: 600;
                    width: 100%;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                }
                
                .pickup-button:hover {
                    background: #EA580C;
                    transform: translateY(-1px);
                }
            </style>
        </head>
        <body>
            <!-- iOS Installation Warning Banner -->
            <div id="ios-install-banner" style="display: none; background: linear-gradient(135deg, #ff9500 0%, #ff6b00 100%); color: white; padding: 15px; text-align: center; position: sticky; top: 0; z-index: 9999; box-shadow: 0 2px 10px rgba(0,0,0,0.2);">
                <div style="font-size: 16px; font-weight: bold; margin-bottom: 8px;">📱 App über Home-Screen öffnen</div>
                <div style="font-size: 14px; line-height: 1.5;">
                    Für Push-Benachrichtigungen öffne die App über das Icon auf deinem Homescreen, nicht über Safari!
                </div>
                <button onclick="document.getElementById('ios-install-banner').style.display='none'" style="margin-top: 10px; background: rgba(255,255,255,0.3); border: 1px solid white; color: white; padding: 8px 16px; border-radius: 4px; font-size: 13px;">
                    Verstanden
                </button>
            </div>

            <!-- Header -->
            <div class="header">
                <?php if ($is_online): ?>
                <button class="hamburger-menu" onclick="toggleMenu()">
                    ☰
                </button>
                <?php endif; ?>
                <div class="header-title">Dashboard v2.0 - NEUE VERSION</div>
                <div class="header-right"></div>
            </div>

            <?php
            // MULTI-DEVICE NOTIFICATION v2.9.6
            if (get_transient('driver_other_device_logout_' . get_current_user_id())) {
                delete_transient('driver_other_device_logout_' . get_current_user_id());
                ?>
                <div id="multi-device-notification" style="background: #ff9800; color: white; padding: 15px; text-align: center; font-weight: 500; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    ⚠️ Sie wurden auf einem anderen Gerät angemeldet. Die andere Session wurde automatisch beendet.
                    <button onclick="document.getElementById('multi-device-notification').style.display='none'" style="background: white; color: #ff9800; border: none; padding: 5px 15px; margin-left: 10px; border-radius: 4px; cursor: pointer; font-weight: 600;">OK</button>
                </div>
                <?php
            }
            ?>

            <?php if ($is_online): ?>
            <!-- Side Menu - Schlichte Version -->
            <div class="side-menu-overlay" onclick="toggleMenu()"></div>
            <div class="side-menu">
                <!-- User Info -->
                <div class="menu-user-section">
                    <div class="menu-avatar"><?php echo esc_html($initials); ?></div>
                    <div class="menu-user-info">
                        <div class="menu-user-name"><?php echo esc_html($current_user->display_name); ?></div>
                        <div class="menu-user-status">🟢 Online</div>
                    </div>
                    <button class="menu-close-btn" onclick="toggleMenu()">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Menu Items -->
                <nav class="menu-nav">
                    <a href="#" onclick="showBestellungen(); return false;" class="menu-link">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                        </svg>
                        <span>Bestellungen</span>
                    </a>

                    <a href="#" onclick="showPackliste(); return false;" class="menu-link">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm2-7h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/>
                        </svg>
                        <span>Packliste</span>
                    </a>

                    <a href="#" onclick="showRouting(); return false;" class="menu-link">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/>
                        </svg>
                        <span>Karte</span>
                    </a>

                    <a href="#" onclick="showVollstaendigeBestellungen(); return false;" class="menu-link">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/>
                        </svg>
                        <span>Abgeschlossen</span>
                    </a>

                    <a href="#" onclick="showLeistung(); return false;" class="menu-link">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M5 9.2h3V19H5zM10.6 5h2.8v14h-2.8zm5.6 8H19v6h-2.8z"/>
                        </svg>
                        <span>Leistung</span>
                    </a>

                    <div class="menu-divider"></div>

                    <a href="#" onclick="showEinstellungen(); return false;" class="menu-link">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
                        </svg>
                        <span>Einstellungen</span>
                    </a>
                </nav>

                <!-- Bottom Actions -->
                <div class="menu-bottom">
                    <button onclick="goOffline()" class="menu-offline-btn">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        Offline gehen
                    </button>
                    <a href="<?php echo wp_logout_url(home_url('/fahrer-login/')); ?>" class="menu-logout-link">Abmelden</a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Main Content -->
            <div class="main-content" id="main-content">
                <!-- Mobile Home Screen -->
                <div class="mobile-home-screen">
                    <!-- Welcome Section -->
                    <div class="welcome-section-mobile">
                        <div class="driver-avatar-large">
                            <?php echo esc_html($initials); ?>
                        </div>
                        <h1 class="welcome-title">Hallo, <?php 
                            $first_name = $current_user->first_name ?: explode(' ', $current_user->display_name)[0];
                            $last_initial = '';
                            if ($current_user->last_name) {
                                $last_initial = ' ' . strtoupper(substr($current_user->last_name, 0, 1)) . '.';
                            }
                            echo esc_html($first_name . $last_initial); 
                        ?></h1>
                        <p class="welcome-subtitle">Willkommen zurück</p>
                    </div>
                    
                    <!-- Start Delivery Section -->
                    <div class="start-delivery-section">
                        <h2 class="section-title">Bestellungen liefern starten</h2>
                        <button class="online-button-large" id="onlineToggleLarge">
                            Online gehen
                        </button>

                        <!-- Push Notifications Activation Button (for both FCM and Web Push) -->
                        <button id="enablePushButton" class="online-button-large" style="
                            display: none;
                            margin-top: 15px;
                            background: #f59e0b;
                            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
                        ">
                            🔔 Push-Benachrichtigungen aktivieren
                        </button>

                        <script>
                            // Show Push button if permissions not granted yet
                            document.addEventListener('DOMContentLoaded', function() {
                                // Check if notifications are supported and not granted
                                if ('Notification' in window && Notification.permission !== 'granted') {
                                    document.getElementById('enablePushButton').style.display = 'block';
                                }

                                // Handle Push button click
                                document.getElementById('enablePushButton')?.addEventListener('click', async function() {
                                    console.log('Push button clicked - requesting permission...');

                                    try {
                                        const permission = await Notification.requestPermission();

                                        if (permission === 'granted') {
                                            console.log('Permission granted, subscribing...');

                                            // Get service worker registration
                                            const swScope = '<?php echo plugin_dir_url(__FILE__); ?>pwa/';
                                            let registration = await navigator.serviceWorker.getRegistration(swScope);

                                            if (!registration) {
                                                console.log('Service worker not found, registering...');
                                                registration = await navigator.serviceWorker.register(
                                                    '<?php echo plugin_dir_url(__FILE__); ?>pwa/service-worker.js',
                                                    { scope: swScope }
                                                );
                                                // Wait for service worker to be ready
                                                await navigator.serviceWorker.ready;
                                                console.log('Service worker ready, now checking active state...');
                                            }

                                            // Ensure service worker is active before subscribing
                                            if (!registration.active) {
                                                console.log('Service worker not yet active, waiting...');
                                                // Wait for activation - get the updated registration
                                                registration = await navigator.serviceWorker.ready;

                                                // Double check if still not active
                                                if (!registration.active) {
                                                    console.error('Service worker failed to activate');
                                                    alert('Service Worker konnte nicht aktiviert werden. Bitte Seite neu laden.');
                                                    return;
                                                }
                                                console.log('Service worker is now active!');
                                            }

                                            // Subscribe to push
                                            const vapidPublicKey = '<?php echo esc_js(get_option("dispatch_vapid_public_key", "")); ?>';

                                            if (!vapidPublicKey || vapidPublicKey.length < 20) {
                                                console.error('VAPID public key is missing or invalid');
                                                alert('Push-Benachrichtigungen können nicht aktiviert werden. Bitte installieren Sie das Plugin neu.');
                                                return;
                                            }

                                            function urlBase64ToUint8Array(base64String) {
                                                try {
                                                    // Remove any whitespace
                                                    base64String = base64String.trim();

                                                    const padding = '='.repeat((4 - base64String.length % 4) % 4);
                                                    const base64 = (base64String + padding)
                                                        .replace(/\-/g, '+')
                                                        .replace(/_/g, '/');

                                                    const rawData = window.atob(base64);
                                                    const outputArray = new Uint8Array(rawData.length);
                                                    for (let i = 0; i < rawData.length; ++i) {
                                                        outputArray[i] = rawData.charCodeAt(i);
                                                    }
                                                    return outputArray;
                                                } catch (e) {
                                                    console.error('Failed to convert VAPID key:', e);
                                                    console.error('VAPID key was:', base64String);
                                                    throw e;
                                                }
                                            }

                                            const subscription = await registration.pushManager.subscribe({
                                                userVisibleOnly: true,
                                                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
                                            });

                                            // Save subscription to server
                                            const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                                                method: 'POST',
                                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                                body: new URLSearchParams({
                                                    action: 'dispatch_save_push_subscription',
                                                    subscription: JSON.stringify(subscription.toJSON()),
                                                    user_id: '<?php echo get_current_user_id(); ?>',
                                                    nonce: dispatch_ajax.nonce
                                                })
                                            });

                                            const data = await response.json();
                                            if (data.success) {
                                                console.log('✅ Push-Benachrichtigungen aktiviert');
                                                document.getElementById('enablePushButton').style.display = 'none';

                                                // Show success toast
                                                if (typeof showNotificationToast === 'function') {
                                                    showNotificationToast('✅ Push-Benachrichtigungen aktiviert!', 'success');
                                                } else {
                                                    alert('✅ Push-Benachrichtigungen aktiviert!');
                                                }
                                            } else {
                                                throw new Error('Server error');
                                            }
                                        } else {
                                            console.log('Permission denied');
                                            alert('❌ Berechtigung verweigert. Bitte in Browser-Einstellungen aktivieren.');
                                        }
                                    } catch (error) {
                                        console.error('Error enabling push notifications:', error);
                                        alert('❌ Fehler: ' + error.message);
                                    }
                                });

                            });
                        </script>
                    </div>
            </div>

            <?php if ($is_online): ?>
            <!-- Bottom Navigation -->
            <div class="bottom-navigation">
                <a href="#bestellungen" class="nav-item">
                    <div class="icon">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 2v3h8V2H8zM9 9l3 4 4-6 1 1.5L12 15 8 10l1-1z"/>
                            <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.89-1.99 2L3 19a2 2 0 002 2h14c1.1 0 2-.9 2-2V5c0-1.11-.9-2-2-2zm0 16H5V8h14v11z"/>
                        </svg>
                    </div>
                    <div class="label" data-i18n="orders">Bestellungen</div>
                </a>
                <a href="#karte" class="nav-item">
                    <div class="icon">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/>
                        </svg>
                    </div>
                    <div class="label" data-i18n="map">Karte</div>
                </a>
                <a href="#warten" class="nav-item">
                    <div class="icon">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M15 1H9v2h6V1zm-4 13h2V8h-2v6zm8.03-6.61l1.42-1.42c-.43-.51-.9-.99-1.41-1.41l-1.42 1.42C16.07 4.74 14.12 4 12 4c-4.97 0-9 4.03-9 9s4.02 9 9 9 9-4.03 9-9c0-2.12-.74-4.07-1.97-5.61zM12 20c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/>
                        </svg>
                    </div>
                    <div class="label" data-i18n="waiting">Warten</div>
                </a>
                <a href="#leistung" class="nav-item">
                    <div class="icon">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M5 9.2h3V19H5zM10.6 5h2.8v14h-2.8zm5.6 8H19v6h-2.8z"/>
                        </svg>
                    </div>
                    <div class="label" data-i18n="performance">Leistung</div>
                </a>
            </div>
            <?php endif; ?>
            
            <script>
            // Initialize AJAX configuration for WordPress - PFAND_FIX_V1_20251217
            const dispatch_ajax = {
                ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>', // Use WordPress admin URL
                nonce: '<?php echo wp_create_nonce("dispatch_ajax_nonce"); ?>',
                username: '<?php echo isset($current_user) && $current_user ? esc_js($current_user->user_login) : ""; ?>',
                version: '<?php echo DISPATCH_VERSION; ?>', // Cache busting
                today_date: '<?php echo (new DateTime('today', wp_timezone()))->format('Y-m-d'); ?>', // Today's date in WP timezone
                timezone_offset: <?php echo wp_timezone()->getOffset(new DateTime()) / 3600; ?>, // Timezone offset in hours (e.g., 2 for UTC+2)
                pfand_items: <?php
                    $pfand_items = get_option('dispatch_pfand_items', [
                        ['id' => 'water', 'icon' => '🍼', 'name' => 'Wasserflasche', 'amount' => 0.25, 'active' => true],
                        ['id' => 'beer', 'icon' => '🍺', 'name' => 'Bierflasche', 'amount' => 0.50, 'active' => true]
                    ]);
                    $active_items = array_filter($pfand_items, function($item) {
                        return !isset($item['active']) || filter_var($item['active'], FILTER_VALIDATE_BOOLEAN);
                    });
                    echo json_encode(array_values($active_items), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                ?>
            };

            // Helper function to get current date/time in WordPress timezone
            window.getWPDate = function(dateString) {
                // If no date provided, get current date
                if (!dateString) {
                    // Create a date in WordPress timezone by applying offset
                    const now = new Date();
                    const utcTime = now.getTime() + (now.getTimezoneOffset() * 60000);
                    return new Date(utcTime + (dispatch_ajax.timezone_offset * 3600000));
                }
                // Parse provided date string
                return new Date(dateString);
            };

            // Helper function to get "today" at midnight in WordPress timezone
            window.getWPToday = function() {
                return new Date(dispatch_ajax.today_date + 'T00:00:00');
            };

            // VAPID public key from server configuration
            <?php
            // Load VAPID configuration
            if (file_exists(plugin_dir_path(__FILE__) . 'pwa/vapid-config.php')) {
                require_once plugin_dir_path(__FILE__) . 'pwa/vapid-config.php';
            }
            ?>
            const vapidPublicKey = '<?php echo defined('VAPID_PUBLIC_KEY') ? esc_js(VAPID_PUBLIC_KEY) : ''; ?>';

            // Refresh nonce every 30 minutes to prevent expiration
            setInterval(function() {
                fetch(dispatch_ajax.ajax_url, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=refresh_nonce',
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.nonce) {
                        dispatch_ajax.nonce = data.data.nonce;
                        console.log('Nonce refreshed');
                    }
                });
            }, 1800000); // 30 minutes

            // Debug: Log the current configuration for troubleshooting
            console.log('AJAX Configuration:', {
                url: dispatch_ajax.ajax_url,
                username: dispatch_ajax.username,
                nonce_length: dispatch_ajax.nonce.length,
                version: dispatch_ajax.version
            });
            console.log('🚀 Dispatch Dashboard Version:', dispatch_ajax.version);

            // Simple notification sound function - define early to be available everywhere
            window.playNotificationSound = function(type = 'default') {
                try {
                    // Create a simple beep sound using Web Audio API
                    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();

                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);

                    // Different frequencies for different notification types
                    if (type === 'success') {
                        oscillator.frequency.value = 800; // Higher pitch for success
                    } else if (type === 'error') {
                        oscillator.frequency.value = 300; // Lower pitch for error
                    } else {
                        oscillator.frequency.value = 600; // Medium pitch for default
                    }

                    gainNode.gain.value = 0.1; // Volume
                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + 0.1); // Play for 100ms
                } catch (e) {
                    // Silently fail if audio is not supported
                    console.log('Audio notification not supported:', e);
                }
            }

            // Push Notification Functions - Define early so they're available
            async function requestPushPermission(registration) {
                console.log('requestPushPermission called with registration:', registration);

                // Check if Notification API is available
                if (!('Notification' in window)) {
                    console.warn('Notification API not available in this browser');
                    return;
                }

                console.log('Current Notification permission:', Notification.permission);

                // Check if already permitted
                if (Notification.permission === 'granted') {
                    console.log('Push-Benachrichtigungen bereits erlaubt');
                    subscribeToPush(registration);
                    return;
                }

                // Check if already denied
                if (Notification.permission === 'denied') {
                    console.log('Push-Benachrichtigungen wurden abgelehnt');
                    showNotificationToast('⚠️ Benachrichtigungen wurden blockiert. Bitte in Browser-Einstellungen aktivieren.', 'warning');
                    return;
                }

                // Show custom permission request
                console.log('Showing custom permission banner...');
                const permissionBanner = document.createElement('div');
                permissionBanner.id = 'push-permission-banner';
                permissionBanner.innerHTML = `
                    <div style="
                        position: fixed;
                        top: 80px;
                        left: 50%;
                        transform: translateX(-50%);
                        background: #3b82f6;
                        color: white;
                        padding: 15px 20px;
                        border-radius: 12px;
                        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                        z-index: 10000;
                        max-width: 90%;
                        font-size: 14px;
                    ">
                        <div style="margin-bottom: 10px;">
                            <strong>🔔 Benachrichtigungen aktivieren</strong>
                        </div>
                        <div style="margin-bottom: 15px;">
                            Erhalte Push-Benachrichtigungen bei neuen Bestellungen
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button onclick="allowPushNotifications()" style="
                                background: white;
                                color: #3b82f6;
                                border: none;
                                padding: 8px 16px;
                                border-radius: 6px;
                                font-weight: bold;
                                cursor: pointer;
                                flex: 1;
                            ">Erlauben</button>
                            <button onclick="denyPushNotifications()" style="
                                background: transparent;
                                color: white;
                                border: 1px solid white;
                                padding: 8px 16px;
                                border-radius: 6px;
                                cursor: pointer;
                                flex: 1;
                            ">Später</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(permissionBanner);

                // Allow function
                window.allowPushNotifications = async () => {
                    console.log('User clicked Allow...');
                    const permission = await Notification.requestPermission();
                    console.log('Permission result:', permission);
                    const banner = document.getElementById('push-permission-banner');
                    if (banner) banner.remove();

                    if (permission === 'granted') {
                        console.log('Push-Benachrichtigungen erlaubt!');
                        subscribeToPush(registration);
                        showNotificationToast('✅ Push-Benachrichtigungen aktiviert!', 'success');
                    } else {
                        showNotificationToast('❌ Push-Benachrichtigungen wurden nicht aktiviert', 'error');
                    }
                };

                // Deny function
                window.denyPushNotifications = () => {
                    console.log('User clicked Later...');
                    const banner = document.getElementById('push-permission-banner');
                    if (banner) banner.remove();
                    showNotificationToast('ℹ️ Sie können Benachrichtigungen später in den Einstellungen aktivieren', 'info');
                };
            }

            async function subscribeToPush(registration) {
                console.log('subscribeToPush called with registration:', registration);

                // Skip Web Push if FCM is configured
                <?php
                $settings = get_option('dispatch_settings', []);
                $fcm_configured = !empty($settings['dispatch_firebase_project_id']);
                ?>
                if (<?php echo $fcm_configured ? 'true' : 'false'; ?>) {
                    console.log('FCM is configured, skipping Web Push subscription');
                    return;
                }

                // Check if registration is valid
                if (!registration) {
                    console.error('No service worker registration available');
                    // Try to get service worker registration
                    if ('serviceWorker' in navigator) {
                        try {
                            const reg = await navigator.serviceWorker.ready;
                            console.log('Got service worker registration:', reg);
                            return subscribeToPush(reg);
                        } catch (error) {
                            console.error('Failed to get service worker:', error);
                            showNotificationToast('❌ Service Worker nicht verfügbar', 'error');
                            return;
                        }
                    } else {
                        showNotificationToast('❌ Service Worker wird nicht unterstützt', 'error');
                        return;
                    }
                }

                // Wait for service worker to be active
                if (!registration.active) {
                    console.log('Waiting for service worker to activate...');

                    // Try to wait for the service worker to become active
                    try {
                        await navigator.serviceWorker.ready;
                        console.log('Service worker is now ready');
                    } catch (e) {
                        console.error('Service worker failed to become ready:', e);
                    }

                    // Double-check if active now
                    if (!registration.active) {
                        console.error('Service worker still not active after waiting');
                        showNotificationToast('⚠️ Service Worker noch nicht bereit. Bitte Seite neu laden.', 'warning');
                        return;
                    }
                }

                try {
                    // Check if pushManager is available
                    if (!registration.pushManager) {
                        console.error('Push Manager not available');
                        showNotificationToast('❌ Push-Benachrichtigungen werden nicht unterstützt', 'error');
                        return;
                    }

                    // Check if already subscribed
                    const existingSubscription = await registration.pushManager.getSubscription();
                    if (existingSubscription) {
                        console.log('Already subscribed, using existing subscription');
                        // Send existing subscription to server
                        await savePushSubscriptionToServer(existingSubscription);
                        return;
                    }

                    // VAPID public key (base64 to Uint8Array)
                    if (!vapidPublicKey) {
                        console.error('VAPID public key not configured');
                        showNotificationToast('❌ Push-Konfiguration fehlt', 'error');
                        return;
                    }
                    const convertedVapidKey = urlBase64ToUint8Array(vapidPublicKey);

                    // Subscribe to push service
                    const subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: convertedVapidKey
                    });

                    console.log('Push subscription created:', subscription);

                    // Send subscription to server
                    await savePushSubscriptionToServer(subscription);

                } catch (error) {
                    console.error('Failed to subscribe to push:', error);
                    showNotificationToast('❌ Fehler bei Push-Registrierung: ' + error.message, 'error');
                }
            }

            async function savePushSubscriptionToServer(subscription) {
                try {
                    const response = await fetch(dispatch_ajax.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        credentials: 'same-origin',
                        body: new URLSearchParams({
                            action: 'save_push_subscription',
                            nonce: dispatch_ajax.nonce,
                            subscription: JSON.stringify(subscription)
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        console.log('Push subscription saved to server successfully');
                        showNotificationToast('✅ Push-Benachrichtigungen aktiviert!', 'success');
                    } else {
                        console.error('Server error saving subscription:', data);
                        showNotificationToast('⚠️ Fehler beim Speichern der Push-Einstellungen', 'warning');
                    }
                } catch (error) {
                    console.error('Error saving subscription to server:', error);
                }
            }

            function showPushReminder(registration) {
                // Don't show if already shown in this session
                if (sessionStorage.getItem('push_reminder_shown')) {
                    console.log('Push reminder already shown this session');
                    return;
                }

                // Mark as shown
                sessionStorage.setItem('push_reminder_shown', 'true');

                const reminderBanner = document.createElement('div');
                reminderBanner.id = 'push-reminder-banner';
                reminderBanner.innerHTML = `
                    <div style="
                        position: fixed;
                        top: 70px;
                        left: 50%;
                        transform: translateX(-50%);
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        padding: 16px 24px;
                        border-radius: 12px;
                        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
                        z-index: 10000;
                        max-width: 90%;
                        width: 400px;
                        animation: slideDown 0.3s ease-out;
                    ">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="font-size: 28px;">🔔</div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; font-size: 15px; margin-bottom: 4px;">Benachrichtigungen aktivieren</div>
                                <div style="font-size: 13px; opacity: 0.9;">Erhalte Echtzeit-Updates zu neuen Bestellungen</div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 12px; margin-top: 16px;">
                            <button onclick="enablePushFromReminder()" style="flex: 1; padding: 10px; background: white; color: #667eea; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px;">
                                Aktivieren
                            </button>
                            <button onclick="dismissPushReminder()" style="padding: 10px 16px; background: rgba(255,255,255,0.2); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px;">
                                Später
                            </button>
                        </div>
                    </div>
                    <style>
                        @keyframes slideDown {
                            from {
                                opacity: 0;
                                transform: translate(-50%, -20px);
                            }
                            to {
                                opacity: 1;
                                transform: translate(-50%, 0);
                            }
                        }
                    </style>
                `;

                document.body.appendChild(reminderBanner);

                // Auto-dismiss after 15 seconds
                setTimeout(() => {
                    const banner = document.getElementById('push-reminder-banner');
                    if (banner) {
                        banner.style.animation = 'slideUp 0.3s ease-out';
                        setTimeout(() => banner.remove(), 300);
                    }
                }, 15000);

                // Make functions global
                window.enablePushFromReminder = async () => {
                    const banner = document.getElementById('push-reminder-banner');
                    if (banner) banner.remove();

                    try {
                        const permission = await Notification.requestPermission();
                        if (permission === 'granted') {
                            console.log('Permission granted from reminder');

                            // Only subscribe to Web Push if FCM is not configured
                            <?php
                            $settings = get_option('dispatch_settings', []);
                            $fcm_configured = !empty($settings['dispatch_firebase_project_id']);
                            ?>
                            if (!<?php echo $fcm_configured ? 'true' : 'false'; ?>) {
                                subscribeToPush(registration);
                            } else {
                                console.log('FCM is configured, not subscribing to Web Push');
                                showNotificationToast('✅ Benachrichtigungen aktiviert!', 'success');
                            }
                        } else {
                            showNotificationToast('❌ Benachrichtigungen wurden nicht aktiviert', 'error');
                        }
                    } catch (error) {
                        console.error('Error requesting permission:', error);
                    }
                };

                window.dismissPushReminder = () => {
                    const banner = document.getElementById('push-reminder-banner');
                    if (banner) {
                        banner.style.animation = 'slideUp 0.3s ease-out';
                        setTimeout(() => banner.remove(), 300);
                    }
                };
            }

            function urlBase64ToUint8Array(base64String) {
                const padding = '='.repeat((4 - base64String.length % 4) % 4);
                const base64 = (base64String + padding)
                    .replace(/-/g, '+')
                    .replace(/_/g, '/');

                const rawData = window.atob(base64);
                const outputArray = new Uint8Array(rawData.length);

                for (let i = 0; i < rawData.length; ++i) {
                    outputArray[i] = rawData.charCodeAt(i);
                }
                return outputArray;
            }

            // Skip test connection - action not registered
            // This was causing 400 errors
            
            // Initialize online status
            let isOnline = false;

            // Track scheduled orders count globally for change detection
            let lastKnownScheduledOrderCount = -1;

            // Background check for scheduled orders (always running)
            function startScheduledOrdersMonitoring() {
                // Check scheduled orders every 15 seconds in background
                setInterval(() => {
                    const isOnline = localStorage.getItem('driver_online_status') === 'true';
                    if (isOnline) {
                        // Silently check for scheduled order changes
                        checkScheduledOrdersInBackground();
                    }
                }, 15000);
            }

            function checkScheduledOrdersInBackground() {
                try {
                    // Check if required data is available
                    if (typeof dispatch_ajax === 'undefined' || !dispatch_ajax || !dispatch_ajax.nonce || !dispatch_ajax.username) {
                        console.debug('Skipping scheduled order check - missing ajax data');
                        return;
                    }

                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 8000);

                    fetch(dispatch_ajax.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=get_driver_scheduled_orders&nonce=' + dispatch_ajax.nonce + '&username=' + encodeURIComponent(dispatch_ajax.username),
                        credentials: 'same-origin',
                        signal: controller.signal
                    })
                    .then(response => {
                        clearTimeout(timeoutId);
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            const currentScheduledCount = data.data.orders ? data.data.orders.length : 0;

                            if (lastKnownScheduledOrderCount !== -1 && currentScheduledCount !== lastKnownScheduledOrderCount) {
                                console.log('Background: Scheduled order count changed:', lastKnownScheduledOrderCount, '->', currentScheduledCount);

                                if (currentScheduledCount > lastKnownScheduledOrderCount) {
                                    // New scheduled order
                                    if (window.notificationSound && window.notificationSound.playScheduledSound) {
                                        window.notificationSound.playScheduledSound();
                                    }
                                    // Toast notification removed - using visual banner instead

                                    // Update display if on Warten page
                                    if (window.location.hash === '#warten') {
                                        // Directly update the display with the new data
                                        window.displayScheduledOrders(data.data.orders);
                                    }
                                } else if (currentScheduledCount < lastKnownScheduledOrderCount) {
                                    // Scheduled order removed
                                    if (window.notificationSound && window.notificationSound.playRemovedSound) {
                                        window.notificationSound.playRemovedSound();
                                    }
                                    // Toast notification removed - using visual banner instead

                                    // Update display if on Warten page
                                    if (window.location.hash === '#warten') {
                                        // Directly update the display with the new data
                                        window.displayScheduledOrders(data.data.orders);
                                    }
                                }
                            }

                            lastKnownScheduledOrderCount = currentScheduledCount;
                        }
                    })
                    .catch(error => {
                        // Silent fail for background checks
                        if (!error.name === 'AbortError') {
                            console.debug('Background scheduled order check failed:', error.message);
                        }
                    });
                } catch (error) {
                    console.debug('Error in background scheduled order check:', error);
                }
            }

            // Check notification permission on page load
            document.addEventListener('DOMContentLoaded', () => {
                console.log('Page loaded, checking notification status...');

                // Start background monitoring for scheduled orders
                startScheduledOrdersMonitoring();

                // Detailed browser capability check
                console.log('Browser details:', {
                    userAgent: navigator.userAgent,
                    platform: navigator.platform,
                    vendor: navigator.vendor,
                    standalone: window.navigator.standalone,
                    isSecureContext: window.isSecureContext
                });

                // Check if notifications are supported and not granted
                if ('Notification' in window) {
                    console.log('✅ Notification API is available');
                    console.log('Initial Notification permission:', Notification.permission);

                    // If permission is default (not asked yet), show a hint after driver goes online
                    if (Notification.permission === 'default') {
                        console.log('Notifications not yet requested - will prompt when driver goes online');
                    } else if (Notification.permission === 'denied') {
                        console.log('Notifications were denied - user needs to enable in browser settings');
                    } else if (Notification.permission === 'granted') {
                        console.log('Notifications already granted');
                    }
                } else {
                    console.log('❌ Notification API not available');

                    // Check for iOS
                    if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
                        console.log('iOS detected - PWA must be installed to home screen for notifications');

                        // Show iOS install hint if not standalone
                        if (!window.navigator.standalone) {
                            // Show prominent banner
                            const banner = document.getElementById('ios-install-banner');
                            if (banner) {
                                banner.style.display = 'block';
                            }

                            // Also show toast
                            setTimeout(() => {
                                showNotificationToast('📱 Fügen Sie die App zum Homescreen hinzu für Push-Benachrichtigungen', 'info', 8000);
                            }, 3000);
                        }
                    } else if (!window.isSecureContext) {
                        console.log('Not in secure context (HTTPS required for notifications)');
                    } else {
                        console.log('Browser does not support Notification API');
                    }
                }

                // Also check for Push API
                if ('PushManager' in window) {
                    console.log('✅ Push API is available');
                } else {
                    console.log('❌ Push API not available');
                }

                // Check Service Worker
                if ('serviceWorker' in navigator) {
                    console.log('✅ Service Worker is supported');
                } else {
                    console.log('❌ Service Worker not supported');
                }
            });

            // Global variable for mainContent to avoid redeclaration errors
            let mainContent;

            // Language system initialization
            let currentLanguage = localStorage.getItem('app_language') || 'de';

            // Translations object
            const translations = {
                de: {
                    // Navigation
                    orders: 'Bestellungen',
                    packlist: 'Packliste',
                    settings: 'Einstellungen',
                    offline: 'Offline',
                    online: 'Online',
                    map: 'Karte',
                    waiting: 'Warten',
                    performance: 'Leistung',
                    completed: 'Abgeschlossen',
                    completedOrders: 'Vollständige Bestellungen',

                    // Settings menu
                    profile: 'Profil',
                    deliveryPreferences: 'Lieferpräferenzen',
                    notifications: 'Benachrichtigungen',
                    navigation: 'Navigation',
                    language: 'Sprache',
                    support: 'Support',
                    display: 'Anzeige',
                    pushNotifications: 'Push-Benachrichtigungen',
                    appInfo: 'App Info',
                    about: 'Über',
                    logout: 'Abmelden',
                    backToDashboard: 'Zurück zum Dashboard',
                    helpAndFeedback: 'Hilfe und Feedback',
                    privacy: 'Datenschutz',

                    // Language settings
                    selectLanguage: 'Wählen Sie Ihre Sprache',
                    changeLanguageInfo: 'Die App wird in der gewählten Sprache angezeigt',
                    tip: 'Hinweis',
                    languageTip: 'Die Spracheinstellung wird lokal gespeichert und bleibt beim nächsten Login erhalten.',
                    languageChanged: 'Sprache geändert',

                    // Orders
                    noOrdersAvailable: 'Keine Bestellungen verfügbar',
                    loadingOrders: 'Lade Bestellungen...',
                    deliveryTime: 'Lieferzeit',
                    address: 'Adresse',
                    orderReady: 'Bestellung bereit',
                    markAsDelivered: 'Als geliefert markieren',
                    navigate: 'Navigieren',
                    call: 'Anrufen',
                    assigned: 'Zugewiesen',
                    deliver: 'Liefern',
                    notPacked: 'Noch nicht gepackt',
                    open: 'Offen',
                    showDetails: 'Details anzeigen',
                    totalAmount: 'Gesamtbetrag',
                    customer: 'Kunde',
                    name: 'Name',
                    phone: 'Telefon',
                    delivery: 'Lieferung',
                    items: 'Artikel',
                    subtotalItems: 'Teilsumme Artikel',
                    deliveryCommission: 'Liefer- und Kommissionspauschale',
                    orderTotal: 'Bestellsumme',
                    toPay: 'Zu zahlen',
                    noDepositItems: 'Kein Pfand-Artikel in dieser Bestellung!',
                    notDefined: 'Nicht definiert',
                    started: 'Gestartet',

                    // Order Details Page
                    order: 'Bestellung',
                    date: 'Datum',
                    time: 'Zeit',
                    plusCode: 'Plus Code',
                    note: 'Notiz',
                    today: 'Heute',
                    notSpecified: 'Nicht angegeben',
                    noItemsAvailable: 'Keine Artikel vorhanden',
                    tax: 'MwSt.',
                    shipping: 'Lieferung',
                    startNavigation: 'Navigation starten',
                    callCustomer: 'Kunde anrufen',
                    payWithSumup: 'Mit SumUp bezahlen',
                    markAsDeliveredButton: 'Als geliefert markieren',
                    loadingOrders: 'Lade Bestellungen...',
                    determiningLocation: 'Standort wird ermittelt...',
                    notDefined: 'Nicht definiert',

                    // Packliste
                    value: 'Wert',
                    all: 'Alle',
                    currentOrders: 'Aktuelle Aufträge',
                    scheduledOrders: 'Geplante Aufträge',
                    loadingPacklist: 'Lade Packliste...',

                    // Map/Routing
                    noDeliveryAddressesToday: 'Keine Lieferadressen heute',
                    newOrdersWillAppearHere: 'Neue Bestellungen werden hier angezeigt',

                    // Warten
                    newScheduledOrder: 'Neuer geplanter Auftrag',
                    newOrderAssignedForFutureDelivery: 'Ein neuer Auftrag wurde für zukünftige Lieferung zugewiesen',

                    // Leistung
                    loadingStatistics: 'Lade Statistiken...',
                    errorLoading: 'Fehler beim Laden',
                    development: 'Entwicklung',
                    week: 'Woche',
                    month: 'Monat',
                    performanceOverview: 'Leistungsübersicht',
                    totalDeliveries: 'Gesamt Lieferungen',
                    successRate: 'Erfolgsquote',
                    avgDeliveryTime: 'Ø Lieferzeit',
                    returnedDeposit: 'Erstattetes Pfand',
                    recentRatings: 'Aktuelle Bewertungen',
                    thisWeek: 'Diese Woche',
                    thisMonth: 'Dieser Monat',
                    rating: 'Bewertung'
                },
                en: {
                    // Navigation
                    orders: 'Orders',
                    packlist: 'Packing List',
                    settings: 'Settings',
                    offline: 'Offline',
                    online: 'Online',
                    map: 'Map',
                    waiting: 'Waiting',
                    performance: 'Performance',
                    completed: 'Completed',
                    completedOrders: 'Completed Orders',

                    // Settings menu
                    profile: 'Profile',
                    deliveryPreferences: 'Delivery Preferences',
                    notifications: 'Notifications',
                    navigation: 'Navigation',
                    language: 'Language',
                    support: 'Support',
                    display: 'Display',
                    pushNotifications: 'Push Notifications',
                    appInfo: 'App Info',
                    about: 'About',
                    logout: 'Logout',
                    backToDashboard: 'Back to Dashboard',
                    helpAndFeedback: 'Help and Feedback',
                    privacy: 'Privacy',

                    // Language settings
                    selectLanguage: 'Select Your Language',
                    changeLanguageInfo: 'The app will be displayed in the selected language',
                    tip: 'Tip',
                    languageTip: 'Language preference is saved locally and persists on next login.',
                    languageChanged: 'Language changed',

                    // Orders
                    noOrdersAvailable: 'No orders available',
                    loadingOrders: 'Loading orders...',
                    deliveryTime: 'Delivery Time',
                    address: 'Address',
                    orderReady: 'Order ready',
                    markAsDelivered: 'Mark as delivered',
                    navigate: 'Navigate',
                    call: 'Call',
                    assigned: 'Assigned',
                    deliver: 'Deliver',
                    notPacked: 'Not packed yet',
                    open: 'Open',
                    showDetails: 'Show details',
                    totalAmount: 'Total Amount',
                    customer: 'Customer',
                    name: 'Name',
                    phone: 'Phone',
                    delivery: 'Delivery',
                    items: 'Items',
                    subtotalItems: 'Subtotal Items',
                    deliveryCommission: 'Delivery and Commission Fee',
                    orderTotal: 'Order Total',
                    toPay: 'To Pay',
                    noDepositItems: 'No deposit items in this order!',
                    notDefined: 'Not defined',
                    started: 'Started',

                    // Order Details Page
                    order: 'Order',
                    date: 'Date',
                    time: 'Time',
                    plusCode: 'Plus Code',
                    note: 'Note',
                    today: 'Today',
                    notSpecified: 'Not specified',
                    noItemsAvailable: 'No items available',
                    tax: 'Tax',
                    shipping: 'Shipping',
                    startNavigation: 'Start Navigation',
                    callCustomer: 'Call Customer',
                    payWithSumup: 'Pay with SumUp',
                    markAsDeliveredButton: 'Mark as Delivered',
                    loadingOrders: 'Loading orders...',
                    determiningLocation: 'Determining location...',
                    notDefined: 'Not defined',

                    // Packliste
                    value: 'Value',
                    all: 'All',
                    currentOrders: 'Current Orders',
                    scheduledOrders: 'Scheduled Orders',
                    loadingPacklist: 'Loading packing list...',

                    // Map/Routing
                    noDeliveryAddressesToday: 'No delivery addresses today',
                    newOrdersWillAppearHere: 'New orders will appear here',

                    // Warten
                    newScheduledOrder: 'New scheduled order',
                    newOrderAssignedForFutureDelivery: 'A new order has been assigned for future delivery',

                    // Leistung
                    loadingStatistics: 'Loading statistics...',
                    errorLoading: 'Error loading',
                    development: 'Development',
                    week: 'Week',
                    month: 'Month',
                    performanceOverview: 'Performance Overview',
                    totalDeliveries: 'Total Deliveries',
                    successRate: 'Success Rate',
                    avgDeliveryTime: 'Avg. Delivery Time',
                    returnedDeposit: 'Returned Deposit',
                    recentRatings: 'Recent Ratings',
                    thisWeek: 'This Week',
                    thisMonth: 'This Month',
                    rating: 'Rating'
                },
                es: {
                    // Navigation
                    orders: 'Pedidos',
                    packlist: 'Lista de Empaque',
                    settings: 'Configuración',
                    offline: 'Desconectado',
                    online: 'En línea',
                    map: 'Mapa',
                    waiting: 'Esperando',
                    performance: 'Rendimiento',
                    completed: 'Completado',
                    completedOrders: 'Pedidos Completados',

                    // Settings menu
                    profile: 'Perfil',
                    deliveryPreferences: 'Preferencias de Entrega',
                    notifications: 'Notificaciones',
                    navigation: 'Navegación',
                    language: 'Idioma',
                    support: 'Soporte',
                    display: 'Pantalla',
                    pushNotifications: 'Notificaciones Push',
                    appInfo: 'Info de App',
                    about: 'Acerca de',
                    logout: 'Cerrar Sesión',
                    backToDashboard: 'Volver al Panel',
                    helpAndFeedback: 'Ayuda y Comentarios',
                    privacy: 'Privacidad',

                    // Language settings
                    selectLanguage: 'Seleccione su idioma',
                    changeLanguageInfo: 'La aplicación se mostrará en el idioma seleccionado',
                    tip: 'Consejo',
                    languageTip: 'La preferencia de idioma se guarda localmente y persiste en el próximo inicio de sesión.',
                    languageChanged: 'Idioma cambiado',

                    // Orders
                    noOrdersAvailable: 'No hay pedidos disponibles',
                    loadingOrders: 'Cargando pedidos...',
                    deliveryTime: 'Hora de entrega',
                    address: 'Dirección',
                    orderReady: 'Pedido listo',
                    markAsDelivered: 'Marcar como entregado',
                    navigate: 'Navegar',
                    call: 'Llamar',
                    assigned: 'Asignado',
                    deliver: 'Entregar',
                    notPacked: 'Aún no empacado',
                    open: 'Abierto',
                    showDetails: 'Mostrar detalles',
                    totalAmount: 'Importe Total',
                    customer: 'Cliente',
                    name: 'Nombre',
                    phone: 'Teléfono',
                    delivery: 'Entrega',
                    items: 'Artículos',
                    subtotalItems: 'Subtotal Artículos',
                    deliveryCommission: 'Tarifa de Entrega y Comisión',
                    orderTotal: 'Total del Pedido',
                    toPay: 'A Pagar',
                    noDepositItems: '¡No hay artículos de depósito en este pedido!',
                    notDefined: 'No definido',
                    started: 'Iniciado',

                    // Order Details Page
                    order: 'Pedido',
                    date: 'Fecha',
                    time: 'Hora',
                    plusCode: 'Plus Code',
                    note: 'Nota',
                    today: 'Hoy',
                    notSpecified: 'No especificado',
                    noItemsAvailable: 'No hay artículos disponibles',
                    tax: 'Impuesto',
                    shipping: 'Envío',
                    startNavigation: 'Iniciar Navegación',
                    callCustomer: 'Llamar Cliente',
                    payWithSumup: 'Pagar con SumUp',
                    markAsDeliveredButton: 'Marcar como Entregado',
                    loadingOrders: 'Cargando pedidos...',
                    determiningLocation: 'Determinando ubicación...',
                    notDefined: 'No definido',

                    // Packliste
                    value: 'Valor',
                    all: 'Todos',
                    currentOrders: 'Pedidos Actuales',
                    scheduledOrders: 'Pedidos Programados',
                    loadingPacklist: 'Cargando lista de empaque...',

                    // Map/Routing
                    noDeliveryAddressesToday: 'No hay direcciones de entrega hoy',
                    newOrdersWillAppearHere: 'Los nuevos pedidos aparecerán aquí',

                    // Warten
                    newScheduledOrder: 'Nuevo pedido programado',
                    newOrderAssignedForFutureDelivery: 'Se ha asignado un nuevo pedido para entrega futura',

                    // Leistung
                    loadingStatistics: 'Cargando estadísticas...',
                    errorLoading: 'Error al cargar',
                    development: 'Desarrollo',
                    week: 'Semana',
                    month: 'Mes',
                    performanceOverview: 'Resumen de Rendimiento',
                    totalDeliveries: 'Entregas Totales',
                    successRate: 'Tasa de Éxito',
                    avgDeliveryTime: 'Tiempo Promedio',
                    returnedDeposit: 'Depósito Devuelto',
                    recentRatings: 'Calificaciones Recientes',
                    thisWeek: 'Esta Semana',
                    thisMonth: 'Este Mes',
                    rating: 'Calificación'
                }
            };

            // Function to get translation
            function t(key) {
                return translations[currentLanguage] && translations[currentLanguage][key]
                    ? translations[currentLanguage][key]
                    : translations['de'][key] || key;
            }

            // Apply translations to UI
            function applyTranslations() {
                // Update navigation items with data-translate attribute
                const navItems = document.querySelectorAll('[data-translate]');
                navItems.forEach(item => {
                    const key = item.getAttribute('data-translate');
                    if (key && translations[currentLanguage] && translations[currentLanguage][key]) {
                        item.textContent = translations[currentLanguage][key];
                    }
                });

                // Update side menu items
                const menuLinks = document.querySelectorAll('.menu-link span');
                if (menuLinks.length >= 6) {
                    menuLinks[0].textContent = translations[currentLanguage].orders || 'Bestellungen';
                    menuLinks[1].textContent = translations[currentLanguage].packlist || 'Packliste';
                    menuLinks[2].textContent = translations[currentLanguage].map || 'Karte';
                    menuLinks[3].textContent = translations[currentLanguage].completed || 'Abgeschlossen';
                    menuLinks[4].textContent = translations[currentLanguage].performance || 'Leistung';
                    menuLinks[5].textContent = translations[currentLanguage].settings || 'Einstellungen';
                }

                // Update bottom menu buttons
                const offlineBtn = document.querySelector('.menu-offline-btn');
                if (offlineBtn) {
                    // Find the text node after the SVG
                    for (let i = 0; i < offlineBtn.childNodes.length; i++) {
                        if (offlineBtn.childNodes[i].nodeType === Node.TEXT_NODE) {
                            const text = offlineBtn.childNodes[i].textContent.trim();
                            if (text.includes('Offline') || text.includes('Desconectar') || text === 'Go Offline') {
                                offlineBtn.childNodes[i].textContent = currentLanguage === 'en' ? '\n                        Go Offline\n                    ' :
                                    currentLanguage === 'es' ? '\n                        Desconectar\n                    ' :
                                    '\n                        Offline gehen\n                    ';
                                break;
                            }
                        }
                    }
                }

                const logoutLink = document.querySelector('.menu-logout-link');
                if (logoutLink) {
                    logoutLink.textContent = translations[currentLanguage].logout || 'Abmelden';
                }

                // Update bottom navigation
                const bottomNavLabels = document.querySelectorAll('.bottom-navigation .label');
                if (bottomNavLabels.length >= 4) {
                    bottomNavLabels[0].textContent = translations[currentLanguage].orders || 'Bestellungen';
                    bottomNavLabels[1].textContent = translations[currentLanguage].map || 'Karte';
                    bottomNavLabels[2].textContent = translations[currentLanguage].waiting || 'Warten';
                    bottomNavLabels[3].textContent = translations[currentLanguage].packlist || 'Packliste';
                }

                // Update current language display
                const langDisplay = document.getElementById('current-language');
                if (langDisplay) {
                    const langNames = {
                        'de': 'Deutsch',
                        'en': 'English',
                        'es': 'Español'
                    };
                    langDisplay.textContent = langNames[currentLanguage];
                }
            }

            // Initialize translations on page load
            document.addEventListener('DOMContentLoaded', () => {
                applyTranslations();
            });

            // Image Modal für Produktfotos - Fahrerseite
            window.showImageModal = function showImageModal(imageUrl, productName) {
                if (!imageUrl) return;

                // Remove existing modal if any
                const existingModal = document.getElementById('image-modal');
                if (existingModal) {
                    existingModal.remove();
                }

                // Create modal overlay
                const modal = document.createElement('div');
                modal.id = 'image-modal';
                modal.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.95);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10000;
                    cursor: pointer;
                    padding: 20px;
                    box-sizing: border-box;
                `;

                // Create image container
                const imageContainer = document.createElement('div');
                imageContainer.style.cssText = `
                    max-width: 95%;
                    max-height: 95%;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    cursor: default;
                `;

                // Create image
                const img = document.createElement('img');
                img.src = imageUrl;
                img.alt = productName || 'Produkt';
                img.style.cssText = `
                    max-width: 100%;
                    max-height: 90vh;
                    object-fit: contain;
                    border-radius: 8px;
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
                `;

                // Create title if productName exists
                if (productName) {
                    const title = document.createElement('div');
                    title.textContent = productName;
                    title.style.cssText = `
                        color: white;
                        font-size: 18px;
                        font-weight: 600;
                        margin-top: 15px;
                        text-align: center;
                        padding: 0 20px;
                    `;
                    imageContainer.appendChild(title);
                }

                // Create close instruction
                const closeText = document.createElement('div');
                closeText.textContent = 'Tippen zum Schließen';
                closeText.style.cssText = `
                    color: rgba(255, 255, 255, 0.7);
                    font-size: 14px;
                    margin-top: 10px;
                    text-align: center;
                `;

                imageContainer.appendChild(img);
                imageContainer.appendChild(closeText);
                modal.appendChild(imageContainer);

                // Close modal on click
                modal.addEventListener('click', function() {
                    modal.remove();
                });

                // Prevent event bubbling on image container
                imageContainer.addEventListener('click', function(e) {
                    e.stopPropagation();
                });

                // Close on escape key
                const handleEscape = function(e) {
                    if (e.key === 'Escape') {
                        modal.remove();
                        document.removeEventListener('keydown', handleEscape);
                    }
                };
                document.addEventListener('keydown', handleEscape);

                // Add to body
                document.body.appendChild(modal);
            };
            
            // Make toggleMenu globally available
            window.toggleMenu = function() {
                try {
                    const menu = document.querySelector('.side-menu');
                    const overlay = document.querySelector('.side-menu-overlay');
                    
                    if (menu && overlay) {
                        menu.classList.toggle('open');
                        overlay.classList.toggle('open');
                    } else {
                        console.error('Menu elements not found:', {menu, overlay});
                    }
                } catch (error) {
                    console.error('Error in toggleMenu:', error);
                }
            };
            
            // Also create regular function for backwards compatibility
            // toggleMenu is already defined as window.toggleMenu above
            
            // Load initial online status on page load
            document.addEventListener('DOMContentLoaded', function() {
                try {
                    const currentStatus = '<?php echo esc_js(get_user_meta(get_current_user_id(), 'driver_online_status', true) ?: 'offline'); ?>';
                    isOnline = currentStatus === 'online';
                    
                    // Sync localStorage with database status
                    localStorage.setItem('driver_online_status', isOnline ? 'true' : 'false');
                
                const button = document.getElementById('onlineToggleLarge');
                if (button) {
                    if (isOnline) {
                        button.style.display = 'none'; // Hide button when online
                        updateDashboardStatus('online', 'Online', false);
                        updateHamburgerMenuForOnlineStatus();
                        // Automatically show orders if already online on page load
                        setTimeout(() => {
                            showBestellungen();
                        }, 500); // Small delay to ensure everything is loaded
                    } else {
                        button.style.display = 'block'; // Show button when offline
                        button.textContent = 'Online gehen';
                        button.classList.add('offline');
                        button.classList.remove('online');
                        updateDashboardStatus('offline', 'Offline', false);
                        updateHamburgerMenuForOfflineStatus();
                    }
                }
                
                // Set up automatic status checking every 10 seconds
                setInterval(function() {
                    checkAndUpdateDriverStatus();
                }, 10000);
                
                // Set up automatic order checking for online drivers
                const orderCheckInterval = setInterval(function() {
                    // Force check localStorage again
                    const storedStatus = localStorage.getItem('driver_online_status');
                    const currentOnlineStatus = storedStatus === 'true';
                    const currentIsOnline = isOnline; // From the global variable
                    
                    
                    // Also check the actual button text as fallback
                    const button = document.getElementById('onlineToggleLarge');
                    const buttonIndicatesOnline = button && button.textContent.includes('Offline gehen');
                    
                    if (currentOnlineStatus || currentIsOnline || buttonIndicatesOnline) {
                        // Only call if function is defined
                        if (typeof checkForNewOrders === 'function') {
                            checkForNewOrders();
                        }
                    } else {
                    }
                }, 15000); // Check every 15 seconds
                
                // Set up automatic date change detection and order refresh
                let lastCheckedDate = new Date().toDateString();
                
                setInterval(function() {
                    const currentDate = new Date().toDateString();
                    
                    // Check if date has changed
                    if (currentDate !== lastCheckedDate) {
                        lastCheckedDate = currentDate;
                        
                        // Check which page is currently active
                        const currentPage = window.location.hash;
                        
                        // Refresh the current page to move orders to correct sections
                        if (currentPage === '#bestellungen' || !currentPage) {
                            // Reload Bestellungen page to show new today's orders
                            if (typeof loadDriverOrders === 'function') {
                                loadDriverOrders();
                            }
                        } else if (currentPage === '#warten') {
                            // Reload Warten page to move today's orders to Bestellungen
                            if (typeof loadScheduledOrders === 'function') {
                                loadScheduledOrders();
                            }
                        }
                        
                        // Show notification to driver
                        if (typeof showNotification === 'function') {
                            showNotification('Datum gewechselt - Aufträge wurden aktualisiert', 'info');
                        }
                    }
                }, 60000); // Check every minute
                
                
                } catch (error) {
                    console.error('Error initializing status:', error);
                }
            });
            
            function checkAndUpdateDriverStatus() {
                try {
                    // Skip if ajax_url is not available
                    if (!dispatch_ajax || !dispatch_ajax.ajax_url) {
                        console.warn('AJAX URL not available, skipping status check');
                        return;
                    }

                    // Add timeout and retry logic
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 8000); // 8 second timeout

                    // Check current status from server without changing it
                    fetch(dispatch_ajax.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=get_driver_current_status&nonce=' + dispatch_ajax.nonce + '&username=' + encodeURIComponent(dispatch_ajax.username),
                        credentials: 'same-origin',
                        signal: controller.signal
                    })
                    .then(response => {
                        clearTimeout(timeoutId);
                        if (response.status === 400 || response.status === 403) {
                            console.warn('Authentication issue, user might need to log in again');
                            return { success: false, requireLogin: true };
                        }
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            const currentStatus = data.data.status;
                            const currentIsOnline = currentStatus === 'online';
                            
                            // Only update UI if status has changed
                            if (currentIsOnline !== isOnline) {
                                
                                isOnline = currentIsOnline;
                                localStorage.setItem('driver_online_status', isOnline ? 'true' : 'false');
                                
                                const button = document.getElementById('onlineToggleLarge');
                                if (button) {
                                    if (isOnline) {
                                        button.style.display = 'none'; // Hide button when online
                                        updateDashboardStatus('online', 'Online', false);
                                        updateHamburgerMenuForOnlineStatus();
                                    } else {
                                        button.style.display = 'block'; // Show button when offline
                                        button.textContent = 'Online gehen';
                                        button.classList.add('offline');
                                        button.classList.remove('online');
                                        updateDashboardStatus('offline', 'Offline', false);
                                        updateHamburgerMenuForOfflineStatus();
                                    }
                                }
                            }
                        }
                    })
                    .catch(error => {
                        clearTimeout(timeoutId);
                        if (error.name === 'AbortError') {
                            console.warn('Status check timed out');
                        } else if (error.message && error.message.includes('Load failed')) {
                            console.warn('Network connection issue, will retry on next interval');
                        } else {
                            console.error('Error checking status:', error);
                        }
                    });
                } catch (error) {
                    console.error('Error in checkAndUpdateDriverStatus:', error);
                }
            }
            
            function toggleOnlineStatus() {
                try {
                    const button = document.getElementById('onlineToggleLarge');
                    
                    if (!button) {
                        console.error('Button not found!');
                        alert('Button nicht gefunden!');
                        return;
                    }
                    
                    // Use the same working logic as the test button
                    
                    button.disabled = true;
                    button.textContent = 'Wird aktualisiert...';
                    
                    fetch(dispatch_ajax.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=toggle_driver_online_status&driver_id=<?php echo $current_user->ID; ?>&nonce=' + dispatch_ajax.nonce,
                        credentials: 'same-origin'
                    })
                    .then(response => {
                        return response.text();
                    })
                    .then(text => {
                        try {
                            const data = JSON.parse(text);
                            
                            if (data.success) {
                                const status = data.data.status;
                                const hasOrders = data.data.has_orders;
                                
                                isOnline = status === 'online';
                                localStorage.setItem('driver_online_status', isOnline ? 'true' : 'false');
                                
                                // Update button
                                button.disabled = false;
                                if (isOnline) {
                                    button.style.display = 'none'; // Hide button when online
                                } else {
                                    button.style.display = 'block'; // Show button when offline
                                    button.textContent = 'Online gehen';
                                    button.classList.remove('online');
                                    button.classList.add('offline');
                                }
                                
                                // If going online, reload page to show menu and navigation
                                if (isOnline) {
                                    // Reload the page to show the hamburger menu and navigation
                                    window.location.reload();

                                    // Request push notifications when going online
                                    console.log('Driver went online, checking push permission status...');
                                    console.log('Notification permission:', 'Notification' in window ? Notification.permission : 'Not supported');

                                    // Immediately show permission request
                                    if ('Notification' in window && Notification.permission === 'default') {
                                        // Show custom banner immediately
                                        const permissionBanner = document.createElement('div');
                                        permissionBanner.id = 'push-permission-banner';
                                        permissionBanner.innerHTML = `
                                            <div style="
                                                position: fixed;
                                                top: 80px;
                                                left: 50%;
                                                transform: translateX(-50%);
                                                background: #3b82f6;
                                                color: white;
                                                padding: 15px 20px;
                                                border-radius: 12px;
                                                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                                                z-index: 10000;
                                                max-width: 90%;
                                                font-size: 14px;
                                            ">
                                                <div style="margin-bottom: 10px;">
                                                    <strong>🔔 Benachrichtigungen aktivieren</strong>
                                                </div>
                                                <div style="margin-bottom: 15px;">
                                                    Erhalte Push-Benachrichtigungen bei neuen Bestellungen
                                                </div>
                                                <div style="display: flex; gap: 10px;">
                                                    <button onclick="handleAllowPush()" style="
                                                        background: white;
                                                        color: #3b82f6;
                                                        border: none;
                                                        padding: 8px 16px;
                                                        border-radius: 6px;
                                                        font-weight: bold;
                                                        cursor: pointer;
                                                        flex: 1;
                                                    ">Erlauben</button>
                                                    <button onclick="handleDenyPush()" style="
                                                        background: transparent;
                                                        color: white;
                                                        border: 1px solid white;
                                                        padding: 8px 16px;
                                                        border-radius: 6px;
                                                        cursor: pointer;
                                                        flex: 1;
                                                    ">Später</button>
                                                </div>
                                            </div>
                                        `;
                                        document.body.appendChild(permissionBanner);

                                        // Define handlers
                                        window.handleAllowPush = async () => {
                                            console.log('User clicked Allow...');
                                            try {
                                                const permission = await Notification.requestPermission();
                                                console.log('Permission result:', permission);

                                                const banner = document.getElementById('push-permission-banner');
                                                if (banner) banner.remove();

                                                if (permission === 'granted') {
                                                    console.log('Push-Benachrichtigungen erlaubt!');
                                                    showNotificationToast('✅ Push-Benachrichtigungen aktiviert!', 'success');

                                                    // Request FCM token if available
                                                    if (window.requestFCMToken && typeof window.requestFCMToken === 'function') {
                                                        console.log('Requesting FCM token after permission granted...');
                                                        window.requestFCMToken();
                                                    }

                                                    // Try to get service worker and subscribe
                                                    if ('serviceWorker' in navigator) {
                                                        navigator.serviceWorker.ready.then(registration => {
                                                            if (typeof subscribeToPush === 'function') {
                                                                subscribeToPush(registration);
                                                            }
                                                        });
                                                    }
                                                } else if (permission === 'denied') {
                                                    showNotificationToast('❌ Push-Benachrichtigungen wurden blockiert', 'error');
                                                }
                                            } catch (error) {
                                                console.error('Error requesting permission:', error);
                                                showNotificationToast('❌ Fehler beim Aktivieren der Benachrichtigungen', 'error');
                                            }
                                        };

                                        window.handleDenyPush = () => {
                                            console.log('User clicked Later...');
                                            const banner = document.getElementById('push-permission-banner');
                                            if (banner) banner.remove();
                                            showNotificationToast('ℹ️ Sie können Benachrichtigungen später aktivieren', 'info');
                                        };
                                    } else if ('Notification' in window && Notification.permission === 'granted') {
                                        // Already granted, try to subscribe
                                        console.log('Notifications already granted, subscribing to push...');

                                        // Request FCM token if available
                                        if (window.requestFCMToken && typeof window.requestFCMToken === 'function') {
                                            console.log('Requesting FCM token for online driver...');
                                            window.requestFCMToken();
                                        }

                                        if ('serviceWorker' in navigator) {
                                            navigator.serviceWorker.ready.then(registration => {
                                                console.log('Service worker ready, subscribing to push...');
                                                if (typeof subscribeToPush === 'function') {
                                                    subscribeToPush(registration);
                                                } else {
                                                    console.error('subscribeToPush function not found');
                                                }
                                            }).catch(error => {
                                                console.error('Service worker not ready:', error);
                                            });
                                        }
                                    } else if ('Notification' in window && Notification.permission === 'denied') {
                                        console.log('Notifications denied by user');
                                        showNotificationToast('⚠️ Benachrichtigungen sind blockiert. Bitte in Browser-Einstellungen aktivieren.', 'warning');
                                    }

                                    // Reset order count to properly detect new orders
                                    lastKnownOrderCount = -1;

                                    // Automatically show orders page when going online
                                    showBestellungen();
                                } else {
                                    updateHamburgerMenuForOfflineStatus();
                                    showDashboard();
                                }
                                
                                alert('Status erfolgreich aktualisiert: ' + (isOnline ? 'Online' : 'Offline'));
                            } else {
                                throw new Error('Server error: ' + JSON.stringify(data));
                            }
                        } catch (e) {
                            console.error('Parse error:', e);
                            alert('Response: ' + text.substring(0, 200));
                            button.disabled = false;
                            button.textContent = isOnline ? 'Offline gehen' : 'Online gehen';
                        }
                    })
                    .catch(error => {
                        console.error('toggleOnlineStatus error:', error);
                        alert('Fehler: ' + error.message);
                        button.disabled = false;
                        button.textContent = isOnline ? 'Offline gehen' : 'Online gehen';
                    });
                    
                    return; // Skip the old logic below
                
                // Show loading state
                button.disabled = true;
                button.textContent = 'Wird aktualisiert...';
                
                // Add timeout for fetch request
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
                
                // Make AJAX call to update driver status and check orders
                const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
                const nonce = '<?php echo wp_create_nonce('dispatch_nonce'); ?>';
                
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=toggle_driver_online_status&nonce=' + nonce,
                    signal: controller.signal,
                    credentials: 'same-origin' // Important for cookies
                })
                .then(response => {
                    clearTimeout(timeoutId); // Clear timeout on response
                    
                    // Don't throw error immediately, try to parse response first
                    return response.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        return data;
                    } catch (e) {
                        console.error('Failed to parse JSON:', e);
                        console.error('Raw response:', text);
                        throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                    }
                })
                .then(data => {
                    if (data.success) {
                        const status = data.data.status;
                        const message = data.data.message;
                        const hasOrders = data.data.has_orders;
                        
                        isOnline = status === 'online';
                        
                        // Update localStorage with new status
                        localStorage.setItem('driver_online_status', isOnline ? 'true' : 'false');
                        
                        // Update button and re-enable it
                        button.disabled = false;
                        if (isOnline) {
                            button.style.display = 'none'; // Hide button when online
                        } else {
                            button.style.display = 'block'; // Show button when offline
                            button.textContent = 'Online gehen';
                            button.classList.remove('online');
                            button.classList.add('offline');
                        }
                        
                        // Update status display in header/dashboard
                        updateDashboardStatus(status, message, hasOrders);
                        
                        // If going online, check for orders and show appropriate view
                        if (isOnline) {
                            // Update hamburger menu for online status
                            updateHamburgerMenuForOnlineStatus();

                            if (hasOrders) {
                                // Initialize order count for new order detection
                                lastKnownOrderCount = data.data.orders_count || 0;
                                // Automatically show orders page when going online
                                showBestellungen();
                            } else {
                                // Initialize order count as 0
                                lastKnownOrderCount = 0;
                                // Show orders page even if empty (will show empty state)
                                showBestellungen();
                            }
                        } else {
                            // Going offline - restore normal menu and dashboard
                            updateHamburgerMenuForOfflineStatus();
                            showDashboard();
                        }
                    } else {
                        console.error('Status update failed:', data.data);
                        alert('Fehler beim Aktualisieren des Status');
                    }
                })
                .catch(error => {
                    console.error('Status error:', error);
                    let errorMessage = 'Netzwerkfehler beim Aktualisieren des Status';
                    
                    if (error.name === 'AbortError') {
                        errorMessage = 'Anfrage hat zu lange gedauert. Bitte erneut versuchen.';
                    } else if (error.message && error.message.includes('HTTP error')) {
                        errorMessage = 'Server-Fehler. Bitte Seite neu laden.';
                    }
                    
                    alert(errorMessage);
                    
                    // Reset button on error
                    button.disabled = false;
                    if (isOnline) {
                        button.textContent = 'Offline gehen';
                    } else {
                        button.textContent = 'Online gehen';
                    }
                })
                .finally(() => {
                    button.disabled = false;
                });
                } catch (error) {
                    console.error('Error in toggleOnlineStatus:', error);
                    alert('Fehler beim Online-Status: ' + error.message);
                }
            }
            
            function updateDashboardStatus(status, message, hasOrders) {
                try {
                    // Update status indicator in the dashboard
                    const statusIndicator = document.querySelector('.status-indicator');
                    const statusText = document.querySelector('.status-text');
                    const statusDot = document.querySelector('.status-dot');
                    
                    if (statusIndicator && statusText && statusDot) {
                        statusText.textContent = message;
                        
                        if (status === 'online') {
                            statusIndicator.style.background = 'rgba(16, 185, 129, 0.1)';
                            statusIndicator.style.borderColor = 'rgba(16, 185, 129, 0.3)';
                            statusDot.style.background = '#10b981';
                        } else {
                            statusIndicator.style.background = 'rgba(255, 193, 7, 0.1)';
                            statusIndicator.style.borderColor = 'rgba(255, 193, 7, 0.3)';
                            statusDot.style.background = '#ffc107';
                        }
                    }
                    
                    // Update status displays (both desktop and mobile profile)
                    const driverStatusDisplay = document.getElementById('driver-status-display');
                    const mobileStatusDisplay = document.getElementById('mobile-status-display');
                    const statusDisplayText = status === 'online' ? 'Online' : 'Offline';
                    
                    if (driverStatusDisplay) {
                        driverStatusDisplay.textContent = statusDisplayText;
                    }
                    if (mobileStatusDisplay) {
                        mobileStatusDisplay.textContent = statusDisplayText;
                    }
                } catch (error) {
                    console.error('Error in updateDashboardStatus:', error);
                }
            }
            
            // Handle bottom navigation
            document.querySelectorAll('.nav-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all items
                    document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
                    
                    // Add active class to clicked item
                    this.classList.add('active');
                    
                    // Handle navigation based on href
                    const href = this.getAttribute('href');
                    switch(href) {
                        case '#bestellungen':
                            showBestellungen();
                            break;
                        case '#karte':
                            showKarte();
                            break;
                        case '#routing':
                            showRouting();
                            break;
                        case '#warten':
                            showWarten();
                            break;
                        case '#leistung':
                            showLeistung();
                            break;
                    }
                });
            });
            
            // Close menu when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.side-menu') && !e.target.closest('.hamburger-menu')) {
                    const menu = document.querySelector('.side-menu');
                    const overlay = document.querySelector('.side-menu-overlay');

                    if (menu && menu.classList.contains('open')) {
                        menu.classList.remove('open');
                        if (overlay) {
                            overlay.classList.remove('open');
                        }
                    }
                }
            });

            // Toggle Hamburger Menu Function
            function toggleMenu() {
                const menu = document.querySelector('.side-menu');
                const overlay = document.querySelector('.side-menu-overlay');

                if (!menu || !overlay) {
                    console.warn('Menu or overlay not found');
                    return;
                }

                // Toggle open class
                menu.classList.toggle('open');
                overlay.classList.toggle('open');
            }

            // Helper function to show back arrow in hamburger menu
            function showBackArrowInHamburger(targetFunction) {
                const hamburgerBtn = document.querySelector('.hamburger-menu');
                if (hamburgerBtn) {
                    hamburgerBtn.innerHTML = `
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" style="display: block;">
                            <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2z"/>
                        </svg>
                    `;
                    hamburgerBtn.onclick = targetFunction;
                    hamburgerBtn.style.display = 'block';
                    hamburgerBtn.style.zIndex = '100';
                }
            }

            // Helper function to restore hamburger menu
            function restoreHamburgerMenu() {
                const hamburgerBtn = document.querySelector('.hamburger-menu');
                if (hamburgerBtn) {
                    hamburgerBtn.innerHTML = '☰';
                    hamburgerBtn.onclick = toggleMenu;
                    hamburgerBtn.style.display = 'block';
                }

                // Clear header-right (remove any buttons like "Speichern")
                const headerRight = document.querySelector('.header-right');
                if (headerRight) {
                    headerRight.innerHTML = '';
                }
            }

            // Mobile Profile Function
            function showProfile() {
                // Close hamburger menu first
                if (typeof toggleMenu === 'function') {
                    toggleMenu();
                }

                // Update header title
                const headerTitle = document.querySelector('.header-title');
                if (headerTitle) {
                    headerTitle.textContent = translations[currentLanguage].profile || 'Profil';
                }

                // Replace hamburger menu with back arrow
                showBackArrowInHamburger(function() { showEinstellungen(); });

                // Add "Speichern" button to header-right
                const headerRight = document.querySelector('.header-right');
                if (headerRight) {
                    headerRight.innerHTML = `
                        <button onclick="saveMobileProfile()" style="background: none; border: none; color: #10b981; font-size: 15px; font-weight: 600; cursor: pointer; padding: 8px 4px; margin-right: 8px;">
                            Speichern
                        </button>
                    `;
                }

                // Reset main-content to normal width
                mainContent = document.querySelector('.main-content');
                if (mainContent) {
                    mainContent.className = 'main-content';
                }

                // Get current user info via AJAX
                fetch(dispatch_ajax.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=dispatch_get_mobile_profile&nonce=' + dispatch_ajax.nonce + ''
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayMobileProfile(data.data);
                    } else {
                        console.error('Profile load failed:', data);
                        // Fallback to admin profile
                        window.location.href = '<?php echo admin_url("admin.php?page=dispatch-driver-profile&driver_id=" . get_current_user_id()); ?>';
                    }
                })
                .catch(error => {
                    console.error('Profile error:', error);
                    // Fallback to admin profile
                    window.location.href = '<?php echo admin_url("admin.php?page=dispatch-driver-profile&driver_id=" . get_current_user_id()); ?>';
                });
            }
            
            // Mobile Orders Function
            function showBestellungen() {
                try {
                    // Update URL hash for tracking
                    window.location.hash = 'bestellungen';

                    // Close hamburger menu without toggle (force close)
                    const menu = document.querySelector('.side-menu');
                    const overlay = document.querySelector('.side-menu-overlay');
                    if (menu && overlay) {
                        menu.classList.remove('open');
                        overlay.classList.remove('open');
                    }
                    
                    // Check if driver is online and ensure correct menu is displayed
                    const isOnline = localStorage.getItem('driver_online_status') === 'true';
                    if (isOnline) {
                        // Make sure we have the online menu
                        updateHamburgerMenuForOnlineStatus();
                    }
                    
                    // Update header title and restore normal header layout
                    const headerTitle = document.querySelector('.header-title');
                    if (headerTitle) {
                        headerTitle.textContent = translations[currentLanguage].orders || 'Bestellungen';
                        // Reset title styling for normal header
                        headerTitle.style.cssText = '';
                    }
                    
                    // Restore normal header layout
                    const header = document.querySelector('.header, .app-header, .top-header');
                    if (header) {
                        header.style.cssText = ''; // Reset any custom styling
                    }
                    
                    // Reset main-content to normal width
                    mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        mainContent.className = 'main-content orders-page';
                    }
                    
                    // Remove any back arrow that might exist
                    const headerLeft = document.querySelector('.header-left');
                    if (headerLeft) {
                        headerLeft.innerHTML = ''; // Clear back arrow
                        headerLeft.style.cssText = ''; // Reset styling
                    }
                    
                    // Remove any fixed back arrows
                    const fixedBackArrow = document.getElementById('vollstaendige-back-arrow');
                    if (fixedBackArrow) {
                        fixedBackArrow.remove();
                    }
                    
                    const fixedBackArrow2 = document.getElementById('vollstaendige-back-arrow-fixed');
                    if (fixedBackArrow2) {
                        fixedBackArrow2.remove();
                    }
                    
                    // Restore hamburger menu
                    const headerRight = document.querySelector('.header-right');
                    if (headerRight) {
                        headerRight.style.display = ''; // Restore visibility
                    }
                    
                    // Remove header spacer if it exists
                    const headerSpacer = document.querySelector('.header-spacer');
                    if (headerSpacer) {
                        headerSpacer.remove();
                    }
                    
                    // Show any hidden menu toggle buttons
                    const menuToggle = document.querySelector('.menu-toggle, .hamburger-menu, [onclick*="toggleMenu"]');
                    if (menuToggle) {
                        menuToggle.style.display = '';
                    }
                    
                    mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        // Show loading state
                        mainContent.innerHTML = `
                            <div class="empty-state-screen">
                                <div class="empty-state-message">${translations[currentLanguage].loadingOrders}</div>
                            </div>
                        `;
                        
                        // Load actual orders
                        loadDriverOrders();

                        // No auto-refresh needed - we have push notifications!
                    }
                } catch (error) {
                    console.error('Error in showBestellungen:', error);
                }
            }
            
            function loadDriverOrders() {
                try {
                    fetch(dispatch_ajax.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=get_driver_assigned_orders&nonce=' + dispatch_ajax.nonce + '&username=' + encodeURIComponent(dispatch_ajax.username),
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // AJAX handler returns orders in data.data.orders
                            displayOrders(data.data.orders);
                        } else {
                            console.error('Failed to load orders:', data.data);
                            showEmptyState();
                        }
                    })
                    .catch(error => {
                        console.error('Error loading orders:', error);
                        showEmptyState();
                    });
                } catch (error) {
                    console.error('Error in loadDriverOrders:', error);
                    showEmptyState();
                }
            }
            
            function displayOrders(orders) {
                try {
                    mainContent = document.querySelector('.main-content');
                    if (!mainContent) return;

                    // Add orders-page class for full width styling
                    mainContent.classList.add('orders-page');

                    if (!orders || orders.length === 0) {
                        showEmptyState();
                        return;
                    }


                    let ordersHTML = `
                        <div class="orders-list-mobile" id="sortable-orders">
                    `;

                    orders.forEach((order, index) => {
                        // Get depot info
                        const depotName = '<?php echo esc_js(get_option("dispatch_default_depot_name", "Depot")); ?>';
                        const depotAddress = '<?php echo esc_js(get_option("dispatch_default_depot_address", "")); ?>';

                        // Status badge - dynamic based on order ready status
                        const statusText = order.is_ready ? translations[currentLanguage].started : translations[currentLanguage].assigned;
                        const statusClass = order.is_ready ? 'started' : 'zugewiesen';
                        const statusBadge = `
                            <div class="status-badge ${statusClass}">${statusText}</div>
                        `;

                        // Action icons (navigation and phone)
                        const actionIcons = `
                            <div class="action-icons">
                                <button class="action-icon nav-icon" onclick="openNavigation('${order.plus_code || order.customer_address}', '<?php echo esc_js(get_option('dispatch_default_depot_address', '')); ?>')" title="${translations[currentLanguage].navigate}">
                                    <svg width="24" height="24" fill="#9CA3AF" viewBox="0 0 24 24">
                                        <path d="M12 2L4.5 20.29l.71.71L12 18l6.79 3 .71-.71z"/>
                                    </svg>
                                </button>
                                <button class="action-icon phone-icon" onclick="callCustomer('${order.customer_phone ? order.customer_phone.replace(/'/g, "\\'") : ''}')" title="${translations[currentLanguage].call}">
                                    <svg width="24" height="24" fill="#9CA3AF" viewBox="0 0 24 24">
                                        <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56a.977.977 0 0 0-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/>
                                    </svg>
                                </button>
                            </div>
                        `;

                        // Use actual timestamps for pickup and delivery
                        let pickupTime = '';
                        let deliveryTime = translations[currentLanguage].open; // Default to "Offen" (Open) instead of "Geliefert"

                        // Debug: Log timestamps
                        console.log('Order #' + order.order_id + ' timestamps:', {
                            started_at: order.started_at,
                            delivered_at: order.delivered_at
                        });

                        // Pickup time = when packlist was completed (car loaded)
                        // Always show the pickup time if available
                        if (order.started_at && order.started_at !== '' && order.started_at !== null) {
                            const startedDate = new Date(order.started_at);
                            if (!isNaN(startedDate.getTime())) {
                                pickupTime = startedDate.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', hour12: false }) + ' Uhr';
                            }
                        } else if (order.is_ready) {
                            // If marked as ready but no timestamp (old data), show "Bereit"
                            pickupTime = translations[currentLanguage].orderReady;
                        } else {
                            // If packlist not completed yet, show "Noch nicht gepackt"
                            pickupTime = translations[currentLanguage].notPacked;
                        }

                        // Delivery time = when order was marked as delivered
                        // Show "Offen" if not delivered yet, actual time when delivered
                        if (order.delivered_at && order.delivered_at !== '' && order.delivered_at !== null) {
                            const deliveredDate = new Date(order.delivered_at);
                            if (!isNaN(deliveredDate.getTime())) {
                                deliveryTime = deliveredDate.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', hour12: false }) + ' Uhr';
                            }
                        }
                        // Remove the estimation logic - just show "Offen" if not delivered

                        // Format order total with exactly 2 decimal places
                        const formatOrderTotal = (total) => {
                            const numericValue = parseFloat(total.replace('€', '').replace(',', '.').trim());
                            return isNaN(numericValue) ? '€0.00' : '€' + numericValue.toFixed(2);
                        };
                        const formattedTotal = formatOrderTotal(order.total);

                        // Use actual delivery_sequence from order data
                        const deliverySequence = order.delivery_sequence || (index + 1);

                        ordersHTML += `
                            <div class="current-order-card" data-order-id="${order.order_id}" data-sequence="${deliverySequence}" draggable="true" style="cursor: move;">
                                <div class="order-header">
                                    <div class="order-header-left">
                                        <div class="drag-handle" style="margin-right: 10px; color: #9CA3AF; cursor: grab;">
                                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M11 18c0 1.1-.9 2-2 2s-2-.9-2-2 .9-2 2-2 2 .9 2 2zm-2-8c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm6 4c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
                                            </svg>
                                        </div>
                                        ${statusBadge}
                                        ${deliverySequence ? `<span style="background: #059669; color: white; padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: bold; margin-left: 8px;">${translations[currentLanguage].deliver}: ${deliverySequence}</span>` : ''}
                                    </div>
                                    ${actionIcons}
                                </div>

                                <div class="order-number-section">
                                    <span class="order-number">#${order.order_number}</span>
                                    <span class="order-total">${formattedTotal}</span>
                                </div>

                                <div class="order-locations">
                                    <!-- Pickup Location -->
                                    <div class="location-item">
                                        <div class="location-marker pickup-marker">
                                            <div class="marker-dot"></div>
                                        </div>
                                        <div class="location-info">
                                            <div class="location-name">${depotName}</div>
                                            <div class="location-address">${depotAddress}</div>
                                        </div>
                                        <div class="location-time">${pickupTime}</div>
                                    </div>

                                    <!-- Delivery Location -->
                                    <div class="location-item">
                                        <div class="location-marker delivery-marker">
                                            <div class="marker-dot"></div>
                                        </div>
                                        <div class="location-info">
                                            <div class="location-name">${order.customer_name}</div>
                                            <div class="location-address">${order.customer_address}</div>
                                        </div>
                                        <div class="location-time">${deliveryTime}</div>
                                    </div>
                                </div>

                                <button class="pickup-button" onclick="showOrderDetail(${order.order_id}, event); event.stopPropagation();">
                                    ${translations[currentLanguage].showDetails} →
                                </button>
                            </div>`;
                    });
                    
                    ordersHTML += '</div>';
                    
                    // Add bottom navigation
                    ordersHTML += `
                        <div class="bottom-navigation">
                            <a href="#bestellungen" class="nav-item active" onclick="showBestellungen()">
                                <div class="icon">
                                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M8 2v3h8V2H8zM9 9l3 4 4-6 1 1.5L12 15 8 10l1-1z"/>
                                        <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.89-1.99 2L3 19a2 2 0 002 2h14c1.1 0 2-.9 2-2V5c0-1.11-.9-2-2-2zm0 16H5V8h14v11z"/>
                                    </svg>
                                </div>
                                <div class="label">${translations[currentLanguage].orders}</div>
                            </a>
                            <a href="#karte" class="nav-item" onclick="showKarte()">
                                <div class="icon">
                                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/>
                                    </svg>
                                </div>
                                <div class="label">${translations[currentLanguage].map}</div>
                            </a>
                            <a href="#warten" class="nav-item" onclick="showWarten()">
                                <div class="icon">
                                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M15 1H9v2h6V1zm-4 13h2V8h-2v6zm8.03-6.61l1.42-1.42c-.43-.51-.9-.99-1.41-1.41l-1.42 1.42C16.07 4.74 14.12 4 12 4c-4.97 0-9 4.03-9 9s4.02 9 9 9 9-4.03 9-9c0-2.12-.74-4.07-1.97-5.61zM12 20c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7-7z"/>
                                    </svg>
                                </div>
                                <div class="label">${translations[currentLanguage].waiting}</div>
                            </a>
                            <a href="#packliste" class="nav-item" onclick="showPackliste()">
                                <div class="icon">
                                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                                    </svg>
                                </div>
                                <div class="label">${translations[currentLanguage].packlist}</div>
                            </a>
                        </div>
                    `;

                    mainContent.innerHTML = ordersHTML;

                    // Initialize drag & drop functionality
                    initializeDragAndDrop();
                } catch (error) {
                    console.error('Error displaying orders:', error);
                    showEmptyState();
                }
            }

            function initializeDragAndDrop() {
                const sortableList = document.getElementById('sortable-orders');
                if (!sortableList) return;

                let draggedElement = null;
                let draggedOverElement = null;

                const orderCards = sortableList.querySelectorAll('.current-order-card');

                orderCards.forEach(card => {
                    card.addEventListener('dragstart', function(e) {
                        draggedElement = this;
                        this.style.opacity = '0.5';
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/html', this.innerHTML);
                    });

                    card.addEventListener('dragend', function(e) {
                        this.style.opacity = '1';

                        // Remove all drag-over classes
                        orderCards.forEach(card => {
                            card.classList.remove('drag-over');
                        });

                        // Save new order to server
                        saveOrderSequence();
                    });

                    card.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        e.dataTransfer.dropEffect = 'move';

                        if (this === draggedElement) return;

                        // Remove previous drag-over class
                        if (draggedOverElement) {
                            draggedOverElement.classList.remove('drag-over');
                        }

                        this.classList.add('drag-over');
                        draggedOverElement = this;

                        // Get bounding rectangles
                        const bounding = this.getBoundingClientRect();
                        const offset = bounding.y + bounding.height / 2;

                        if (e.clientY - offset > 0) {
                            this.parentNode.insertBefore(draggedElement, this.nextSibling);
                        } else {
                            this.parentNode.insertBefore(draggedElement, this);
                        }
                    });

                    card.addEventListener('drop', function(e) {
                        e.stopPropagation();
                        e.preventDefault();
                        this.classList.remove('drag-over');
                    });

                    // Prevent click events during drag
                    card.addEventListener('click', function(e) {
                        if (this.getAttribute('data-dragging') === 'true') {
                            e.stopPropagation();
                            e.preventDefault();
                        }
                    });
                });
            }

            function saveOrderSequence() {
                const sortableList = document.getElementById('sortable-orders');
                if (!sortableList) return;

                const orderCards = sortableList.querySelectorAll('.current-order-card');
                const sequence = [];

                orderCards.forEach((card, index) => {
                    sequence.push({
                        order_id: card.getAttribute('data-order-id'),
                        sequence: index + 1
                    });
                });

                console.log('Saving new order sequence:', sequence);

                // Send to server
                fetch(dispatch_ajax.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=save_order_sequence&nonce=' + dispatch_ajax.nonce + '&sequence=' + encodeURIComponent(JSON.stringify(sequence)),
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Order sequence saved successfully');
                    } else {
                        console.error('Failed to save sequence:', data);
                    }
                })
                .catch(error => {
                    console.error('Error saving sequence:', error);
                });
            }

            // Show Order Detail Function
            window.showOrderDetail = function(orderId, event) {
                if (event) {
                    event.stopPropagation();
                }

                // Load order details via AJAX
                fetch(dispatch_ajax.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_order_details&order_id=' + orderId + '&nonce=' + dispatch_ajax.nonce,
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        displayOrderDetail(data.data);
                    } else {
                        console.error('Failed to load order details:', data);
                    }
                })
                .catch(error => {
                    console.error('Error loading order details:', error);
                });
            }

            function displayOrderDetail(order) {
                const mainContent = document.querySelector('.main-content');
                if (!mainContent) return;

                // Format prices
                const formatPrice = (price) => {
                    // Extract numeric value from string or use the number directly
                    let numericValue = 0;

                    if (typeof price === 'string') {
                        // Remove currency symbol and any whitespace, then parse
                        numericValue = parseFloat(price.replace('€', '').replace(',', '.').trim());
                    } else if (typeof price === 'number') {
                        numericValue = price;
                    }

                    // Format with exactly 2 decimal places
                    if (isNaN(numericValue)) {
                        return '€0.00';
                    }

                    return '€' + numericValue.toFixed(2);
                };

                let detailHTML = `
                    <div class="order-detail-page">
                        <!-- Header with back button -->
                        <div class="detail-header-mobile">
                            <button class="back-button" onclick="loadDriverOrders()">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M15 6l-6 6 6 6"/>
                                </svg>
                            </button>
                            <h2 class="detail-title">${translations[currentLanguage].order} #${order.order_number}</h2>
                            <div class="header-space"></div>
                        </div>

                        <!-- Order Info Section -->
                        <div class="detail-section">
                            <div class="status-info">
                                <span class="detail-badge ${order.is_ready ? 'started' : 'zugewiesen'}">
                                    ${order.is_ready ? translations[currentLanguage].started : translations[currentLanguage].assigned}
                                </span>
                                <span class="order-time-info">${order.delivery_date || translations[currentLanguage].today}</span>
                            </div>

                            <div class="total-info">
                                <span class="total-label">${translations[currentLanguage].totalAmount}:</span>
                                <span class="total-amount">${formatPrice(order.total)}</span>
                            </div>
                        </div>

                        <!-- Customer Section -->
                        <div class="detail-section">
                            <h3 class="section-title">${translations[currentLanguage].customer}</h3>
                            <div class="info-card">
                                <div class="info-row">
                                    <span class="info-label">${translations[currentLanguage].name}:</span>
                                    <span class="info-value">${order.customer_name}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">${translations[currentLanguage].address}:</span>
                                    <span class="info-value">${order.customer_address}</span>
                                </div>
                                ${order.customer_phone ? `
                                <div class="info-row">
                                    <span class="info-label">${translations[currentLanguage].phone}:</span>
                                    <a href="tel:${order.customer_phone}" class="info-value phone-link">${order.customer_phone}</a>
                                </div>
                                ` : ''}
                                ${order.customer_note ? `
                                <div class="info-row">
                                    <span class="info-label">${translations[currentLanguage].note}:</span>
                                    <span class="info-value">${order.customer_note}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>

                        <!-- Delivery Info Section -->
                        <div class="detail-section">
                            <h3 class="section-title">${translations[currentLanguage].delivery}</h3>
                            <div class="info-card">
                                <div class="info-row">
                                    <span class="info-label">${translations[currentLanguage].date}:</span>
                                    <span class="info-value">${order.delivery_date || translations[currentLanguage].today}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">${translations[currentLanguage].time}:</span>
                                    <span class="info-value">${order.delivery_time || translations[currentLanguage].notSpecified}</span>
                                </div>
                                ${order.plus_code ? `
                                <div class="info-row">
                                    <span class="info-label">${translations[currentLanguage].plusCode}:</span>
                                    <span class="info-value">${order.plus_code}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>

                        <!-- Items Section -->
                        <div class="detail-section">
                            <h3 class="section-title">${translations[currentLanguage].items} (${order.items ? order.items.length : 0})</h3>
                            <div class="items-list">`;

                // Add order items
                if (order.items && order.items.length > 0) {
                    order.items.forEach(item => {
                        // Check if this is a Pfand item
                        const isPfandItem = item.is_pfand ||
                                          item.name.toLowerCase().includes('pfand') ||
                                          item.name.toLowerCase().includes('mehrweg');

                        detailHTML += `
                            <div class="item-card ${isPfandItem ? 'pfand-item' : ''}">
                                ${isPfandItem ? '<div class="pfand-icon">🍾</div>' : ''}
                                <div class="item-quantity">${item.quantity}x</div>
                                <div class="item-details">
                                    <div class="item-name">${item.name}</div>
                                    ${item.sku ? `<div class="item-sku">SKU: ${item.sku}</div>` : ''}
                                </div>
                                <div class="item-price">${formatPrice(item.price)}</div>
                            </div>`;
                    });
                } else {
                    detailHTML += `<div class="no-items">${translations[currentLanguage].noItemsAvailable}</div>`;
                }

                detailHTML += `
                            </div>
                        </div>

                        <!-- Order Totals Section -->
                        <div class="detail-section totals-section">
                            <div class="totals-breakdown">
                                <div class="total-row subtotal-row">
                                    <span class="total-label">${translations[currentLanguage].subtotalItems}:</span>
                                    <span class="total-value">${formatPrice(order.subtotal || 0)}</span>
                                </div>

                                ${order.fees && order.fees.length > 0 ? order.fees.map(fee => `
                                    <div class="total-row">
                                        <span class="total-label">${fee.name}:</span>
                                        <span class="total-value">${formatPrice(fee.amount)}</span>
                                    </div>
                                `).join('') : ''}

                                ${order.shipping_items && order.shipping_items.length > 0 ? order.shipping_items.map(item => `
                                    <div class="total-row">
                                        <span class="total-label">${item.name}:</span>
                                        <span class="total-value">${formatPrice(item.amount)}</span>
                                    </div>
                                `).join('') : (order.shipping > 0 ? `
                                    <div class="total-row">
                                        <span class="total-label">${translations[currentLanguage].shipping}:</span>
                                        <span class="total-value">${formatPrice(order.shipping)}</span>
                                    </div>
                                ` : '')}

                                ${order.tax > 0 ? `
                                    <div class="total-row">
                                        <span class="total-label">21% ${translations[currentLanguage].tax}:</span>
                                        <span class="total-value">${formatPrice(order.tax)}</span>
                                    </div>
                                ` : ''}

                                <div class="total-row grand-total">
                                    <span class="total-label">${translations[currentLanguage].orderTotal}:</span>
                                    <span class="total-value">${formatPrice(order.total)}</span>
                                </div>

                                ${order.has_refunds ? `
                                    <div class="total-row refund-row">
                                        <span class="total-label" style="color:#dc2626;">Rückerstattet:</span>
                                        <span class="total-value" style="color:#dc2626;">-${formatPrice(order.total_refunded)}</span>
                                    </div>
                                    <div class="total-row net-payment">
                                        <span class="total-label" style="font-size:1.1em; font-weight:700;">Restzahlung:</span>
                                        <span class="total-value" style="font-size:1.1em; font-weight:700;">${formatPrice(order.net_payment)}</span>
                                    </div>
                                ` : ''}

                                ${order.payment_method && order.payment_method !== 'CASH' && !order.has_refunds ? `
                                    <div class="total-row payment-info">
                                        <span class="total-label">${translations[currentLanguage].toPay}:</span>
                                        <span class="total-value">${formatPrice(order.total)}</span>
                                    </div>
                                ` : ''}
                            </div>
                        </div>

                        <!-- Pfand Section -->
                        <div class="detail-section pfand-section">
                            <div class="pfand-container" id="pfandContainer">
                                <div class="pfand-loading">Lade Pfand-Informationen...</div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="detail-actions-section">
                            <button class="action-button primary" onclick="openNavigation('${order.plus_code || order.customer_address}', '<?php echo esc_js(get_option('dispatch_default_depot_address', '')); ?>')">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2L4.5 20.29l.71.71L12 18l6.79 3 .71-.71z"/>
                                </svg>
                                ${translations[currentLanguage].startNavigation}
                            </button>

                            ${order.customer_phone ? `
                            <button class="action-button secondary" onclick="callCustomer('${order.customer_phone}')">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56a.977.977 0 0 0-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/>
                                </svg>
                                ${translations[currentLanguage].callCustomer}
                            </button>
                            ` : ''}

                            <button class="action-button payment" onclick="openSumUpPayment(${order.order_id}, '${formatPrice(order.total)}')">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/>
                                </svg>
                                ${translations[currentLanguage].payWithSumup}
                            </button>

                            <button class="action-button success" onclick="markOrderDelivered(${order.order_id})">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/>
                                </svg>
                                ${translations[currentLanguage].markAsDeliveredButton}
                            </button>
                        </div>
                    </div>

                    <style>
                        .order-detail-page {
                            padding-bottom: 80px;
                        }

                        .detail-header-mobile {
                            display: flex;
                            align-items: center;
                            justify-content: space-between;
                            padding: 16px 20px;
                            background: #1C1C1E;
                            border-bottom: 1px solid rgba(255,255,255,0.1);
                            position: sticky;
                            top: 0;
                            z-index: 100;
                        }

                        .back-button {
                            background: none;
                            border: none;
                            color: #007AFF;
                            padding: 8px;
                            margin: -8px;
                            cursor: pointer;
                        }

                        .detail-title {
                            color: #ffffff;
                            font-size: 18px;
                            font-weight: 600;
                            margin: 0;
                        }

                        .header-space {
                            width: 40px;
                        }

                        .detail-section {
                            padding: 20px;
                            border-bottom: 1px solid rgba(255,255,255,0.05);
                        }

                        .section-title {
                            color: #ffffff;
                            font-size: 16px;
                            font-weight: 600;
                            margin-bottom: 12px;
                        }

                        .status-info {
                            display: flex;
                            align-items: center;
                            gap: 12px;
                            margin-bottom: 16px;
                        }

                        .detail-badge {
                            padding: 6px 14px;
                            border-radius: 6px;
                            font-size: 13px;
                            font-weight: 600;
                        }

                        .detail-badge.zugewiesen {
                            background: #D4A017;
                            color: #000000;
                        }

                        .detail-badge.started {
                            background: #10B981;
                            color: white;
                        }

                        .order-time-info {
                            color: rgba(255,255,255,0.6);
                            font-size: 14px;
                        }

                        .total-info {
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            padding: 16px;
                            background: rgba(255,255,255,0.05);
                            border-radius: 8px;
                        }

                        .total-label {
                            color: rgba(255,255,255,0.7);
                            font-size: 15px;
                        }

                        .total-amount {
                            color: #10B981;
                            font-size: 20px;
                            font-weight: 600;
                        }

                        .info-card {
                            background: rgba(255,255,255,0.05);
                            border-radius: 8px;
                            padding: 16px;
                        }

                        .info-row {
                            display: flex;
                            justify-content: space-between;
                            padding: 8px 0;
                            border-bottom: 1px solid rgba(255,255,255,0.05);
                        }

                        .info-row:last-child {
                            border-bottom: none;
                        }

                        .info-label {
                            color: rgba(255,255,255,0.6);
                            font-size: 14px;
                        }

                        .info-value {
                            color: #ffffff;
                            font-size: 14px;
                            text-align: right;
                            max-width: 60%;
                        }

                        .phone-link {
                            color: #007AFF;
                            text-decoration: none;
                        }

                        .items-list {
                            background: rgba(255,255,255,0.05);
                            border-radius: 8px;
                            overflow: hidden;
                        }

                        .item-card {
                            display: flex;
                            align-items: center;
                            padding: 12px 16px;
                            border-bottom: 1px solid rgba(255,255,255,0.05);
                        }

                        .item-card.pfand-item {
                            background: rgba(0,122,255,0.05);
                            border-left: 3px solid #007AFF;
                            padding-left: 13px;
                        }

                        .pfand-icon {
                            font-size: 20px;
                            margin-right: 8px;
                        }

                        .item-card:last-child {
                            border-bottom: none;
                        }

                        .item-quantity {
                            background: #007AFF;
                            color: white;
                            padding: 4px 8px;
                            border-radius: 4px;
                            font-size: 12px;
                            font-weight: 600;
                            margin-right: 12px;
                        }

                        .item-details {
                            flex: 1;
                        }

                        .item-name {
                            color: #ffffff;
                            font-size: 14px;
                            margin-bottom: 2px;
                        }

                        .item-sku {
                            color: rgba(255,255,255,0.5);
                            font-size: 12px;
                        }

                        .item-price {
                            color: #ffffff;
                            font-size: 14px;
                            font-weight: 500;
                        }

                        .totals-section {
                            background: linear-gradient(135deg, #1C1C1E 0%, #2C2C2E 100%);
                            border: 1px solid rgba(255,255,255,0.1);
                        }

                        .totals-breakdown {
                            display: flex;
                            flex-direction: column;
                            gap: 4px;
                        }

                        .total-row {
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            padding: 10px 0;
                            font-size: 14px;
                        }

                        .total-row.subtotal-row {
                            padding-bottom: 12px;
                            border-bottom: 1px solid rgba(255,255,255,0.1);
                            margin-bottom: 8px;
                        }

                        .total-row.grand-total {
                            margin-top: 12px;
                            padding: 16px 0;
                            border-top: 2px solid rgba(16, 185, 129, 0.3);
                            background: rgba(16, 185, 129, 0.1);
                            margin-left: -16px;
                            margin-right: -16px;
                            padding-left: 16px;
                            padding-right: 16px;
                            border-radius: 8px;
                        }

                        .total-row.payment-info {
                            margin-top: 12px;
                            padding: 16px;
                            background: rgba(59, 130, 246, 0.15);
                            border: 1px solid rgba(59, 130, 246, 0.3);
                            border-radius: 8px;
                            margin-left: -16px;
                            margin-right: -16px;
                            padding-left: 32px;
                            padding-right: 32px;
                        }

                        .total-label {
                            color: rgba(255,255,255,0.65);
                            font-size: 14px;
                        }

                        .total-row.subtotal-row .total-label {
                            font-weight: 500;
                            color: rgba(255,255,255,0.85);
                        }

                        .total-row.grand-total .total-label {
                            color: #10B981;
                            font-size: 15px;
                            font-weight: 600;
                            letter-spacing: 0.3px;
                        }

                        .total-row.payment-info .total-label {
                            color: #60A5FA;
                            font-weight: 600;
                        }

                        .total-value {
                            color: #ffffff;
                            font-weight: 500;
                            font-size: 14px;
                        }

                        .total-row.subtotal-row .total-value {
                            font-weight: 600;
                            font-size: 15px;
                        }

                        .total-row.grand-total .total-value {
                            font-size: 20px;
                            font-weight: 700;
                            color: #10B981;
                            letter-spacing: 0.5px;
                        }

                        .total-row.payment-info .total-value {
                            font-size: 18px;
                            font-weight: 700;
                            color: #60A5FA;
                        }

                        .pfand-section {
                            background: transparent;
                            border-left: none;
                        }

                        .pfand-container {
                            min-height: 100px;
                        }

                        .pfand-loading {
                            text-align: center;
                            color: rgba(255,255,255,0.5);
                            padding: 20px;
                        }

                        .detail-actions-section {
                            padding: 16px;
                            display: flex;
                            flex-direction: column;
                            gap: 10px;
                            background: #000000;
                        }

                        .action-button {
                            width: 100%;
                            padding: 14px 20px;
                            border: none;
                            border-radius: 10px;
                            font-size: 15px;
                            font-weight: 600;
                            cursor: pointer;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            gap: 10px;
                            transition: all 0.2s ease;
                            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                        }

                        .action-button svg {
                            flex-shrink: 0;
                        }

                        .action-button.primary {
                            background: linear-gradient(135deg, #007AFF 0%, #0051D5 100%);
                            color: white;
                            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.4);
                        }

                        .action-button.primary:active {
                            transform: scale(0.98);
                            box-shadow: 0 2px 6px rgba(0, 122, 255, 0.3);
                        }

                        .action-button.secondary {
                            background: rgba(255,255,255,0.08);
                            color: white;
                            border: 1.5px solid rgba(255,255,255,0.2);
                            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
                        }

                        .action-button.secondary:active {
                            background: rgba(255,255,255,0.12);
                            transform: scale(0.98);
                        }

                        .action-button.success {
                            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
                            color: white;
                            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
                        }

                        .action-button.success:active {
                            transform: scale(0.98);
                            box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
                        }

                        .action-button.payment {
                            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
                            color: white;
                            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
                        }

                        .action-button.payment:active {
                            transform: scale(0.98);
                            box-shadow: 0 2px 6px rgba(59, 130, 246, 0.3);
                        }
                    </style>
                `;

                mainContent.innerHTML = detailHTML;

                // Store order data globally for Pfand modal
                window.currentOrderData = order;

                // Load Pfand data
                loadPfandData(order);
            }

            function loadPfandData(order) {
                const pfandContainer = document.getElementById('pfandContainer');
                if (!pfandContainer) return;

                // Check if order has Pfand
                const hasPfand = order.pfand_data && order.pfand_data.has_pfand && order.pfand_data.total_pfand > 0;

                if (hasPfand) {
                    // Only display the button, no Pfand details
                    pfandContainer.innerHTML = `
                        <div class="pfand-info" style="background: transparent; border: none; border-left: none; padding: 20px;">
                            <button class="action-button pfand-refund-btn"
                                    style="width: 100%; background: #46b450; border: none; border-color: #46b450; border-left: none;"
                                    data-order-id="${order.order_id}"
                                    data-order-number="${order.order_number}"
                                    data-customer-name="${order.customer_name}"
                                    data-customer-email="${order.email || order.customer_email || ''}"
                                    data-pfand-amount="${order.pfand_data.total_pfand}"
                                    onclick="handlePfandRefundClick(this)">
                                <span style="margin-right: 8px;">🔄</span>
                                Pfand zurückerstatten
                            </button>
                        </div>
                    `;
                } else {
                    pfandContainer.innerHTML = `
                        <div class="pfand-info">
                            <p style="color: rgba(255,255,255,0.5); text-align: center; padding: 20px;">
                                ${translations[currentLanguage].noDepositItems}
                            </p>
                        </div>
                    `;
                }
            }

            // Handle Pfand refund button click - mit Fehlerbehandlung
            window.handlePfandRefundClick = function(button) {
                console.log('handlePfandRefundClick called');

                const pfandData = {
                    orderId: button.getAttribute('data-order-id'),
                    orderNumber: button.getAttribute('data-order-number'),
                    customerName: button.getAttribute('data-customer-name'),
                    customerEmail: button.getAttribute('data-customer-email') || '',
                    pfandAmount: parseFloat(button.getAttribute('data-pfand-amount') || 0)
                };

                console.log('Pfand refund button clicked with data:', pfandData);

                // Warte kurz, falls openPfandModal noch nicht geladen ist
                setTimeout(function() {
                    if (typeof window.openPfandModal === 'function') {
                        console.log('Calling openPfandModal');
                        window.openPfandModal(pfandData);
                    } else if (typeof openPfandModal === 'function') {
                        console.log('Calling global openPfandModal');
                        openPfandModal(pfandData);
                    } else {
                        console.error('openPfandModal function not found - trying jQuery ready');
                        // Versuche es nochmal mit jQuery ready
                        jQuery(document).ready(function() {
                            if (typeof window.openPfandModal === 'function') {
                                window.openPfandModal(pfandData);
                            } else {
                                alert('Pfand-Modal konnte nicht geladen werden. Bitte Seite neu laden.');
                                console.error('openPfandModal still not available');
                            }
                        });
                    }
                }, 100);
            }

            // Helper function to open Pfand modal with current order data
            window.openPfandModalWithData = function() {
                if (window.currentOrderData) {
                    const order = window.currentOrderData;
                    openPfandModal({
                        orderId: order.order_id || order.id,
                        orderNumber: order.order_number,
                        customerName: order.customer_name || order.customer,
                        customerEmail: order.email || order.customer_email || '',
                        pfandAmount: order.pfand_data ? parseFloat(order.pfand_data.total_pfand || 0) : 0
                    });
                }
            }

            // EXACT COPY FROM DASHBOARD admin-script.js - Pfand Implementation
            // Wrapped in jQuery ready to ensure jQuery is available
            jQuery(document).ready(function($) {

                // Pfand-Items aus den Einstellungen laden (dynamisch aus DB) - v2.9.82
                const pfandItemsConfig = <?php
                    $pfand_items = get_option('dispatch_pfand_items', [
                        ['id' => 'water', 'icon' => '🍼', 'name' => 'Wasserflasche', 'amount' => 0.25, 'active' => true],
                        ['id' => 'beer', 'icon' => '🍺', 'name' => 'Bierflasche', 'amount' => 0.50, 'active' => true]
                    ]);
                    // Nur aktive Items - v2.9.86: Flexibler Boolean-Check für verschiedene Datenbank-Formate
                    $active_items = array_filter($pfand_items, function($item) {
                        return !isset($item['active']) || filter_var($item['active'], FILTER_VALIDATE_BOOLEAN);
                    });
                    echo json_encode(array_values($active_items), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                ?>;

                // Helper function to get pfand amount by id
                function getPfandAmount(itemId) {
                    const item = pfandItemsConfig.find(i => i.id === itemId);
                    return item ? parseFloat(item.amount) : 0;
                }

                window.openPfandModal = function(data) {
                    // Normalize incoming data and guard missing fields
                    const d = arguments[0] || {};
                    const pfandAmount = Number(d.pfandAmount ?? d.pfand_amount ?? d.pfand ?? 0);
                    console.log('Opening Pfand Modal with data:', data);


                    // Schließe aktuelles Modal
                    $('.modal-overlay').remove();

                    // Füge CSS-Styles hinzu, falls nicht vorhanden
                    if (!document.getElementById('pfand-modal-styles')) {
                        const styleSheet = document.createElement('style');
                        styleSheet.id = 'pfand-modal-styles';
                        styleSheet.textContent = `
                            ${window.pfandModalCSS || `
                            /* Mobile-First Design System */
                            .pfand-modal-wrapper {
                                position: fixed !important;
                                top: 0 !important;
                                left: 0 !important;
                                width: 100% !important;
                                height: 100% !important;
                                z-index: 1000000 !important;
                                display: none;
                                opacity: 0;
                                transition: opacity 0.3s ease;
                            }
                            .pfand-modal-wrapper.show {
                                display: flex !important;
                                align-items: flex-end;
                                justify-content: center;
                            }
                            .pfand-modal-wrapper.visible {
                                opacity: 1;
                            }
                            .pfand-modal-overlay {
                                position: absolute;
                                top: 0;
                                left: 0;
                                width: 100%;
                                height: 100%;
                                background: rgba(0, 0, 0, 0.7);
                            }

                            /* Mobile-optimiertes Modal */
                            .pfand-modal {
                                position: relative;
                                background: white;
                                border-radius: 20px 20px 0 0;
                                width: 100%;
                                max-width: 100%;
                                height: 85vh;
                                max-height: 85vh;
                                display: flex;
                                flex-direction: column;
                                box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.2);
                            }

                            /* Header mit Touch-optimiertem Close Button */
                            .pfand-modal-header {
                                padding: 16px;
                                border-bottom: 1px solid #e5e7eb;
                                background: #f9fafb;
                                border-radius: 20px 20px 0 0;
                                position: relative;
                                flex-shrink: 0;
                            }

                            .pfand-modal-title {
                                display: flex;
                                align-items: center;
                                justify-content: space-between;
                                margin: 0;
                            }

                            .pfand-modal-title h2 {
                                display: flex;
                                align-items: center;
                                gap: 8px;
                                margin: 0;
                                font-size: 1.125rem;
                                font-weight: 600;
                                color: #1f2937;
                            }

                            /* Größerer Close Button für Touch */
                            .pfand-modal-close {
                                background: #ffffff;
                                border: 2px solid #e5e7eb;
                                padding: 0;
                                border-radius: 50%;
                                cursor: pointer;
                                font-size: 1.5rem;
                                color: #6b7280;
                                min-width: 44px;
                                height: 44px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                -webkit-tap-highlight-color: transparent;
                            }

                            .pfand-modal-close:active {
                                background: #e5e7eb;
                                transform: scale(0.95);
                            }

                            /* Body mit besserer Scroll-Performance */
                            .pfand-modal-body {
                                padding: 16px;
                                overflow-y: auto;
                                flex: 1;
                                -webkit-overflow-scrolling: touch;
                                scroll-behavior: smooth;
                            }

                            /* Footer mit großen Touch-Buttons */
                            .pfand-modal-footer {
                                padding: 12px;
                                background: #ffffff;
                                border-top: 1px solid #e5e7eb;
                                display: flex;
                                gap: 10px;
                                flex-shrink: 0;
                                box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
                            }

                            .pfand-section {
                                background: #ffffff;
                                border: 1px solid #e5e7eb;
                                border-radius: 12px;
                                padding: 12px;
                                margin-bottom: 12px;
                            }

                            /* Mobile-optimierte Buttons */
                            .pfand-btn {
                                padding: 12px 16px;
                                border: none;
                                border-radius: 8px;
                                font-size: 0.95rem;
                                font-weight: 600;
                                cursor: pointer;
                                transition: all 0.2s ease;
                                display: inline-flex;
                                align-items: center;
                                justify-content: center;
                                gap: 6px;
                                flex: 1;
                                min-height: 44px;
                                -webkit-tap-highlight-color: transparent;
                            }

                            .pfand-btn-primary {
                                background: #46b450;
                                color: white;
                            }

                            .pfand-btn-primary:active {
                                background: #3da044;
                                transform: scale(0.98);
                            }

                            .pfand-btn-secondary {
                                background: #6b7280;
                                color: white;
                            }

                            .pfand-btn-secondary:active {
                                background: #4b5563;
                                transform: scale(0.98);
                            }

                            .pfand-btn-danger {
                                background: #dc3545;
                                color: white;
                            }

                            .pfand-btn:disabled {
                                opacity: 0.5;
                                cursor: not-allowed;
                            }

                            /* Große Touch-optimierte Plus/Minus Buttons */
                            .pfand-quantity-control {
                                display: flex;
                                align-items: center;
                                gap: 8px;
                            }

                            .pfand-quantity-btn {
                                width: 44px !important;
                                height: 44px !important;
                                min-width: 44px !important;
                                border: 2px solid #d1d5db !important;
                                background: #ffffff !important;
                                border-radius: 8px !important;
                                cursor: pointer;
                                display: flex !important;
                                align-items: center !important;
                                justify-content: center !important;
                                font-size: 1.5rem !important;
                                font-weight: bold !important;
                                color: #374151 !important;
                                -webkit-tap-highlight-color: transparent;
                                transition: all 0.2s ease;
                            }

                            .pfand-quantity-btn:active {
                                background: #e5e7eb !important;
                                transform: scale(0.95);
                            }

                            .pfand-quantity-input {
                                width: 60px !important;
                                height: 44px !important;
                                text-align: center !important;
                                border: 2px solid #d1d5db !important;
                                border-radius: 8px !important;
                                font-size: 1.125rem !important;
                                font-weight: bold !important;
                                color: #1f2937 !important;
                            }

                            /* Mobile Item Rows */
                            .pfand-item-row {
                                display: flex;
                                flex-direction: column;
                                gap: 12px;
                                padding: 16px 12px;
                                border-bottom: 1px solid #e5e7eb;
                                background: #ffffff;
                                border-radius: 8px;
                                margin-bottom: 8px;
                            }

                            .pfand-item-name {
                                display: flex;
                                align-items: center;
                                gap: 12px;
                                font-size: 1rem;
                                font-weight: 500;
                            }

                            .pfand-item-controls {
                                display: flex;
                                justify-content: space-between;
                                align-items: center;
                            }

                            /* Order Group Mobile */
                            .pfand-order-group {
                                border: 2px solid #e5e7eb;
                                border-radius: 12px;
                                margin-bottom: 16px;
                                overflow: hidden;
                            }

                            .pfand-order-header {
                                background: #f9fafb;
                                padding: 12px 16px;
                                font-weight: 600;
                                color: #1f2937;
                                border-bottom: 1px solid #e5e7eb;
                                font-size: 0.9rem;
                            }

                            .pfand-order-items {
                                padding: 12px;
                            }

                            /* Desktop Anpassungen */
                            @media (min-width: 768px) {
                                .pfand-modal-wrapper.show {
                                    align-items: center;
                                }

                                .pfand-modal {
                                    border-radius: 12px;
                                    width: 90%;
                                    max-width: 900px;
                                    height: auto;
                                    max-height: 90vh;
                                }

                                .pfand-modal-header {
                                    padding: 24px;
                                    border-radius: 12px 12px 0 0;
                                }

                                .pfand-modal-title h2 {
                                    font-size: 1.5rem;
                                }

                                .pfand-modal-body {
                                    padding: 24px;
                                }

                                .pfand-modal-footer {
                                    padding: 20px 24px;
                                    border-radius: 0 0 12px 12px;
                                }

                                .pfand-item-row {
                                    flex-direction: row;
                                    justify-content: space-between;
                                    align-items: center;
                                }

                                .pfand-btn {
                                    flex: initial;
                                    padding: 10px 20px;
                                    font-size: 0.875rem;
                                }

                                .pfand-quantity-btn {
                                    width: 32px !important;
                                    height: 32px !important;
                                    min-width: 32px !important;
                                    font-size: 1.125rem !important;
                                }

                                .pfand-quantity-input {
                                    width: 50px !important;
                                    height: 32px !important;
                                    font-size: 1rem !important;
                                }
                            }

                            @keyframes spin {
                                0% { transform: rotate(0deg); }
                                100% { transform: rotate(360deg); }
                            }

                            @keyframes slideUp {
                                from {
                                    transform: translateY(100%);
                                    opacity: 0;
                                }
                                to {
                                    transform: translateY(0);
                                    opacity: 1;
                                }
                            }

                            .pfand-modal {
                                animation: slideUp 0.3s ease-out;
                            }
                            `}
                        `;
                        document.head.appendChild(styleSheet);
                    }

                    const pfandModal = `
                        <div class="pfand-modal-wrapper show visible" role="dialog" aria-modal="true" aria-labelledby="driver-pfand-title">
                            <div class="pfand-modal-overlay" aria-hidden="true"></div>
                            <div class="pfand-modal" role="document">
                                <div class="pfand-modal-header">
                                    <div class="pfand-modal-title">
                                        <h2 id="driver-pfand-title">
                                            <span aria-hidden="true">🍶</span>
                                            <span>Pfand zurückerstatten</span>
                                        </h2>
                                        <button type="button" class="pfand-modal-close" aria-label="Modal schließen">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="pfand-modal-body">
                                    <input type="hidden" id="pfand-order-id" value="${data.orderId}" />
                                    <input type="hidden" id="pfand-customer-email" value="${data.customerEmail}" />

                                    <!-- Order Info -->
                                    <div class="pfand-section">
                                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:15px;">
                                            <div style="display:flex; align-items:center; gap:10px;">
                                                <i class="fas fa-shopping-cart" style="color:#46b450;"></i>
                                                <div>
                                                    <label style="font-size:12px; color:#6b7280; display:block;">Bestellung</label>
                                                    <span style="font-weight:bold; color:#1f2937;">#${data.orderNumber}</span>
                                                </div>
                                            </div>
                                            <div style="display:flex; align-items:center; gap:10px;">
                                                <i class="fas fa-user" style="color:#46b450;"></i>
                                                <div>
                                                    <label style="font-size:12px; color:#6b7280; display:block;">Kunde</label>
                                                    <span style="font-weight:bold; color:#1f2937;">${data.customerName}</span>
                                                </div>
                                            </div>
                                            <div style="display:flex; align-items:center; gap:10px;">
                                                <i class="fas fa-coins" style="color:#46b450;"></i>
                                                <div>
                                                    <label style="font-size:12px; color:#6b7280; display:block;">Pfand gesamt</label>
                                                    <span style="font-weight:bold; font-size:18px; color:#46b450;">€${data.pfandAmount.toFixed(2)}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Loading State -->
                                    <div id="pfand-loading" style="text-align:center; padding:40px;">
                                        <div style="display:inline-block; width:40px; height:40px; border:4px solid #f3f3f3; border-top:4px solid #46b450; border-radius:50%; animation:spin 1s linear infinite;"></div>
                                        <p style="margin-top:10px; color:#374151;">Lade Pfand-Historie...</p>
                                    </div>

                                    <!-- Content wird hier per AJAX geladen -->
                                    <div id="pfand-content" style="display:none;">
                                        <!-- Wird dynamisch gefüllt -->
                                    </div>
                                </div>

                                <div class="pfand-modal-footer">
                                    <button type="button" class="pfand-btn pfand-btn-secondary pfand-cancel">
                                        <i class="fas fa-times"></i>
                                        Abbrechen
                                    </button>
                                    <button type="button" class="pfand-btn pfand-btn-primary pfand-confirm" disabled>
                                        <i class="fas fa-check"></i>
                                        <span id="pfand-confirm-text">Verrechnung durchführen</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;

                    $('body').append(pfandModal);

                    // Event Handler mit neuen Klassen
                    $('.pfand-modal-close, .pfand-cancel').on('click', function() {
                        $('.pfand-modal-wrapper').fadeOut(300, function() {
                            $(this).remove();
                        });
                    });

                    // ESC-Taste schließt Modal
                    $(document).on('keydown.pfandModal', function(e) {
                        if (e.key === 'Escape') {
                            $('.pfand-modal-wrapper').fadeOut(300, function() {
                                $(this).remove();
                            });
                            $(document).off('keydown.pfandModal');
                        }
                    });

                    // Klick auf Overlay schließt Modal
                    $('.pfand-modal-overlay').on('click', function() {
                        $('.pfand-modal-wrapper').fadeOut(300, function() {
                            $(this).remove();
                        });
                    });

                    // Lade Pfand-Historie
                    loadPfandHistory(data);

                    // If pfandAmount is missing but we have an orderId & email, try to fetch via AJAX
                    if ((!pfandAmount || isNaN(pfandAmount)) && d.orderId) {
                        const email = d.customerEmail || d.customer_email || jQuery('#pfand-customer-email').val() || '';
                        if (email) {
                            jQuery.post(ajaxurl, {
                                action: 'dispatch_load_pfand_manager_interface',
                                nonce: (window.dispatchPfand && dispatchPfand.nonce) || (window.dispatchData && dispatchData.nonce) || '',
                                customer_email: email,
                                customer_name: d.customerName || d.customer_name || ''
                            }).done(function(resp){
                                if (resp && resp.success && resp.data && typeof resp.data.pfandAmount !== 'undefined') {
                                    const amt = Number(resp.data.pfandAmount) || 0;
                                    jQuery('#pfand-total-amount, #pfand-order-pfand-amount').text('€' + amt.toFixed(2));
                                }
                            });
                        }
                    }
                }

                function loadPfandHistory(data) {
                    console.log('Loading Pfand history for:', data);

                    // Lade echte Kundenhistorie via AJAX
                    $.ajax({
                        url: dispatch_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'dispatch_get_customer_pfand_history',
                            customer_email: data.customerEmail,
                            exclude_order_id: data.orderId,
                            include_current: 'false',
                            nonce: dispatch_ajax.nonce
                        },
                        success: (response) => {
                            console.log('Pfand history response:', response);

                            $('#pfand-loading').hide();

                            if (response.success && response.data.history.length > 0) {
                                displayPfandModal(data, response.data);
                            } else {
                                $('#pfand-content').show().html(`
                                    <div style="background:#fff3cd; border:1px solid #ffeaa7; border-radius:5px; padding:15px; margin-bottom:20px;">
                                        <h4 style="color:#856404; margin:0 0 10px 0;">⚠️ Keine Pfand-Historie gefunden</h4>
                                        <p style="margin:0; color:#856404;">Für diesen Kunden wurden keine weiteren Bestellungen mit Pfand gefunden.</p>
                                    </div>

                                    <div style="background:#f8f9fa; padding:15px; border-radius:5px; text-align:center;">
                                        <p style="color:#1f2937;"><strong>Aktuelle Bestellung:</strong> #${data.orderNumber}</p>
                                        <p style="color:#1f2937;"><strong>Pfand-Betrag:</strong> €${data.pfandAmount.toFixed(2)}</p>
                                        <p style="color:#6b7280; font-size:14px;">Diese Bestellung hat Pfand, aber es gibt keine Historie für Rückgaben.</p>
                                    </div>
                                `);
                                $('.pfand-confirm').prop('disabled', true);
                            }
                        },
                        error: (xhr, status, error) => {
                            console.error('Fehler beim Laden der Pfand-Historie:', error);
                            $('#pfand-loading').hide();
                            $('#pfand-content').show().html(`
                                <div style="background:#f8d7da; border:1px solid #f5c6cb; border-radius:5px; padding:15px;">
                                    <h4 style="color:#721c24; margin:0 0 10px 0;">❌ Fehler</h4>
                                    <p style="margin:0; color:#721c24;">Pfand-Historie konnte nicht geladen werden: ${error}</p>
                                </div>
                            `);
                        }
                    });
                }

                function displayPfandModal(data, historyData) {
                    let currentRefundMode = 'with-order';

                    // v2.9.9: Calculate already refunded items
                    const refundedItems = historyData.history.filter(item => item.already_refunded === true);
                    const totalRefunded = refundedItems.reduce((sum, item) => sum + (item.total_pfand * item.quantity), 0);

                    const modalContent = `
                        ${historyData.is_demo ? `
                        <div style="background:#fff3cd; border:1px solid #ffeaa7; padding:15px; border-radius:8px; margin-bottom:20px;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <span style="font-size:20px;">🧪</span>
                                <div>
                                    <strong style="color:#856404;">Demo-Modus aktiv</strong>
                                    <p style="margin:5px 0 0 0; color:#856404; font-size:14px;">
                                        Das originale Pfand-Plugin ist nicht installiert. Es werden Demo-Daten angezeigt.
                                    </p>
                                </div>
                            </div>
                        </div>
                        ` : ''}

                        ${generateRefundedItemsSection(refundedItems, totalRefunded)}

                        <!-- Rückgabe-Modus Auswahl -->
                        <div style="background:#f8f9fa; padding:12px; border-radius:8px; margin-bottom:12px;">
                            <h4 style="margin:0 0 8px 0; color:#46b450; font-size:16px;">🔄 Rückgabe-Modus</h4>
                            <div style="display:flex; flex-direction:column; gap:15px;">
                                <label style="display:flex; align-items:flex-start; padding:15px; border:2px solid #e5e5e5; border-radius:8px; cursor:pointer; transition:all 0.2s;" class="refund-mode-option" data-mode="with-order">
                                    <input type="radio" name="refund-mode" value="with-order" checked style="margin-right:12px; margin-top:3px;">
                                    <div>
                                        <strong style="display:block; margin-bottom:5px; color:#46b450;">Mit neuer Bestellung verrechnen</strong>
                                        <small style="color:#6b7280;">Pfand wird mit dem Betrag der aktuellen Bestellung verrechnet</small>
                                    </div>
                                </label>

                                <label style="display:flex; align-items:flex-start; padding:15px; border:2px solid #e5e5e5; border-radius:8px; cursor:pointer; transition:all 0.2s;" class="refund-mode-option" data-mode="pfand-only">
                                    <input type="radio" name="refund-mode" value="pfand-only" style="margin-right:12px; margin-top:3px;">
                                    <div>
                                        <strong style="display:block; margin-bottom:5px; color:#374151;">Nur Pfand zurückgeben</strong>
                                        <small style="color:#6b7280;">Pfand-Gutschrift erstellen (z.B. bei Abreise, keine neue Bestellung)</small>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Aktuelle Bestellung -->
                        <div id="current-order-section" style="background:#f8f9fa; padding:12px; border-radius:8px; margin-bottom:12px;">
                            <h4 style="margin:0 0 8px 0; color:#46b450; font-size:16px;">🧾 Aktuelle Bestellung</h4>
                            <div style="background:white; padding:10px; border-radius:5px; border:1px solid #ddd;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                    <span style="color:#1f2937; font-weight:600;">Bestellung #${data.orderNumber}</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; padding:4px 0;">
                                    <span style="color:#374151;">Gesamtbetrag:</span>
                                    <span id="order-total" style="color:#374151;">€${(parseFloat(historyData.order_total) || 0).toFixed(2)}</span>
                                </div>
                                ${totalRefunded > 0 ? `
                                <div style="display:flex; justify-content:space-between; padding:4px 0; color:#dc2626;">
                                    <span style="color:#dc2626;">Rückerstattet:</span>
                                    <span style="color:#dc2626;">-€${totalRefunded.toFixed(2)}</span>
                                </div>
                                ` : ''}
                                <div style="display:flex; justify-content:space-between; padding:4px 0;">
                                    <span style="color:#374151;">Pfand:</span>
                                    <span style="color:#374151;">€${data.pfandAmount.toFixed(2)}</span>
                                </div>
                                <div id="credit-row" style="display:none; justify-content:space-between; padding:4px 0; color:#46b450;">
                                    <span style="color:#46b450;">Pfand-Gutschrift:</span>
                                    <span id="credit-amount" style="color:#46b450;">- €0.00</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; padding:8px 0; margin-top:8px; border-top:2px solid #46b450; font-weight:bold; font-size:16px;">
                                    <span style="color:#1f2937;">${totalRefunded > 0 ? 'Restzahlung:' : 'Zu zahlen:'}</span>
                                    <span id="final-amount" style="color:#1f2937; font-size:16px;">€${(parseFloat(historyData.order_total) - totalRefunded).toFixed(2)}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Nur-Pfand Zusammenfassung -->
                        <div id="pfand-only-summary" style="display:none; background:#e8f5e8; padding:10px; border-radius:8px; margin-bottom:12px;">
                            <h4 style="margin:0 0 8px 0; color:#46b450; font-size:16px;">💰 Pfand-Rückerstattung</h4>
                            <div style="background:white; padding:10px; border-radius:5px; border:1px solid #ddd;">
                                <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
                                    <i class="fas fa-info-circle" style="color:#46b450;"></i>
                                    <p style="margin:0; color:#374151;">Der Kunde erhält das Pfand ohne neue Bestellung zurück.</p>
                                </div>
                                <div style="display:flex; justify-content:space-between; padding:4px 0;">
                                    <span style="color:#374151;">Pfand-Gutschrift:</span>
                                    <span id="pfand-only-credit" style="color:#059669; font-weight:600;">€0.00</span>
                                </div>
                                <div id="pfand-deduction-row" style="display:none; justify-content:space-between; padding:4px 0; color:#dc3545;">
                                    <span style="color:#dc3545;">Abzug fehlende Flaschen:</span>
                                    <span id="pfand-deduction" style="color:#dc3545;">- €0.00</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; padding:8px 0; margin-top:8px; border-top:2px solid #46b450; font-weight:bold; font-size:16px;">
                                    <span style="color:#1f2937;">Auszuzahlen:</span>
                                    <span id="pfand-only-total" style="color:#059669;">€0.00</span>
                                </div>
                            </div>
                        </div>

                        ${generatePfandHistoryHTML(historyData.history)}
                        ${generateMissingBottlesHTML()}
                        ${generateSummaryHTML()}
                        ${generateNotesHTML()}
                    `;

                    $('#pfand-content').show().html(modalContent);

                    // Event Handler
                    bindPfandModalEvents(data, historyData);

                    // Aktiviere den Confirm Button
                    $('.pfand-confirm').prop('disabled', false);
                }

                function generateRefundedItemsSection(refundedItems, totalRefunded) {
                    if (!refundedItems || refundedItems.length === 0) return '';

                    // Group by order
                    const orderGroups = {};
                    refundedItems.forEach(item => {
                        if (!orderGroups[item.order_id]) {
                            orderGroups[item.order_id] = {
                                order_number: item.order_number,
                                date: item.date,
                                refund_date: item.refund_date,
                                refund_driver: item.refund_driver,
                                items: [],
                                total: 0
                            };
                        }
                        orderGroups[item.order_id].items.push(item);
                        orderGroups[item.order_id].total += (item.total_pfand * item.quantity);
                    });

                    let html = `
                        <div style="background:#d1fae5; border:2px solid #10b981; padding:15px; border-radius:8px; margin-bottom:15px;">
                            <h4 style="margin:0 0 12px 0; color:#059669; font-size:16px; display:flex; align-items:center; gap:8px;">
                                <span>📋</span> Bereits erstattete Artikel
                            </h4>
                    `;

                    Object.keys(orderGroups).forEach(orderId => {
                        const group = orderGroups[orderId];
                        html += `
                            <div style="background:white; padding:12px; border-radius:6px; margin-bottom:10px; border:1px solid #10b981;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                    <div>
                                        <strong style="color:#059669;">✓ Bestellung #${group.order_number}</strong>
                                        <div style="color:#6b7280; font-size:0.875rem; margin-top:2px;">
                                            Bestellt: ${group.date}
                                        </div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div style="font-weight:bold; color:#059669;">€${group.total.toFixed(2)}</div>
                                    </div>
                                </div>
                                <div style="border-top:1px solid #e5e5e5; padding-top:8px; margin-top:8px;">
                        `;

                        group.items.forEach(item => {
                            html += `
                                <div style="display:flex; justify-content:space-between; padding:4px 0; color:#374151; font-size:0.875rem;">
                                    <span>${item.quantity}x ${item.item_name}</span>
                                    <span>€${(item.pfand_per_item * item.quantity).toFixed(2)}</span>
                                </div>
                            `;
                        });

                        html += `
                                    <div style="border-top:1px solid #e5e5e5; margin-top:8px; padding-top:8px; color:#6b7280; font-size:0.75rem;">
                                        <div>Erstattet am: ${group.refund_date || 'unbekannt'}</div>
                                        ${group.refund_driver ? `<div>Durch: ${group.refund_driver}</div>` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    html += `
                            <div style="background:#059669; color:white; padding:10px; border-radius:6px; text-align:right; font-weight:bold;">
                                Gesamt erstattet: €${totalRefunded.toFixed(2)}
                            </div>
                        </div>
                    `;

                    return html;
                }

                function generatePfandHistoryHTML(history) {
                    if (!history || history.length === 0) return '';

                    // Gruppiere nach Bestellung
                    const orderGroups = {};
                    history.forEach(item => {
                        if (!orderGroups[item.order_id]) {
                            orderGroups[item.order_id] = {
                                order_number: item.order_number,
                                date: item.date,
                                items: []
                            };
                        }
                        orderGroups[item.order_id].items.push(item);
                    });

                    let html = `
                        <div style="background:#fff; border:1px solid #e5e5e5; padding:12px; border-radius:8px; margin-bottom:12px;">
                            <h4 style="margin:0 0 6px 0; color:#46b450; font-size:1rem;">🔄 Zurückgegebene Pfandartikel auswählen</h4>
                            <p style="margin:0 0 10px 0; color:#6b7280; font-size:0.875rem;">Wählen Sie die zurückgegebenen Artikel:</p>

                            <div style="margin-bottom:10px; display:flex; gap:8px;">
                                <button type="button" id="select-all-pfand" class="pfand-btn pfand-btn-primary pfand-btn-sm" style="flex:1;">
                                    <span>✓</span> Alle
                                </button>
                                <button type="button" id="deselect-all-pfand" class="pfand-btn pfand-btn-danger pfand-btn-sm" style="flex:1;">
                                    <span>✗</span> Keine
                                </button>
                            </div>
                    `;

                    Object.keys(orderGroups).forEach(orderId => {
                        const group = orderGroups[orderId];
                        html += `
                            <div style="border:1px solid #ddd; border-radius:5px; margin-bottom:10px; overflow:hidden;">
                                <div style="background:#f8f9fa; padding:10px; border-bottom:1px solid #ddd; font-weight:bold; color:#1f2937;">
                                    Bestellung #${group.order_number} - ${group.date}
                                </div>
                                <div style="padding:10px; background:#f8f9fa;">
                        `;

                        group.items.forEach(item => {
                            // FIX v2.9.8: Check if item was already refunded
                            // v2.9.9: Allow editing for admin corrections, but add visual warning
                            const isRefunded = item.already_refunded === true;
                            const refundedClass = isRefunded ? 'already-refunded-item' : '';
                            const refundedStyle = isRefunded ? 'border-left:4px solid #ffa500;' : '';
                            const refundedBadge = isRefunded ? '<span style="background:#ffa500; color:white; padding:2px 8px; border-radius:4px; font-size:0.75rem; margin-left:8px;">⚠️ Bereits erstattet</span>' : '';

                            html += `
                                <div class="pfand-item-row ${refundedClass}" style="${refundedStyle}" data-already-refunded="${isRefunded}">
                                    <div class="pfand-item-name">
                                        <input type="checkbox"
                                               class="pfand-item-checkbox"
                                               data-order-id="${item.order_id}"
                                               data-item-id="${item.item_id}"
                                               data-max-quantity="${item.quantity}"
                                               data-price-per-item="${item.pfand_per_item}"
                                               data-item-name="${item.item_name}"
                                               data-already-refunded="${isRefunded}"
                                               data-refund-driver="${item.refund_driver || ''}"
                                               data-refund-date="${item.refund_date || ''}"
                                               style="width:20px; height:20px; margin-right:12px;">
                                        <div style="flex:1;">
                                            <div style="color:#1f2937; font-size:1rem; font-weight:500;">${item.item_name} ${refundedBadge}</div>
                                            <div style="color:#6b7280; font-size:0.875rem; margin-top:4px;">
                                                €${item.pfand_per_item.toFixed(2)}/Stück • Max: ${item.quantity}
                                                ${isRefunded ? ' • Erstattet am ' + (item.refund_date || 'unbekannt') + (item.refund_driver ? ' durch ' + item.refund_driver : '') : ''}
                                            </div>
                                            ${isRefunded ? '<div style="color:#d97706; font-size:0.75rem; margin-top:4px; font-weight:600;">ℹ️ Korrektur möglich - Auswahl erfordert Bestätigung</div>' : ''}
                                        </div>
                                    </div>
                                    <div class="pfand-item-controls">
                                        <div class="pfand-quantity-control">
                                            <button type="button" class="pfand-quantity-btn quantity-btn minus" aria-label="Weniger">−</button>
                                            <input type="number" class="pfand-quantity-input item-quantity" value="0" min="0" max="${item.quantity}" aria-label="Menge" readonly>
                                            <button type="button" class="pfand-quantity-btn quantity-btn plus" aria-label="Mehr">+</button>
                                        </div>
                                        <div style="min-width:80px; text-align:right;">
                                            <div style="font-weight:bold; color:#1f2937; font-size:1.125rem;">€<span class="item-total">0.00</span></div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });

                        html += `
                                </div>
                            </div>
                        `;
                    });

                    html += `
                            <div id="selected-summary" style="display:none; background:#e8f5e8; padding:8px; border-radius:5px; text-align:right; margin-top:10px;">
                                <strong style="color:#1f2937;">Ausgewählte Pfand-Gutschrift: <span id="selected-total" style="color:#46b450;">€0.00</span></strong>
                            </div>
                        </div>
                    `;

                    return html;
                }

                function generateMissingBottlesHTML() {
                    // v2.9.82: Dynamisch aus pfandItemsConfig generieren
                    if (!pfandItemsConfig || pfandItemsConfig.length === 0) {
                        return ''; // Keine Pfand-Items konfiguriert
                    }

                    let itemsHTML = '';
                    pfandItemsConfig.forEach(item => {
                        itemsHTML += `
                            <div class="pfand-item-row" style="background:white; padding:10px; border-radius:5px;" data-pfand-id="${item.id}" data-pfand-amount="${item.amount}">
                                <div class="pfand-item-name">
                                    <div style="flex:1;">
                                        <div style="color:#374151; font-size:1rem; font-weight:500;">${item.icon} ${item.name}</div>
                                        <div style="color:#6b7280; font-size:0.875rem; margin-top:4px;">€${parseFloat(item.amount).toFixed(2)}/Stück</div>
                                    </div>
                                </div>
                                <div class="pfand-item-controls">
                                    <div class="pfand-quantity-control">
                                        <button type="button" class="pfand-quantity-btn missing-btn minus" data-target="${item.id}" aria-label="Weniger ${item.name}">−</button>
                                        <input type="number" id="missing-${item.id}" class="pfand-quantity-input missing-input" data-amount="${item.amount}" value="0" min="0" max="99" aria-label="Anzahl fehlende ${item.name}" readonly>
                                        <button type="button" class="pfand-quantity-btn missing-btn plus" data-target="${item.id}" aria-label="Mehr ${item.name}">+</button>
                                    </div>
                                    <div style="min-width:80px; text-align:right;">
                                        <div style="font-weight:bold; color:#dc3545; font-size:1rem;">€<span id="${item.id}-total">0.00</span></div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    return `
                        <div id="missing-bottles-section" style="display:none; background:#fff3cd; border:1px solid #ffeaa7; padding:12px; border-radius:8px; margin-bottom:12px;">
                            <h4 style="margin:0 0 8px 0; color:#856404; font-size:1rem;">⚠️ Fehlende Flaschen</h4>
                            <p style="margin:0 0 12px 0; color:#856404; font-size:0.875rem;">Abzug für fehlende Flaschen eingeben:</p>
                            <div style="display:flex; flex-direction:column; gap:12px;">
                                ${itemsHTML}
                            </div>
                            <div id="missing-summary" style="display:none; text-align:right; margin-top:15px; padding-top:15px; border-top:1px solid #ffeaa7;">
                                <strong style="color:#dc3545;">Abzug für fehlende Flaschen: <span id="missing-total">€0.00</span></strong>
                            </div>
                        </div>
                    `;
                }

                function generateSummaryHTML() {
                    return `
                        <div id="detailed-summary" style="display:none; background:#f8f9fa; padding:10px; border-radius:8px; margin-bottom:12px;">
                            <h4 style="margin:0 0 8px 0; color:#46b450; font-size:16px;">📋 Zusammenfassung der Rückgabe</h4>
                            <div id="summary-content" style="background:white; padding:10px; border-radius:5px; border:1px solid #ddd;">
                                <!-- Wird dynamisch gefüllt -->
                            </div>
                        </div>
                    `;
                }

                function generateNotesHTML() {
                    return `
                        <div style="background:#f8f9fa; padding:12px; border-radius:8px;">
                            <h4 style="margin:0 0 8px 0; color:#46b450; font-size:1rem;">📝 Notiz (optional)</h4>
                            <textarea id="pfand-reason" rows="2" style="width:100%; border:1px solid #ccc; border-radius:4px; padding:8px; font-size:0.875rem;" placeholder="z.B. Kunde reist ab"></textarea>

                            <div id="refund-info" style="margin-top:8px; padding:8px; background:#e7f5ff; border-radius:4px; border:1px solid #bee5eb; font-size:0.875rem;">
                                <i class="fas fa-info-circle" style="color:#0c5460;"></i>
                                <span style="color:#0c5460;">Pfandartikel werden als Gutschrift verrechnet.</span>
                            </div>
                        </div>
                    `;
                }

                function bindPfandModalEvents(data, historyData) {
                    let currentRefundMode = 'with-order';

                    // Refund Mode Wechsel
                    $('input[name="refund-mode"]').on('change', function() {
                        currentRefundMode = $(this).val();

                        if (currentRefundMode === 'pfand-only') {
                            $('#current-order-section').slideUp(200);
                            $('#pfand-only-summary').slideDown(200);
                            $('#refund-info').html('<i class="fas fa-info-circle" style="color:#0c5460;"></i> <span style="color:#0c5460;">Eine Gutschrift wird in WooCommerce erstellt. Der Betrag muss dem Kunden manuell ausgezahlt werden.</span>');
                            $('#pfand-confirm-text').text('Pfand auszahlen');
                        } else {
                            $('#pfand-only-summary').slideUp(200);
                            $('#current-order-section').slideDown(200);
                            $('#refund-info').html('<i class="fas fa-info-circle" style="color:#0c5460;"></i> <span style="color:#0c5460;">Die ausgewählten Pfandartikel werden als Gutschrift verrechnet.</span>');
                            $('#pfand-confirm-text').text('Verrechnung durchführen');
                        }

                        updatePfandTotals();
                    });

                    // Alle auswählen / abwählen
                    $('#select-all-pfand').on('click', function() {
                        $('.pfand-item-checkbox').each(function() {
                            const $checkbox = $(this);
                            const $quantity = $checkbox.closest('div').parent().find('.item-quantity');
                            const maxQuantity = parseInt($checkbox.data('max-quantity'));

                            // v2.9.9: Skip already refunded items in "Select All"
                            // Admin must explicitly select them to trigger confirmation
                            const isAlreadyRefunded = $checkbox.data('already-refunded') === true || $checkbox.data('already-refunded') === 'true';
                            if (isAlreadyRefunded) {
                                return; // Skip this item
                            }

                            $checkbox.prop('checked', true);
                            $quantity.val(maxQuantity);
                        });

                        $('#missing-bottles-section').show();
                        updatePfandTotals();
                    });

                    $('#deselect-all-pfand').on('click', function() {
                        $('.pfand-item-checkbox').prop('checked', false);
                        $('.item-quantity').val(0);
                        // v2.9.9: Remove highlight from correction items
                        $('.pfand-item-row').css('background', '');
                        $('#missing-bottles-section').hide();
                        updatePfandTotals();
                    });

                    // Checkbox Events
                    $('.pfand-item-checkbox').on('change', function() {
                        const $checkbox = $(this);
                        const $quantity = $checkbox.closest('div').parent().find('.item-quantity');
                        const maxQuantity = parseInt($checkbox.data('max-quantity'));

                        // v2.9.9: Check if this is an already refunded item - require confirmation
                        const isAlreadyRefunded = $checkbox.data('already-refunded') === true || $checkbox.data('already-refunded') === 'true';

                        if ($checkbox.is(':checked') && isAlreadyRefunded) {
                            const itemName = $checkbox.data('item-name');
                            const refundDriver = $checkbox.data('refund-driver');
                            const refundDate = $checkbox.data('refund-date');

                            let confirmMessage = '⚠️ ACHTUNG: Korrektur einer bereits durchgeführten Erstattung\n\n';
                            confirmMessage += `Artikel: ${itemName}\n`;
                            confirmMessage += `Bereits erstattet am: ${refundDate || 'unbekannt'}\n`;
                            if (refundDriver) {
                                confirmMessage += `Durch: ${refundDriver}\n`;
                            }
                            confirmMessage += '\n';
                            confirmMessage += 'Möchten Sie wirklich eine Korrektur durchführen?\n';
                            confirmMessage += 'Dies wird als neue Pfand-Erstattung verarbeitet.';

                            if (!confirm(confirmMessage)) {
                                // User cancelled - uncheck the checkbox
                                $checkbox.prop('checked', false);
                                return;
                            }

                            // User confirmed - highlight the correction
                            $checkbox.closest('.pfand-item-row').css('background', '#fef3c7');
                        }

                        if ($checkbox.is(':checked')) {
                            if (parseInt($quantity.val()) === 0) {
                                $quantity.val(maxQuantity);
                            }
                        } else {
                            $quantity.val(0);
                            // Remove highlight if unchecked
                            $checkbox.closest('.pfand-item-row').css('background', '');
                        }

                        updatePfandTotals();
                        toggleMissingBottlesSection();
                    });

                    // Quantity Events
                    $('.item-quantity').on('change input', function() {
                        const $quantity = $(this);
                        const $checkbox = $quantity.closest('div').parent().find('.pfand-item-checkbox');
                        const value = parseInt($quantity.val()) || 0;

                        if (value > 0) {
                            $checkbox.prop('checked', true);
                        } else {
                            $checkbox.prop('checked', false);
                        }

                        updatePfandTotals();
                        toggleMissingBottlesSection();
                    });

                    // Quantity Buttons
                    $('.quantity-btn').on('click', function() {
                        const $btn = $(this);
                        const $quantity = $btn.siblings('.item-quantity');
                        const $row = $btn.closest('.pfand-item-row');
                        const $checkbox = $row.find('.pfand-item-checkbox');
                        const currentVal = parseInt($quantity.val()) || 0;
                        const maxVal = parseInt($quantity.attr('max'));
                        const pricePerItem = parseFloat($checkbox.data('price-per-item')) || 0;

                        // v2.9.9: Check if this is an already refunded item when increasing quantity
                        const isAlreadyRefunded = $checkbox.data('already-refunded') === true || $checkbox.data('already-refunded') === 'true';

                        if ($btn.hasClass('plus') && currentVal < maxVal) {
                            // If already refunded and currently at 0, show confirmation
                            if (isAlreadyRefunded && currentVal === 0) {
                                const itemName = $checkbox.data('item-name');
                                const refundDriver = $checkbox.data('refund-driver');
                                const refundDate = $checkbox.data('refund-date');

                                let confirmMessage = '⚠️ ACHTUNG: Korrektur einer bereits durchgeführten Erstattung\n\n';
                                confirmMessage += `Artikel: ${itemName}\n`;
                                confirmMessage += `Bereits erstattet am: ${refundDate || 'unbekannt'}\n`;
                                if (refundDriver) {
                                    confirmMessage += `Durch: ${refundDriver}\n`;
                                }
                                confirmMessage += '\n';
                                confirmMessage += 'Möchten Sie wirklich eine Korrektur durchführen?\n';
                                confirmMessage += 'Dies wird als neue Pfand-Erstattung verarbeitet.';

                                if (!confirm(confirmMessage)) {
                                    return; // User cancelled
                                }

                                // User confirmed - highlight the correction
                                $row.css('background', '#fef3c7');
                            }

                            $quantity.val(currentVal + 1);
                            $checkbox.prop('checked', true);
                        } else if ($btn.hasClass('minus') && currentVal > 0) {
                            $quantity.val(currentVal - 1);
                            if (currentVal - 1 === 0) {
                                $checkbox.prop('checked', false);
                                // Remove highlight if going back to 0
                                $row.css('background', '');
                            }
                        }

                        // Update item total
                        const newQuantity = parseInt($quantity.val()) || 0;
                        const itemTotal = (newQuantity * pricePerItem).toFixed(2);
                        $row.find('.item-total').text(itemTotal);

                        updatePfandTotals();
                        toggleMissingBottlesSection();
                    });

                    // Missing Bottles Events
                    $('.missing-btn').on('click', function() {
                        const $btn = $(this);
                        const target = $btn.data('target');
                        const $input = $('#missing-' + target);
                        const currentVal = parseInt($input.val()) || 0;

                        if ($btn.hasClass('plus') && currentVal < 99) {
                            $input.val(currentVal + 1);
                        } else if ($btn.hasClass('minus') && currentVal > 0) {
                            $input.val(currentVal - 1);
                        }

                        updatePfandTotals();
                    });

                    // v2.9.82: Dynamischer Selector für alle konfigurierten Pfand-Items
                    $('.missing-input').on('change input', function() {
                        updatePfandTotals();
                    });

                    // Confirm Button Event
                    $('.pfand-confirm').on('click', function() {
                        confirmPfandRefund(data, currentRefundMode);
                    });
                }

                function toggleMissingBottlesSection() {
                    const hasSelectedItems = $('.pfand-item-checkbox:checked').length > 0;
                    if (hasSelectedItems) {
                        $('#missing-bottles-section').slideDown(200);
                    } else {
                        $('#missing-bottles-section').slideUp(200);
                    }
                }

                function updatePfandTotals() {
                    let totalCredit = 0;
                    let summaryItems = [];

                    // Berechne ausgewählte Pfand-Artikel
                    $('.pfand-item-checkbox:checked').each(function() {
                        const $checkbox = $(this);
                        const $quantity = $checkbox.closest('div').parent().find('.item-quantity');
                        const quantity = parseInt($quantity.val()) || 0;
                        const pricePerItem = parseFloat($checkbox.data('price-per-item'));
                        const itemName = $checkbox.data('item-name');

                        if (quantity > 0) {
                            const itemTotal = quantity * pricePerItem;
                            totalCredit += itemTotal;
                            summaryItems.push({
                                name: itemName,
                                quantity: quantity,
                                price: pricePerItem,
                                total: itemTotal
                            });
                        }
                    });

                    // Berechne fehlende Flaschen Abzug - v2.9.82: Dynamisch aus pfandItemsConfig
                    let totalDeduction = 0;

                    // Dynamische Berechnung für alle konfigurierten Pfand-Items
                    pfandItemsConfig.forEach(item => {
                        const missingCount = parseInt($('#missing-' + item.id).val()) || 0;
                        const itemAmount = parseFloat(item.amount);
                        const itemTotal = missingCount * itemAmount;

                        // Update individual bottle total display
                        $('#' + item.id + '-total').text(itemTotal.toFixed(2));

                        if (missingCount > 0) {
                            totalDeduction += itemTotal;
                            summaryItems.push({
                                name: 'Fehlende ' + item.name + 'n',
                                quantity: missingCount,
                                price: -itemAmount,
                                total: -itemTotal,
                                isDeduction: true
                            });
                        }
                    });

                    const netCredit = Math.max(0, totalCredit - totalDeduction);

                    // Update UI
                    $('#selected-total').text('€' + totalCredit.toFixed(2));

                    if (totalCredit > 0) {
                        $('#selected-summary').show();
                    } else {
                        $('#selected-summary').hide();
                    }

                    // Missing bottles summary
                    if (totalDeduction > 0) {
                        $('#missing-total').text('- €' + totalDeduction.toFixed(2));
                        $('#missing-summary').show();
                    } else {
                        $('#missing-summary').hide();
                    }

                    // Mode-specific updates
                    const currentMode = $('input[name="refund-mode"]:checked').val();

                    if (currentMode === 'pfand-only') {
                        $('#pfand-only-credit').text('€' + totalCredit.toFixed(2));
                        $('#pfand-deduction').text('- €' + totalDeduction.toFixed(2));
                        $('#pfand-only-total').text('€' + netCredit.toFixed(2));

                        if (totalDeduction > 0) {
                            $('#pfand-deduction-row').show();
                        } else {
                            $('#pfand-deduction-row').hide();
                        }

                        $('#pfand-confirm-text').text(netCredit > 0 ? `Pfand auszahlen (€${netCredit.toFixed(2)})` : 'Pfand auszahlen');
                    } else {
                        const orderTotal = parseFloat($('#order-total').text().replace('€', '')) || 0;
                        const finalAmount = Math.max(0, orderTotal - netCredit);

                        $('#credit-amount').text('- €' + netCredit.toFixed(2));
                        $('#final-amount').text('€' + finalAmount.toFixed(2));

                        if (netCredit > 0) {
                            $('#credit-row').show();
                        } else {
                            $('#credit-row').hide();
                        }

                        if (finalAmount < orderTotal && netCredit > 0) {
                            $('#pfand-confirm-text').text(`Verrechnung durchführen (€${netCredit.toFixed(2)} Gutschrift)`);
                        } else {
                            $('#pfand-confirm-text').text('Verrechnung durchführen');
                        }
                    }

                    // Detailed Summary
                    if (summaryItems.length > 0) {
                        let summaryHtml = '<ul style="list-style:none; padding:0; margin:0;">';

                        summaryItems.forEach(item => {
                            const style = item.isDeduction ? 'color:#dc3545;' : 'color:#1f2937;';
                            summaryHtml += `
                                <li style="display:flex; justify-content:space-between; padding:5px 0; border-bottom:1px solid #eee; ${style}">
                                    <span>${item.quantity}x ${item.name}</span>
                                    <span>${item.total >= 0 ? '+' : ''}€${item.total.toFixed(2)}</span>
                                </li>
                            `;
                        });

                        summaryHtml += `</ul>
                            <div style="border-top:2px solid #46b450; padding-top:10px; margin-top:10px; text-align:right;">
                                <strong style="color:#1f2937;">Gesamte Gutschrift: <span style="color:#46b450;">€${netCredit.toFixed(2)}</span></strong>
                            </div>
                        `;

                        $('#summary-content').html(summaryHtml);
                        $('#detailed-summary').show();
                    } else {
                        $('#detailed-summary').hide();
                    }
                }

                function confirmPfandRefund(data, refundMode) {
                    // Sammle ausgewählte Items
                    const refundItems = [];
                    $('.pfand-item-checkbox:checked').each(function() {
                        const $checkbox = $(this);
                        const $quantity = $checkbox.closest('div').parent().find('.item-quantity');
                        const quantity = parseInt($quantity.val()) || 0;

                        if (quantity > 0) {
                            const pricePerItem = parseFloat($checkbox.data('price-per-item'));
                            refundItems.push({
                                order_id: $checkbox.data('order-id'),
                                item_id: $checkbox.data('item-id'),
                                item_name: $checkbox.data('item-name'),
                                quantity: quantity,
                                amount: (quantity * pricePerItem).toFixed(2)
                            });
                        }
                    });

                    if (refundItems.length === 0) {
                        alert('Bitte wählen Sie mindestens einen Pfand-Artikel aus.');
                        return;
                    }

                    // Sammle fehlende Flaschen - v2.9.82: Dynamisch aus pfandItemsConfig
                    const missingBottles = {};
                    pfandItemsConfig.forEach(item => {
                        const missingCount = parseInt($('#missing-' + item.id).val()) || 0;
                        if (missingCount > 0) {
                            missingBottles[item.name] = missingCount;
                        }
                    });

                    const reason = $('#pfand-reason').val().trim();

                    // Deaktiviere Button
                    $('.pfand-confirm').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Wird verarbeitet...');

                    // Calculate final amount for display
                    const orderTotal = parseFloat($('#order-total').text().replace('€', '')) || parseFloat(data.order_total) || 0;
                    const creditAmount = parseFloat($('#credit-amount').text().replace('- €', '').replace('€', '')) || 0;
                    const finalAmount = Math.max(0, orderTotal - creditAmount);

                    // AJAX Request
                    $.ajax({
                        url: dispatch_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'dispatch_refund_pfand',
                            current_order_id: data.orderId,
                            refund_items: refundItems,
                            missing_bottles: missingBottles,
                            reason: reason,
                            refund_mode: refundMode,
                            customer_email: data.customerEmail,
                            nonce: dispatch_ajax.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Show success message with the actual amounts
                                let successMessage = response.data.message;
                                if (refundMode === 'with-order' && response.data.new_total !== undefined) {
                                    const pfandAmount = parseFloat(response.data.pfand_amount || 0);
                                    const newTotal = parseFloat(response.data.new_total);

                                    // Create big, clear message for mobile
                                    successMessage = `✅ PFAND VERRECHNET!\n\n` +
                                                   `Pfand: €${pfandAmount.toFixed(2)}\n` +
                                                   `━━━━━━━━━━━━━━\n\n` +
                                                   `💰 ZU KASSIEREN:\n` +
                                                   `   €${newTotal.toFixed(2)}\n\n` +
                                                   `━━━━━━━━━━━━━━`;

                                    // Update the "Zu zahlen" field if it exists on the page
                                    $('.payment-info .total-value').text('€' + newTotal.toFixed(2));
                                    $('.total-row.grand-total .total-value').text('€' + newTotal.toFixed(2));
                                }

                                // UX FIX v2.9.7: Remove entire modal wrapper properly
                                $('.pfand-modal-wrapper').fadeOut(300, function() {
                                    $(this).remove();
                                });

                                // Show success message
                                alert(successMessage);

                                // UX FIX v2.9.7: Reload order details to show updated amounts
                                // This ensures driver stays on order details page and sees new "Zu zahlen"
                                // Uses AJAX (showOrderDetail) instead of page reload - no race conditions!
                                if (typeof window.showOrderDetail === 'function') {
                                    console.log('[v2.9.7] Reloading order details via AJAX for orderId:', data.orderId);
                                    window.showOrderDetail(data.orderId);
                                } else {
                                    console.warn('[v2.9.7] showOrderDetail function not available, trying alternative...');
                                    // Fallback: manually reload via fetch
                                    fetch(dispatch_ajax.ajax_url, {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                        body: 'action=get_order_details&order_id=' + data.orderId + '&nonce=' + dispatch_ajax.nonce,
                                        credentials: 'same-origin'
                                    })
                                    .then(response => response.json())
                                    .then(detailData => {
                                        if (detailData.success && detailData.data && typeof displayOrderDetail === 'function') {
                                            displayOrderDetail(detailData.data);
                                        }
                                    })
                                    .catch(err => console.error('[v2.9.7] Failed to reload order details:', err));
                                }
                            } else {
                                alert('❌ Fehler: ' + (response.data?.message || response.data || 'Unbekannter Fehler'));
                                $('.pfand-confirm').prop('disabled', false).html('<i class="fas fa-check"></i> <span id="pfand-confirm-text">Verrechnung durchführen</span>');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', {
                                status: xhr.status,
                                statusText: xhr.statusText,
                                responseText: xhr.responseText,
                                error: error
                            });

                            let errorMessage = '❌ Fehler beim Verarbeiten der Pfand-Rückerstattung:\n\n';

                            if (xhr.status === 500) {
                                errorMessage += 'Serverfehler - bitte prüfen Sie die Server-Logs.';
                            } else if (xhr.status === 403) {
                                errorMessage += 'Keine Berechtigung - bitte neu anmelden.';
                            } else if (xhr.status === 0) {
                                errorMessage += 'Netzwerkfehler - bitte Verbindung prüfen.';
                            } else {
                                errorMessage += error || xhr.statusText || 'Unbekannter Fehler';
                            }

                            alert(errorMessage);
                            $('.pfand-confirm').prop('disabled', false).html('<i class="fas fa-check"></i> <span id="pfand-confirm-text">Verrechnung durchführen</span>');
                        }
                    });
                }

            }); // End of jQuery ready

            // END PFAND IMPLEMENTATION
            function showEmptyState() {
                mainContent = document.querySelector('.main-content');
                if (mainContent) {
                    // Add orders-page class for full width styling
                    mainContent.classList.add('orders-page');
                    mainContent.innerHTML = `
                        <div class="empty-state-screen">
                            <div class="empty-state-icon">
                                <svg viewBox="0 0 24 24">
                                    <path d="M19 7h-3V6a4 4 0 0 0-8 0v1H5a1 1 0 0 0-1 1v11a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V8a1 1 0 0 0-1-1zM10 6a2 2 0 0 1 4 0v1h-4V6zm8 13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V9h2v1a1 1 0 0 0 2 0V9h2v10z"/>
                                </svg>
                            </div>
                            <div class="empty-state-message">Sie haben keine Bestellungen</div>
                        </div>
                        
                        <!-- Bottom Navigation -->
                        <div class="bottom-navigation">
                            <a href="#bestellungen" class="nav-item active" onclick="showBestellungen()">
                                <div class="icon">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 2v3h8V2H8zM9 9l3 4 4-6 1 1.5L12 15 8 10l1-1z"/>
                            <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.89-1.99 2L3 19a2 2 0 002 2h14c1.1 0 2-.9 2-2V5c0-1.11-.9-2-2-2zm0 16H5V8h14v11z"/>
                        </svg>
                    </div>
                                <div class="label" data-i18n="orders">Bestellungen</div>
                            </a>
                            <a href="#karte" class="nav-item" onclick="showKarte()">
                                <div class="icon">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/>
                        </svg>
                    </div>
                                <div class="label" data-i18n="map">Karte</div>
                            </a>
                            <a href="#warten" class="nav-item" onclick="showWarten()">
                                <div class="icon">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M15 1H9v2h6V1zm-4 13h2V8h-2v6zm8.03-6.61l1.42-1.42c-.43-.51-.9-.99-1.41-1.41l-1.42 1.42C16.07 4.74 14.12 4 12 4c-4.97 0-9 4.03-9 9s4.02 9 9 9 9-4.03 9-9c0-2.12-.74-4.07-1.97-5.61zM12 20c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/>
                        </svg>
                    </div>
                                <div class="label" data-i18n="waiting">Warten</div>
                            </a>
                            <a href="#packliste" class="nav-item" onclick="showPackliste()">
                                <div class="icon">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                        </svg>
                    </div>
                                <div class="label" data-i18n="packlist">Packliste</div>
                            </a>
                        </div>
                    `;
                }
            }
            
            // Show Dashboard Function
            function showDashboard() {
                try {
                    // Close hamburger menu first
                    toggleMenu();
                    
                    // Remove orders-page class if present
                    mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        mainContent.classList.remove('orders-page');
                    }
                    
                    // Reload the page to show dashboard
                    location.reload();
                } catch (error) {
                    console.error('Error in showDashboard:', error);
                }
            }
            
            // Neue schlichte Menu-Version benötigt kein dynamisches Update mehr
            function updateHamburgerMenuForOnlineStatus() {
                // Menu ist jetzt statisch - keine dynamischen Updates nötig
                console.log('Menu update skipped - using static menu');
            }

            function updateHamburgerMenuForOfflineStatus() {
                // Menu ist jetzt statisch - keine dynamischen Updates nötig
                console.log('Menu update skipped - using static menu');
            }

            // Legacy - alte Funktion für Kompatibilität
            function _legacyUpdateHamburgerMenuForOnlineStatus() {
                try {

                    // Get user data
                    fetch(dispatch_ajax.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=dispatch_get_mobile_profile&nonce=' + dispatch_ajax.nonce + ''
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const profileData = data.data;
                            const menuItems = document.querySelector('.menu-items-legacy');
                            if (menuItems) {
                                menuItems.innerHTML = `
                                    <li class="driver-info-menu">
                                        <div class="driver-avatar-small">${profileData.initials || 'KA'}</div>
                                        <div class="driver-details-menu">
                                            <span class="driver-name-menu">${profileData.name || 'Klaus Arends'}</span>
                                            <span class="driver-status-online">• Online</span>
                                        </div>
                                    </li>
                                    <li><a href="#karte" onclick="showRouting(); return false;">Karte</a></li>
                                    <li><a href="#packliste" onclick="showPackliste(); return false;">Packliste</a></li>
                                    <li><a href="#vollstaendige-bestellungen" onclick="showVollstaendigeBestellungen(); return false;">Vollständige Bestellungen</a></li>
                                    <li><a href="#leistung" onclick="showLeistung(); return false;">Leistung</a></li>
                                    <li><a href="#einstellungen" onclick="showEinstellungen(); return false;">Einstellungen</a></li>
                                    <li><a href="#sprache" onclick="showSprache(); return false;">Sprache</a></li>
                                    <li style="margin-top: 20px; padding: 15px;">
                                        <button onclick="goOffline(); return false;" style="
                                            width: 100%;
                                            padding: 20px;
                                            background: #ef4444;
                                            border: none;
                                            border-radius: 50px;
                                            color: white;
                                            font-size: 18px;
                                            font-weight: 600;
                                            cursor: pointer;
                                            transition: all 0.3s ease;
                                            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
                                        " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 25px rgba(239, 68, 68, 0.5)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(239, 68, 68, 0.4)';">
                                            Offline gehen
                                        </button>
                                    </li>
                                    <li style="margin-top: 10px;">
                                        <a href="<?php echo wp_logout_url(home_url('/fahrer-login/')); ?>" style="
                                            display: block;
                                            padding: 15px;
                                            color: #ef4444;
                                            text-decoration: none;
                                            font-size: 16px;
                                            font-weight: 500;
                                            text-align: center;
                                            transition: background 0.3s ease;
                                        " onmouseover="this.style.background='rgba(239, 68, 68, 0.1)';" onmouseout="this.style.background='transparent';">
                                            Abmelden
                                        </a>
                                    </li>
                                `;
                            }
                        } else {
                            // Fallback with static data
                            const menuItems = document.querySelector('.menu-items');
                            if (menuItems) {
                                menuItems.innerHTML = `
                                    <li class="driver-info-menu">
                                        <div class="driver-avatar-small">KA</div>
                                        <div class="driver-details-menu">
                                            <span class="driver-name-menu">Klaus Arends</span>
                                            <span class="driver-status-online">• Online</span>
                                        </div>
                                    </li>
                                    <li><a href="#karte" onclick="showRouting(); return false;">Karte</a></li>
                                    <li><a href="#packliste" onclick="showPackliste(); return false;">Packliste</a></li>
                                    <li><a href="#vollstaendige-bestellungen" onclick="showVollstaendigeBestellungen(); return false;">Vollständige Bestellungen</a></li>
                                    <li><a href="#leistung" onclick="showLeistung(); return false;">Leistung</a></li>
                                    <li><a href="#einstellungen" onclick="showEinstellungen(); return false;">Einstellungen</a></li>
                                    <li><a href="#sprache" onclick="showSprache(); return false;">Sprache</a></li>
                                    <li style="margin-top: 20px; padding: 15px;">
                                        <button onclick="goOffline(); return false;" style="
                                            width: 100%;
                                            padding: 20px;
                                            background: #ef4444;
                                            border: none;
                                            border-radius: 50px;
                                            color: white;
                                            font-size: 18px;
                                            font-weight: 600;
                                            cursor: pointer;
                                            transition: all 0.3s ease;
                                            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
                                        " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 25px rgba(239, 68, 68, 0.5)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(239, 68, 68, 0.4)';">
                                            Offline gehen
                                        </button>
                                    </li>
                                    <li style="margin-top: 10px;">
                                        <a href="<?php echo wp_logout_url(home_url('/fahrer-login/')); ?>" style="
                                            display: block;
                                            padding: 15px;
                                            color: #ef4444;
                                            text-decoration: none;
                                            font-size: 16px;
                                            font-weight: 500;
                                            text-align: center;
                                            transition: background 0.3s ease;
                                        " onmouseover="this.style.background='rgba(239, 68, 68, 0.1)';" onmouseout="this.style.background='transparent';">
                                            Abmelden
                                        </a>
                                    </li>
                                `;
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching profile data for menu:', error);
                        // Use fallback
                        const menuItems = document.querySelector('.menu-items');
                        if (menuItems) {
                            menuItems.innerHTML = `
                                <li class="driver-info-menu">
                                    <div class="driver-avatar-small">KA</div>
                                    <div class="driver-details-menu">
                                        <span class="driver-name-menu">Klaus Arends</span>
                                        <span class="driver-status-online">• Online</span>
                                    </div>
                                </li>
                                <li><a href="#vollstaendige-bestellungen" onclick="showVollstaendigeBestellungen(); return false;">Vollständige Bestellungen</a></li>
                                <li><a href="#einstellungen" onclick="showEinstellungen(); return false;">Einstellungen</a></li>
                                <li><a href="#sprache" onclick="showSprache(); return false;">Sprache</a></li>
                            `;
                        }
                    });
                } catch (error) {
                    console.error('Error updating hamburger menu for online status:', error);
                }
            }

            // Handle Push Notification Toggle
            async function handlePushToggle(checkbox) {
                try {
                    console.log('🔔 handlePushToggle called, checkbox.checked:', checkbox.checked);

                    if (!('Notification' in window)) {
                        console.error('❌ Notification API not available');
                        showNotificationToast('❌ Push-Benachrichtigungen werden in diesem Browser nicht unterstützt', 'error');
                        checkbox.checked = false;
                        return;
                    }

                    console.log('📱 Current notification permission:', Notification.permission);

                    if (checkbox.checked) {
                        // User wants to enable push notifications
                        console.log('👆 User enabled push, requesting permission...');

                        const permission = await Notification.requestPermission();
                        console.log('✅ Permission result:', permission);

                        if (permission === 'granted') {
                            console.log('🎉 Permission granted! Subscribing to push...');

                            // Get service worker registration
                            const swScope = '<?php echo plugin_dir_url(__FILE__); ?>pwa/';
                            console.log('🔍 Looking for SW with scope:', swScope);
                            let registration = await navigator.serviceWorker.getRegistration(swScope);

                            if (!registration) {
                                console.log('❌ Service worker not found, registering new one...');
                                registration = await navigator.serviceWorker.register(
                                    '<?php echo plugin_dir_url(__FILE__); ?>pwa/service-worker.js',
                                    { scope: swScope }
                                );
                                console.log('⏳ Waiting for service worker to be ready...');
                                await navigator.serviceWorker.ready;
                                console.log('✅ Service worker ready!');
                            } else {
                                console.log('✅ Service worker found:', registration.scope);
                                console.log('📊 SW state:', registration.active ? 'active' : (registration.waiting ? 'waiting' : 'installing'));
                            }

                            // Subscribe to push
                            const vapidPublicKey = '<?php echo esc_js(get_option("dispatch_vapid_public_key", "")); ?>';
                            console.log('🔑 Using VAPID key:', vapidPublicKey ? vapidPublicKey.substring(0, 20) + '...' : 'MISSING');

                            if (!vapidPublicKey || vapidPublicKey.length < 20) {
                                throw new Error('VAPID public key is missing or invalid. Please reinstall the plugin.');
                            }

                            // Validate VAPID key format
                            try {
                                const keyArray = urlBase64ToUint8Array(vapidPublicKey);
                                console.log('✅ VAPID key converted successfully, length:', keyArray.length);
                            } catch (keyError) {
                                console.error('❌ Invalid VAPID key format:', keyError);
                                throw new Error('VAPID key format is invalid. Please reinstall the plugin.');
                            }

                            const subscription = await registration.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
                            });

                            console.log('📱 Subscription created:', subscription.endpoint.substring(0, 50) + '...');

                            // Save subscription to server
                            console.log('💾 Saving subscription to server...');
                            const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({
                                    action: 'dispatch_save_push_subscription',
                                    subscription: JSON.stringify(subscription.toJSON()),
                                    user_id: '<?php echo get_current_user_id(); ?>',
                                    nonce: dispatch_ajax.nonce
                                })
                            });

                            const data = await response.json();
                            console.log('📡 Server response:', data);

                            if (data.success) {
                                console.log('🎉 Push-Benachrichtigungen erfolgreich aktiviert!');
                                showNotificationToast('✅ Push-Benachrichtigungen aktiviert', 'success');
                            } else {
                                throw new Error('Server error: ' + (data.data || 'Unknown'));
                            }
                        } else {
                            // Permission denied
                            checkbox.checked = false;
                            showNotificationToast('❌ Berechtigung verweigert. Bitte in Browser-Einstellungen aktivieren.', 'error');
                        }
                    } else {
                        // User wants to disable push notifications
                        console.log('Disabling push notifications...');

                        const registration = await navigator.serviceWorker.getRegistration();
                        if (registration) {
                            const subscription = await registration.pushManager.getSubscription();
                            if (subscription) {
                                await subscription.unsubscribe();
                                showNotificationToast('🔕 Push-Benachrichtigungen deaktiviert', 'info');
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error toggling push notifications:', error);
                    checkbox.checked = false;
                    showNotificationToast('❌ Fehler: ' + error.message, 'error');
                }
            }

            // Helper function for VAPID key conversion
            function urlBase64ToUint8Array(base64String) {
                // Remove any whitespace
                base64String = base64String.trim();

                const padding = '='.repeat((4 - base64String.length % 4) % 4);
                const base64 = (base64String + padding)
                    .replace(/\-/g, '+')
                    .replace(/_/g, '/');
                const rawData = window.atob(base64);
                const outputArray = new Uint8Array(rawData.length);
                for (let i = 0; i < rawData.length; ++i) {
                    outputArray[i] = rawData.charCodeAt(i);
                }
                return outputArray;
            }

            // Placeholder functions for online menu items
            function showPackliste() {
                try {
                    toggleMenu(); // Close hamburger menu

                    // Update header title
                    const headerTitle = document.querySelector('.header-title');
                    if (headerTitle) {
                        headerTitle.textContent = translations[currentLanguage].packlist || 'Packliste';
                    }

                    // Restore hamburger menu (Packliste is a main navigation item, not a submenu)
                    restoreHamburgerMenu();
                    
                    mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        // Add full-width class for packliste
                        mainContent.className = 'main-content orders-page';
                        
                        mainContent.innerHTML = `
                            <div class="packliste-container" style="background: #111827; height: 100%; overflow-y: auto; padding: 15px;">
                                
                                <!-- Header Stats -->
                                <div class="packliste-stats" style="background: #1F2937; border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; text-align: center;">
                                        <div>
                                            <div style="color: #10B981; font-size: 24px; font-weight: 700;" id="total-orders">-</div>
                                            <div style="color: #9CA3AF; font-size: 12px;">${translations[currentLanguage].orders}</div>
                                        </div>
                                        <div>
                                            <div style="color: #F59E0B; font-size: 24px; font-weight: 700;" id="total-items">-</div>
                                            <div style="color: #9CA3AF; font-size: 12px;">${translations[currentLanguage].items}</div>
                                        </div>
                                        <div>
                                            <div style="color: #3B82F6; font-size: 24px; font-weight: 700;" id="total-value">-</div>
                                            <div style="color: #9CA3AF; font-size: 12px;">${translations[currentLanguage].value}</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Filter Options -->
                                <div class="packliste-filters" style="display: flex; gap: 8px; margin-bottom: 20px; overflow-x: auto; justify-content: space-between; align-items: center;">
                                    <div style="display: flex; gap: 8px;">
                                        <button class="filter-btn" data-filter="all" onclick="filterPackliste('all')"
                                                style="background: #374151; color: #9CA3AF; border: none; padding: 8px 16px; border-radius: 20px; white-space: nowrap; font-size: 14px; cursor: pointer;">
                                            ${translations[currentLanguage].all}
                                        </button>
                                        <button class="filter-btn active" data-filter="current" onclick="filterPackliste('current')"
                                                style="background: #10B981; color: white; border: none; padding: 8px 16px; border-radius: 20px; white-space: nowrap; font-size: 14px; cursor: pointer;">
                                            ${translations[currentLanguage].currentOrders}
                                        </button>
                                        <button class="filter-btn" data-filter="scheduled" onclick="filterPackliste('scheduled')"
                                                style="background: #374151; color: #9CA3AF; border: none; padding: 8px 16px; border-radius: 20px; white-space: nowrap; font-size: 14px; cursor: pointer;">
                                            ${translations[currentLanguage].scheduledOrders}
                                        </button>
                                    </div>
                                    <!-- Kein Button mehr - automatischer Abschluss -->
                                </div>
                                
                                <!-- Loading State -->
                                <div id="packliste-loading" class="loading-state" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 200px; color: #9CA3AF;">
                                    <div style="margin-bottom: 16px; font-size: 32px;">📦</div>
                                    <div>${translations[currentLanguage].loadingPacklist}</div>
                                </div>
                                
                                <!-- Packliste Content -->
                                <div id="packliste-content" style="display: none;">
                                    <!-- Will be populated by loadPackliste() -->
                                </div>
                                
                                <!-- Bottom Navigation -->
                                <div class="bottom-navigation" style="margin-top: 80px;">
                                    <a href="#bestellungen" class="nav-item active" onclick="showBestellungen()">
                                        <div class="icon">
                                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M8 2v3h8V2H8zM9 9l3 4 4-6 1 1.5L12 15 8 10l1-1z"/>
                                                <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.89-1.99 2L3 19a2 2 0 002 2h14c1.1 0 2-.9 2-2V5c0-1.11-.9-2-2-2zm0 16H5V8h14v11z"/>
                                            </svg>
                                        </div>
                                        <div class="label" data-i18n="orders">${translations[currentLanguage].orders}</div>
                                    </a>
                                    <a href="#karte" class="nav-item" onclick="showKarte()">
                                        <div class="icon">
                                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/>
                                            </svg>
                                        </div>
                                        <div class="label" data-i18n="map">${translations[currentLanguage].map}</div>
                                    </a>
                                    <a href="#warten" class="nav-item" onclick="showWarten()">
                                        <div class="icon">
                                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M15 1H9v2h6V1zm-4 13h2V8h-2v6zm8.03-6.61l1.42-1.42c-.43-.51-.9-.99-1.41-1.41l-1.42 1.42C16.07 4.74 14.12 4 12 4c-4.97 0-9 4.03-9 9s4.02 9 9 9 9-4.03 9-9c0-2.12-.74-4.07-1.97-5.61zM12 20c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/>
                                            </svg>
                                        </div>
                                        <div class="label" data-i18n="waiting">${translations[currentLanguage].waiting}</div>
                                    </a>
                                    <a href="#packliste" class="nav-item" onclick="showPackliste()">
                                        <div class="icon">
                                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                                            </svg>
                                        </div>
                                        <div class="label" data-i18n="packlist">${translations[currentLanguage].packlist}</div>
                                    </a>
                                </div>
                                
                            </div>
                        `;
                        
                        // Load packliste data
                        loadPackliste();
                    }
                } catch (error) {
                    console.error('Error in showPackliste:', error);
                }
            }
            
            function loadPackliste() {
                try {
                    
                    fetch(dispatch_ajax.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'Cache-Control': 'no-cache'
                        },
                        body: 'action=get_driver_packliste&nonce=' + dispatch_ajax.nonce + '&_t=' + Date.now(),
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            displayPackliste(data.data);
                        } else {
                            console.error('Failed to load packliste:', data);
                            showEmptyPackliste();
                        }
                    })
                    .catch(error => {
                        console.error('Error loading packliste:', error);
                        showEmptyPackliste();
                    });
                } catch (error) {
                    console.error('Error in loadPackliste:', error);
                    showEmptyPackliste();
                }
            }
            
            function displayPackliste(data) {
                try {
                    const loadingState = document.getElementById('packliste-loading');
                    const packlisteContent = document.getElementById('packliste-content');

                    if (loadingState) loadingState.style.display = 'none';
                    if (packlisteContent) packlisteContent.style.display = 'block';

                    // Update stats
                    const totalOrders = document.getElementById('total-orders');
                    const totalItems = document.getElementById('total-items');
                    const totalValue = document.getElementById('total-value');

                    // Stats are in data.stats object
                    const stats = data.stats || {};
                    if (totalOrders) totalOrders.textContent = stats.total_orders || '0';
                    if (totalItems) totalItems.textContent = stats.total_items || '0';
                    if (totalValue) totalValue.textContent = stats.total_value || '0,00 €';

                    const orders = data.orders || [];

                    console.log('Display Packliste - Received orders:', orders);
                    console.log('First order object:', orders[0]);

                    // Debug: Show order_type for all orders
                    orders.forEach((order, idx) => {
                        console.log(`Order ${idx}: #${order.order_id}, delivery_date="${order.delivery_date}", order_type="${order.order_type}"`);
                    });

                    if (orders.length === 0) {
                        showEmptyPackliste();
                        return;
                    }

                    let packlisteHTML = '';

                    orders.forEach((order, orderIndex) => {
                        // Use order_id if available, fallback to id
                        const orderId = order.order_id || order.id;
                        console.log(`Order ${orderIndex}: order_id=${order.order_id}, id=${order.id}, using=${orderId}`);
                        const statusColor = getOrderStatusColor(order.status);
                        const statusText = getOrderStatusText(order.status);

                        // Calculate display sequence number (first item in list = last delivery)
                        const displaySequence = order.delivery_sequence && order.delivery_sequence !== 9999
                            ? order.delivery_sequence
                            : (orders.length - orderIndex); // Fallback to position-based numbering

                        packlisteHTML += `
                            <div class="order-pack-card" data-status="${order.status}" data-order-id="${orderId}" data-order-type="${order.order_type || 'current'}" data-delivery-date="${order.delivery_date || ''}" style="background: #1F2937; border: 1px solid #374151; border-radius: 12px; margin-bottom: 16px; overflow: hidden;">

                                <!-- Order Header -->
                                <div class="order-pack-header" style="padding: 16px; background: ${statusColor}15; border-bottom: 1px solid #374151;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div style="flex: 1;">
                                            <div style="display: flex; align-items: center; gap: 8px; color: white; font-weight: 600; font-size: 16px; margin-bottom: 4px;">
                                                <span>Bestellung #${order.order_number}</span>
                                                <span style="background: #059669; color: white; padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 600;">
                                                    Liefern: ${displaySequence}
                                                </span>
                                            </div>
                                            <div style="color: #9CA3AF; font-size: 14px;">
                                                ${order.customer_name}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Items List -->
                                <div class="order-items-list" style="padding: 0;">
                        `;
                        
                        order.items.forEach((item, itemIndex) => {
                            // Use image if available, otherwise use emoji
                            const imageContent = item.image_url
                                ? `<img src="${item.image_url}" alt="${item.name}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px; cursor: pointer;" onclick="showImageModal('${item.image_url}', '${item.name}')">`
                                : `<span style="font-size: 24px;">📦</span>`;

                            // Parse variation info from name if it's in the old format
                            let itemName = item.name;
                            let parsedSize = item.size;
                            let parsedFlavor = item.flavor;

                            // Check if variation info is embedded in the name (old format)
                            if (!parsedSize && !parsedFlavor && itemName && itemName.includes('(') && itemName.includes(')')) {
                                const nameMatch = itemName.match(/^(.*?)\s*\((.*?)\)$/);
                                if (nameMatch) {
                                    itemName = nameMatch[1].trim();
                                    const variations = nameMatch[2];

                                    // Parse variations
                                    const parts = variations.split(',').map(p => p.trim());
                                    parts.forEach(part => {
                                        const lowerPart = part.toLowerCase();
                                        if (lowerPart.includes('liter') || lowerPart.includes(' l') || lowerPart.includes('ml')) {
                                            parsedSize = part;
                                        } else if (lowerPart.includes(':')) {
                                            const [key, value] = part.split(':').map(p => p.trim());
                                            if (key.toLowerCase().includes('geschmack') || key.toLowerCase().includes('flavor')) {
                                                parsedFlavor = value;
                                            } else if (key.toLowerCase().includes('liter') || key.toLowerCase().includes('size')) {
                                                parsedSize = value;
                                            }
                                        } else {
                                            // If not size, assume it's flavor
                                            if (!parsedSize) {
                                                parsedFlavor = part;
                                            }
                                        }
                                    });
                                }
                            }

                            // Build variation display with visual separation
                            let variationDisplay = '';
                            if (parsedSize || parsedFlavor || item.package_quantity) {
                                variationDisplay = '<div style="display: flex; gap: 10px; margin-top: 6px; flex-wrap: wrap;">';

                                // Size/Volume badge (prominent display)
                                if (parsedSize) {
                                    variationDisplay += `
                                        <span style="
                                            background: #3B82F6;
                                            color: white;
                                            padding: 4px 10px;
                                            border-radius: 6px;
                                            font-size: 14px;
                                            font-weight: 600;
                                            display: inline-flex;
                                            align-items: center;
                                            gap: 4px;
                                        ">
                                            <span style="font-size: 16px;">📏</span> ${parsedSize}
                                        </span>
                                    `;
                                }

                                // Flavor badge (different color for distinction)
                                if (parsedFlavor) {
                                    variationDisplay += `
                                        <span style="
                                            background: #10B981;
                                            color: white;
                                            padding: 4px 10px;
                                            border-radius: 6px;
                                            font-size: 14px;
                                            font-weight: 600;
                                            display: inline-flex;
                                            align-items: center;
                                            gap: 4px;
                                        ">
                                            <span style="font-size: 16px;">🍹</span> ${parsedFlavor}
                                        </span>
                                    `;
                                }

                                // Package quantity badge (Menge)
                                if (item.package_quantity) {
                                    variationDisplay += `
                                        <span style="
                                            background: #F59E0B;
                                            color: white;
                                            padding: 4px 10px;
                                            border-radius: 6px;
                                            font-size: 14px;
                                            font-weight: 600;
                                            display: inline-flex;
                                            align-items: center;
                                            gap: 4px;
                                        ">
                                            <span style="font-size: 16px;">📦</span> ${item.package_quantity}
                                        </span>
                                    `;
                                }

                                variationDisplay += '</div>';
                            } else if (item.variation_info) {
                                // Fallback to original variation info if no specific fields
                                variationDisplay = `<div style="color: #9CA3AF; font-size: 13px; margin-top: 4px;">${item.variation_info}</div>`;
                            }

                            packlisteHTML += `
                                <div class="pack-item" style="display: flex; align-items: center; padding: 12px 16px; border-bottom: ${itemIndex < order.items.length - 1 ? '1px solid #374151' : 'none'};">
                                    <div class="item-quantity" style="color: #F59E0B; font-weight: 700; font-size: 20px; min-width: 45px; text-align: center; margin-right: 12px; background: rgba(245, 158, 11, 0.1); padding: 8px; border-radius: 8px;">
                                        ${item.quantity}x
                                    </div>
                                    <div class="item-image" style="width: 60px; height: 60px; background: #374151; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px; overflow: hidden;">
                                        ${imageContent}
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="color: white; font-weight: 600; font-size: 17px;">
                                            ${item.name}
                                        </div>
                                        ${variationDisplay}
                                    </div>
                                    <div style="display: flex; align-items: center;">
                                        <label style="display: flex; align-items: center; gap: 8px; color: white; font-size: 14px; cursor: pointer;">
                                            <input type="checkbox"
                                                   class="item-checkbox"
                                                   data-order-id="${orderId}"
                                                   data-item-index="${itemIndex}"
                                                   onchange="checkPacklistProgress()"
                                                   style="width: 24px; height: 24px; cursor: pointer;">
                                        </label>
                                    </div>
                                </div>
                            `;
                        });

                        packlisteHTML += `
                                </div>
                                <!-- Order Total (hidden, used for stats calculation) -->
                                <div class="order-total" style="display: none;">${order.total}</div>
                            </div>
                        `;
                    });
                    
                    if (packlisteContent) {
                        packlisteContent.innerHTML = packlisteHTML;

                        // Restore checked items from localStorage (both current and scheduled)
                        // Use today's date in the key for current items so it resets daily
                        const todayDateStr = dispatch_ajax.today_date; // From WordPress timezone
                        const packedCurrentItems = JSON.parse(localStorage.getItem('packedCurrentItems_' + todayDateStr) || '{}');
                        const packedScheduledItems = JSON.parse(localStorage.getItem('packedScheduledItems') || '{}');

                        // Restore current orders checkboxes
                        for (const key in packedCurrentItems) {
                            const item = packedCurrentItems[key];
                            const checkbox = document.querySelector(`.order-pack-card[data-order-type="current"] input[data-order-id="${item.orderId}"][data-item-index="${item.itemIndex}"]`);
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                        }

                        // Restore scheduled orders checkboxes
                        for (const key in packedScheduledItems) {
                            const item = packedScheduledItems[key];
                            const checkbox = document.querySelector(`.order-pack-card[data-order-type="scheduled"] input[data-order-id="${item.orderId}"][data-item-index="${item.itemIndex}"]`);
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                        }

                        // Update visual feedback only (no auto-complete on page load)
                        updatePacklistVisualFeedback();
                    }

                    // Apply default filter: show only current orders on initial load
                    setTimeout(() => {
                        filterPackliste('current');
                    }, 100);

                } catch (error) {
                    console.error('Error displaying packliste:', error);
                    showEmptyPackliste();
                }
            }
            
            // Function to update only visual feedback (no auto-complete)
            window.updatePacklistVisualFeedback = function() {
                try {
                    // Separate checkboxes by order type (current vs scheduled)
                    const currentOrderCheckboxes = document.querySelectorAll('.order-pack-card[data-order-type="current"] .item-checkbox');
                    const scheduledOrderCheckboxes = document.querySelectorAll('.order-pack-card[data-order-type="scheduled"] .item-checkbox');

                    const currentChecked = document.querySelectorAll('.order-pack-card[data-order-type="current"] .item-checkbox:checked');
                    const scheduledChecked = document.querySelectorAll('.order-pack-card[data-order-type="scheduled"] .item-checkbox:checked');

                    // Save checked state to localStorage - separate for current and scheduled
                    const packedCurrentItems = {};
                    const packedScheduledItems = {};

                    currentChecked.forEach((cb, index) => {
                        const orderId = cb.dataset.orderId;
                        const itemIndex = cb.dataset.itemIndex;
                        const key = `${orderId}_${itemIndex}`;
                        packedCurrentItems[key] = { orderId, itemIndex };
                    });

                    scheduledChecked.forEach((cb, index) => {
                        const orderId = cb.dataset.orderId;
                        const itemIndex = cb.dataset.itemIndex;
                        const key = `${orderId}_${itemIndex}`;
                        packedScheduledItems[key] = { orderId, itemIndex };
                    });

                    const todayDateStr = dispatch_ajax.today_date; // From WordPress timezone
                    localStorage.setItem('packedCurrentItems_' + todayDateStr, JSON.stringify(packedCurrentItems));
                    localStorage.setItem('packedScheduledItems', JSON.stringify(packedScheduledItems));

                    // Update order card visual feedback
                    const orderCards = document.querySelectorAll('.order-pack-card');
                    orderCards.forEach(card => {
                        const orderId = card.dataset.orderId;
                        const orderCheckboxes = card.querySelectorAll('.item-checkbox');
                        const orderCheckedBoxes = card.querySelectorAll('.item-checkbox:checked');

                        if (orderCheckboxes.length > 0 && orderCheckboxes.length === orderCheckedBoxes.length) {
                            // All items in this order are packed
                            card.style.opacity = '0.7';
                            card.style.background = '#064E3B';
                        } else {
                            // Not all items are packed
                            card.style.opacity = '1';
                            card.style.background = '#1F2937';
                        }
                    });
                } catch (error) {
                    console.error('Error updating packlist visual feedback:', error);
                }
            };

            // Store pending timers for each order
            window.orderCompletionTimers = window.orderCompletionTimers || {};

            window.checkPacklistProgress = function() {
                try {
                    console.log('checkPacklistProgress called - WITH AUTO-COMPLETE');

                    // Separate checkboxes by order type (current vs scheduled)
                    const currentOrderCheckboxes = document.querySelectorAll('.order-pack-card[data-order-type="current"] .item-checkbox');
                    const scheduledOrderCheckboxes = document.querySelectorAll('.order-pack-card[data-order-type="scheduled"] .item-checkbox');

                    const currentChecked = document.querySelectorAll('.order-pack-card[data-order-type="current"] .item-checkbox:checked');
                    const scheduledChecked = document.querySelectorAll('.order-pack-card[data-order-type="scheduled"] .item-checkbox:checked');

                    console.log(`Current orders: ${currentOrderCheckboxes.length} checkboxes, ${currentChecked.length} checked`);
                    console.log(`Scheduled orders: ${scheduledOrderCheckboxes.length} checkboxes, ${scheduledChecked.length} checked`);

                    // Save checked state to localStorage - separate for current and scheduled
                    const packedCurrentItems = {};
                    const packedScheduledItems = {};

                    currentChecked.forEach((checkbox) => {
                        const orderId = checkbox.dataset.orderId;
                        const itemIndex = checkbox.dataset.itemIndex;
                        packedCurrentItems[`${orderId}_${itemIndex}`] = { orderId, itemIndex };
                    });

                    scheduledChecked.forEach((checkbox) => {
                        const orderId = checkbox.dataset.orderId;
                        const itemIndex = checkbox.dataset.itemIndex;
                        packedScheduledItems[`${orderId}_${itemIndex}`] = { orderId, itemIndex };
                    });

                    const todayDateStr = dispatch_ajax.today_date; // From WordPress timezone
                    localStorage.setItem('packedCurrentItems_' + todayDateStr, JSON.stringify(packedCurrentItems));
                    localStorage.setItem('packedScheduledItems', JSON.stringify(packedScheduledItems));

                    // Update order card visual feedback AND check for incomplete orders
                    const orderCards = document.querySelectorAll('.order-pack-card');
                    orderCards.forEach(card => {
                        const orderId = card.dataset.orderId;
                        const orderCheckboxes = card.querySelectorAll('.item-checkbox');
                        const orderCheckedBoxes = card.querySelectorAll('.item-checkbox:checked');

                        if (orderCheckboxes.length > 0 && orderCheckboxes.length === orderCheckedBoxes.length) {
                            // All items in this order are packed
                            card.style.opacity = '0.7';
                            card.style.background = '#064E3B';

                            // Check if this order was already marked as ready
                            const wasAlreadyReady = card.dataset.wasReady === 'true';

                            if (!wasAlreadyReady) {
                                // NEW: Mark this individual order as ready immediately
                                console.log(`✅ Order ${orderId} fully packed - marking as ready`);
                                card.dataset.wasReady = 'true';

                                // Check if already processing this order
                                const processingKey = `order_${orderId}_processing`;
                                const isProcessing = localStorage.getItem(processingKey) === 'true';

                                if (!isProcessing) {
                                    localStorage.setItem(processingKey, 'true');

                                    // No notification here - will show after success

                                    // Store timer ID so we can cancel it later
                                    const timerId = setTimeout(() => {
                                        // Check if order is still fully packed
                                        const stillFullyPacked = card.querySelectorAll('.item-checkbox').length ===
                                                               card.querySelectorAll('.item-checkbox:checked').length;

                                        if (stillFullyPacked) {
                                            // Mark order as ready on server
                                            window.completePackliste('single', orderId);
                                        } else {
                                            console.log(`Order ${orderId} no longer fully packed - cancelling`);
                                            localStorage.removeItem(processingKey);
                                            showNotification(`⚠️ Bestellung #${orderId} abgebrochen`, 'warning');
                                        }

                                        // Clear timer reference
                                        delete window.orderCompletionTimers[orderId];
                                    }, 1000);

                                    // Store timer so we can cancel it if needed
                                    window.orderCompletionTimers[orderId] = timerId;
                                }
                            }
                        } else {
                            // Not all items are packed
                            card.style.opacity = '1';
                            card.style.background = '#1F2937';

                            // IMPORTANT: If this order was previously marked as ready, unmark it
                            const wasReady = card.dataset.wasReady === 'true';
                            if (wasReady) {
                                console.log(`⚠️ Order ${orderId} is no longer fully packed - unmarking as ready`);
                                card.dataset.wasReady = 'false';

                                // Cancel pending timer if exists
                                if (window.orderCompletionTimers[orderId]) {
                                    clearTimeout(window.orderCompletionTimers[orderId]);
                                    delete window.orderCompletionTimers[orderId];
                                    console.log(`⏹️ Cancelled completion timer for order ${orderId}`);
                                }

                                // Clear processing flag for this order
                                const processingKey = `order_${orderId}_processing`;
                                localStorage.removeItem(processingKey);
                                console.log('🔄 Cleared processing flag to allow re-completion');

                                // Send AJAX to unmark order as ready
                                fetch(dispatch_ajax.ajax_url, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: `action=unmark_order_ready&order_id=${orderId}&nonce=${dispatch_ajax.nonce}`,
                                    credentials: 'same-origin'
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        console.log(`✅ Order ${orderId} unmarked as ready on server`);
                                        showNotification(`Bestellung #${orderId} als "nicht geladen" markiert`, 'info');
                                    } else {
                                        console.error(`❌ Failed to unmark order ${orderId}:`, data);
                                    }
                                })
                                .catch(error => {
                                    console.error(`❌ Error unmarking order ${orderId}:`, error);
                                });
                            }
                        }
                    });

                    // Auto-complete ONLY when all CURRENT (today's) items are checked
                    if (currentOrderCheckboxes.length > 0 && currentOrderCheckboxes.length === currentChecked.length) {
                        console.log('==========================================');
                        console.log('ALL CURRENT ORDER ITEMS CHECKED! CHECKING IF WE SHOULD AUTO-COMPLETE...');
                        console.log('==========================================');

                        // Get unique order IDs for current orders only
                        const currentOrderIds = [...new Set(Array.from(currentChecked).map(cb => cb.dataset.orderId))].sort();
                        console.log('Current order IDs for auto-complete:', currentOrderIds);

                        // Use today's date in the key so it resets daily
                        const today = new Date().toISOString().split('T')[0];
                        const autoCompletedKey = 'packliste_completed_' + today + '_' + currentOrderIds.join('_');
                        console.log('Auto-complete key:', autoCompletedKey);

                        const wasAutoCompleted = localStorage.getItem(autoCompletedKey) === 'true';
                        const isCurrentlyProcessing = localStorage.getItem('packliste_current_processing') === 'true';
                        console.log('Was already auto-completed?', wasAutoCompleted);
                        console.log('Currently processing?', isCurrentlyProcessing);

                        if (!wasAutoCompleted && !isCurrentlyProcessing) {
                            console.log('NOT YET COMPLETED - TRIGGERING IN 2 SECONDS...');

                            // Show notification that it will be processed
                            showNotification('✅ Alle HEUTIGEN Bestellungen gepackt - wird in 2 Sekunden als geladen markiert...', 'success');

                            // Set processing flag to prevent multiple submissions
                            localStorage.setItem('packliste_current_processing', 'true');

                            // Wait 2 seconds to allow user to uncheck if mistake
                            setTimeout(() => {
                                // Check again if all current orders are still checked
                                const stillAllCurrentChecked = document.querySelectorAll('.order-pack-card[data-order-type="current"] .item-checkbox:checked').length ===
                                                             document.querySelectorAll('.order-pack-card[data-order-type="current"] .item-checkbox').length;

                                if (stillAllCurrentChecked) {
                                    console.log('==========================================');
                                    console.log('STILL ALL CURRENT ORDERS CHECKED - CALLING completePackliste() FOR CURRENT ORDERS ONLY');
                                    console.log('==========================================');
                                    localStorage.setItem(autoCompletedKey, 'true');
                                    // Pass flag to indicate only current orders
                                    window.completePackliste('current');
                                } else {
                                    console.log('User unchecked some current order items - cancelling auto-complete');
                                    showNotification('⚠️ Auto-Abschluss für heutige Bestellungen abgebrochen', 'warning');
                                    localStorage.removeItem('packliste_current_processing');
                                }
                            }, 2000); // Give user 2 seconds to correct mistake
                        } else if (wasAutoCompleted) {
                            console.log('CURRENT ORDERS ALREADY AUTO-COMPLETED - SKIPPING');
                        } else if (isCurrentlyProcessing) {
                            console.log('CURRENT ORDERS ALREADY PROCESSING - SKIPPING');
                        }
                    } else if (currentOrderCheckboxes.length > 0) {
                        console.log(`Not all current order items checked: ${currentChecked.length}/${currentOrderCheckboxes.length}`);
                        // Clear processing flag if user unchecks
                        localStorage.removeItem('packliste_current_processing');
                    }

                    // Log scheduled orders status (but don't trigger auto-complete for future orders)
                    if (scheduledOrderCheckboxes.length > 0) {
                        if (scheduledOrderCheckboxes.length === scheduledChecked.length) {
                            console.log('✓ All scheduled order items are checked (but NOT triggering completion - these are for future delivery)');
                        } else {
                            console.log(`Scheduled order items: ${scheduledChecked.length}/${scheduledOrderCheckboxes.length} checked`);
                        }
                    }

                } catch (error) {
                    console.error('Error checking packlist progress:', error);
                }
            }

            window.completePackliste = function(orderType = 'all', singleOrderId = null) {
                try {
                    console.log('==========================================');
                    console.log('completePackliste CALLED!');
                    console.log('==========================================');

                    console.log('Order type to complete:', orderType);
                    console.log('Single order ID:', singleOrderId);

                    // Handle single order completion
                    if (orderType === 'single' && singleOrderId) {
                        console.log(`Completing single order: ${singleOrderId}`);

                        // Send AJAX to mark order as ready
                        fetch(dispatch_ajax.ajax_url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=complete_packliste&order_ids=${singleOrderId}&nonce=${dispatch_ajax.nonce}`,
                            credentials: 'same-origin'
                        })
                        .then(response => response.json())
                        .then(data => {
                            const processingKey = `order_${singleOrderId}_processing`;
                            localStorage.removeItem(processingKey);

                            if (data.success) {
                                console.log(`✅ Order ${singleOrderId} marked as ready on server`);
                                showNotification(`✅ Bestellung #${singleOrderId} geladen`, 'success');

                                // DON'T reload - just update timestamps
                                // Reload would cause page jump
                            } else {
                                console.error(`❌ Failed to mark order ${singleOrderId} as ready:`, data);
                                showNotification(`❌ Fehler beim Markieren von #${singleOrderId}`, 'error');

                                // Reset order card state
                                const card = document.querySelector(`.order-pack-card[data-order-id="${singleOrderId}"]`);
                                if (card) {
                                    card.dataset.wasReady = 'false';
                                }
                            }
                        })
                        .catch(error => {
                            console.error(`❌ Error completing order ${singleOrderId}:`, error);
                            showNotification(`❌ Netzwerkfehler bei #${singleOrderId}`, 'error');

                            const processingKey = `order_${singleOrderId}_processing`;
                            localStorage.removeItem(processingKey);

                            const card = document.querySelector(`.order-pack-card[data-order-id="${singleOrderId}"]`);
                            if (card) {
                                card.dataset.wasReady = 'false';
                            }
                        });

                        return; // Exit function after single order completion
                    }

                    // Continue with batch order completion
                    let targetCheckboxes;
                    let notificationPrefix;

                    if (orderType === 'current') {
                        targetCheckboxes = document.querySelectorAll('.order-pack-card[data-order-type="current"] .item-checkbox');
                        notificationPrefix = 'heutige';
                    } else if (orderType === 'scheduled') {
                        targetCheckboxes = document.querySelectorAll('.order-pack-card[data-order-type="scheduled"] .item-checkbox');
                        notificationPrefix = 'geplante';
                    } else {
                        targetCheckboxes = document.querySelectorAll('.item-checkbox');
                        notificationPrefix = 'alle';
                    }

                    console.log(`Found ${targetCheckboxes.length} ${notificationPrefix} checkboxes`);

                    const allChecked = Array.from(targetCheckboxes).every(cb => cb.checked);
                    console.log(`All ${notificationPrefix} checked?`, allChecked);

                    if (!allChecked) {
                        showNotification(`⚠️ Bitte alle ${notificationPrefix} Artikel abhaken!`, 'warning');
                        return;
                    }

                    const completeBtn = document.getElementById('packliste-complete-btn');
                    if (completeBtn) {
                        completeBtn.disabled = true;
                        completeBtn.textContent = 'Wird gesendet...';
                    }

                    const orderIds = [...new Set(Array.from(targetCheckboxes).map(cb => cb.dataset.orderId))];

                    // First, activate the ready-toggle for each order
                    console.log('==========================================');
                    console.log('ACTIVATING READY TOGGLES FOR ORDERS');
                    console.log('==========================================');

                    // Create array of promises for toggle updates
                    const togglePromises = orderIds.map(orderId => {
                        return new Promise((resolve) => {
                            console.log('==========================================');
                            console.log(`DEBUG: Processing order ${orderId}`);
                            console.log('Looking for selector: .ready-switch[data-order-id="' + orderId + '"]');

                            const readySwitch = document.querySelector(`.ready-switch[data-order-id="${orderId}"]`);
                            console.log('Ready switch found?', readySwitch ? 'YES' : 'NO');

                            // Also try alternative selectors
                            if (!readySwitch) {
                                console.log('Trying alternative selector: input.ready-toggle[data-order-id="' + orderId + '"]');
                                const altSwitch = document.querySelector(`input.ready-toggle[data-order-id="${orderId}"]`);
                                console.log('Alternative switch found?', altSwitch ? 'YES' : 'NO');
                            }

                            // Log all ready switches on page for debugging
                            const allSwitches = document.querySelectorAll('.ready-switch, .ready-toggle, input[type="checkbox"][data-order-id]');
                            console.log('All switches on page:', allSwitches.length);
                            allSwitches.forEach(sw => {
                                console.log(' - Switch with order-id:', sw.dataset.orderId, 'Classes:', sw.className);
                            });

                            if (readySwitch && !readySwitch.checked) {
                                console.log(`Activating ready toggle for order ${orderId}`);
                                readySwitch.checked = true;

                                // Send AJAX to update order status
                                const status = 'ready';
                                const ajaxUrl = dispatch_ajax.ajax_url || ajaxurl || '/wp-admin/admin-ajax.php';
                                const nonce = dispatch_ajax.nonce || window.dispatch_nonce || '';

                                console.log('AJAX Details:');
                                console.log(' - URL:', ajaxUrl);
                                console.log(' - Action: dispatch_update_order_status');
                                console.log(' - Order ID:', orderId);
                                console.log(' - Status:', status);
                                console.log(' - Nonce:', nonce);

                                fetch(ajaxUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: `action=dispatch_update_order_status&order_id=${orderId}&status=${status}&nonce=${nonce}`,
                                    credentials: 'same-origin'
                                })
                                .then(response => response.json())
                                .then(data => {
                                    console.log(`Response for order ${orderId}:`, data);
                                    if (data.success) {
                                        console.log(`✅ Order ${orderId} marked as ready via packlist completion`);
                                        resolve(true);
                                    } else {
                                        console.error(`❌ Failed to mark order ${orderId} as ready:`, data);
                                        // Revert checkbox on error
                                        readySwitch.checked = false;
                                        resolve(false);
                                    }
                                })
                                .catch(error => {
                                    console.error(`❌ Error marking order ${orderId} as ready:`, error);
                                    readySwitch.checked = false;
                                    resolve(false);
                                });
                            } else if (readySwitch && readySwitch.checked) {
                                console.log(`✓ Order ${orderId} already marked as ready`);
                                resolve(true);
                            } else {
                                console.log(`⚠️ Ready switch not found for order ${orderId} - This is likely because we're on the driver app, not admin dashboard`);
                                console.log('We will still mark it as ready on the server side via complete_packliste');
                                resolve(true); // Still resolve to continue flow
                            }
                        });
                    });

                    // Wait for all toggles to be updated before sending completion notification
                    Promise.all(togglePromises).then((results) => {
                        console.log('All toggle updates completed:', results);

                        // Send notification to admin
                        console.log('==========================================');
                        console.log('SENDING AJAX REQUEST TO COMPLETE PACKLISTE');
                        console.log('Orders to mark as ready:', orderIds);
                        console.log('AJAX URL:', dispatch_ajax.ajax_url);
                        console.log('Nonce:', dispatch_ajax.nonce);
                        console.log('Full request body:', 'action=complete_packliste&nonce=' + dispatch_ajax.nonce + '&order_ids=' + orderIds.join(','));
                        console.log('==========================================');

                        // Try with fetch first, with retry logic
                        const attemptComplete = (retryCount = 0) => {
                        console.log(`Attempt ${retryCount + 1} to complete packliste...`);

                        // Use jQuery AJAX as fallback if fetch fails (or XMLHttpRequest if jQuery not available)
                        if (retryCount > 0) {
                            console.log('Using fallback method for retry...');

                            // Try jQuery first
                            if (typeof jQuery !== 'undefined') {
                                console.log('Using jQuery AJAX...');
                            jQuery.ajax({
                                url: dispatch_ajax.ajax_url,
                                type: 'POST',
                                data: {
                                    action: 'complete_packliste',
                                    nonce: dispatch_ajax.nonce,
                                    order_ids: orderIds.join(',')
                                },
                                timeout: 30000, // 30 second timeout
                                success: function(data) {
                                    console.log('==========================================');
                                    console.log('JQUERY AJAX SUCCESS:', data);
                                    console.log('==========================================');

                                    // Clear localStorage
                                    localStorage.removeItem('packedItems');
                                    localStorage.removeItem('packliste_processing');

                                    // Clear the auto-complete flag to allow re-submission if needed
                                    const autoCompletedKey = 'autoCompleted_' + orderIds.join('_');
                                    localStorage.removeItem(autoCompletedKey);

                                    // Show appropriate success message
                                    if (data.all_daily_orders_packed) {
                                        showNotification('🎉 Alle Tagesbestellungen geladen! Admin wurde benachrichtigt.', 'success');
                                        if (typeof playNotificationSound === 'function') {
                                            playNotificationSound('success');
                                        }
                                    } else {
                                        showNotification('✅ Packliste erfolgreich abgeschlossen!', 'success');
                                    }

                                    // Reload driver orders to update status badges
                                    if (typeof loadDriverOrders === 'function') {
                                        console.log('Reloading driver orders to update status badges...');
                                        setTimeout(() => {
                                            loadDriverOrders();
                                        }, 1000);
                                    }

                                    // Mark all cards as completed visually (without reloading)
                                    const orderCards = document.querySelectorAll('.order-pack-card');
                                    orderCards.forEach(card => {
                                        card.style.opacity = '0.5';
                                        card.style.background = '#064E3B';

                                        // Add a "completed" badge
                                        if (!card.querySelector('.completed-badge')) {
                                            const badge = document.createElement('div');
                                            badge.className = 'completed-badge';
                                            badge.innerHTML = '✅ GELADEN';
                                            badge.style.cssText = 'position: absolute; top: 10px; right: 10px; background: #10B981; color: white; padding: 5px 10px; border-radius: 6px; font-weight: bold; z-index: 10;';
                                            card.style.position = 'relative';
                                            card.appendChild(badge);
                                        }

                                        // Disable all checkboxes in this card
                                        const checkboxes = card.querySelectorAll('.item-checkbox');
                                        checkboxes.forEach(cb => {
                                            cb.disabled = true;
                                            cb.style.opacity = '0.7';
                                        });
                                    });

                                    // After 3 seconds, go back to order list
                                    setTimeout(() => {
                                        console.log('Returning to order list after packlist completion');
                                        // Clear the packlist view
                                        const packlisteContent = document.getElementById('packliste-content');
                                        if (packlisteContent) {
                                            packlisteContent.style.display = 'none';
                                        }
                                        // Show orders again
                                        showBestellungen();
                                    }, 3000);
                                },
                                error: function(xhr, status, error) {
                                    console.error('jQuery AJAX failed:', status, error);
                                    showNotification('❌ Netzwerkfehler - bitte erneut versuchen', 'error');

                                    // Clear the auto-complete flag to allow retry
                                    const autoCompletedKey = 'autoCompleted_' + orderIds.join('_');
                                    localStorage.removeItem(autoCompletedKey);
                                    localStorage.removeItem('packliste_processing');
                                }
                            });
                            return;
                            } else {
                                // Use plain XMLHttpRequest as last fallback
                                console.log('Using XMLHttpRequest fallback...');
                                const xhr = new XMLHttpRequest();
                                xhr.open('POST', dispatch_ajax.ajax_url, true);
                                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                xhr.timeout = 30000; // 30 seconds

                                xhr.onload = function() {
                                    if (xhr.status === 200) {
                                        try {
                                            const data = JSON.parse(xhr.responseText);
                                            console.log('==========================================');
                                            console.log('XHR SUCCESS:', data);
                                            console.log('==========================================');

                                            // Clear localStorage
                                            localStorage.removeItem('packedItems');
                                            localStorage.removeItem('packliste_processing');
                                            const autoCompletedKey = 'autoCompleted_' + orderIds.join('_');
                                            localStorage.removeItem(autoCompletedKey);

                                            // Show success message
                                            showNotification('✅ Packliste erfolgreich abgeschlossen!', 'success');

                                            // Reload driver orders to update status badges
                                            if (typeof loadDriverOrders === 'function') {
                                                console.log('Reloading driver orders to update status badges...');
                                                setTimeout(() => {
                                                    loadDriverOrders();
                                                }, 1000);
                                            }

                                            // Mark all cards as completed visually (without reloading)
                                            const orderCards = document.querySelectorAll('.order-pack-card');
                                            orderCards.forEach(card => {
                                                card.style.opacity = '0.5';
                                                card.style.background = '#064E3B';

                                                // Add a "completed" badge
                                                if (!card.querySelector('.completed-badge')) {
                                                    const badge = document.createElement('div');
                                                    badge.className = 'completed-badge';
                                                    badge.innerHTML = '✅ GELADEN';
                                                    badge.style.cssText = 'position: absolute; top: 10px; right: 10px; background: #10B981; color: white; padding: 5px 10px; border-radius: 6px; font-weight: bold; z-index: 10;';
                                                    card.style.position = 'relative';
                                                    card.appendChild(badge);
                                                }

                                                // Disable all checkboxes in this card
                                                const checkboxes = card.querySelectorAll('.item-checkbox');
                                                checkboxes.forEach(cb => {
                                                    cb.disabled = true;
                                                    cb.style.opacity = '0.7';
                                                });
                                            });

                                            // After 3 seconds, go back to order list
                                            setTimeout(() => {
                                                console.log('Returning to order list after packlist completion');
                                                // Clear the packlist view
                                                const packlisteContent = document.getElementById('packliste-content');
                                                if (packlisteContent) {
                                                    packlisteContent.style.display = 'none';
                                                }
                                                // Show orders again
                                                showBestellungen();
                                            }, 3000);
                                        } catch (e) {
                                            console.error('Error parsing response:', e);
                                            showNotification('❌ Fehler beim Verarbeiten der Antwort', 'error');
                                        }
                                    } else {
                                        console.error('XHR failed with status:', xhr.status);
                                        showNotification('❌ Server-Fehler: ' + xhr.status, 'error');
                                    }
                                };

                                xhr.onerror = function() {
                                    console.error('XHR network error');
                                    showNotification('❌ Netzwerkfehler', 'error');
                                    const autoCompletedKey = 'autoCompleted_' + orderIds.join('_');
                                    localStorage.removeItem(autoCompletedKey);
                                    localStorage.removeItem('packliste_processing');
                                };

                                xhr.send('action=complete_packliste&nonce=' + dispatch_ajax.nonce + '&order_ids=' + orderIds.join(','));
                                return;
                            }
                        }

                        fetch(dispatch_ajax.ajax_url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'action=complete_packliste&nonce=' + dispatch_ajax.nonce + '&order_ids=' + orderIds.join(','),
                            credentials: 'same-origin',
                            signal: AbortSignal.timeout(30000) // 30 second timeout
                        })
                        .then(response => {
                            console.log('Response status:', response.status);
                            if (!response.ok) {
                                throw new Error('HTTP error! status: ' + response.status);
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('==========================================');
                            console.log('RESPONSE FROM SERVER:', data);
                            console.log('==========================================');
                            if (data.success) {
                                // Clear localStorage
                                localStorage.removeItem('packedItems');
                                localStorage.removeItem('packliste_processing');
                                localStorage.removeItem('packliste_current_processing'); // Reset current processing flag

                                // Clear the auto-complete flag
                                const autoCompletedKey = 'autoCompleted_' + orderIds.join('_');
                                localStorage.removeItem(autoCompletedKey);

                                // Show appropriate success message
                                if (data.data && data.data.all_daily_orders_packed) {
                                    showNotification('🎉 Alle Tagesbestellungen geladen! Admin wurde benachrichtigt.', 'success');
                                    if (typeof playNotificationSound === 'function') {
                                        playNotificationSound('success');
                                    }
                                } else {
                                    showNotification('✅ Packliste erfolgreich abgeschlossen!', 'success');
                                }

                                // Reload driver orders to update status badges
                                if (typeof loadDriverOrders === 'function') {
                                    console.log('Reloading driver orders to update status badges...');
                                    setTimeout(() => {
                                        loadDriverOrders();
                                    }, 1000);
                                }

                                // Mark all cards as completed visually (without reloading)
                                const orderCards = document.querySelectorAll('.order-pack-card');
                                orderCards.forEach(card => {
                                    card.style.opacity = '0.5';
                                    card.style.background = '#064E3B';

                                    // Add a "completed" badge
                                    if (!card.querySelector('.completed-badge')) {
                                        const badge = document.createElement('div');
                                        badge.className = 'completed-badge';
                                        badge.innerHTML = '✅ GELADEN';
                                        badge.style.cssText = 'position: absolute; top: 10px; right: 10px; background: #10B981; color: white; padding: 5px 10px; border-radius: 6px; font-weight: bold; z-index: 10;';
                                        card.style.position = 'relative';
                                        card.appendChild(badge);
                                    }

                                    // Disable all checkboxes in this card
                                    const checkboxes = card.querySelectorAll('.item-checkbox');
                                    checkboxes.forEach(cb => {
                                        cb.disabled = true;
                                        cb.style.opacity = '0.7';
                                    });
                                });

                                // Don't reload - just update the visual state and go back to orders
                                // After 3 seconds, go back to order list
                                setTimeout(() => {
                                    console.log('Returning to order list after packlist completion');
                                    // Clear the packlist view
                                    const packlisteContent = document.getElementById('packliste-content');
                                    if (packlisteContent) {
                                        packlisteContent.style.display = 'none';
                                    }
                                    // Show orders again
                                    showBestellungen();
                                }, 3000);
                            } else {
                                console.error('Server returned error:', data);
                                throw new Error(data.data?.message || 'Server error');
                            }
                        })
                        .catch(error => {
                            console.error('Error completing packliste:', error);

                            if (retryCount < 2) {
                                console.log('Retrying in 2 seconds...');
                                showNotification('⏳ Verbindungsfehler - versuche erneut...', 'warning');
                                setTimeout(() => {
                                    attemptComplete(retryCount + 1);
                                }, 2000);
                            } else {
                                showNotification('❌ Fehler beim Senden - bitte manuell erneut versuchen', 'error');
                                // Clear the auto-complete flag to allow manual retry
                                const autoCompletedKey = 'autoCompleted_' + orderIds.join('_');
                                localStorage.removeItem(autoCompletedKey);
                                localStorage.removeItem('packliste_processing');

                                if (completeBtn) {
                                    completeBtn.disabled = false;
                                    completeBtn.textContent = 'Erneut versuchen';
                                }
                            }
                        });
                    };

                        attemptComplete();
                    }).catch(error => {
                        console.error('Error updating toggle states:', error);
                        showNotification('❌ Fehler beim Aktualisieren der Status', 'error');
                    });

                } catch (error) {
                    console.error('Error in completePackliste:', error);
                }
            }
            

            function showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                let bgColor = '#3B82F6'; // default info
                if (type === 'success') bgColor = '#10B981';
                else if (type === 'warning') bgColor = '#F59E0B';
                else if (type === 'error') bgColor = '#EF4444';

                notification.style.cssText = `
                    position: fixed;
                    top: 80px;
                    left: 0;
                    right: 0;
                    width: 100%;
                    background: ${bgColor};
                    color: white;
                    padding: 16px 24px;
                    font-size: 18px;
                    font-weight: 600;
                    text-align: center;
                    z-index: 10000;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.3);
                    animation: slideDown 0.3s ease;
                `;
                notification.textContent = message;
                document.body.appendChild(notification);

                setTimeout(() => {
                    notification.remove();
                }, 3000);
            }
            
            function showEmptyPackliste() {
                // Ensure full width for empty packliste
                mainContent = document.querySelector('.main-content');
                if (mainContent) {
                    mainContent.className = 'main-content orders-page';
                }
                
                const loadingState = document.getElementById('packliste-loading');
                const packlisteContent = document.getElementById('packliste-content');
                
                if (loadingState) loadingState.style.display = 'none';
                if (packlisteContent) {
                    packlisteContent.style.display = 'block';
                    packlisteContent.innerHTML = `
                        <div style="text-align: center; padding: 60px 20px; color: #9CA3AF;">
                            <div style="font-size: 64px; margin-bottom: 20px;">📋</div>
                            <div style="font-size: 18px; margin-bottom: 8px;">Keine Artikel zu packen</div>
                            <div style="font-size: 14px; opacity: 0.7;">Neue Bestellungen werden hier angezeigt</div>
                        </div>
                    `;
                }
                
                // Reset stats
                const totalOrders = document.getElementById('total-orders');
                const totalItems = document.getElementById('total-items');
                const totalValue = document.getElementById('total-value');
                
                if (totalOrders) totalOrders.textContent = '0';
                if (totalItems) totalItems.textContent = '0';
                if (totalValue) totalValue.textContent = '0,00 €';
            }
            
            function getOrderStatusColor(status) {
                switch (status) {
                    case 'processing': return '#3B82F6';
                    case 'completed': return '#10B981';
                    case 'on-hold': return '#F59E0B';
                    case 'pending': return '#9CA3AF';
                    case 'zugewiesen': return '#F59E0B'; // Gelb für zugewiesen
                    case 'gestartet': return '#10B981'; // Grün für gestartet
                    default: return '#6B7280';
                }
            }
            
            function getOrderStatusText(status) {
                switch (status) {
                    case 'processing': return 'In Bearbeitung';
                    case 'completed': return 'Abgeschlossen';
                    case 'on-hold': return 'Wartend';
                    case 'pending': return 'Ausstehend';
                    case 'zugewiesen': return 'Zugewiesen';
                    case 'gestartet': return 'Gestartet';
                    default: return 'Unbekannt';
                }
            }
            
            function filterPackliste(filter) {
                try {

                    // Update filter buttons
                    const filterButtons = document.querySelectorAll('.filter-btn');
                    filterButtons.forEach(btn => {
                        if (btn.dataset.filter === filter) {
                            btn.classList.add('active');
                            btn.style.background = '#10B981';
                            btn.style.color = 'white';
                        } else {
                            btn.classList.remove('active');
                            btn.style.background = '#374151';
                            btn.style.color = '#9CA3AF';
                        }
                    });

                    // Show/hide order cards based on filter
                    const orderCards = document.querySelectorAll('.order-pack-card');

                    console.log(`Filtering packliste with filter: ${filter}`);

                    orderCards.forEach(card => {
                        const orderType = card.dataset.orderType; // 'current' or 'scheduled' - set by server
                        let showCard = false;

                        console.log(`Order ${card.dataset.orderId} - Order Type: "${orderType}" (Delivery: ${card.dataset.deliveryDate})`);

                        if (filter === 'all') {
                            showCard = true;
                        } else if (filter === 'current') {
                            // Show orders that server marked as 'current'
                            showCard = (orderType === 'current');
                            console.log(`  -> Current filter: orderType='${orderType}' -> showCard=${showCard}`);
                        } else if (filter === 'scheduled') {
                            // Show orders that server marked as 'scheduled'
                            showCard = (orderType === 'scheduled');
                            console.log(`  -> Scheduled filter: orderType='${orderType}' -> showCard=${showCard}`);
                        } else {
                            // Legacy filter (processing, on-hold, etc.)
                            showCard = card.dataset.status === filter;
                            console.log(`  -> Legacy filter: status='${card.dataset.status}' -> showCard=${showCard}`);
                        }

                        console.log(`  -> Final decision: showCard = ${showCard}`);
                        card.style.display = showCard ? 'block' : 'none';
                    });

                    // Count visible cards and update stats
                    const visibleCards = Array.from(orderCards).filter(card => card.style.display !== 'none');
                    console.log(`Filter complete. Visible cards: ${visibleCards.length}/${orderCards.length}`);

                    // Calculate stats for visible orders only
                    let totalOrders = visibleCards.length;
                    let totalItems = 0;
                    let totalValue = 0;

                    visibleCards.forEach(card => {
                        // Count items (checkboxes) in this order
                        const itemCheckboxes = card.querySelectorAll('.item-checkbox');
                        totalItems += itemCheckboxes.length;

                        // Parse order total from card (if available)
                        const orderValueText = card.querySelector('.order-total')?.textContent || '0';
                        // Extract number from text like "45,50 €"
                        const orderValue = parseFloat(orderValueText.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
                        totalValue += orderValue;
                    });

                    // Update stats display
                    const totalOrdersEl = document.getElementById('total-orders');
                    const totalItemsEl = document.getElementById('total-items');
                    const totalValueEl = document.getElementById('total-value');

                    if (totalOrdersEl) totalOrdersEl.textContent = totalOrders;
                    if (totalItemsEl) totalItemsEl.textContent = totalItems;
                    if (totalValueEl) totalValueEl.textContent = totalValue.toFixed(2).replace('.', ',') + ' €';

                } catch (error) {
                    console.error('Error in filterPackliste:', error);
                }
            }
            
            function toggleOrderItems(orderId) {
                try {
                    
                    // Find the button using its ID
                    const button = document.getElementById(`btn-order-${orderId}`);
                    if (!button) {
                        console.error('Button not found for order:', orderId);
                        return;
                    }
                    
                    // Find the order card container
                    const orderCardContainer = button.closest('.order-pack-card');
                    if (!orderCardContainer) {
                        console.error('Order card container not found');
                        return;
                    }
                    
                    // Check if details are already expanded
                    let detailsSection = orderCardContainer.querySelector('.order-details-expanded');
                    
                    if (detailsSection) {
                        // Collapse - remove details section
                        detailsSection.remove();
                        button.textContent = 'Details anzeigen';
                        button.style.background = '#3B82F6';
                    } else {
                        // Expand - show simple details without AJAX for now
                        button.textContent = 'Details ausblenden';
                        button.style.background = '#EF4444';
                        
                        // Get order info from the card
                        const orderHeader = orderCardContainer.querySelector('.order-pack-header');
                        const orderNumber = orderCardContainer.querySelector('[style*="Bestellung"]')?.textContent || 'Unbekannt';
                        const customerInfo = orderCardContainer.querySelector('[style*="9CA3AF"]')?.textContent || 'Keine Info';
                        
                        // Create expanded details section
                        const detailsHTML = `
                            <div class="order-details-expanded" style="background: #0F1419; padding: 16px; margin-top: 8px; border-radius: 8px; border-top: 1px solid #374151;">
                                <div style="color: white; font-weight: 600; margin-bottom: 12px; font-size: 16px;">
                                    📋 Bestelldetails
                                </div>
                                
                                <div style="display: grid; gap: 8px; margin-bottom: 16px;">
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="color: #9CA3AF; font-size: 14px;">Bestell-ID:</span>
                                        <span style="color: white; font-size: 14px;">${orderNumber}</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="color: #9CA3AF; font-size: 14px;">Info:</span>
                                        <span style="color: white; font-size: 14px; text-align: right; max-width: 60%;">${customerInfo}</span>
                                    </div>
                                </div>
                                
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <button onclick="openNavigation('${customerInfo}', '<?php echo esc_js(get_option('dispatch_default_depot_address', '')); ?>')" style="background: #1E40AF; color: white; border: none; padding: 16px 24px; border-radius: 25px; font-size: 18px; cursor: pointer; display: flex; align-items: center; gap: 12px; font-weight: 600; box-shadow: 0 3px 8px rgba(30,64,175,0.4);">
                                        <span style="font-size: 28px;">🚗</span> Navigation
                                    </button>
                                    <button onclick="markOrderPacked(${orderId})" style="background: #10B981; color: white; border: none; padding: 8px 16px; border-radius: 20px; font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                                        ✅ Als gepackt markieren
                                    </button>
                                </div>
                            </div>
                        `;
                        
                        // Add the expanded details after the order footer
                        const orderFooter = orderCardContainer.querySelector('.order-pack-footer');
                        if (orderFooter) {
                            orderFooter.insertAdjacentHTML('afterend', detailsHTML);
                        }
                    }
                } catch (error) {
                    console.error('Error in toggleOrderItems:', error);
                }
            }
            
            function markOrderPacked(orderId) {
                if (confirm('Bestellung als gepackt markieren?')) {
                    // Future implementation
                    alert('Bestellung wurde als gepackt markiert!');
                }
            }
            
            function showFullscreenImage(imageUrl, itemName) {
                try {
                    
                    // Remove any existing fullscreen viewer
                    const existingViewer = document.getElementById('fullscreen-image-viewer');
                    if (existingViewer) {
                        existingViewer.remove();
                    }
                    
                    // Create fullscreen overlay
                    const fullscreenViewer = document.createElement('div');
                    fullscreenViewer.id = 'fullscreen-image-viewer';
                    fullscreenViewer.style.cssText = `
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0, 0, 0, 0.95);
                        z-index: 10000;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        padding: 20px;
                        box-sizing: border-box;
                        animation: fadeIn 0.3s ease-out;
                    `;
                    
                    fullscreenViewer.innerHTML = `
                        <style>
                            @keyframes fadeIn {
                                from { opacity: 0; }
                                to { opacity: 1; }
                            }
                            @keyframes zoomIn {
                                from { transform: scale(0.8); opacity: 0; }
                                to { transform: scale(1); opacity: 1; }
                            }
                            .fullscreen-image-container {
                                animation: zoomIn 0.3s ease-out;
                            }
                        </style>
                        
                        <!-- Close Button -->
                        <button onclick="closeFullscreenImage()" 
                                style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.9); color: #333; border: none; width: 44px; height: 44px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; z-index: 10001; box-shadow: 0 2px 10px rgba(0,0,0,0.3);"
                                title="Schließen">
                            ×
                        </button>
                        
                        <!-- Product Info Header -->
                        <div style="position: absolute; top: 20px; left: 20px; right: 80px; background: rgba(0,0,0,0.7); color: white; padding: 12px 16px; border-radius: 8px; backdrop-filter: blur(10px);">
                            <div style="font-weight: 600; font-size: 16px; margin-bottom: 4px;">Produktfoto</div>
                            <div style="font-size: 14px; opacity: 0.9;">${itemName}</div>
                        </div>
                        
                        <!-- Image Container -->
                        <div class="fullscreen-image-container" style="max-width: 90%; max-height: 80%; display: flex; align-items: center; justify-content: center; margin-top: 80px;">
                            <img src="${imageUrl}" 
                                 alt="${itemName}"
                                 style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.5);"
                                 onload="this.style.opacity='1'"
                                 onerror="showImageError(this)">
                        </div>
                        
                        <!-- Instructions -->
                        <div style="position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.7); color: white; padding: 8px 16px; border-radius: 20px; font-size: 14px; white-space: nowrap;">
                            💡 Tippen Sie irgendwo, um zu schließen
                        </div>
                    `;
                    
                    // Close on click anywhere
                    fullscreenViewer.addEventListener('click', function(e) {
                        // Don't close if clicking on the image itself
                        if (e.target.tagName !== 'IMG') {
                            closeFullscreenImage();
                        }
                    });
                    
                    // Close on escape key
                    const handleEscape = function(e) {
                        if (e.key === 'Escape') {
                            closeFullscreenImage();
                            document.removeEventListener('keydown', handleEscape);
                        }
                    };
                    document.addEventListener('keydown', handleEscape);
                    
                    // Prevent scrolling while fullscreen is open
                    document.body.style.overflow = 'hidden';
                    
                    // Add to DOM
                    document.body.appendChild(fullscreenViewer);
                    
                } catch (error) {
                    console.error('Error showing fullscreen image:', error);
                }
            }
            
            function closeFullscreenImage() {
                try {
                    const viewer = document.getElementById('fullscreen-image-viewer');
                    if (viewer) {
                        // Fade out animation
                        viewer.style.animation = 'fadeOut 0.2s ease-out';
                        viewer.style.animationFillMode = 'forwards';
                        
                        // Add fadeOut keyframe
                        if (!document.querySelector('#fadeOutStyle')) {
                            const style = document.createElement('style');
                            style.id = 'fadeOutStyle';
                            style.innerHTML = `
                                @keyframes fadeOut {
                                    from { opacity: 1; }
                                    to { opacity: 0; }
                                }
                            `;
                            document.head.appendChild(style);
                        }
                        
                        setTimeout(() => {
                            if (viewer && viewer.parentNode) {
                                viewer.remove();
                            }
                            // Restore scrolling
                            document.body.style.overflow = '';
                        }, 200);
                    }
                } catch (error) {
                    console.error('Error closing fullscreen image:', error);
                }
            }
            
            function showImageError(imgElement) {
                try {
                    // Replace failed image with error message
                    imgElement.style.display = 'none';
                    const errorDiv = document.createElement('div');
                    errorDiv.style.cssText = `
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        padding: 40px;
                        text-align: center;
                        color: white;
                        background: rgba(255,255,255,0.1);
                        border-radius: 12px;
                        border: 2px dashed rgba(255,255,255,0.3);
                    `;
                    errorDiv.innerHTML = `
                        <div style="font-size: 48px; margin-bottom: 16px;">📷</div>
                        <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">Bild kann nicht geladen werden</div>
                        <div style="font-size: 14px; opacity: 0.7;">Das Produktfoto ist möglicherweise nicht verfügbar</div>
                    `;
                    imgElement.parentNode.appendChild(errorDiv);
                } catch (error) {
                    console.error('Error showing image error:', error);
                }
            }
            
            function showOrderDetailsExpanded(container, orderDetails, button) {
                try {
                    // Create expanded details section
                    const detailsHTML = `
                        <div class="order-details-expanded" style="background: #0F1419; padding: 16px; margin-top: 8px; border-radius: 8px; border-top: 1px solid #374151;">
                            <div style="color: white; font-weight: 600; margin-bottom: 12px; font-size: 16px;">
                                📋 Bestelldetails
                            </div>
                            
                            <div style="display: grid; gap: 8px; margin-bottom: 16px;">
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: #9CA3AF; font-size: 14px;">Bestell-ID:</span>
                                    <span style="color: white; font-size: 14px;">#${orderDetails.order_number}</span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: #9CA3AF; font-size: 14px;">Status:</span>
                                    <span style="color: #10B981; font-size: 14px;">${orderDetails.status_text}</span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: #9CA3AF; font-size: 14px;">Kunde:</span>
                                    <span style="color: white; font-size: 14px;">${orderDetails.customer_name}</span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: #9CA3AF; font-size: 14px;">Adresse:</span>
                                    <span style="color: white; font-size: 14px; text-align: right; max-width: 60%;">${orderDetails.delivery_address}</span>
                                </div>
                                ${orderDetails.phone ? `
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: #9CA3AF; font-size: 14px;">Telefon:</span>
                                    <span style="color: white; font-size: 14px;">${orderDetails.phone}</span>
                                </div>
                                ` : ''}
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: #9CA3AF; font-size: 14px;">Gesamt:</span>
                                    <span style="color: #10B981; font-weight: 600; font-size: 14px;">${orderDetails.total}</span>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <button onclick="callCustomer('${orderDetails.phone ? orderDetails.phone.replace(/'/g, "\\'") : ''}')" style="background: #10B981; color: white; border: none; padding: 8px 16px; border-radius: 20px; font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                                    📞 Kunde anrufen
                                </button>
                                <button onclick="openNavigation('${orderDetails.plus_code || orderDetails.delivery_address}', '<?php echo esc_js(get_option('dispatch_default_depot_address', '')); ?>')" style="background: #1E40AF; color: white; border: none; padding: 16px 24px; border-radius: 25px; font-size: 18px; cursor: pointer; display: flex; align-items: center; gap: 12px; font-weight: 600; box-shadow: 0 3px 8px rgba(30,64,175,0.4);">
                                    <span style="font-size: 28px;">🚗</span> Navigation
                                </button>
                                <button onclick="markOrderDelivered(${orderDetails.id})" style="background: #F59E0B; color: white; border: none; padding: 8px 16px; border-radius: 20px; font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                                    ✅ Als geliefert markieren
                                </button>
                            </div>
                        </div>
                    `;
                    
                    // Add the expanded details after the order footer
                    const orderFooter = container.querySelector('.order-pack-footer');
                    if (orderFooter) {
                        orderFooter.insertAdjacentHTML('afterend', detailsHTML);
                    }
                    
                    // Update button
                    button.textContent = 'Details ausblenden';
                    button.style.background = '#EF4444';
                    
                } catch (error) {
                    console.error('Error showing order details:', error);
                }
            }
            
            function markOrderDelivered(orderId) {
                if (confirm('Bestellung als geliefert markieren?')) {
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'dispatch_mark_delivered',
                            order_id: orderId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('✅ Bestellung wurde als geliefert markiert!');
                            location.reload();
                        } else {
                            alert('❌ Fehler: ' + (data.data?.message || 'Unbekannter Fehler'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('❌ Fehler beim Markieren der Bestellung');
                    });
                }
            }

            // Track pending SumUp payment
            let pendingSumUpOrderId = null;

            function openSumUpPayment(orderId, amount) {
                // Remove € symbol and convert to cents for SumUp
                const numericAmount = parseFloat(amount.replace('€', '').trim());
                const amountInCents = Math.round(numericAmount * 100);

                // Get current user's SumUp affiliate key from user meta
                const userId = '<?php echo get_current_user_id(); ?>';

                // Fetch user's SumUp credentials
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'get_sumup_credentials',
                        user_id: userId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.affiliate_key) {
                        // Store pending order ID for confirmation later
                        pendingSumUpOrderId = orderId;
                        localStorage.setItem('pendingSumUpOrderId', orderId);
                        localStorage.setItem('pendingSumUpAmount', amount);

                        // Build SumUp URL WITHOUT callbacks - no redirect back
                        // This way iOS stays in SumUp app and user manually returns to PWA
                        const sumupUrl = `sumupmerchant://pay/1.0?affiliate-key=${data.data.affiliate_key}&app-id=de.absa.driver&total=${amountInCents}&currency=EUR&title=Bestellung%20${orderId}&foreign-tx-id=${orderId}`;

                        console.log('Opening SumUp with URL:', sumupUrl);

                        // Show instruction modal before opening SumUp
                        showSumUpInstructionModal(orderId, amount, sumupUrl);
                    } else {
                        alert('❌ SumUp Zugangsdaten nicht konfiguriert. Bitte kontaktieren Sie den Administrator.');
                    }
                })
                .catch(error => {
                    console.error('Error fetching SumUp credentials:', error);
                    alert('❌ Fehler beim Laden der SumUp-Daten');
                });
            }

            function showSumUpInstructionModal(orderId, amount, sumupUrl) {
                // Create modal overlay
                const modal = document.createElement('div');
                modal.id = 'sumup-modal';
                modal.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0,0,0,0.8);
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                `;

                modal.innerHTML = `
                    <div style="background: white; border-radius: 16px; padding: 24px; max-width: 360px; width: 100%; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                        <div style="font-size: 48px; margin-bottom: 16px;">💳</div>
                        <h3 style="margin: 0 0 12px 0; color: #1f2937; font-size: 20px;">SumUp Zahlung</h3>
                        <p style="margin: 0 0 8px 0; color: #6b7280; font-size: 14px;">Bestellung #${orderId}</p>
                        <p style="margin: 0 0 20px 0; color: #059669; font-size: 24px; font-weight: bold;">${amount}</p>

                        <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 12px; margin-bottom: 20px;">
                            <p style="margin: 0; color: #92400e; font-size: 13px; line-height: 1.5;">
                                <strong>📱 Hinweis:</strong><br>
                                Nach der Zahlung bitte <strong>manuell zurück</strong> zu dieser App wechseln.
                            </p>
                        </div>

                        <button onclick="launchSumUpApp('${sumupUrl}')" style="
                            width: 100%;
                            padding: 16px;
                            background: linear-gradient(135deg, #3B82F6, #2563EB);
                            color: white;
                            border: none;
                            border-radius: 12px;
                            font-size: 16px;
                            font-weight: 600;
                            cursor: pointer;
                            margin-bottom: 12px;
                        ">
                            💳 SumUp öffnen
                        </button>

                        <button onclick="closeSumUpModal()" style="
                            width: 100%;
                            padding: 12px;
                            background: #f3f4f6;
                            color: #4b5563;
                            border: none;
                            border-radius: 12px;
                            font-size: 14px;
                            cursor: pointer;
                        ">
                            Abbrechen
                        </button>
                    </div>
                `;

                document.body.appendChild(modal);
            }

            function launchSumUpApp(sumupUrl) {
                // Close the instruction modal
                const modal = document.getElementById('sumup-modal');
                if (modal) {
                    modal.remove();
                }

                // Open SumUp app
                window.location.href = sumupUrl;

                // Show confirmation modal after a short delay (user returns manually)
                // This will be shown when user comes back to the app
                setTimeout(() => {
                    showSumUpConfirmationModal();
                }, 1000);
            }

            function closeSumUpModal() {
                const modal = document.getElementById('sumup-modal');
                if (modal) {
                    modal.remove();
                }
                pendingSumUpOrderId = null;
                localStorage.removeItem('pendingSumUpOrderId');
                localStorage.removeItem('pendingSumUpAmount');
            }

            function showSumUpConfirmationModal() {
                const orderId = localStorage.getItem('pendingSumUpOrderId');
                const amount = localStorage.getItem('pendingSumUpAmount');

                if (!orderId) return;

                // Remove any existing modal
                const existingModal = document.getElementById('sumup-confirm-modal');
                if (existingModal) existingModal.remove();

                const modal = document.createElement('div');
                modal.id = 'sumup-confirm-modal';
                modal.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0,0,0,0.8);
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                `;

                modal.innerHTML = `
                    <div style="background: white; border-radius: 16px; padding: 24px; max-width: 360px; width: 100%; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                        <div style="font-size: 48px; margin-bottom: 16px;">✅</div>
                        <h3 style="margin: 0 0 12px 0; color: #1f2937; font-size: 20px;">Zahlung abschließen</h3>
                        <p style="margin: 0 0 8px 0; color: #6b7280; font-size: 14px;">Bestellung #${orderId}</p>
                        <p style="margin: 0 0 20px 0; color: #059669; font-size: 24px; font-weight: bold;">${amount}</p>

                        <p style="margin: 0 0 20px 0; color: #4b5563; font-size: 14px;">
                            War die SumUp-Zahlung erfolgreich?
                        </p>

                        <button onclick="confirmSumUpPayment('${orderId}')" style="
                            width: 100%;
                            padding: 16px;
                            background: linear-gradient(135deg, #10B981, #059669);
                            color: white;
                            border: none;
                            border-radius: 12px;
                            font-size: 16px;
                            font-weight: 600;
                            cursor: pointer;
                            margin-bottom: 12px;
                        ">
                            ✅ Ja, Zahlung erfolgreich
                        </button>

                        <button onclick="cancelSumUpPayment()" style="
                            width: 100%;
                            padding: 16px;
                            background: linear-gradient(135deg, #EF4444, #DC2626);
                            color: white;
                            border: none;
                            border-radius: 12px;
                            font-size: 16px;
                            font-weight: 600;
                            cursor: pointer;
                            margin-bottom: 12px;
                        ">
                            ❌ Nein, fehlgeschlagen
                        </button>

                        <button onclick="closeSumUpConfirmModal()" style="
                            width: 100%;
                            padding: 12px;
                            background: #f3f4f6;
                            color: #4b5563;
                            border: none;
                            border-radius: 12px;
                            font-size: 14px;
                            cursor: pointer;
                        ">
                            Später bestätigen
                        </button>
                    </div>
                `;

                document.body.appendChild(modal);
            }

            function confirmSumUpPayment(orderId) {
                // Mark payment as received
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'mark_sumup_payment',
                        order_id: orderId,
                        status: 'paid'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    closeSumUpConfirmModal();
                    localStorage.removeItem('pendingSumUpOrderId');
                    localStorage.removeItem('pendingSumUpAmount');

                    // Show success message
                    alert('✅ Zahlung erfolgreich verbucht!');

                    // Reload orders to update status
                    if (typeof fetchAndRenderOrders === 'function') {
                        fetchAndRenderOrders();
                    } else {
                        window.location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error marking payment:', error);
                    alert('❌ Fehler beim Speichern der Zahlung');
                });
            }

            function cancelSumUpPayment() {
                closeSumUpConfirmModal();
                localStorage.removeItem('pendingSumUpOrderId');
                localStorage.removeItem('pendingSumUpAmount');
                alert('❌ Zahlung wurde nicht verbucht');
            }

            function closeSumUpConfirmModal() {
                const modal = document.getElementById('sumup-confirm-modal');
                if (modal) {
                    modal.remove();
                }
            }

            // Check for pending SumUp payment when page loads or becomes visible
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible') {
                    const pendingOrderId = localStorage.getItem('pendingSumUpOrderId');
                    if (pendingOrderId) {
                        // Small delay to ensure page is fully visible
                        setTimeout(() => {
                            showSumUpConfirmationModal();
                        }, 500);
                    }
                }
            });

            // Also check on page load
            window.addEventListener('load', function() {
                const pendingOrderId = localStorage.getItem('pendingSumUpOrderId');
                if (pendingOrderId) {
                    setTimeout(() => {
                        showSumUpConfirmationModal();
                    }, 1000);
                }
            });

            // Legacy callback handling (fallback if SumUp does redirect)
            window.addEventListener('load', function() {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('sumup_success')) {
                    const orderId = urlParams.get('order_id');
                    localStorage.removeItem('pendingSumUpOrderId');
                    localStorage.removeItem('pendingSumUpAmount');

                    // Mark payment as received
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'mark_sumup_payment',
                            order_id: orderId,
                            status: 'paid'
                        })
                    })
                    .then(() => {
                        alert('✅ Zahlung erfolgreich!');
                        // Remove URL parameters and reload
                        window.location.href = window.location.pathname;
                    });
                } else if (urlParams.has('sumup_fail')) {
                    localStorage.removeItem('pendingSumUpOrderId');
                    localStorage.removeItem('pendingSumUpAmount');
                    alert('❌ Zahlung fehlgeschlagen oder abgebrochen');
                    // Remove URL parameters
                    window.location.href = window.location.pathname;
                }
            });

            function showVollstaendigeBestellungen() {
                try {
                    toggleMenu(); // Close hamburger menu
                    
                    // Update header title and center it properly
                    const headerTitle = document.querySelector('.header-title');
                    if (headerTitle) {
                        headerTitle.textContent = translations[currentLanguage].completedOrders || 'Vollständige Bestellungen';
                        headerTitle.style.cssText = 'text-align: center; margin: 0 auto; flex: 1; color: white; font-size: 18px; font-weight: 600;';
                    }
                    
                    // Ensure header has proper flex layout
                    const header = document.querySelector('.header, .app-header, .top-header');
                    if (header) {
                        header.style.cssText += '; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; position: relative;';
                    }
                    
                    // Show back arrow and hide hamburger menu
                    // Single clean approach - use only fixed position back arrow
                    
                    // Remove any existing back arrows first
                    const existingArrow1 = document.getElementById('vollstaendige-back-arrow');
                    if (existingArrow1) {
                        existingArrow1.remove();
                    }
                    
                    const existingArrow2 = document.getElementById('vollstaendige-back-arrow-fixed');
                    if (existingArrow2) {
                        existingArrow2.remove();
                    }
                    
                    // Clear header-left to avoid conflicts
                    const headerLeft = document.querySelector('.header-left');
                    if (headerLeft) {
                        headerLeft.innerHTML = '';
                    }
                    
                    // Create single fixed back arrow
                    const backArrow = document.createElement('div');
                    backArrow.id = 'vollstaendige-back-arrow-fixed';
                    backArrow.style.cssText = 'position: fixed; top: 20px; left: 20px; z-index: 9999; pointer-events: auto;';
                    backArrow.innerHTML = `
                        <button class="back-button-fixed" onclick="showBestellungen(); return false;" style="background: none; border: none; color: white; padding: 12px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2z"/>
                            </svg>
                        </button>
                    `;
                    document.body.appendChild(backArrow);
                    
                    // Hide hamburger menu and add layout balance
                    const headerRight = document.querySelector('.header-right');
                    if (headerRight) {
                        headerRight.style.display = 'none';
                        
                        // Create invisible spacer for layout balance
                        if (headerRight.parentNode && !document.querySelector('.header-spacer')) {
                            const spacer = document.createElement('div');
                            spacer.className = 'header-spacer';
                            spacer.style.cssText = 'width: 48px; height: 48px;';
                            headerRight.parentNode.appendChild(spacer);
                        }
                    }
                    
                    // Also hide any menu toggle button that might exist
                    const menuToggle = document.querySelector('.menu-toggle, .hamburger-menu, [onclick*="toggleMenu"]');
                    if (menuToggle) {
                        menuToggle.style.display = 'none';
                    }
                    
                    mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        mainContent.innerHTML = `
                            <div class="completed-orders-container" style="padding: 20px; height: 100%; display: flex; flex-direction: column;">
                                <!-- Tab Navigation -->
                                <div class="tab-navigation" style="display: flex; margin-bottom: 20px; gap: 0; border-radius: 8px; overflow: hidden; border: 1px solid #374151;">
                                    <button class="tab-btn active" data-tab="heute" onclick="switchCompletedTab('heute')" 
                                            style="flex: 1; padding: 12px 20px; background: #374151; color: white; border: none; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                                        HEUTE (<span id="heute-count">0</span>)
                                    </button>
                                    <button class="tab-btn" data-tab="gestern" onclick="switchCompletedTab('gestern')" 
                                            style="flex: 1; padding: 12px 20px; background: #1f2937; color: #9CA3AF; border: none; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                                        GESTERN (<span id="gestern-count">0</span>)
                                    </button>
                                </div>
                                
                                <!-- Content Area -->
                                <div class="tab-content" style="flex: 1; display: flex; flex-direction: column;">
                                    <div id="completed-orders-list" style="flex: 1;">
                                        <!-- Loading state -->
                                        <div class="loading-state" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #9CA3AF;">
                                            <div style="margin-bottom: 20px;">Lade abgeschlossene Bestellungen...</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        // Load completed orders for today immediately
                        loadCompletedOrders('heute');
                    }
                } catch (error) {
                    console.error('Error in showVollstaendigeBestellungen:', error);
                }
            }
            
            function switchCompletedTab(tab) {
                try {
                    
                    // Update tab buttons
                    const tabButtons = document.querySelectorAll('.tab-btn');
                    tabButtons.forEach(btn => {
                        if (btn.dataset.tab === tab) {
                            btn.classList.add('active');
                            btn.style.background = '#374151';
                            btn.style.color = 'white';
                        } else {
                            btn.classList.remove('active');
                            btn.style.background = '#1f2937';
                            btn.style.color = '#9CA3AF';
                        }
                    });
                    
                    // Load orders for the selected tab
                    loadCompletedOrders(tab);
                } catch (error) {
                    console.error('Error in switchCompletedTab:', error);
                }
            }
            
            function loadCompletedOrders(period) {
                try {
                    
                    const ordersList = document.getElementById('completed-orders-list');
                    if (!ordersList) {
                        console.error('completed-orders-list element not found!');
                        return;
                    }
                    
                    
                    // Show loading state
                    ordersList.innerHTML = `
                        <div class="loading-state" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #9CA3AF;">
                            <div style="margin-bottom: 20px;">Lade ${period === 'heute' ? 'heutige' : 'gestrige'} Bestellungen...</div>
                        </div>
                    `;
                    
                    // Make AJAX call to get completed orders
                    fetch(dispatch_ajax.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=get_completed_orders&nonce=${dispatch_ajax.nonce}&period=${period}`,
                        credentials: 'same-origin'
                    })
                    .then(response => {
                        return response.text();
                    })
                    .then(text => {
                        try {
                            const data = JSON.parse(text);
                            if (data.success) {
                                displayCompletedOrders(data.data.orders, period);
                                updateTabCounts(period, data.data.orders.length);
                            } else {
                                console.error('API returned error:', data);
                                showCompletedOrdersError('Fehler beim Laden der Bestellungen: ' + (data.data || 'Unbekannter Fehler'));
                            }
                        } catch (e) {
                            console.error('JSON Parse error:', e);
                            console.error('Raw response text:', text);
                            showCompletedOrdersError('Server-Fehler: Ungültige Antwort');
                        }
                    })
                    .catch(error => {
                        console.error('Error loading completed orders:', error);
                        showCompletedOrdersError('Netzwerk-Fehler');
                    });
                } catch (error) {
                    console.error('Error in loadCompletedOrders:', error);
                }
            }
            
            function displayCompletedOrders(orders, period) {
                try {
                    const ordersList = document.getElementById('completed-orders-list');
                    if (!ordersList) return;
                    
                    if (orders.length === 0) {
                        // Show empty state with image like in the screenshot
                        ordersList.innerHTML = `
                            <div class="empty-state" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; text-align: center; padding: 40px 20px;">
                                <!-- Empty state illustration -->
                                <div style="margin-bottom: 40px; position: relative;">
                                    <div style="width: 120px; height: 120px; background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; position: relative; margin: 0 auto;">
                                        <!-- Documents/Papers illustration -->
                                        <div style="position: relative;">
                                            <!-- First document (back) -->
                                            <div style="width: 50px; height: 60px; background: white; border-radius: 6px; position: absolute; top: -5px; left: -5px; transform: rotate(-10deg); border: 2px solid #d1d5db; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"></div>
                                            <!-- Second document (middle) -->
                                            <div style="width: 50px; height: 60px; background: white; border-radius: 6px; position: absolute; top: 0px; left: 0px; transform: rotate(5deg); border: 2px solid #d1d5db; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"></div>
                                            <!-- Third document (front) -->
                                            <div style="width: 50px; height: 60px; background: white; border-radius: 6px; position: relative; z-index: 3; border: 2px solid #d1d5db; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                                                <!-- Lines on document -->
                                                <div style="position: absolute; top: 12px; left: 8px; right: 8px; height: 2px; background: #e5e7eb; border-radius: 1px;"></div>
                                                <div style="position: absolute; top: 20px; left: 8px; right: 12px; height: 2px; background: #e5e7eb; border-radius: 1px;"></div>
                                                <div style="position: absolute; top: 28px; left: 8px; right: 8px; height: 2px; background: #e5e7eb; border-radius: 1px;"></div>
                                                <div style="position: absolute; top: 36px; left: 8px; right: 16px; height: 2px; background: #e5e7eb; border-radius: 1px;"></div>
                                            </div>
                                        </div>
                                        
                                        <!-- Floating dots around the circle -->
                                        <div style="position: absolute; top: 20px; right: 25px; width: 8px; height: 8px; background: #d1d5db; border-radius: 50%; opacity: 0.6;"></div>
                                        <div style="position: absolute; bottom: 25px; left: 15px; width: 6px; height: 6px; background: #d1d5db; border-radius: 50%; opacity: 0.4;"></div>
                                        <div style="position: absolute; top: 35px; left: 10px; width: 4px; height: 4px; background: #d1d5db; border-radius: 50%; opacity: 0.5;"></div>
                                        <div style="position: absolute; bottom: 35px; right: 20px; width: 10px; height: 10px; background: #d1d5db; border-radius: 50%; opacity: 0.3;"></div>
                                    </div>
                                </div>
                                
                                <!-- Text matching the screenshot -->
                                <div style="color: #6B7280; font-size: 16px; font-weight: 400; line-height: 1.5; max-width: 280px;">
                                    Derzeit liegen keine abgeschlossenen Bestellungen vor
                                </div>
                            </div>
                        `;
                    } else {
                        // Show orders
                        let ordersHTML = '';
                        orders.forEach(order => {
                            ordersHTML += `
                                <div class="completed-order-card" style="background: white; border: 1px solid #E5E7EB; border-radius: 8px; padding: 16px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                        <div>
                                            <div style="font-weight: 600; color: #1F2937; margin-bottom: 4px;">#${order.order_number}</div>
                                            <div style="color: #6B7280; font-size: 14px;">${order.customer_name}</div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="background: #10B981; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; margin-bottom: 4px;">
                                                ✓ Abgeschlossen
                                            </div>
                                            <div style="color: #6B7280; font-size: 12px;">${order.completed_time}</div>
                                        </div>
                                    </div>
                                    <div style="color: #6B7280; font-size: 14px; margin-bottom: 8px;">
                                        📍 ${order.delivery_address}
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid #F3F4F6;">
                                        <div style="color: #6B7280; font-size: 14px;">
                                            ${order.delivery_datetime}
                                        </div>
                                        <div style="font-weight: 600; color: #1F2937;">
                                            €${order.total}
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        
                        ordersList.innerHTML = `
                            <div class="completed-orders-scroll" style="height: 100%; overflow-y: auto; -webkit-overflow-scrolling: touch;">
                                ${ordersHTML}
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error('Error in displayCompletedOrders:', error);
                }
            }
            
            function updateTabCounts(period, count) {
                try {
                    const countElement = document.getElementById(period + '-count');
                    if (countElement) {
                        countElement.textContent = count;
                    }
                } catch (error) {
                    console.error('Error in updateTabCounts:', error);
                }
            }
            
            function showCompletedOrdersError(message) {
                try {
                    const ordersList = document.getElementById('completed-orders-list');
                    if (ordersList) {
                        ordersList.innerHTML = `
                            <div class="error-state" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; text-align: center; color: #EF4444;">
                                <div style="margin-bottom: 20px;">⚠️</div>
                                <div style="margin-bottom: 8px; font-weight: 500;">${message}</div>
                                <button onclick="loadCompletedOrders('heute')" style="padding: 8px 16px; background: #3B82F6; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 12px;">
                                    Erneut versuchen
                                </button>
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error('Error in showCompletedOrdersError:', error);
                }
            }
            
            function showEinstellungen() {
                try {
                    toggleMenu(); // Close hamburger menu

                    // Get current language
                    const currentLang = localStorage.getItem('app_language') || 'de';

                    // Get main-content element
                    const mainContent = document.querySelector('.main-content');
                    if (!mainContent) {
                        console.error('Main content element not found');
                        return;
                    }

                    // Reset main-content to normal width
                    mainContent.className = 'main-content';

                    // Update header title with translation
                    const headerTitle = document.querySelector('.header-title');
                    if (headerTitle) {
                        headerTitle.textContent = translations[currentLanguage].settings || 'Einstellungen';
                    }

                    // Replace hamburger menu with back arrow to Dashboard
                    showBackArrowInHamburger(function() { goBackFromSettings(); });

                    // Clear header-right (remove any buttons from previous pages)
                    const headerRight = document.querySelector('.header-right');
                    if (headerRight) {
                        headerRight.innerHTML = '';
                    }

                    // Set content - Tab Navigation wie Bottom Navigation
                    mainContent.innerHTML = `
                            <div class="settings-container" style="background: #0f172a; height: 100%; display: flex; flex-direction: column;">

                                <!-- Tab Navigation -->
                                <div class="settings-tabs">
                                    <a href="#" onclick="showProfile(); return false;" class="settings-tab-item">
                                        <div class="icon">
                                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                            </svg>
                                        </div>
                                        <div class="label">${translations[currentLanguage].profile || 'Profil'}</div>
                                    </a>
                                    <a href="#" onclick="showLieferpraeferenzen(); return false;" class="settings-tab-item">
                                        <div class="icon">
                                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>
                                            </svg>
                                        </div>
                                        <div class="label">${translations[currentLanguage].pushNotifications ? translations[currentLanguage].pushNotifications.substring(0, 6) + '.' : 'Benach.'}</div>
                                    </a>
                                    <a href="#" onclick="showUeber(); return false;" class="settings-tab-item">
                                        <div class="icon">
                                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M11 17h2v-6h-2v6zm1-15C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zM11 9h2V7h-2v2z"/>
                                            </svg>
                                        </div>
                                        <div class="label">${translations[currentLanguage].appInfo || 'Über'}</div>
                                    </a>
                                    <a href="#" onclick="showSprache(); return false;" class="settings-tab-item">
                                        <div class="icon">
                                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12.87 15.07l-2.54-2.51.03-.03c1.74-1.94 2.98-4.17 3.71-6.53H17V4h-7V2H8v2H1v1.99h11.17C11.5 7.92 10.44 9.75 9 11.35 8.07 10.32 7.3 9.19 6.69 8h-2c.73 1.63 1.73 3.17 2.98 4.56l-5.09 5.02L4 19l5-5 3.11 3.11.76-2.04zM18.5 10h-2L12 22h2l1.12-3h4.75L21 22h2l-4.5-12zm-2.62 7l1.62-4.33L19.12 17h-3.24z"/>
                                            </svg>
                                        </div>
                                        <div class="label">${translations[currentLanguage].language || 'Sprache'}</div>
                                    </a>
                                </div>

                                <!-- Content Area -->
                                <div class="settings-content" style="flex: 1; padding: 20px; overflow-y: auto;">
                                    <div style="text-align: center; color: #94a3b8; padding: 40px 20px;">
                                        <svg width="48" height="48" fill="currentColor" viewBox="0 0 24 24" style="opacity: 0.5; margin-bottom: 16px;">
                                            <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.07.62-.07.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
                                        </svg>
                                        <p style="font-size: 16px; margin: 0;">${currentLang === 'en' ? 'Select a tab above' : currentLang === 'es' ? 'Seleccione una pestaña arriba' : 'Wähle einen Tab oben aus'}</p>
                                    </div>
                                </div>

                                <!-- Tab Styles -->
                                <style>
                                    .settings-tabs {
                                        display: flex;
                                        justify-content: space-around;
                                        background: #2D3748;
                                        border-bottom: 1px solid #4A5568;
                                        padding: 8px 0;
                                    }

                                    .settings-tab-item {
                                        display: flex;
                                        flex-direction: column;
                                        align-items: center;
                                        text-decoration: none;
                                        color: #9CA3AF;
                                        transition: all 0.3s ease;
                                        padding: 8px 12px;
                                        border-radius: 8px;
                                        min-width: 70px;
                                    }

                                    .settings-tab-item:hover {
                                        color: #48BB78;
                                        background: rgba(72, 187, 120, 0.1);
                                    }

                                    .settings-tab-item .icon {
                                        margin-bottom: 4px;
                                        transition: transform 0.3s ease;
                                    }

                                    .settings-tab-item:hover .icon {
                                        transform: scale(1.1);
                                    }

                                    .settings-tab-item .label {
                                        font-size: 11px;
                                        font-weight: 500;
                                    }
                                </style>
                            </div>
                        `;
                } catch (error) {
                    console.error('Error in showEinstellungen:', error);
                }
            }

            // Function to go back from settings and restore hamburger menu
            function goBackFromSettings() {
                try {
                    // Restore hamburger menu
                    restoreHamburgerMenu();

                    // Check online status and show appropriate view
                    const isOnline = localStorage.getItem('driver_online_status') === 'true';
                    if (isOnline) {
                        showBestellungen();
                    } else {
                        showDashboard();
                    }
                } catch (error) {
                    console.error('Error in goBackFromSettings:', error);
                }
            }

            // Settings menu item functions
            function showLieferpraeferenzen() {
                try {

                    // Update header title
                    const headerTitle = document.querySelector('.header-title');
                    if (headerTitle) {
                        headerTitle.textContent = translations[currentLanguage].notifications || 'Benachrichtigungen';
                    }

                    // Replace hamburger menu with back arrow
                    showBackArrowInHamburger(function() { showEinstellungen(); });

                    mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        // Check push notification permission
                        const pushEnabled = ('Notification' in window) && Notification.permission === 'granted';
                        const pushChecked = pushEnabled ? 'checked' : '';

                        mainContent.innerHTML = `
                            <div class="notifications-container" style="background: #0f172a; height: 100%; overflow-y: auto;">
                                <div class="notifications-list" style="padding: 0;">

                                    <!-- Push-Benachrichtigungen -->
                                    <div class="notification-item">
                                        <div class="notification-info">
                                            <div class="notification-title">🔔 Push-Benachrichtigungen</div>
                                            <div class="notification-desc">Erhalte Benachrichtigungen bei neuen Bestellungen</div>
                                        </div>
                                        <div class="toggle-switch">
                                            <input type="checkbox" id="push_notifications" ${pushChecked} onchange="handlePushToggle(this)">
                                            <label for="push_notifications"></label>
                                        </div>
                                    </div>

                                    <!-- Ton für neue Bestellungen -->
                                    <div class="notification-item">
                                        <div class="notification-info">
                                            <div class="notification-title">🔊 Ton für neue Bestellungen</div>
                                            <div class="notification-desc">Spiele einen Ton ab, wenn neue Bestellungen eintreffen</div>
                                        </div>
                                        <div class="toggle-switch">
                                            <input type="checkbox" id="alarm_neue_bestellungen" checked>
                                            <label for="alarm_neue_bestellungen"></label>
                                        </div>
                                    </div>

                                    <!-- Entzogene Aufträge -->
                                    <div class="notification-item">
                                        <div class="notification-info">
                                            <div class="notification-title">📤 Entzogene Aufträge</div>
                                            <div class="notification-desc">Erhalte eine Benachrichtigung, wenn dir ein Auftrag entzogen wird</div>
                                        </div>
                                        <div class="toggle-switch">
                                            <input type="checkbox" id="notify_removed_orders" checked>
                                            <label for="notify_removed_orders"></label>
                                        </div>
                                    </div>

                                </div>

                                <!-- Notification Item Styles -->
                                <style>
                                    .notification-item {
                                        display: flex;
                                        align-items: center;
                                        justify-content: space-between;
                                        padding: 18px 20px;
                                        border-bottom: 1px solid #1e293b;
                                        gap: 16px;
                                        transition: background 0.2s;
                                    }

                                    .notification-item:hover {
                                        background: rgba(59, 130, 246, 0.05);
                                    }

                                    .notification-info {
                                        flex: 1;
                                        min-width: 0;
                                    }

                                    .notification-title {
                                        color: #f1f5f9;
                                        font-size: 16px;
                                        font-weight: 600;
                                        margin-bottom: 4px;
                                    }

                                    .notification-desc {
                                        color: #94a3b8;
                                        font-size: 14px;
                                        line-height: 1.4;
                                    }
                                </style>

                            </div>
                        `;
                        
                        // Add CSS for toggle switches
                        const style = document.createElement('style');
                        style.innerHTML = `
                            .toggle-switch {
                                position: relative;
                                display: inline-block;
                                width: 52px;
                                height: 32px;
                            }
                            
                            .toggle-switch input {
                                opacity: 0;
                                width: 0;
                                height: 0;
                            }
                            
                            .toggle-switch label {
                                position: absolute;
                                cursor: pointer;
                                top: 0;
                                left: 0;
                                right: 0;
                                bottom: 0;
                                background-color: #374151;
                                transition: 0.4s;
                                border-radius: 32px;
                            }
                            
                            .toggle-switch label:before {
                                position: absolute;
                                content: "";
                                height: 24px;
                                width: 24px;
                                left: 4px;
                                bottom: 4px;
                                background-color: white;
                                transition: 0.4s;
                                border-radius: 50%;
                            }
                            
                            .toggle-switch input:checked + label {
                                background-color: #10B981;
                            }
                            
                            .toggle-switch input:checked + label:before {
                                transform: translateX(20px);
                            }
                            
                            .setting-item:last-child {
                                border-bottom: none !important;
                            }
                        `;
                        document.head.appendChild(style);
                        
                        // Load current settings
                        loadLieferpraeferenzenSettings();
                        
                        // Add event listeners for toggles
                        document.querySelectorAll('.toggle-switch input').forEach(toggle => {
                            toggle.addEventListener('change', function() {
                                saveLieferpraeferenzenSetting(this.id, this.checked);
                            });
                        });
                    }
                } catch (error) {
                    console.error('Error in showLieferpraeferenzen:', error);
                }
            }
            
            function loadLieferpraeferenzenSettings() {
                // Load settings from server first, then fall back to localStorage defaults
                fetch(dispatch_ajax.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_lieferpraeferenzen_settings&nonce=' + dispatch_ajax.nonce + '',
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    let settings = {};
                    
                    if (data.success) {
                        settings = data.data;
                    } else {
                        // Fall back to localStorage
                        settings = JSON.parse(localStorage.getItem('lieferpraeferenzen_settings') || '{}');
                    }
                    
                    // Set default values based on screenshot
                    const defaults = {
                        'alarm_neue_bestellungen': true,  // ON in screenshot
                        'status_bestaetigung': false,     // OFF in screenshot
                        'zustellnachweis': true           // ON in screenshot
                    };
                    
                    Object.keys(defaults).forEach(key => {
                        const toggle = document.getElementById(key);
                        if (toggle) {
                            toggle.checked = settings[key] !== undefined ? settings[key] : defaults[key];
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading settings:', error);
                    
                    // Fall back to localStorage and defaults
                    const settings = JSON.parse(localStorage.getItem('lieferpraeferenzen_settings') || '{}');
                    const defaults = {
                        'alarm_neue_bestellungen': true,
                        'status_bestaetigung': false,
                        'zustellnachweis': true
                    };
                    
                    Object.keys(defaults).forEach(key => {
                        const toggle = document.getElementById(key);
                        if (toggle) {
                            toggle.checked = settings[key] !== undefined ? settings[key] : defaults[key];
                        }
                    });
                });
            }
            
            function saveLieferpraeferenzenSetting(settingKey, value) {
                try {
                    
                    // Save to localStorage
                    const settings = JSON.parse(localStorage.getItem('lieferpraeferenzen_settings') || '{}');
                    settings[settingKey] = value;
                    localStorage.setItem('lieferpraeferenzen_settings', JSON.stringify(settings));
                    
                    // Optional: Save to server via AJAX
                    fetch(dispatch_ajax.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=save_lieferpraeferenzen_setting&setting_key=${settingKey}&setting_value=${value}&nonce=' + dispatch_ajax.nonce + '`,
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                    })
                    .catch(error => {
                        console.error('Error saving setting:', error);
                    });
                    
                } catch (error) {
                    console.error('Error in saveLieferpraeferenzenSetting:', error);
                }
            }
            
            function showNavigationSettings() {
                try {
                    // Update header title
                    const headerTitle = document.querySelector('.header-title');
                    if (headerTitle) {
                        headerTitle.textContent = translations[currentLanguage].navigation || 'Navigation';
                    }

                    // Show back arrow to Settings
                    const headerLeft = document.querySelector('.header-left');
                    if (headerLeft) {
                        headerLeft.innerHTML = `
                            <button class="back-button" onclick="showEinstellungen(); return false;" style="background: none; border: none; color: white; padding: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; position: relative; z-index: 100;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2z"/>
                                </svg>
                            </button>
                        `;
                    }

                    // Hide hamburger menu
                    const hamburgerBtn = document.querySelector('.hamburger-menu');
                    if (hamburgerBtn) {
                        hamburgerBtn.style.display = 'none';
                    }

                    // Get current navigation preference
                    const currentNavApp = localStorage.getItem('preferred_nav_app') || 'google';

                    const mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        mainContent.innerHTML = `
                            <div class="navigation-settings-container" style="background: #111827; height: 100%; overflow-y: auto; padding: 20px;">
                                <div class="info-message" style="background: linear-gradient(135deg, #3B82F6, #2563EB); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);">
                                    <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">
                                        🚗 Navigations-App
                                    </div>
                                    <div style="font-size: 14px; opacity: 0.9;">
                                        Wählen Sie Ihre bevorzugte App für die Navigation zu Kunden
                                    </div>
                                </div>

                                <div class="navigation-options" style="background: #1F2937; border-radius: 12px; padding: 16px;">
                                    <div style="margin-bottom: 16px; border-bottom: 1px solid #374151; padding-bottom: 16px;">
                                        <label style="display: flex; align-items: center; padding: 12px; cursor: pointer; border-radius: 8px; transition: background 0.2s;"
                                               onmouseover="this.style.background='#374151'" onmouseout="this.style.background='transparent'">
                                            <input type="radio" name="nav_app" value="google" ${currentNavApp === 'google' ? 'checked' : ''}
                                                   onchange="saveNavigationPreference('google')"
                                                   style="width: 20px; height: 20px; margin-right: 16px; cursor: pointer;">
                                            <div style="flex: 1;">
                                                <div style="color: white; font-size: 16px; font-weight: 500;">Google Maps</div>
                                                <div style="color: #9CA3AF; font-size: 12px; margin-top: 2px;">Die meistgenutzte Navigations-App</div>
                                            </div>
                                        </label>
                                    </div>

                                    <div style="margin-bottom: 16px; border-bottom: 1px solid #374151; padding-bottom: 16px;">
                                        <label style="display: flex; align-items: center; padding: 12px; cursor: pointer; border-radius: 8px; transition: background 0.2s;"
                                               onmouseover="this.style.background='#374151'" onmouseout="this.style.background='transparent'">
                                            <input type="radio" name="nav_app" value="apple" ${currentNavApp === 'apple' ? 'checked' : ''}
                                                   onchange="saveNavigationPreference('apple')"
                                                   style="width: 20px; height: 20px; margin-right: 16px; cursor: pointer;">
                                            <div style="flex: 1;">
                                                <div style="color: white; font-size: 16px; font-weight: 500;">Apple Karten</div>
                                                <div style="color: #9CA3AF; font-size: 12px; margin-top: 2px;">Integriert in iOS, energieeffizient</div>
                                            </div>
                                        </label>
                                    </div>

                                    <div style="padding-bottom: 8px;">
                                        <label style="display: flex; align-items: center; padding: 12px; cursor: pointer; border-radius: 8px; transition: background 0.2s;"
                                               onmouseover="this.style.background='#374151'" onmouseout="this.style.background='transparent'">
                                            <input type="radio" name="nav_app" value="waze" ${currentNavApp === 'waze' ? 'checked' : ''}
                                                   onchange="saveNavigationPreference('waze')"
                                                   style="width: 20px; height: 20px; margin-right: 16px; cursor: pointer;">
                                            <div style="flex: 1;">
                                                <div style="color: white; font-size: 16px; font-weight: 500;">Waze</div>
                                                <div style="color: #9CA3AF; font-size: 12px; margin-top: 2px;">Community-basierte Navigation mit Verkehrsinfos</div>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div style="background: #1F2937; border-radius: 12px; padding: 16px; margin-top: 20px;">
                                    <div style="color: #9CA3AF; font-size: 14px; line-height: 1.6;">
                                        <strong style="color: white;">ℹ️ Hinweis:</strong><br>
                                        Die gewählte App öffnet sich automatisch, wenn Sie auf das Navigations-Symbol bei einer Bestellung tippen.
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error('Error in showNavigationSettings:', error);
                }
            }

            // Save navigation preference
            function saveNavigationPreference(app) {
                localStorage.setItem('preferred_nav_app', app);
                showNotificationToast(`✓ ${app === 'google' ? 'Google Maps' : app === 'apple' ? 'Apple Karten' : 'Waze'} als Standard gesetzt`, 'success');
            }

            // Language Settings
            function showLanguageSettings() {
                try {
                    // Update header title
                    const headerTitle = document.querySelector('.header-title');
                    if (headerTitle) {
                        headerTitle.textContent = translations[currentLanguage].language || 'Sprache';
                    }

                    // Show back arrow to Settings
                    const headerLeft = document.querySelector('.header-left');
                    if (headerLeft) {
                        headerLeft.innerHTML = `
                            <button class="back-button" onclick="showEinstellungen(); return false;" style="background: none; border: none; color: white; padding: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; position: relative; z-index: 100;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2z"/>
                                </svg>
                            </button>
                        `;
                    }

                    // Hide hamburger menu
                    const hamburgerBtn = document.querySelector('.hamburger-menu');
                    if (hamburgerBtn) {
                        hamburgerBtn.style.display = 'none';
                    }

                    // Use the global currentLanguage, don't redefine it
                    currentLanguage = localStorage.getItem('app_language') || 'de';

                    mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        mainContent.innerHTML = `
                            <div class="language-settings-container" style="background: #111827; height: 100%; overflow-y: auto; padding: 20px;">
                                <div class="info-message" style="background: linear-gradient(135deg, #3B82F6, #2563EB); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);">
                                    <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">
                                        🌍 ${translations[currentLanguage].selectLanguage || 'Wählen Sie Ihre Sprache'}
                                    </div>
                                    <div style="font-size: 14px; opacity: 0.9;">
                                        ${translations[currentLanguage].changeLanguageInfo || 'Die App wird in der gewählten Sprache angezeigt'}
                                    </div>
                                </div>

                                <div class="language-options" style="background: #1F2937; border-radius: 12px; padding: 16px;">
                                    <!-- Deutsch -->
                                    <div style="margin-bottom: 16px; border-bottom: 1px solid #374151; padding-bottom: 16px;">
                                        <label style="display: flex; align-items: center; padding: 12px; cursor: pointer; border-radius: 8px; transition: background 0.2s;"
                                               onmouseover="this.style.background='#374151'" onmouseout="this.style.background='transparent'">
                                            <input type="radio" name="language" value="de" ${currentLanguage === 'de' ? 'checked' : ''}
                                                   onchange="changeLanguage('de')"
                                                   style="width: 20px; height: 20px; margin-right: 16px; cursor: pointer;">
                                            <div style="flex: 1;">
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-size: 24px;">🇩🇪</span>
                                                    <div>
                                                        <div style="color: white; font-size: 16px; font-weight: 500;">Deutsch</div>
                                                        <div style="color: #9CA3AF; font-size: 12px; margin-top: 2px;">German</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </label>
                                    </div>

                                    <!-- English -->
                                    <div style="margin-bottom: 16px; border-bottom: 1px solid #374151; padding-bottom: 16px;">
                                        <label style="display: flex; align-items: center; padding: 12px; cursor: pointer; border-radius: 8px; transition: background 0.2s;"
                                               onmouseover="this.style.background='#374151'" onmouseout="this.style.background='transparent'">
                                            <input type="radio" name="language" value="en" ${currentLanguage === 'en' ? 'checked' : ''}
                                                   onchange="changeLanguage('en')"
                                                   style="width: 20px; height: 20px; margin-right: 16px; cursor: pointer;">
                                            <div style="flex: 1;">
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-size: 24px;">🇬🇧</span>
                                                    <div>
                                                        <div style="color: white; font-size: 16px; font-weight: 500;">English</div>
                                                        <div style="color: #9CA3AF; font-size: 12px; margin-top: 2px;">Englisch</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </label>
                                    </div>

                                    <!-- Español -->
                                    <div style="padding-bottom: 8px;">
                                        <label style="display: flex; align-items: center; padding: 12px; cursor: pointer; border-radius: 8px; transition: background 0.2s;"
                                               onmouseover="this.style.background='#374151'" onmouseout="this.style.background='transparent'">
                                            <input type="radio" name="language" value="es" ${currentLanguage === 'es' ? 'checked' : ''}
                                                   onchange="changeLanguage('es')"
                                                   style="width: 20px; height: 20px; margin-right: 16px; cursor: pointer;">
                                            <div style="flex: 1;">
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-size: 24px;">🇪🇸</span>
                                                    <div>
                                                        <div style="color: white; font-size: 16px; font-weight: 500;">Español</div>
                                                        <div style="color: #9CA3AF; font-size: 12px; margin-top: 2px;">Spanisch</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div style="margin-top: 20px; padding: 15px; background: #374151; border-radius: 8px;">
                                    <div style="color: #9CA3AF; font-size: 14px;">
                                        <strong style="color: white;">💡 ${translations[currentLanguage].tip || 'Hinweis'}:</strong><br>
                                        ${translations[currentLanguage].languageTip || 'Die Spracheinstellung wird lokal gespeichert und bleibt beim nächsten Login erhalten.'}
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error('Error in showLanguageSettings:', error);
                }
            }

            function changeLanguage(lang) {
                localStorage.setItem('app_language', lang);
                currentLanguage = lang;

                // Update current language display in settings
                const langDisplay = document.getElementById('current-language');
                if (langDisplay) {
                    const langNames = {
                        'de': 'Deutsch',
                        'en': 'English',
                        'es': 'Español'
                    };
                    langDisplay.textContent = langNames[lang];
                }

                // Reload the interface with new language
                applyTranslations();
                showNotificationToast(translations[lang].languageChanged || 'Sprache geändert', 'success');

                // Refresh current view
                setTimeout(() => {
                    showLanguageSettings();
                }, 300);
            }

            function showSupportSettings() {
                try {
                    // Update header title
                    const headerTitle = document.querySelector('.header-title');
                    if (headerTitle) {
                        headerTitle.textContent = translations[currentLanguage].support || 'Support';
                    }

                    // Show back arrow to Settings
                    const headerLeft = document.querySelector('.header-left');
                    if (headerLeft) {
                        headerLeft.innerHTML = `
                            <button class="back-button" onclick="showEinstellungen(); return false;" style="background: none; border: none; color: white; padding: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; position: relative; z-index: 100;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2z"/>
                                </svg>
                            </button>
                        `;
                    }

                    // Hide hamburger menu
                    const hamburgerBtn = document.querySelector('.hamburger-menu');
                    if (hamburgerBtn) {
                        hamburgerBtn.style.display = 'none';
                    }

                    // Get current user and device info
                    const username = dispatch_ajax?.username || 'Unbekannt';
                    const userAgent = navigator.userAgent;
                    const platform = navigator.platform;
                    const appVersion = '2.0.0'; // You can update this
                    const currentDate = new Date().toLocaleString('de-DE');

                    const mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        mainContent.innerHTML = `
                            <div class="support-settings-container" style="background: #111827; height: 100%; overflow-y: auto; padding: 20px;">

                                <!-- Support Header -->
                                <div style="background: linear-gradient(135deg, #10B981, #059669); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);">
                                    <div style="font-size: 20px; font-weight: 600; margin-bottom: 8px;">
                                        📧 Support kontaktieren
                                    </div>
                                    <div style="font-size: 14px; opacity: 0.9;">
                                        Wir sind für Sie da! Kontaktieren Sie uns bei Fragen oder Problemen.
                                    </div>
                                </div>

                                <!-- Contact Options -->
                                <div style="background: #1F2937; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                                    <div style="color: white; font-size: 16px; font-weight: 600; margin-bottom: 16px;">Kontaktmöglichkeiten</div>

                                    <!-- Email Support -->
                                    <button onclick="sendSupportEmail()" style="width: 100%; background: #2563EB; color: white; border: none; padding: 16px; border-radius: 8px; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; font-size: 16px; font-weight: 500;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
                                            <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                                        </svg>
                                        E-Mail senden
                                    </button>

                                    <!-- WhatsApp Support -->
                                    <button onclick="openWhatsAppSupport()" style="width: 100%; background: #25D366; color: white; border: none; padding: 16px; border-radius: 8px; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; font-size: 16px; font-weight: 500;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.149-.67.149-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                        </svg>
                                        WhatsApp
                                    </button>

                                    <!-- Phone Support -->
                                    <button onclick="callSupport()" style="width: 100%; background: #374151; color: white; border: none; padding: 16px; border-radius: 8px; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; font-size: 16px; font-weight: 500;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
                                            <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56a.977.977 0 0 0-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/>
                                        </svg>
                                        Anrufen: +34 123 456 789
                                    </button>
                                </div>

                                <!-- System Information -->
                                <div style="background: #1F2937; border-radius: 12px; padding: 20px;">
                                    <div style="color: white; font-size: 16px; font-weight: 600; margin-bottom: 16px;">System-Informationen</div>
                                    <div style="color: #9CA3AF; font-size: 14px; line-height: 1.8;">
                                        <div style="margin-bottom: 8px;"><strong style="color: white;">Fahrer:</strong> ${username}</div>
                                        <div style="margin-bottom: 8px;"><strong style="color: white;">App-Version:</strong> ${appVersion}</div>
                                        <div style="margin-bottom: 8px;"><strong style="color: white;">Gerät:</strong> ${platform}</div>
                                        <div style="margin-bottom: 8px;"><strong style="color: white;">Browser:</strong> ${userAgent.split(' ').slice(-2).join(' ')}</div>
                                        <div><strong style="color: white;">Zeitstempel:</strong> ${currentDate}</div>
                                    </div>
                                </div>

                                <!-- Support Hours -->
                                <div style="background: #1F2937; border-radius: 12px; padding: 20px; margin-top: 20px; margin-bottom: 100px;">
                                    <div style="color: white; font-size: 16px; font-weight: 600; margin-bottom: 12px;">🕰 Support-Zeiten</div>
                                    <div style="color: #9CA3AF; font-size: 14px; line-height: 1.6;">
                                        <div>Montag - Freitag: 8:00 - 20:00 Uhr</div>
                                        <div>Samstag: 9:00 - 18:00 Uhr</div>
                                        <div>Sonntag: Notfallsupport</div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error('Error in showSupportSettings:', error);
                }
            }

            // Support functions
            function sendSupportEmail() {
                // Get system information
                const username = dispatch_ajax?.username || 'Unbekannt';
                const userAgent = navigator.userAgent;
                const platform = navigator.platform;
                const appVersion = '2.0.0';
                const currentDate = new Date().toLocaleString('de-DE');
                const currentUrl = window.location.href;

                // Create email body with pre-filled information
                const subject = encodeURIComponent('Support-Anfrage: Dispatch Dashboard');
                const body = encodeURIComponent(`
Hallo ABSA SL Support-Team,

ich benötige Hilfe mit der Dispatch Dashboard App.

--- PROBLEMBESCHREIBUNG ---
[Bitte beschreiben Sie Ihr Problem hier]



--- SYSTEM-INFORMATIONEN ---
Fahrer: ${username}
App-Version: ${appVersion}
Zeitpunkt: ${currentDate}
URL: ${currentUrl}
Gerät: ${platform}
Browser: ${userAgent}

--- SCHRITTE ZUR REPRODUKTION ---
1.
2.
3.

--- ERWARTETES VERHALTEN ---


--- TATSÄCHLICHES VERHALTEN ---


Mit freundlichen Grüßen
${username}`);

                // Open email client
                window.location.href = `mailto:support@absa-sl.com?subject=${subject}&body=${body}`;
            }

            function openWhatsAppSupport() {
                // WhatsApp number (without + and spaces)
                const phoneNumber = '34123456789'; // Replace with actual support number
                const username = dispatch_ajax?.username || 'Unbekannt';
                const message = encodeURIComponent(`Hallo, ich benötige Hilfe mit der Dispatch Dashboard App.\n\nFahrer: ${username}\nProblem: `);

                // Open WhatsApp
                window.open(`https://wa.me/${phoneNumber}?text=${message}`, '_blank');
            }

            function callSupport() {
                // Support phone number
                window.location.href = 'tel:+34123456789'; // Replace with actual support number
            }

            function showAnzeigeSettings() {
                try {
                    
                    // Update header title
                    const headerTitle = document.querySelector('.header-title');
                    if (headerTitle) {
                        headerTitle.textContent = translations[currentLanguage].display || 'Anzeige';
                    }
                    
                    // Show back arrow to Settings
                    const headerLeft = document.querySelector('.header-left');
                    if (headerLeft) {
                        headerLeft.innerHTML = `
                            <button class="back-button" onclick="showEinstellungen(); return false;" style="background: none; border: none; color: white; padding: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; position: relative; z-index: 100;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2z"/>
                                </svg>
                            </button>
                        `;
                    }
                    
                    // Hide hamburger menu
                    const hamburgerBtn = document.querySelector('.hamburger-menu');
                    if (hamburgerBtn) {
                        hamburgerBtn.style.display = 'none';
                    }
                    
                    mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        mainContent.innerHTML = `
                            <div class="anzeige-settings-container" style="background: #111827; height: 100%; overflow-y: auto; padding: 20px;">
                                
                                <!-- Screen Brightness -->
                                <div class="setting-section" style="background: #1F2937; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                                    <div style="color: white; font-size: 18px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                                        🔆 Bildschirmhelligkeit
                                    </div>
                                    
                                    <!-- Brightness Slider -->
                                    <div style="margin-bottom: 16px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                            <span style="color: #9CA3AF; font-size: 14px;">🌙 Dunkel</span>
                                            <span id="brightness-value" style="color: white; font-weight: 600; font-size: 16px;">75%</span>
                                            <span style="color: #9CA3AF; font-size: 14px;">☀️ Hell</span>
                                        </div>
                                        <div style="position: relative;">
                                            <input type="range" id="brightness-slider" min="20" max="100" value="75" 
                                                   style="width: 100%; height: 8px; background: #374151; border-radius: 4px; outline: none; appearance: none; cursor: pointer;"
                                                   oninput="updateBrightness(this.value)">
                                        </div>
                                        <div style="color: #9CA3AF; font-size: 12px; margin-top: 8px; text-align: center;">
                                            Niedrigere Werte sparen Akku, höhere Werte verbessern die Sichtbarkeit
                                        </div>
                                    </div>
                                    
                                    <!-- Auto Brightness Toggle -->
                                    <div class="setting-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-top: 1px solid #374151;">
                                        <div>
                                            <div style="color: white; font-size: 16px; font-weight: 500; margin-bottom: 4px;">
                                                Automatische Helligkeit
                                            </div>
                                            <div style="color: #9CA3AF; font-size: 13px;">
                                                Helligkeit automatisch an Umgebungslicht anpassen
                                            </div>
                                        </div>
                                        <div class="toggle-switch" data-setting="auto_brightness">
                                            <input type="checkbox" id="auto_brightness" onchange="toggleAutoBrightness(this.checked)">
                                            <label for="auto_brightness"></label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Theme Settings -->
                                <div class="setting-section" style="background: #1F2937; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                                    <div style="color: white; font-size: 18px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                                        🎨 Design
                                    </div>
                                    
                                    <!-- Dark/Light Mode Toggle -->
                                    <div class="setting-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; margin-bottom: 16px;">
                                        <div>
                                            <div style="color: white; font-size: 16px; font-weight: 500; margin-bottom: 4px;">
                                                Dunkles Design
                                            </div>
                                            <div style="color: #9CA3AF; font-size: 13px;">
                                                Reduziert Augenbelastung bei schwachem Licht
                                            </div>
                                        </div>
                                        <div class="toggle-switch" data-setting="dark_mode">
                                            <input type="checkbox" id="dark_mode" checked onchange="toggleDarkMode(this.checked)">
                                            <label for="dark_mode"></label>
                                        </div>
                                    </div>
                                    
                                    <!-- Font Size -->
                                    <div style="border-top: 1px solid #374151; padding-top: 16px;">
                                        <div style="color: white; font-size: 16px; font-weight: 500; margin-bottom: 12px;">
                                            Schriftgröße
                                        </div>
                                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                                            <button class="font-size-btn" data-size="small" onclick="setFontSize('small')" 
                                                    style="background: #374151; color: #9CA3AF; border: none; padding: 12px; border-radius: 8px; cursor: pointer; font-size: 12px;">
                                                Klein
                                            </button>
                                            <button class="font-size-btn active" data-size="medium" onclick="setFontSize('medium')" 
                                                    style="background: #10B981; color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; font-size: 14px;">
                                                Normal
                                            </button>
                                            <button class="font-size-btn" data-size="large" onclick="setFontSize('large')" 
                                                    style="background: #374151; color: #9CA3AF; border: none; padding: 12px; border-radius: 8px; cursor: pointer; font-size: 16px;">
                                                Groß
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Display Options -->
                                <div class="setting-section" style="background: #1F2937; border-radius: 12px; padding: 20px; margin-bottom: 100px;">
                                    <div style="color: white; font-size: 18px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                                        📱 Anzeige-Optionen
                                    </div>
                                    
                                    <!-- Keep Screen On -->
                                    <div class="setting-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #374151; margin-bottom: 12px;">
                                        <div>
                                            <div style="color: white; font-size: 16px; font-weight: 500; margin-bottom: 4px;">
                                                Bildschirm aktiv halten
                                            </div>
                                            <div style="color: #9CA3AF; font-size: 13px;">
                                                Verhindert automatisches Abschalten während der Fahrt
                                            </div>
                                        </div>
                                        <div class="toggle-switch" data-setting="keep_screen_on">
                                            <input type="checkbox" id="keep_screen_on" onchange="toggleKeepScreenOn(this.checked)">
                                            <label for="keep_screen_on"></label>
                                        </div>
                                    </div>
                                    
                                    <!-- Show Status Bar -->
                                    <div class="setting-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #374151; margin-bottom: 12px;">
                                        <div>
                                            <div style="color: white; font-size: 16px; font-weight: 500; margin-bottom: 4px;">
                                                Statusleiste anzeigen
                                            </div>
                                            <div style="color: #9CA3AF; font-size: 13px;">
                                                Zeigt Uhrzeit, Akku und Signalstärke
                                            </div>
                                        </div>
                                        <div class="toggle-switch" data-setting="show_status_bar">
                                            <input type="checkbox" id="show_status_bar" checked onchange="toggleStatusBar(this.checked)">
                                            <label for="show_status_bar"></label>
                                        </div>
                                    </div>
                                    
                                    <!-- Animation Speed -->
                                    <div style="padding: 12px 0;">
                                        <div style="color: white; font-size: 16px; font-weight: 500; margin-bottom: 12px;">
                                            Animationsgeschwindigkeit
                                        </div>
                                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                                            <button class="animation-speed-btn" data-speed="slow" onclick="setAnimationSpeed('slow')" 
                                                    style="background: #374151; color: #9CA3AF; border: none; padding: 8px 12px; border-radius: 8px; cursor: pointer; font-size: 12px;">
                                                Langsam
                                            </button>
                                            <button class="animation-speed-btn active" data-speed="normal" onclick="setAnimationSpeed('normal')" 
                                                    style="background: #10B981; color: white; border: none; padding: 8px 12px; border-radius: 8px; cursor: pointer; font-size: 12px;">
                                                Normal
                                            </button>
                                            <button class="animation-speed-btn" data-speed="fast" onclick="setAnimationSpeed('fast')" 
                                                    style="background: #374151; color: #9CA3AF; border: none; padding: 8px 12px; border-radius: 8px; cursor: pointer; font-size: 12px;">
                                                Schnell
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                        `;
                        
                        // Load current display settings
                        loadDisplaySettings();
                    }
                } catch (error) {
                    console.error('Error in showAnzeigeSettings:', error);
                }
            }
            
            function showPushSettings() {
                try {
                    // Update header title
                    const headerTitle = document.querySelector('.header-title');
                    if (headerTitle) {
                        headerTitle.textContent = translations[currentLanguage].pushNotifications || 'Push-Benachrichtigungen';
                    }

                    // Show back arrow to Settings
                    const headerLeft = document.querySelector('.header-left');
                    if (headerLeft) {
                        headerLeft.innerHTML = `
                            <button class="back-button" onclick="showEinstellungen(); return false;" style="background: none; border: none; color: white; padding: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; position: relative; z-index: 100;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2z"/>
                                </svg>
                            </button>
                        `;
                    }

                    // Hide hamburger menu
                    const hamburgerBtn = document.querySelector('.hamburger-menu');
                    if (hamburgerBtn) {
                        hamburgerBtn.style.display = 'none';
                    }

                    // Get main content
                    const mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        // Check current permission status
                        const notificationStatus = 'Notification' in window ? Notification.permission : 'unsupported';
                        const isSubscribed = localStorage.getItem('push_subscription_saved') === 'true';

                        mainContent.innerHTML = `
                            <div class="push-settings-container" style="background: #111827; height: 100%; overflow-y: auto; padding: 20px;">

                                <!-- Push Notification Status -->
                                <div class="setting-section" style="background: #1F2937; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                                    <div style="color: white; font-size: 18px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                                        🔔 Push-Benachrichtigungen
                                    </div>

                                    <div style="margin-bottom: 20px;">
                                        <div style="color: #9CA3AF; font-size: 14px; margin-bottom: 8px;">Status:</div>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="width: 10px; height: 10px; border-radius: 50%; background: ${
                                                notificationStatus === 'granted' ? '#10B981' :
                                                notificationStatus === 'denied' ? '#EF4444' : '#F59E0B'
                                            };"></div>
                                            <span style="color: white; font-size: 16px;">
                                                ${
                                                    notificationStatus === 'granted' ? 'Aktiviert' :
                                                    notificationStatus === 'denied' ? 'Blockiert' :
                                                    notificationStatus === 'unsupported' ? 'Nicht unterstützt' : 'Nicht aktiviert'
                                                }
                                            </span>
                                        </div>
                                        ${
                                            notificationStatus === 'denied' ?
                                            '<div style="color: #EF4444; font-size: 12px; margin-top: 8px;">⚠️ Benachrichtigungen wurden in den Browser-Einstellungen blockiert</div>' :
                                            notificationStatus === 'unsupported' ?
                                            '<div style="color: #F59E0B; font-size: 12px; margin-top: 8px;">⚠️ Ihr Browser unterstützt keine Push-Benachrichtigungen</div>' :
                                            ''
                                        }
                                    </div>

                                    <!-- Enable/Disable Button -->
                                    <div style="margin-bottom: 20px;">
                                        ${
                                            notificationStatus === 'default' ?
                                            `<button onclick="handlePushPermissionRequest()" style="width: 100%; background: #10B981; color: white; border: none; padding: 12px 20px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer;">
                                                🔔 Benachrichtigungen aktivieren
                                            </button>` :
                                            notificationStatus === 'granted' ?
                                            `<button onclick="togglePushSubscription()" style="width: 100%; background: ${isSubscribed ? '#EF4444' : '#10B981'}; color: white; border: none; padding: 12px 20px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer;">
                                                ${isSubscribed ? '🔕 Benachrichtigungen deaktivieren' : '🔔 Benachrichtigungen aktivieren'}
                                            </button>` :
                                            notificationStatus === 'denied' ?
                                            `<div style="background: #374151; padding: 12px 20px; border-radius: 8px; text-align: center;">
                                                <div style="color: #9CA3AF; font-size: 14px;">Bitte erlauben Sie Benachrichtigungen in Ihren Browser-Einstellungen</div>
                                            </div>` :
                                            `<div style="background: #374151; padding: 12px 20px; border-radius: 8px; text-align: center;">
                                                <div style="color: #9CA3AF; font-size: 14px;">Push-Benachrichtigungen sind nicht verfügbar</div>
                                            </div>`
                                        }
                                    </div>

                                    <!-- Notification Types -->
                                    <div style="border-top: 1px solid #374151; padding-top: 20px;">
                                        <div style="color: white; font-size: 16px; font-weight: 600; margin-bottom: 16px;">Benachrichtigungstypen</div>

                                        <div style="margin-bottom: 12px;">
                                            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                                                <input type="checkbox" id="notify-new-orders" checked style="width: 20px; height: 20px; cursor: pointer;">
                                                <span style="color: white; font-size: 14px;">📦 Neue Bestellungen</span>
                                            </label>
                                        </div>

                                        <div style="margin-bottom: 12px;">
                                            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                                                <input type="checkbox" id="notify-removed-orders" checked style="width: 20px; height: 20px; cursor: pointer;">
                                                <span style="color: white; font-size: 14px;">❌ Entfernte Bestellungen</span>
                                            </label>
                                        </div>

                                        <div style="margin-bottom: 12px;">
                                            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                                                <input type="checkbox" id="notify-scheduled-orders" checked style="width: 20px; height: 20px; cursor: pointer;">
                                                <span style="color: white; font-size: 14px;">📅 Geplante Aufträge</span>
                                            </label>
                                        </div>

                                        <div style="margin-bottom: 12px;">
                                            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                                                <input type="checkbox" id="notify-sound" checked style="width: 20px; height: 20px; cursor: pointer;">
                                                <span style="color: white; font-size: 14px;">🔊 Benachrichtigungston</span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Test Notification -->
                                    ${notificationStatus === 'granted' ? `
                                    <div style="border-top: 1px solid #374151; padding-top: 20px;">
                                        <button onclick="sendTestNotification()" style="width: 100%; background: #374151; color: white; border: none; padding: 12px 20px; border-radius: 8px; font-size: 14px; cursor: pointer;">
                                            💬 Test-Benachrichtigung senden
                                        </button>
                                    </div>
                                    ` : ''}
                                </div>

                                <!-- Info Section -->
                                <div class="setting-section" style="background: #1F2937; border-radius: 12px; padding: 20px;">
                                    <div style="color: white; font-size: 16px; font-weight: 600; margin-bottom: 12px;">ℹ️ Information</div>
                                    <div style="color: #9CA3AF; font-size: 14px; line-height: 1.6;">
                                        Push-Benachrichtigungen informieren Sie über neue Bestellungen, auch wenn die App im Hintergrund läuft oder geschlossen ist.
                                        <br><br>
                                        <strong style="color: white;">Hinweise:</strong>
                                        <ul style="margin-top: 8px; padding-left: 20px;">
                                            <li>Die App muss zum Homescreen hinzugefügt sein (iOS)</li>
                                            <li>Benachrichtigungen müssen im Browser erlaubt sein</li>
                                            <li>Eine Internetverbindung ist erforderlich</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        `;

                        // Save notification preferences when changed
                        const checkboxes = mainContent.querySelectorAll('input[type="checkbox"]');
                        checkboxes.forEach(checkbox => {
                            checkbox.addEventListener('change', function() {
                                localStorage.setItem(this.id, this.checked);
                            });
                            // Load saved state
                            const savedState = localStorage.getItem(checkbox.id);
                            if (savedState !== null) {
                                checkbox.checked = savedState === 'true';
                            }
                        });
                    }
                } catch (error) {
                    console.error('Error in showPushSettings:', error);
                }
            }

            // Function to toggle push subscription
            async function togglePushSubscription() {
                const isSubscribed = localStorage.getItem('push_subscription_saved') === 'true';
                if (isSubscribed) {
                    // Unsubscribe
                    localStorage.removeItem('push_subscription_saved');
                    showPushSettings(); // Refresh the view
                    showNotificationToast('Push-Benachrichtigungen deaktiviert', 'info');
                } else {
                    // Subscribe
                    if ('serviceWorker' in navigator) {
                        try {
                            const registration = await navigator.serviceWorker.ready;
                            requestPushPermission(registration);
                        } catch (error) {
                            console.error('Service Worker not available:', error);
                            showNotificationToast('❌ Service Worker nicht verfügbar', 'error');
                        }
                    } else {
                        showNotificationToast('❌ Service Worker wird nicht unterstützt', 'error');
                    }
                }
            }

            // Function to handle push permission request from settings
            async function handlePushPermissionRequest() {
                if ('serviceWorker' in navigator) {
                    try {
                        const registration = await navigator.serviceWorker.ready;
                        requestPushPermission(registration);
                    } catch (error) {
                        console.error('Service Worker not available:', error);
                        showNotificationToast('❌ Service Worker nicht verfügbar', 'error');
                    }
                } else {
                    showNotificationToast('❌ Service Worker wird nicht unterstützt', 'error');
                }
            }

            // Function to send test notification
            function sendTestNotification() {
                try {
                    if ('Notification' in window && Notification.permission === 'granted') {
                        new Notification('🔔 Test-Benachrichtigung', {
                            body: 'Dies ist eine Test-Benachrichtigung von der Dispatch App',
                            icon: '/wp-content/plugins/dispatch-dashboard/pwa/icons/icon-192x192.png',
                            badge: '/wp-content/plugins/dispatch-dashboard/pwa/icons/icon-72x72.png',
                            vibrate: [200, 100, 200],
                            tag: 'test-notification'
                        });

                        // Play notification sound if enabled
                        const soundEnabled = localStorage.getItem('notify-sound') !== 'false';
                        if (soundEnabled && window.notificationSound && window.notificationSound.play) {
                            window.notificationSound.play();
                        }
                    }
                } catch (error) {
                    console.error('Error sending test notification:', error);
                }
            }

            function updateBrightness(value) {
                try {

                    // Update display value
                    const brightnessValue = document.getElementById('brightness-value');
                    if (brightnessValue) {
                        brightnessValue.textContent = value + '%';
                    }
                    
                    // Apply brightness to screen (if supported by browser)
                    if (document.body.style.filter !== undefined) {
                        const brightnessDecimal = value / 100;
                        document.body.style.filter = `brightness(${brightnessDecimal})`;
                    }
                    
                    // Save setting
                    saveDisplaySetting('brightness', value);
                    
                    // Disable auto brightness when manually adjusted
                    const autoBrightnessToggle = document.getElementById('auto_brightness');
                    if (autoBrightnessToggle && autoBrightnessToggle.checked) {
                        autoBrightnessToggle.checked = false;
                        saveDisplaySetting('auto_brightness', false);
                    }
                    
                } catch (error) {
                    console.error('Error updating brightness:', error);
                }
            }
            
            function toggleAutoBrightness(enabled) {
                try {
                    saveDisplaySetting('auto_brightness', enabled);
                    
                    const slider = document.getElementById('brightness-slider');
                    if (enabled) {
                        // Set to automatic brightness (75% default)
                        if (slider) {
                            slider.value = 75;
                            updateBrightness(75);
                        }
                        // In a real app, this would use device light sensor
                        alert('Automatische Helligkeit aktiviert. Verwendet Gerätesensor (Simulation: 75%)');
                    } else {
                        alert('Automatische Helligkeit deaktiviert. Manuelle Einstellung aktiv.');
                    }
                } catch (error) {
                    console.error('Error toggling auto brightness:', error);
                }
            }
            
            function toggleDarkMode(enabled) {
                try {
                    saveDisplaySetting('dark_mode', enabled);

                    // Apply dark/light mode
                    if (enabled) {
                        document.body.classList.add('dark-mode');
                        document.body.classList.remove('light-mode');
                        // Dark mode colors
                        document.documentElement.style.setProperty('--bg-primary', '#111827');
                        document.documentElement.style.setProperty('--bg-secondary', '#1F2937');
                        document.documentElement.style.setProperty('--text-primary', '#F9FAFB');
                        document.documentElement.style.setProperty('--text-secondary', '#9CA3AF');
                    } else {
                        document.body.classList.add('light-mode');
                        document.body.classList.remove('dark-mode');
                        // Light mode colors
                        document.documentElement.style.setProperty('--bg-primary', '#FFFFFF');
                        document.documentElement.style.setProperty('--bg-secondary', '#F3F4F6');
                        document.documentElement.style.setProperty('--text-primary', '#111827');
                        document.documentElement.style.setProperty('--text-secondary', '#6B7280');
                    }

                    // No alert needed - saveDisplaySetting already shows toast
                } catch (error) {
                    console.error('Error toggling dark mode:', error);
                }
            }
            
            function setFontSize(size) {
                try {
                    
                    // Update button states
                    document.querySelectorAll('.font-size-btn').forEach(btn => {
                        if (btn.dataset.size === size) {
                            btn.classList.add('active');
                            btn.style.background = '#10B981';
                            btn.style.color = 'white';
                        } else {
                            btn.classList.remove('active');
                            btn.style.background = '#374151';
                            btn.style.color = '#9CA3AF';
                        }
                    });
                    
                    // Apply font size
                    const fontSizes = { small: '12px', medium: '14px', large: '16px' };
                    if (document.body.style.fontSize !== undefined) {
                        document.body.style.fontSize = fontSizes[size];
                    }
                    
                    saveDisplaySetting('font_size', size);
                    
                } catch (error) {
                    console.error('Error setting font size:', error);
                }
            }
            
            function setAnimationSpeed(speed) {
                try {
                    
                    // Update button states
                    document.querySelectorAll('.animation-speed-btn').forEach(btn => {
                        if (btn.dataset.speed === speed) {
                            btn.classList.add('active');
                            btn.style.background = '#10B981';
                            btn.style.color = 'white';
                        } else {
                            btn.classList.remove('active');
                            btn.style.background = '#374151';
                            btn.style.color = '#9CA3AF';
                        }
                    });
                    
                    // Apply animation speed via CSS variables or classes
                    const speeds = { slow: '0.8s', normal: '0.3s', fast: '0.1s' };
                    document.documentElement.style.setProperty('--animation-duration', speeds[speed]);
                    
                    saveDisplaySetting('animation_speed', speed);
                    
                } catch (error) {
                    console.error('Error setting animation speed:', error);
                }
            }
            
            function toggleKeepScreenOn(enabled) {
                try {
                    saveDisplaySetting('keep_screen_on', enabled);
                    
                    // In a real PWA, this would use Screen Wake Lock API
                    if (enabled && 'wakeLock' in navigator) {
                        navigator.wakeLock.request('screen').then(() => {
                        }).catch(err => {
                            console.error('Screen wake lock failed:', err);
                        });
                    }
                    
                    alert(enabled ? 'Bildschirm bleibt aktiv' : 'Normale Energieverwaltung');
                } catch (error) {
                    console.error('Error toggling keep screen on:', error);
                }
            }
            
            function toggleStatusBar(enabled) {
                try {
                    saveDisplaySetting('show_status_bar', enabled);
                    
                    // In a real app, this would control status bar visibility
                    alert(enabled ? 'Statusleiste wird angezeigt' : 'Statusleiste wird ausgeblendet');
                } catch (error) {
                    console.error('Error toggling status bar:', error);
                }
            }
            
            function loadDisplaySettings() {
                try {
                    const settings = JSON.parse(localStorage.getItem('display_settings') || '{}');
                    
                    // Load brightness
                    const brightness = settings.brightness || 75;
                    const brightnessSlider = document.getElementById('brightness-slider');
                    if (brightnessSlider) {
                        brightnessSlider.value = brightness;
                        updateBrightness(brightness);
                    }
                    
                    // Load toggles
                    const toggles = ['auto_brightness', 'dark_mode', 'keep_screen_on', 'show_status_bar'];
                    toggles.forEach(toggleId => {
                        const toggle = document.getElementById(toggleId);
                        if (toggle) {
                            const value = settings[toggleId];
                            if (value !== undefined) {
                                toggle.checked = value;
                            }
                        }
                    });
                    
                    // Load font size
                    const fontSize = settings.font_size || 'medium';
                    setFontSize(fontSize);
                    
                    // Load animation speed
                    const animationSpeed = settings.animation_speed || 'normal';
                    setAnimationSpeed(animationSpeed);
                    
                } catch (error) {
                    console.error('Error loading display settings:', error);
                }
            }
            
            function saveDisplaySetting(key, value) {
                try {
                    const settings = JSON.parse(localStorage.getItem('display_settings') || '{}');
                    settings[key] = value;
                    localStorage.setItem('display_settings', JSON.stringify(settings));

                    // Settings are saved locally only (no server sync needed)
                    console.log(`Display setting saved: ${key} = ${value}`);

                    // Show success feedback
                    showNotificationToast('✓ Einstellung gespeichert', 'success');

                } catch (error) {
                    console.error('Error saving display setting:', error);
                }
            }
            
            function showHilfeUndFeedback() {
                try {
                    // Update header title
                    const headerTitle = document.querySelector('.header-title');
                    if (headerTitle) {
                        headerTitle.textContent = translations[currentLanguage].helpAndFeedback || 'Hilfe und Feedback';
                    }

                    // Show back arrow to Settings
                    const headerLeft = document.querySelector('.header-left');
                    if (headerLeft) {
                        headerLeft.innerHTML = `
                            <button class="back-button" onclick="showEinstellungen(); return false;" style="background: none; border: none; color: white; padding: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; position: relative; z-index: 100;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2z"/>
                                </svg>
                            </button>
                        `;
                    }

                    // Hide hamburger menu
                    const hamburgerBtn = document.querySelector('.hamburger-menu');
                    if (hamburgerBtn) {
                        hamburgerBtn.style.display = 'none';
                    }

                    // Reset main-content to normal width
                    mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        mainContent.className = 'main-content';
                        mainContent.innerHTML = `
                            <div class="help-feedback-container" style="background: #111827; height: 100%; overflow-y: auto; padding: 20px;">
                                <div class="info-message" style="background: linear-gradient(135deg, #3B82F6, #2563EB); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);">
                                    <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">
                                        💬 Hilfe und Feedback
                                    </div>
                                    <div style="font-size: 14px; opacity: 0.9;">
                                        Bei Fragen oder Feedback kontaktieren Sie uns gerne
                                    </div>
                                </div>

                                <div style="background: #1F2937; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                                    <h3 style="color: white; margin: 0 0 16px 0; font-size: 18px;">Support Kontakt</h3>
                                    <div style="color: #9CA3AF; line-height: 1.8;">
                                        <p style="margin-bottom: 12px;">📧 Email: support@dispatch-dashboard.com</p>
                                        <p style="margin-bottom: 12px;">📞 Telefon: +49 XXX XXXXXX</p>
                                        <p style="margin-bottom: 0;">⏰ Erreichbar: Mo-Fr 9:00 - 18:00 Uhr</p>
                                    </div>
                                </div>

                                <div style="background: #1F2937; border-radius: 12px; padding: 20px;">
                                    <h3 style="color: white; margin: 0 0 16px 0; font-size: 18px;">FAQ</h3>
                                    <div style="color: #9CA3AF;">
                                        <p style="margin-bottom: 8px;">• Wie kann ich meine Lieferpräferenzen ändern?</p>
                                        <p style="margin-bottom: 8px;">• Was bedeuten die verschiedenen Status?</p>
                                        <p style="margin-bottom: 8px;">• Wie funktionieren Push-Benachrichtigungen?</p>
                                        <p style="margin-bottom: 0;">• Wie kann ich mein Profil bearbeiten?</p>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error('Error in showHilfeUndFeedback:', error);
                }
            }
            
            function showDatenschutz() {
                try {
                    // Update header title
                    const headerTitle = document.querySelector('.header-title');
                    if (headerTitle) {
                        headerTitle.textContent = translations[currentLanguage].privacy || 'Datenschutz';
                    }

                    // Show back arrow to Settings
                    const headerLeft = document.querySelector('.header-left');
                    if (headerLeft) {
                        headerLeft.innerHTML = `
                            <button class="back-button" onclick="showEinstellungen(); return false;" style="background: none; border: none; color: white; padding: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; position: relative; z-index: 100;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2z"/>
                                </svg>
                            </button>
                        `;
                    }

                    // Hide hamburger menu
                    const hamburgerBtn = document.querySelector('.hamburger-menu');
                    if (hamburgerBtn) {
                        hamburgerBtn.style.display = 'none';
                    }

                    // Reset main-content to normal width
                    mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        mainContent.className = 'main-content';
                        mainContent.innerHTML = `
                            <div class="privacy-container" style="background: #111827; height: 100%; overflow-y: auto; padding: 20px;">
                                <div class="info-message" style="background: linear-gradient(135deg, #3B82F6, #2563EB); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);">
                                    <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">
                                        🔒 Datenschutz
                                    </div>
                                    <div style="font-size: 14px; opacity: 0.9;">
                                        Ihre Daten sind bei uns sicher
                                    </div>
                                </div>

                                <div style="background: #1F2937; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                                    <h3 style="color: white; margin: 0 0 16px 0; font-size: 18px;">Datenschutzrichtlinie</h3>
                                    <div style="color: #9CA3AF; line-height: 1.8;">
                                        <p style="margin-bottom: 12px;">Wir nehmen den Schutz Ihrer persönlichen Daten sehr ernst. Diese Datenschutzrichtlinie erklärt, welche Daten wir sammeln und wie wir sie verwenden.</p>
                                        <p style="margin-bottom: 12px;">Ihre Standortdaten werden nur während aktiver Lieferungen verwendet und nicht länger als nötig gespeichert.</p>
                                        <p style="margin-bottom: 0;">Für weitere Details besuchen Sie bitte unsere vollständige Datenschutzerklärung auf unserer Website.</p>
                                    </div>
                                </div>

                                <div style="background: #1F2937; border-radius: 12px; padding: 20px;">
                                    <h3 style="color: white; margin: 0 0 16px 0; font-size: 18px;">Ihre Rechte</h3>
                                    <div style="color: #9CA3AF;">
                                        <p style="margin-bottom: 8px;">• Recht auf Auskunft</p>
                                        <p style="margin-bottom: 8px;">• Recht auf Berichtigung</p>
                                        <p style="margin-bottom: 8px;">• Recht auf Löschung</p>
                                        <p style="margin-bottom: 0;">• Recht auf Datenübertragbarkeit</p>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error('Error in showDatenschutz:', error);
                }
            }
            
            function showUeber() {
                try {
                    // Update header title
                    const headerTitle = document.querySelector('.header-title');
                    if (headerTitle) {
                        headerTitle.textContent = translations[currentLanguage].about || 'Über';
                    }

                    // Replace hamburger menu with back arrow
                    showBackArrowInHamburger(function() { showEinstellungen(); });

                    // Reset main-content to normal width
                    mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        mainContent.className = 'main-content';
                        mainContent.innerHTML = `
                            <div class="about-container" style="background: #111827; height: 100%; overflow-y: auto; padding: 20px;">
                                <div class="info-message" style="background: linear-gradient(135deg, #3B82F6, #2563EB); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);">
                                    <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">
                                        ℹ️ Über Dispatch Dashboard
                                    </div>
                                    <div style="font-size: 14px; opacity: 0.9;">
                                        Die moderne Lösung für Ihre Lieferverwaltung
                                    </div>
                                </div>

                                <div style="background: #1F2937; border-radius: 12px; padding: 20px; margin-bottom: 20px; text-align: center;">
                                    <div style="margin-bottom: 20px;">
                                        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: 20px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center;">
                                            <svg width="40" height="40" viewBox="0 0 24 24" fill="white">
                                                <path d="M18 18.5c.83 0 1.5-.67 1.5-1.5s-.67-1.5-1.5-1.5-1.5.67-1.5 1.5.67 1.5 1.5 1.5zM19.5 9.5h-1.84l-.6-1.34-.66-1.42-.02-.06c-.19-.37-.58-.62-1.01-.62H13c-.43 0-.82.25-1.01.62l-.02.06-.66 1.42-.6 1.34H7.5c-.28 0-.5.22-.5.5s.22.5.5.5h2.08l1.42 3.15V17c0 .28.22.5.5.5s.5-.22.5-.5v-3.51c0-.1-.03-.19-.09-.27l-1.24-2.75.59-1.29L11.62 10h4.76l.36-.82.59 1.29-1.24 2.75c-.06.08-.09.17-.09.27V17c0 .28.22.5.5.5s.5-.22.5-.5v-3.35l1.42-3.15h2.08c.28 0 .5-.22.5-.5s-.22-.5-.5-.5zM12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/>
                                            </svg>
                                        </div>
                                    </div>
                                    <h2 style="color: white; font-size: 24px; margin: 0 0 8px 0;">Dispatch Dashboard</h2>
                                    <p style="color: #3B82F6; font-size: 18px; margin: 0 0 4px 0;">Version 2.0.3</p>
                                    <p style="color: #9CA3AF; font-size: 14px;">für WooCommerce</p>
                                </div>

                                <div style="background: #1F2937; border-radius: 12px; padding: 20px;">
                                    <h3 style="color: white; margin: 0 0 16px 0; font-size: 18px;">Features</h3>
                                    <div style="color: #9CA3AF; line-height: 1.8;">
                                        <p style="margin-bottom: 8px;">✓ Echtzeit-Tracking</p>
                                        <p style="margin-bottom: 8px;">✓ Push-Benachrichtigungen</p>
                                        <p style="margin-bottom: 8px;">✓ Offline-Funktionalität</p>
                                        <p style="margin-bottom: 8px;">✓ Routenoptimierung</p>
                                        <p style="margin-bottom: 0;">✓ Detaillierte Statistiken</p>
                                    </div>
                                </div>

                                <div style="text-align: center; margin-top: 20px; color: #6B7280; font-size: 12px;">
                                    © 2024 Dispatch Dashboard. Alle Rechte vorbehalten.
                                </div>
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error('Error in showUeber:', error);
                }
            }
            
            function showSprache() {
                try {
                    // Get current language
                    const currentLang = localStorage.getItem('app_language') || 'de';

                    // Update header title with translation
                    const headerTitle = document.querySelector('.header-title');
                    if (headerTitle) {
                        headerTitle.textContent = translations[currentLanguage].language || 'Sprache';
                    }

                    // Replace hamburger menu with back arrow
                    showBackArrowInHamburger(function() { showEinstellungen(); });

                    mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        mainContent.className = 'main-content';
                        mainContent.innerHTML = `
                            <div style="background: #1e293b; height: 100%; overflow-y: auto;">
                                <!-- Deutsch -->
                                <a href="#" onclick="setLanguage('de'); return false;" class="menu-link ${currentLang === 'de' ? 'active-lang' : ''}">
                                    <span style="font-size: 24px;">🇩🇪</span>
                                    <span style="flex: 1;">Deutsch</span>
                                    ${currentLang === 'de' ? '<span style="color: #10b981;">✓</span>' : ''}
                                </a>

                                <!-- Englisch -->
                                <a href="#" onclick="setLanguage('en'); return false;" class="menu-link ${currentLang === 'en' ? 'active-lang' : ''}">
                                    <span style="font-size: 24px;">🇬🇧</span>
                                    <span style="flex: 1;">English</span>
                                    ${currentLang === 'en' ? '<span style="color: #10b981;">✓</span>' : ''}
                                </a>

                                <!-- Spanisch -->
                                <a href="#" onclick="setLanguage('es'); return false;" class="menu-link ${currentLang === 'es' ? 'active-lang' : ''}">
                                    <span style="font-size: 24px;">🇪🇸</span>
                                    <span style="flex: 1;">Español</span>
                                    ${currentLang === 'es' ? '<span style="color: #10b981;">✓</span>' : ''}
                                </a>

                                <style>
                                    .menu-link.active-lang {
                                        border-left-color: #10b981 !important;
                                        background: rgba(16, 185, 129, 0.1);
                                    }
                                </style>
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error('Error in showSprache:', error);
                }
            }

            function setLanguage(lang) {
                localStorage.setItem('app_language', lang);
                currentLanguage = lang; // Update global language variable
                if (typeof showNotificationToast === 'function') {
                    const langNames = {
                        'de': 'Deutsch',
                        'en': 'English',
                        'es': 'Español'
                    };
                    showNotificationToast(translations[lang].languageChanged + ': ' + langNames[lang], 'success');
                }
                // Apply translations immediately
                applyTranslations();
                // Reload language page to show updated language
                setTimeout(() => showSprache(), 100);
            }
            
            function goOffline() {
                try {
                    // Close menu immediately and keep it closed
                    const menu = document.querySelector('.side-menu');
                    const overlay = document.querySelector('.side-menu-overlay');
                    if (menu && overlay) {
                        menu.classList.remove('open');
                        overlay.classList.remove('open');
                    }
                    
                    // Confirm before going offline
                    if (confirm('Möchten Sie wirklich offline gehen?')) {
                        // Call the offline function directly without needing the button
                        if (isOnline) {
                            goOfflineDirectly();
                        }
                    }
                } catch (error) {
                    console.error('Error in goOffline:', error);
                }
            }
            
            // Store last known order count
            let lastKnownOrderCount = 0;
            window.notificationSound = null;
            
            // Listen for messages from Service Worker
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', event => {
                    // Handle PUSH_NOTIFICATION (Web Push VAPID in foreground)
                    if (event.data && event.data.type === 'PUSH_NOTIFICATION') {
                        const notification = event.data.notification || {};
                        const data = event.data.data || {};

                        // Show in-app toast banner
                        if (typeof showNotificationToast === 'function') {
                            const toastMessage = notification.body || 'Neue Bestellung zugewiesen!';
                            showNotificationToast('🚚 ' + toastMessage, 'success', 5000);
                        }

                        // Play notification sound
                        if (window.notificationSound) {
                            if (!window.notificationSound.initialized) {
                                window.notificationSound.init();
                            }
                            window.notificationSound.play();
                        }

                        // Refresh orders list
                        if (typeof loadDriverOrders === 'function') {
                            loadDriverOrders();
                        }

                        return;
                    }

                    // Handle different message types from Service Worker
                    if (event.data && (event.data.type === 'REFRESH_ORDERS' ||
                        event.data.type === 'reload_app' ||
                        event.data.action === 'reload_app')) {

                        // Refresh the packliste
                        if (typeof loadPackliste === 'function') {
                            loadPackliste();
                        }

                        // Refresh orders list
                        if (typeof loadDriverOrders === 'function') {
                            loadDriverOrders();
                        } else if (typeof showBestellungen === 'function') {
                            showBestellungen();
                        }

                        // If sequence was updated, specifically reload the current view
                        if (event.data.reason === 'sequence_updated' ||
                            event.data.reason === 'orders_updated') {

                            // Force reload current data
                            const currentHash = window.location.hash;

                            if (currentHash === '#packliste' || !currentHash) {
                                if (typeof loadPackliste === 'function') {
                                    console.log('Force reloading Packliste due to sequence update');
                                    loadPackliste();
                                }
                            } else if (currentHash === '#bestellungen') {
                                if (typeof showBestellungen === 'function') {
                                    console.log('Force reloading Bestellungen due to update');
                                    showBestellungen();
                                }
                            }
                        }
                    }
                });
            }

            // Initialize notification sound
            function initializeNotificationSound() {
                try {
                    // Create notification sound object
                    window.notificationSound = {
                        audioContext: null,
                        initialized: false,
                        unlocked: false, // iOS requires playing a sound on user interaction to unlock

                        init: function() {
                            if (this.initialized) return;

                            try {
                                this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                                this.initialized = true;
                            } catch (err) {
                                console.error('Audio initialization failed:', err);
                            }
                        },

                        play: function() {
                            // Initialize on first play attempt
                            if (!this.initialized) {
                                this.init();
                            }

                            if (!this.audioContext) {
                                return Promise.resolve();
                            }

                            // On iOS, if not unlocked yet, we can't play sound
                            if (!this.unlocked && this.audioContext.state === 'suspended') {
                                return Promise.resolve();
                            }

                            return this.playBeeps();
                        },

                        playBeeps: function() {
                            try {
                                // Create a more pleasant notification sound (3 beeps)
                                const playBeep = (frequency, startTime) => {
                                    const oscillator = this.audioContext.createOscillator();
                                    const gainNode = this.audioContext.createGain();

                                    oscillator.connect(gainNode);
                                    gainNode.connect(this.audioContext.destination);

                                    oscillator.frequency.setValueAtTime(frequency, startTime);
                                    oscillator.type = 'sine';

                                    // Envelope
                                    gainNode.gain.setValueAtTime(0, startTime);
                                    gainNode.gain.linearRampToValueAtTime(0.3, startTime + 0.01);
                                    gainNode.gain.exponentialRampToValueAtTime(0.01, startTime + 0.15);

                                    oscillator.start(startTime);
                                    oscillator.stop(startTime + 0.15);
                                };

                                const now = this.audioContext.currentTime;

                                // Play 3 quick beeps
                                playBeep(800, now);
                                playBeep(800, now + 0.2);
                                playBeep(1000, now + 0.4);

                                return Promise.resolve();

                            } catch (error) {
                                console.error('Error playing notification sound:', error);
                                return Promise.reject(error);
                            }
                        },

                        // Play sound for removed orders (descending tone)
                        playRemovedSound: function() {
                            // Initialize if needed
                            if (!this.initialized) {
                                this.init();
                            }

                            if (!this.audioContext) {
                                return Promise.resolve();
                            }

                            // On iOS, if not unlocked yet, we can't play sound
                            if (!this.unlocked && this.audioContext.state === 'suspended') {
                                return Promise.resolve();
                            }

                            return this.playRemovedBeeps();
                        },

                        playRemovedBeeps: function() {
                            try {
                                const now = this.audioContext.currentTime;

                                // Helper function to play single beep
                                const playBeep = (frequency, startTime) => {
                                    const oscillator = this.audioContext.createOscillator();
                                    const gainNode = this.audioContext.createGain();

                                    oscillator.connect(gainNode);
                                    gainNode.connect(this.audioContext.destination);

                                    oscillator.frequency.value = frequency;
                                    oscillator.type = 'sine';

                                    // Quick fade in/out
                                    gainNode.gain.setValueAtTime(0, startTime);
                                    gainNode.gain.linearRampToValueAtTime(0.3, startTime + 0.01);
                                    gainNode.gain.linearRampToValueAtTime(0, startTime + 0.15);

                                    oscillator.start(startTime);
                                    oscillator.stop(startTime + 0.15);
                                };

                                // Play 2 descending beeps
                                playBeep(800, now);
                                playBeep(600, now + 0.2);

                                return Promise.resolve();

                            } catch (error) {
                                console.error('Error playing removed sound:', error);
                                return Promise.reject(error);
                            }
                        },

                        // Play sound for scheduled orders (different pattern)
                        playScheduledSound: function() {
                            // Initialize if needed
                            if (!this.initialized) {
                                this.init();
                            }

                            if (!this.audioContext) {
                                return Promise.resolve();
                            }

                            // On iOS, if not unlocked yet, we can't play sound
                            if (!this.unlocked && this.audioContext.state === 'suspended') {
                                return Promise.resolve();
                            }

                            return this.playScheduledBeeps();
                        },

                        playScheduledBeeps: function() {
                            try {
                                const now = this.audioContext.currentTime;

                                // Helper function to play single beep
                                const playBeep = (frequency, startTime, duration = 0.15) => {
                                    const oscillator = this.audioContext.createOscillator();
                                    const gainNode = this.audioContext.createGain();

                                    oscillator.connect(gainNode);
                                    gainNode.connect(this.audioContext.destination);

                                    oscillator.frequency.value = frequency;
                                    oscillator.type = 'sine';

                                    // Quick fade in/out
                                    gainNode.gain.setValueAtTime(0, startTime);
                                    gainNode.gain.linearRampToValueAtTime(0.25, startTime + 0.01);
                                    gainNode.gain.linearRampToValueAtTime(0, startTime + duration);

                                    oscillator.start(startTime);
                                    oscillator.stop(startTime + duration);
                                };

                                // Play a distinctive pattern for scheduled orders (two long beeps)
                                playBeep(600, now, 0.3);
                                playBeep(700, now + 0.4, 0.3);

                                return Promise.resolve();

                            } catch (error) {
                                console.error('Error playing scheduled sound:', error);
                                return Promise.reject(error);
                            }
                        }
                    };

                    // Initialize audio on first user interaction (required by browsers, especially iOS)
                    const initAudioOnInteraction = function() {
                        if (!window.notificationSound) {
                            return;
                        }

                        // Initialize if not initialized yet
                        if (!window.notificationSound.initialized) {
                            window.notificationSound.init();
                        }

                        // IMPORTANT: Unlock AudioContext if not unlocked yet
                        // On iOS, we must PLAY a silent sound to unlock - resume() doesn't work!
                        if (!window.notificationSound.unlocked && window.notificationSound.audioContext) {
                            try {
                                const oscillator = window.notificationSound.audioContext.createOscillator();
                                const gainNode = window.notificationSound.audioContext.createGain();

                                gainNode.gain.value = 0.001; // Almost silent (0 might not work on some browsers)
                                oscillator.connect(gainNode);
                                gainNode.connect(window.notificationSound.audioContext.destination);

                                const now = window.notificationSound.audioContext.currentTime;
                                oscillator.start(now);
                                oscillator.stop(now + 0.01);

                                window.notificationSound.unlocked = true;
                            } catch (err) {
                                console.error('Failed to unlock AudioContext:', err);
                            }
                        }
                    };

                    // Listen for various user interactions (iOS needs touchstart!)
                    // Don't use { once: true } - we need to keep listening until unlock succeeds!
                    const clickHandler = function(e) {
                        initAudioOnInteraction();
                        // Remove listeners after successful unlock
                        if (window.notificationSound && window.notificationSound.unlocked) {
                            document.removeEventListener('click', clickHandler);
                            document.removeEventListener('keydown', keydownHandler);
                            document.removeEventListener('touchstart', touchstartHandler);
                            document.removeEventListener('touchend', touchendHandler);
                        }
                    };
                    const keydownHandler = clickHandler;
                    const touchstartHandler = clickHandler;
                    const touchendHandler = clickHandler;

                    document.addEventListener('click', clickHandler);
                    document.addEventListener('keydown', keydownHandler);
                    document.addEventListener('touchstart', touchstartHandler);
                    document.addEventListener('touchend', touchendHandler);

                    // Also try to initialize on page load (may fail due to autoplay policy)
                    document.addEventListener('DOMContentLoaded', function() {
                        setTimeout(() => {
                            if (window.notificationSound && !window.notificationSound.initialized) {
                                window.notificationSound.init();
                            }
                        }, 1000);
                    });

                } catch (error) {
                    console.error('Error setting up notification sound:', error);
                }
            }
            
            function checkForNewOrders() {
                try {
                    // Get current online status from localStorage
                    const currentOnlineStatus = localStorage.getItem('driver_online_status') === 'true';

                    // Skip if ajax_url is not available or driver is offline
                    if (!dispatch_ajax || !dispatch_ajax.ajax_url || !currentOnlineStatus) {
                        return;
                    }

                    // Add timeout
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 8000);

                    fetch(dispatch_ajax.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=get_driver_assigned_orders&nonce=' + dispatch_ajax.nonce + '&username=' + encodeURIComponent(dispatch_ajax.username),
                        credentials: 'same-origin',
                        signal: controller.signal
                    })
                    .then(response => {
                        clearTimeout(timeoutId);
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            const currentOrderCount = data.data.orders ? data.data.orders.length : 0;
                            
                            // Check if there are changes in orders (new or removed)
                            if (lastKnownOrderCount >= 0 && currentOrderCount !== lastKnownOrderCount) {
                                console.log('Order count changed: ' + lastKnownOrderCount + ' -> ' + currentOrderCount);

                                if (currentOrderCount > lastKnownOrderCount) {
                                    console.log('New order detected!');

                                    // Only play sound/show notification if FCM is not active
                                    // FCM will handle notifications, polling is just for fallback
                                    if (typeof fcmActive !== 'undefined' && fcmActive) {
                                        console.log('FCM is active, skipping polling notification (FCM already notified)');
                                    } else {
                                        console.log('FCM not active, using polling notification as fallback');

                                        // Play notification sound
                                        console.log('🔊 [Polling] Attempting to play sound for new order');
                                        if (window.notificationSound) {
                                            console.log('🔊 [Polling] notificationSound exists');
                                            console.log('🔊 [Polling] notificationSound.initialized:', window.notificationSound.initialized);
                                            console.log('🔊 [Polling] notificationSound.unlocked:', window.notificationSound.unlocked);

                                            // Force init if not initialized
                                            if (!window.notificationSound.initialized) {
                                                console.log('🔊 [Polling] Initializing sound on demand');
                                                window.notificationSound.init();
                                            }

                                            // Play directly - play() method handles unlock check
                                            console.log('🔊 [Polling] Playing sound');
                                            const playResult = window.notificationSound.play();
                                            if (playResult && playResult.catch) {
                                                playResult.catch(err => {
                                                    console.warn('❌ [Polling] Could not play sound:', err);
                                                });
                                            }
                                        } else {
                                            console.warn('❌ [Polling] notificationSound not available');
                                        }

                                        // DISABLED: Local fallback notifications AND toast messages
                                        // These were causing duplicate/incorrect notifications
                                        // Server push notifications handle all messaging correctly
                                        console.log('📱 New order detected - server push notifications will handle this');

                                        // REMOVED: In-app toast was also causing duplicate notifications
                                        // The server push notification already shows the correct message
                                    }

                                } else if (currentOrderCount < lastKnownOrderCount) {
                                    console.log('Order removed');

                                    // Play different sound for removed orders (descending tone)
                                    if (window.notificationSound && window.notificationSound.playRemovedSound) {
                                        window.notificationSound.playRemovedSound();
                                    }

                                    // Show browser notification for removed orders (skip if FCM is active)
                                    try {
                                        // Check if FCM is configured - if yes, skip local notification
                                        const fcmConfigured = typeof firebase !== 'undefined' && firebase.messaging;
                                        if (!fcmConfigured && 'Notification' in window && Notification.permission === 'granted') {
                                            new Notification('❌ Bestellung entfernt', {
                                                body: 'Eine Bestellung wurde aus deiner Liste entfernt',
                                                icon: '/wp-content/plugins/dispatch-dashboard/pwa/icons/icon-192x192.png',
                                                badge: '/wp-content/plugins/dispatch-dashboard/pwa/icons/icon-72x72.png',
                                                vibrate: [200, 100, 200],
                                                tag: 'order-removed-' + Date.now(),
                                                requireInteraction: false
                                            });
                                            console.log('Local notification sent for removed order');
                                        } else {
                                            console.log('Notifications not available or not granted');
                                            // No visual notification - just log
                                        }
                                    } catch (error) {
                                        console.error('Error showing notification:', error);
                                    }

                                    // Toast notification removed - using visual banner instead
                                }

                                // Automatically refresh the orders list if on Bestellungen page
                                const currentHash = window.location.hash;
                                if (currentHash === '#bestellungen' || !currentHash) {
                                    console.log('Refreshing orders list...');
                                    if (typeof loadDriverOrders === 'function') {
                                        loadDriverOrders();
                                    } else if (typeof showBestellungen === 'function') {
                                        showBestellungen();
                                    }
                                }
                                // setTimeout(function() {
                                //     // Debug: Check what elements exist
                                //     // Try multiple selectors with detailed logging
                                //     let bestellungenBtn = null;
                                //     
                                //     // Method 1: Look for onclick attribute
                                //     bestellungenBtn = document.querySelector('[onclick*="showBestellungen"]');
                                //     
                                //     if (!bestellungenBtn) {
                                //         // Method 2: Look in bottom navigation
                                //         bestellungenBtn = document.querySelector('.bottom-navigation .nav-item:first-child');
                                //     }
                                //     
                                //     if (!bestellungenBtn) {
                                //         // Method 3: Look for text content
                                //         const navItems = document.querySelectorAll('.nav-item');
                                //         for (let item of navItems) {
                                //             if (item.textContent && item.textContent.includes('Bestellungen')) {
                                //                 bestellungenBtn = item;
                                //                 break;
                                //             }
                                //         }
                                //     }
                                //     
                                //     if (bestellungenBtn) {
                                //         // Try clicking with detailed logging
                                //         bestellungenBtn.click();
                                //         
                                //         // Also try triggering onclick if it exists
                                //         if (bestellungenBtn.onclick) {
                                //             bestellungenBtn.onclick();
                                //         }
                                //     } else {
                                //         if (typeof showBestellungen === 'function') {
                                //             showBestellungen();
                                //         } else {
                                //             // Last resort - refresh page
                                //             window.location.reload();
                                //         }
                                //     }
                                // }, 1500);
                                
                            } else {
                            }
                            
                            lastKnownOrderCount = currentOrderCount;
                        }
                    })
                    .catch(error => {
                        clearTimeout(timeoutId);
                        if (error.name === 'AbortError') {
                            console.warn('Order check timed out');
                        } else if (error.message && error.message.includes('Load failed')) {
                            console.warn('Network connection issue when checking orders, will retry');
                        } else {
                            console.error('Error checking for new orders:', error);
                        }
                    });
                } catch (error) {
                    console.error('Error in checkForNewOrders:', error);
                }
            }
            
            function showNotificationToast(message, type = 'info') {
                // Create toast notification element
                const toast = document.createElement('div');
                toast.className = `notification-toast ${type}`;
                toast.innerHTML = `
                    <div class="toast-content">
                        <span class="toast-message">${message}</span>
                        <button class="toast-close" onclick="this.parentElement.parentElement.remove()">×</button>
                    </div>
                `;
                
                // Add styles
                toast.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #1f2937;
                    color: white;
                    padding: 15px 20px;
                    border-radius: 10px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                    z-index: 10000;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
                    animation: slideInToast 0.3s ease-out;
                `;
                
                document.body.appendChild(toast);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.style.animation = 'slideOutToast 0.3s ease-out';
                        setTimeout(() => toast.remove(), 300);
                    }
                }, 5000);
            }
            
            function refreshOrdersDisplay(orders) {
                try {
                    const ordersContainer = document.querySelector('.orders-list-mobile');
                    if (!ordersContainer) {
                        console.warn('orders-list-mobile container not found!');
                        return;
                    }
                    
                    if (orders.length === 0) {
                        // Show empty state
                        ordersContainer.innerHTML = `
                            <div class="empty-orders-state">
                                <div class="empty-orders-image">
                                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M9 11H5a2 2 0 0 0-2 2v3c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-3a2 2 0 0 0-2-2h-4'/%3E%3Cpath d='m9 7 3 3 3-3'/%3E%3Cpath d='M12 2v8'/%3E%3C/svg%3E" alt="Keine Bestellungen" style="width: 80px; height: 80px; color: #6b7280;">
                                </div>
                                <h3>Keine Bestellungen</h3>
                                <p>Aktuell sind Ihnen keine Bestellungen zugewiesen</p>
                            </div>
                        `;
                        
                        // Add bottom navigation if it doesn't exist
                        if (!document.querySelector('.bottom-navigation')) {
                            mainContent = document.querySelector('.main-content');
                            if (mainContent) {
                                const nav = document.createElement('div');
                                nav.className = 'bottom-navigation';
                                nav.innerHTML = `
                                    <a href="#bestellungen" class="nav-item active" onclick="showBestellungen()">
                                        <div class="icon">
                                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M8 2v3h8V2H8zM9 9l3 4 4-6 1 1.5L12 15 8 10l1-1z"/>
                                                <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.89-1.99 2L3 19a2 2 0 002 2h14c1.1 0 2-.9 2-2V5c0-1.11-.9-2-2-2zm0 16H5V8h14v11z"/>
                                            </svg>
                                        </div>
                                        <div class="label" data-i18n="orders">Bestellungen</div>
                                    </a>
                                    <a href="#karte" class="nav-item" onclick="showKarte()">
                                        <div class="icon">
                                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/>
                                            </svg>
                                        </div>
                                        <div class="label" data-i18n="map">Karte</div>
                                    </a>
                                    <a href="#warten" class="nav-item" onclick="showWarten()">
                                        <div class="icon">
                                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M15 1H9v2h6V1zm-4 13h2V8h-2v6zm8.03-6.61l1.42-1.42c-.43-.51-.9-.99-1.41-1.41l-1.42 1.42C16.07 4.74 14.12 4 12 4c-4.97 0-9 4.03-9 9s4.02 9 9 9 9-4.03 9-9c0-2.12-.74-4.07-1.97-5.61zM12 20c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/>
                                            </svg>
                                        </div>
                                        <div class="label" data-i18n="waiting">Warten</div>
                                    </a>
                                    <a href="#packliste" class="nav-item" onclick="showPackliste()">
                                        <div class="icon">
                                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                                            </svg>
                                        </div>
                                        <div class="label" data-i18n="packlist">Packliste</div>
                                    </a>
                                `;
                                mainContent.appendChild(nav);
                            }
                        }
                    } else {
                        // Show orders
                        let ordersHTML = '';
                        orders.forEach(order => {
                            ordersHTML += `
                                <div class="order-card-mobile" onclick="showOrderDetails('${order.id}')">
                                    <div class="order-header">
                                        <div class="order-number">#${order.order_number}</div>
                                        <div class="order-status ${order.status}">${order.status_text}</div>
                                    </div>
                                    <div class="order-customer">
                                        <strong>${order.customer_name}</strong>
                                        <div class="order-address">${order.customer_address}</div>
                                    </div>
                                    <div class="order-details">
                                        <div class="order-time">📅 ${order.delivery_datetime || order.delivery_time || 'Nicht angegeben'}</div>
                                        <div class="order-total">💰 ${order.total} €</div>
                                    </div>
                                </div>
                            `;
                        });
                        ordersContainer.innerHTML = ordersHTML;
                        
                        // Add bottom navigation if it doesn't exist
                        if (!document.querySelector('.bottom-navigation')) {
                            mainContent = document.querySelector('.main-content');
                            if (mainContent) {
                                const nav = document.createElement('div');
                                nav.className = 'bottom-navigation';
                                nav.innerHTML = `
                                    <a href="#bestellungen" class="nav-item active" onclick="showBestellungen()">
                                        <div class="icon">
                                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M8 2v3h8V2H8zM9 9l3 4 4-6 1 1.5L12 15 8 10l1-1z"/>
                                                <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.89-1.99 2L3 19a2 2 0 002 2h14c1.1 0 2-.9 2-2V5c0-1.11-.9-2-2-2zm0 16H5V8h14v11z"/>
                                            </svg>
                                        </div>
                                        <div class="label" data-i18n="orders">Bestellungen</div>
                                    </a>
                                    <a href="#karte" class="nav-item" onclick="showKarte()">
                                        <div class="icon">
                                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/>
                                            </svg>
                                        </div>
                                        <div class="label" data-i18n="map">Karte</div>
                                    </a>
                                    <a href="#warten" class="nav-item" onclick="showWarten()">
                                        <div class="icon">
                                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M15 1H9v2h6V1zm-4 13h2V8h-2v6zm8.03-6.61l1.42-1.42c-.43-.51-.9-.99-1.41-1.41l-1.42 1.42C16.07 4.74 14.12 4 12 4c-4.97 0-9 4.03-9 9s4.02 9 9 9 9-4.03 9-9c0-2.12-.74-4.07-1.97-5.61zM12 20c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/>
                                            </svg>
                                        </div>
                                        <div class="label" data-i18n="waiting">Warten</div>
                                    </a>
                                    <a href="#packliste" class="nav-item" onclick="showPackliste()">
                                        <div class="icon">
                                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                                            </svg>
                                        </div>
                                        <div class="label" data-i18n="packlist">Packliste</div>
                                    </a>
                                `;
                                mainContent.appendChild(nav);
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error refreshing orders display:', error);
                }
            }
            
            // Function to request notification permission from settings
            function requestNotificationPermission() {
                if ('Notification' in window && Notification.permission === 'default') {
                    Notification.requestPermission().then(permission => {
                        const statusDiv = document.getElementById('push-permission-status');
                        if (permission === 'granted') {
                            console.log('Push-Benachrichtigungen aktiviert!');
                            if (statusDiv) {
                                statusDiv.innerHTML = '<div style="background: #10B981; color: white; padding: 8px 12px; border-radius: 6px; font-size: 12px;">✅ Aktiviert</div>';
                            }
                            showNotificationToast('✅ Push-Benachrichtigungen aktiviert!', 'success');

                            // Subscribe to push if service worker is ready
                            if ('serviceWorker' in navigator) {
                                navigator.serviceWorker.ready.then(registration => {
                                    if (typeof subscribeToPush === 'function') {
                                        subscribeToPush(registration);
                                    }
                                });
                            }

                            // Update button to show test button
                            setTimeout(() => {
                                showLieferpraeferenzen();
                            }, 1500);
                        } else if (permission === 'denied') {
                            if (statusDiv) {
                                statusDiv.innerHTML = '<div style="background: #EF4444; color: white; padding: 8px 12px; border-radius: 6px; font-size: 12px;">❌ Blockiert - Bitte in Browser-Einstellungen aktivieren</div>';
                            }
                            showNotificationToast('❌ Push-Benachrichtigungen wurden blockiert', 'error');
                        }
                    });
                }
            }


            // Initialize sound when page loads
            document.addEventListener('DOMContentLoaded', function() {
                initializeNotificationSound();

                // All test buttons have been removed - system is production ready
                
            });
            
            function goOfflineDirectly() {
                try {
                    
                    // Make AJAX call to update driver status to offline
                    fetch(dispatch_ajax.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=toggle_driver_online_status&nonce=' + dispatch_ajax.nonce + '',
                        credentials: 'same-origin'
                    })
                    .then(response => {
                        return response.text();
                    })
                    .then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error('Invalid JSON: ' + text);
                        }
                    })
                    .then(data => {
                        if (data.success) {
                            const status = data.data.status;
                            const message = data.data.message;
                            
                            isOnline = status === 'online';
                            localStorage.setItem('driver_online_status', isOnline ? 'true' : 'false');
                            
                            // Update any button that exists
                            const button = document.getElementById('onlineToggleLarge');
                            if (button) {
                                if (isOnline) {
                                    button.style.display = 'none'; // Hide button when online
                                } else {
                                    button.style.display = 'block'; // Show button when offline
                                    button.textContent = 'Online gehen';
                                    button.classList.add('offline');
                                    button.classList.remove('online');
                                }
                            }
                            
                            // Update status display and menu
                            updateDashboardStatus(status, message, false);
                            
                            if (isOnline) {
                                updateHamburgerMenuForOnlineStatus();
                            } else {
                                updateHamburgerMenuForOfflineStatus();
                                showDashboard();
                            }
                        } else {
                            console.error('Status update failed:', data.data);
                            alert('Fehler beim Aktualisieren des Status');
                        }
                    })
                    .catch(error => {
                        console.error('Status error:', error);
                        alert('Netzwerkfehler beim Aktualisieren des Status');
                    });
                } catch (error) {
                    console.error('Error in goOfflineDirectly:', error);
                }
            }
            
            // Bottom Navigation Functions
            function showRouting() {
                try {
                    toggleMenu(); // Close hamburger menu

                    // Check if driver is online and ensure correct menu is displayed
                    const isOnline = localStorage.getItem('driver_online_status') === 'true';
                    if (isOnline) {
                        // Make sure we have the online menu
                        updateHamburgerMenuForOnlineStatus();
                    }

                    // Update header title - FIXED: Use same method as other menu items
                    const headerTitle = document.querySelector('.header-title');
                    if (headerTitle) {
                        headerTitle.textContent = translations[currentLanguage].map || 'Karte';
                    }

                    // Ensure header has hamburger menu (fallback if header doesn't have it)
                    const header = document.querySelector('.page-header');
                    if (header && !header.querySelector('.header-hamburger')) {
                        header.innerHTML = `
                            <div class="header-hamburger" onclick="toggleMenu()" style="cursor: pointer;">☰</div>
                            <div class="header-title">Karte</div>
                            <div class="header-actions"></div>
                        `;
                    }
                    
                    mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        mainContent.innerHTML = `
                            <!-- Interactive Google Maps - Full Screen -->
                            <div id="driver-map" style="height: calc(100vh - 110px); width: 100%; position: absolute; top: 60px; left: 0; background: #1a1a1a;">
                                <!-- Google Maps will be loaded here -->
                            </div>

                            <!-- Bottom Sheet for Order Details -->
                            <style>
                                .order-bottom-sheet {
                                    position: fixed;
                                    bottom: 56px;
                                    left: 0;
                                    right: 0;
                                    background: white;
                                    border-radius: 20px 20px 0 0;
                                    box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
                                    transform: translateY(100%);
                                    transition: transform 0.3s ease-out;
                                    z-index: 9998;
                                    max-height: 70vh;
                                    overflow: hidden;
                                }

                                .order-bottom-sheet.active {
                                    transform: translateY(0);
                                }

                                .bottom-sheet-handle {
                                    width: 40px;
                                    height: 4px;
                                    background: #CBD5E1;
                                    border-radius: 2px;
                                    margin: 12px auto;
                                }

                                .bottom-sheet-content {
                                    padding: 0 20px 20px;
                                    overflow-y: auto;
                                    max-height: calc(70vh - 40px);
                                }

                                .bottom-sheet-header {
                                    display: flex;
                                    justify-content: space-between;
                                    align-items: center;
                                    margin-bottom: 20px;
                                    padding-bottom: 15px;
                                    border-bottom: 1px solid #E2E8F0;
                                }

                                .bottom-sheet-title {
                                    font-size: 18px;
                                    font-weight: 600;
                                    color: #1E293B;
                                }

                                .bottom-sheet-badge {
                                    background: #10B981;
                                    color: white;
                                    padding: 4px 12px;
                                    border-radius: 12px;
                                    font-size: 12px;
                                    font-weight: 600;
                                }

                                .bottom-sheet-info-row {
                                    display: flex;
                                    align-items: flex-start;
                                    margin-bottom: 16px;
                                    gap: 12px;
                                }

                                .bottom-sheet-icon {
                                    font-size: 20px;
                                    min-width: 24px;
                                }

                                .bottom-sheet-info-content {
                                    flex: 1;
                                }

                                .bottom-sheet-info-label {
                                    font-size: 12px;
                                    color: #64748B;
                                    margin-bottom: 2px;
                                }

                                .bottom-sheet-info-value {
                                    font-size: 15px;
                                    color: #1E293B;
                                    font-weight: 500;
                                }

                                .bottom-sheet-actions {
                                    display: flex;
                                    gap: 10px;
                                    margin-top: 20px;
                                }

                                .bottom-sheet-btn {
                                    flex: 1;
                                    padding: 14px;
                                    border-radius: 12px;
                                    border: none;
                                    font-size: 15px;
                                    font-weight: 600;
                                    cursor: pointer;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    gap: 8px;
                                    text-decoration: none;
                                }

                                .bottom-sheet-btn-call {
                                    background: #10B981;
                                    color: white;
                                }

                                .bottom-sheet-btn-navigate {
                                    background: #3B82F6;
                                    color: white;
                                }

                                .bottom-sheet-overlay {
                                    position: fixed;
                                    top: 0;
                                    left: 0;
                                    right: 0;
                                    bottom: 0;
                                    background: rgba(0,0,0,0.5);
                                    opacity: 0;
                                    visibility: hidden;
                                    transition: opacity 0.3s, visibility 0.3s;
                                    z-index: 9997;
                                }

                                .bottom-sheet-overlay.active {
                                    opacity: 1;
                                    visibility: visible;
                                }
                            </style>

                            <div class="bottom-sheet-overlay" id="bottom-sheet-overlay" onclick="closeBottomSheet()"></div>
                            <div id="order-bottom-sheet" class="order-bottom-sheet">
                                <div class="bottom-sheet-handle" onclick="closeBottomSheet()"></div>
                                <div class="bottom-sheet-content" id="bottom-sheet-content">
                                    <!-- Content will be dynamically inserted -->
                                </div>
                            </div>

                                <!-- Mobile Bottom Menu - 4 Items -->
                                <div style="position: fixed; bottom: 0; left: 0; right: 0; background: #2D3748; height: 56px; z-index: 9999; display: flex; align-items: center; justify-content: space-around; padding: 0 10px; border-top: 1px solid #4A5568;">
                                    <a href="#" onclick="showBestellungen(); return false;" style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; color: #48BB78; min-width: 60px;">
                                        <svg style="width: 22px; height: 22px; margin-bottom: 4px;" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M8 2v3h8V2H8zM9 9l3 4 4-6 1 1.5L12 15 8 10l1-1z"/>
                                            <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.89-1.99 2L3 19a2 2 0 002 2h14c1.1 0 2-.9 2-2V5c0-1.11-.9-2-2-2zm0 16H5V8h14v11z"/>
                                        </svg>
                                        <span style="font-size: 10px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">BESTELLUNGEN</span>
                                    </a>

                                    <a href="#" onclick="showKarte(); return false;" style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; color: #48BB78; min-width: 60px;">
                                        <svg style="width: 22px; height: 22px; margin-bottom: 4px;" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/>
                                        </svg>
                                        <span style="font-size: 10px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">KARTE</span>
                                    </a>

                                    <a href="#" onclick="showWarten(); return false;" style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; color: #48BB78; min-width: 60px;">
                                        <svg style="width: 22px; height: 22px; margin-bottom: 4px;" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M15 1H9v2h6V1zm-4 13h2V8h-2v6zm8.03-6.61l1.42-1.42c-.43-.51-.9-.99-1.41-1.41l-1.42 1.42C16.07 4.74 14.12 4 12 4c-4.97 0-9 4.03-9 9s4.02 9 9 9 9-4.03 9-9c0-2.12-.74-4.07-1.97-5.61zM12 20c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/>
                                        </svg>
                                        <span style="font-size: 10px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">WARTEN</span>
                                    </a>

                                    <a href="#" onclick="showPackliste(); return false;" style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; color: #48BB78; min-width: 60px;">
                                        <svg style="width: 22px; height: 22px; margin-bottom: 4px;" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                                        </svg>
                                        <span style="font-size: 10px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">PACKLISTE</span>
                                    </a>
                                </div>
                                
                            </div>
                        `;
                        
                        // Clear any cached route data before loading new locations
                        if (typeof clearRouteCache === 'function') {
                            clearRouteCache();
                        }
                        
                        // Clear any stored route information
                        localStorage.removeItem('driver_current_route');
                        localStorage.removeItem('driver_route_data');
                        localStorage.removeItem('cached_delivery_locations');
                        
                        // Load delivery locations
                        loadDeliveryLocations();
                        
                        // Initialize Google Maps
                        initializeDriverMap();
                        
                        // Ensure body has padding for mobile menu
                        document.body.style.paddingBottom = '56px';
                    }
                } catch (error) {
                    console.error('Error in showRouting:', error);
                }
            }
            
            function showKarte() {
                try {
                    // Redirect to Routing function since the map is now in the hamburger menu
                    showRouting();
                } catch (error) {
                    console.error('Error in showKarte:', error);
                }
            }
            
            function loadDeliveryLocations() {
                try {
                    
                    fetch(dispatch_ajax.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'Cache-Control': 'no-cache'
                        },
                        body: 'action=get_driver_delivery_locations&nonce=' + dispatch_ajax.nonce + '&_t=' + Date.now(),
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            displayDeliveryLocations(data.data);
                        } else {
                            console.error('Failed to load locations:', data);
                            showEmptyLocations();
                        }
                    })
                    .catch(error => {
                        console.error('Error loading locations:', error);
                        showEmptyLocations();
                    });
                } catch (error) {
                    console.error('Error in loadDeliveryLocations:', error);
                    showEmptyLocations();
                }
            }
            
            function displayDeliveryLocations(data) {
                try {
                    const locationsList = document.getElementById('locations-list');
                    const locationsCount = document.getElementById('locations-count');
                    const routeDistance = document.getElementById('route-distance');
                    const routeTime = document.getElementById('route-time');
                    const routeStops = document.getElementById('route-stops');
                    
                    if (!locationsList) return;
                    
                    const locations = data.locations || [];
                    const stats = data.stats || {};
                    
                    // Update stats
                    if (locationsCount) locationsCount.textContent = locations.length;
                    if (routeDistance) routeDistance.textContent = stats.distance || '0 km';
                    if (routeTime) routeTime.textContent = stats.time || '0 min';
                    if (routeStops) routeStops.textContent = locations.length;
                    
                    if (locations.length === 0) {
                        showEmptyLocations();
                        return;
                    }
                    
                    let locationsHTML = '';
                    
                    locations.forEach((location, index) => {
                        const statusColor = getStatusColor(location.status);
                        const statusIcon = getStatusIcon(location.status);
                        
                        locationsHTML += `
                            <div class="location-card" onclick="openLocation(${location.order_id})" style="background: #1F2937; border: 1px solid #374151; border-radius: 12px; padding: 16px; cursor: pointer; transition: all 0.2s;">
                                <div style="display: flex; align-items: flex-start; gap: 12px;">
                                    <div style="display: flex; flex-direction: column; align-items: center; margin-top: 4px;">
                                        <div style="width: 32px; height: 32px; background: ${statusColor}; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                                            ${statusIcon}
                                        </div>
                                        <div style="color: #9CA3AF; font-size: 12px; margin-top: 4px;">#${index + 1}</div>
                                    </div>
                                    
                                    <div style="flex: 1;">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                            <div style="color: white; font-weight: 600; font-size: 16px;">
                                                ${location.customer_name}
                                            </div>
                                            <div style="background: ${statusColor}; color: white; font-size: 11px; padding: 4px 8px; border-radius: 12px;">
                                                ${location.status_text}
                                            </div>
                                        </div>
                                        
                                        <div style="color: #9CA3AF; font-size: 14px; margin-bottom: 8px;">
                                            📍 ${location.address}
                                        </div>
                                        
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div style="color: #10B981; font-size: 14px; font-weight: 500;">
                                                ${translations[currentLanguage].order} #${location.order_number}
                                            </div>
                                            <div style="display: flex; gap: 8px;">
                                                <button onclick="event.stopPropagation(); openNavigation('${location.address}', '<?php echo esc_js(get_option('dispatch_default_depot_address', '')); ?>')" style="background: #1E40AF; color: white; border: none; padding: 14px 20px; border-radius: 20px; font-size: 16px; cursor: pointer; font-weight: 600; box-shadow: 0 2px 6px rgba(30,64,175,0.3);">
                                                    <span style="font-size: 24px;">🚗</span> ${translations[currentLanguage].navigation}
                                                </button>
                                                <button onclick="event.stopPropagation(); callCustomer('${location.phone ? location.phone.replace(/'/g, "\\'") : ''}')" style="background: #10B981; color: white; border: none; padding: 6px 12px; border-radius: 16px; font-size: 12px; cursor: pointer;">
                                                    📞 ${translations[currentLanguage].call}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    locationsList.innerHTML = locationsHTML;

                    // Update map markers to show numbers
                    updateMapMarkers();
                } catch (error) {
                    console.error('Error displaying locations:', error);
                    showEmptyLocations();
                }
            }
            
            function showEmptyLocations() {
                const locationsList = document.getElementById('locations-list');
                const locationsCount = document.getElementById('locations-count');
                
                if (locationsCount) locationsCount.textContent = '0';
                
                if (locationsList) {
                    locationsList.innerHTML = `
                        <div style="text-align: center; padding: 40px 20px; color: #9CA3AF;">
                            <div style="font-size: 48px; margin-bottom: 16px;">📍</div>
                            <div style="font-size: 16px; margin-bottom: 8px;">${translations[currentLanguage].noDeliveryAddressesToday}</div>
                            <div style="font-size: 14px; opacity: 0.7;">${translations[currentLanguage].newOrdersWillAppearHere}</div>
                        </div>
                    `;
                }
            }
            
            function getStatusColor(status) {
                switch (status) {
                    case 'processing': return '#3B82F6';
                    case 'completed': return '#10B981';
                    case 'on-hold': return '#F59E0B';
                    case 'pending': return '#9CA3AF';
                    default: return '#6B7280';
                }
            }
            
            function getStatusIcon(status) {
                switch (status) {
                    case 'processing': return '🚚';
                    case 'completed': return '✅';
                    case 'on-hold': return '⏸️';
                    case 'pending': return '⏳';
                    default: return '📦';
                }
            }
            
            function refreshRoute() {
                loadDeliveryLocations();
                if (window.driverMap) {
                    updateMapMarkers();
                }
            }

            // Auto-refresh delivery locations every 30 seconds to get updated sequence
            let autoRefreshInterval = null;
            function startAutoRefresh() {
                if (autoRefreshInterval) {
                    clearInterval(autoRefreshInterval);
                }
                autoRefreshInterval = setInterval(function() {
                    console.log('Auto-refreshing delivery locations...');
                    loadDeliveryLocations();
                    if (window.driverMap) {
                        updateMapMarkers();
                    }
                }, 30000); // 30 seconds
            }

            // Start auto-refresh when page loads
            document.addEventListener('DOMContentLoaded', function() {
                startAutoRefresh();
            });

            // Google Maps variables
            let driverMap = null;
            let driverMarker = null;
            let pickupMarker = null;
            let customerMarkers = [];
            let routePath = null;
            let directionsService = null;
            let directionsRenderer = null;
            let mapType = 'roadmap';
            
            function initializeDriverMap() {
                try {
                    // Check if Leaflet is available
                    if (typeof L === 'undefined') {
                        console.error('Leaflet not loaded');
                        return;
                    }

                    // Create the map
                    const mapElement = document.getElementById('driver-map');
                    if (!mapElement) {
                        console.error('Map element not found');
                        return;
                    }
                    
                    // Get GPS location first, then initialize map with actual position
                    if (navigator.geolocation) {
                        // Show loading message
                        mapElement.innerHTML = `<div style="display: flex; align-items: center; justify-content: center; height: 100%; width: 100%; background: #1a1a1a; color: white; font-size: 16px; position: absolute; top: 0; left: 0;">📍 ${translations[currentLanguage].determiningLocation}</div>`;
                        
                        // Progressive geolocation: try fast first, then accurate
                        const getLocationWithFallback = () => {
                            // First attempt: Fast but less accurate
                            navigator.geolocation.getCurrentPosition(
                                (position) => {
                                    const userLocation = {
                                        lat: position.coords.latitude,
                                        lng: position.coords.longitude
                                    };
                                    sendLocationToServer(userLocation.lat, userLocation.lng);
                                    initializeMapAtLocation(mapElement, userLocation);
                                },
                                (error) => {
                                    console.warn('Fast GPS failed, trying high accuracy...', error.message);
                                    // Second attempt: High accuracy but slower
                                    navigator.geolocation.getCurrentPosition(
                                        (position) => {
                                            const userLocation = {
                                                lat: position.coords.latitude,
                                                lng: position.coords.longitude
                                            };
                                            sendLocationToServer(userLocation.lat, userLocation.lng);
                                            initializeMapAtLocation(mapElement, userLocation);
                                        },
                                        (error) => {
                                            console.warn('High accuracy GPS failed, using Hamburg as default. Error:', error.message);
                                            const defaultLocation = { lat: 53.5511, lng: 9.9937 };
                                            initializeMapAtLocation(mapElement, defaultLocation);
                                        },
                                        {
                                            timeout: 15000,
                                            enableHighAccuracy: true,
                                            maximumAge: 300000 // 5 minutes
                                        }
                                    );
                                },
                                {
                                    timeout: 8000,
                                    enableHighAccuracy: false,
                                    maximumAge: 600000 // 10 minutes
                                }
                            );
                        };
                        
                        getLocationWithFallback();
                    } else {
                        // No geolocation support, use Hamburg
                        const defaultLocation = { lat: 53.5511, lng: 9.9937 };
                        initializeMapAtLocation(mapElement, defaultLocation);
                    }
                    
                } catch (error) {
                    console.error('Error initializing map:', error);
                    showMapError();
                }
            }
            
            function initializeMapAtLocation(mapElement, center) {
                try {
                    // Remove existing map instance if any
                    if (driverMap) {
                        driverMap.remove();
                        driverMap = null;
                    }

                    // Initialize Leaflet map
                    driverMap = L.map(mapElement).setView([center.lat, center.lng], 15);

                    // Add OpenStreetMap tiles (light theme)
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors',
                        maxZoom: 19
                    }).addTo(driverMap);

                    // Add driver marker at current position
                    const driverIcon = L.divIcon({
                        html: '<div style="background: #4285f4; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"></div>',
                        iconSize: [20, 20],
                        className: ''
                    });

                    driverMarker = L.marker([center.lat, center.lng], { icon: driverIcon })
                        .addTo(driverMap)
                        .bindPopup('Ihre Position');

                    // Start continuous GPS tracking to update position
                    startLocationTracking();

                    // Load and display delivery markers
                    updateMapMarkers();

                } catch (error) {
                    console.error('Error initializing map at location:', error);
                    console.error('Error details:', error.message, error.stack);
                    showMapError('Fehler beim Laden der Karte: ' + error.message);
                }
            }

            let lastSentPosition = null;
            let lastSentTime = 0;
            const SEND_INTERVAL_MS = <?php echo floatval(get_option('dispatch_gps_update_interval', 0.5)) * 60 * 1000; ?>;
            const MIN_DISTANCE_METERS = 50;

            function calculateDistance(lat1, lon1, lat2, lon2) {
                const R = 6371e3;
                const φ1 = lat1 * Math.PI / 180;
                const φ2 = lat2 * Math.PI / 180;
                const Δφ = (lat2 - lat1) * Math.PI / 180;
                const Δλ = (lon2 - lon1) * Math.PI / 180;

                const a = Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
                          Math.cos(φ1) * Math.cos(φ2) *
                          Math.sin(Δλ / 2) * Math.sin(Δλ / 2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

                return R * c;
            }

            function sendPositionToServer(position) {
                const now = Date.now();
                const timeSinceLastSend = now - lastSentTime;

                if (lastSentPosition) {
                    const distance = calculateDistance(
                        lastSentPosition.latitude,
                        lastSentPosition.longitude,
                        position.coords.latitude,
                        position.coords.longitude
                    );

                    if (timeSinceLastSend < SEND_INTERVAL_MS && distance < MIN_DISTANCE_METERS) {
                        console.log('Skipping position update (too soon or too close)');
                        return;
                    }
                }

                const data = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    altitude: position.coords.altitude || 0,
                    speed: position.coords.speed || 0,
                    accuracy: position.coords.accuracy || 0,
                    timestamp: new Date(position.timestamp).toISOString()
                };

                console.log('Sending position to server:', data);

                fetch(dispatch_ajax.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'update_driver_location',
                        nonce: dispatch_ajax.nonce,
                        ...data
                    }),
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        console.log('Position updated successfully');
                        lastSentPosition = data;
                        lastSentTime = now;
                    } else {
                        console.warn('Position update failed:', result.data);
                    }
                })
                .catch(error => {
                    console.error('Error sending position:', error);
                });
            }

            function startLocationTracking() {
                if (navigator.geolocation) {
                    // Watch position for real-time updates
                    // Battery-friendly location tracking with progressive accuracy
                    let watchId = navigator.geolocation.watchPosition(
                        (position) => {
                            const pos = {
                                lat: position.coords.latitude,
                                lng: position.coords.longitude
                            };

                            console.log('GPS position updated:', pos);

                            // Send position to WordPress/Traccar
                            sendPositionToServer(position);

                            // Update driver marker position (Leaflet)
                            if (driverMarker) {
                                driverMarker.setLatLng([pos.lat, pos.lng]);
                            }
                        },
                        (error) => {
                            console.warn('GPS tracking error:', error.message, 'Code:', error.code);
                            // Handle different error codes
                            switch(error.code) {
                                case error.PERMISSION_DENIED:
                                    console.error('Location permission denied by user');
                                    break;
                                case error.POSITION_UNAVAILABLE:
                                    console.warn('Location information unavailable');
                                    break;
                                case error.TIMEOUT:
                                    console.warn('Location request timed out');
                                    break;
                                default:
                                    console.error('Unknown location error:', error);
                                    break;
                            }
                        },
                        {
                            enableHighAccuracy: false, // Start with battery-friendly mode
                            timeout: 15000, // Longer timeout
                            maximumAge: 120000 // 2 minutes cache for battery saving
                        }
                    );
                    
                    // Store watchId to clear later if needed
                    if (!window.locationWatchIds) window.locationWatchIds = [];
                    window.locationWatchIds.push(watchId);
                }
            }
            
            function loadGoogleMapsAPI() {
                const apiKey = '<?php echo get_option('dispatch_google_maps_api_key', ''); ?>';
                
                if (!apiKey) {
                    console.error('Google Maps API key not configured');
                    showMapError('Google Maps API Key nicht konfiguriert');
                    return;
                }
                
                const script = document.createElement('script');
                script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places,geometry&loading=async&callback=initializeDriverMapCallback`;
                script.async = true;
                script.defer = true;
                document.head.appendChild(script);
            }
            
            window.initializeDriverMapCallback = function() {
                initializeDriverMap();
            };
            
            function showMapError(message = 'Karte konnte nicht geladen werden') {
                const mapElement = document.getElementById('driver-map');
                if (mapElement) {
                    mapElement.innerHTML = `
                        <div style="display: flex; align-items: center; justify-content: center; height: 100%; background: #1F2937; border-radius: 12px;">
                            <div style="text-align: center; padding: 20px;">
                                <div style="color: #F59E0B; font-size: 48px; margin-bottom: 16px;">🗺️</div>
                                <div style="color: white; font-size: 16px; margin-bottom: 8px;">${message}</div>
                                <div style="color: #9CA3AF; font-size: 14px;">Bitte GPS-Berechtigung erteilen oder Seite neu laden</div>
                            </div>
                        </div>
                    `;
                }
            }
            
            function addMapControls() {
                // Center on current location button
                const centerButton = document.createElement('button');
                centerButton.innerHTML = '📍';
                centerButton.style.cssText = 'background: white; border: none; width: 40px; height: 40px; border-radius: 20px; margin: 10px; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.3); font-size: 20px;';
                centerButton.onclick = centerMap;
                
                // Toggle map type button  
                const typeButton = document.createElement('button');
                typeButton.innerHTML = '🛰️';
                typeButton.style.cssText = 'background: white; border: none; width: 40px; height: 40px; border-radius: 20px; margin: 10px; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.3); font-size: 20px;';
                typeButton.onclick = toggleMapType;
                
                driverMap.controls[google.maps.ControlPosition.RIGHT_TOP].push(centerButton);
                driverMap.controls[google.maps.ControlPosition.RIGHT_TOP].push(typeButton);
            }
            
            function getCurrentLocation() {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            const pos = {
                                lat: position.coords.latitude,
                                lng: position.coords.longitude
                            };
                            
                            // Send location to server first
                            sendLocationToServer(pos.lat, pos.lng);
                            
                            // Update driver marker
                            updateDriverPosition(pos);
                            
                            // Center map on driver
                            if (driverMap) {
                                driverMap.setCenter(pos);
                            }
                        },
                        (error) => {
                            console.error('Error getting location:', error);
                            // Use default location or stored location
                        }
                    );
                    
                    // Watch position for real-time updates
                    // Optimized location tracking for driver position
                    let watchId = navigator.geolocation.watchPosition(
                        (position) => {
                            const pos = {
                                lat: position.coords.latitude,
                                lng: position.coords.longitude
                            };
                            updateDriverPosition(pos);
                        },
                        (error) => {
                            console.warn('Driver position tracking error:', error.message, 'Code:', error.code);
                            // Provide user-friendly error messages
                            switch(error.code) {
                                case error.PERMISSION_DENIED:
                                    console.error('❌ Standortberechtigung verweigert. Bitte in den Browser-Einstellungen erlauben.');
                                    break;
                                case error.POSITION_UNAVAILABLE:
                                    console.warn('⚠️ Standort nicht verfügbar. GPS möglicherweise deaktiviert.');
                                    break;
                                case error.TIMEOUT:
                                    console.warn('⏱️ Standortanfrage zeitüberschritten. Versuche erneut...');
                                    break;
                                default:
                                    console.error('🚫 Unbekannter Standortfehler:', error);
                                    break;
                            }
                        },
                        {
                            enableHighAccuracy: false, // Battery-friendly default
                            timeout: 12000, // Reasonable timeout
                            maximumAge: 90000 // 1.5 minutes cache
                        }
                    );
                    
                    // Store watchId for cleanup
                    if (!window.locationWatchIds) window.locationWatchIds = [];
                    window.locationWatchIds.push(watchId);
                }
            }
            
            // Utility function to clear all location watching
            function clearLocationWatching() {
                if (window.locationWatchIds && window.locationWatchIds.length > 0) {
                    window.locationWatchIds.forEach(watchId => {
                        navigator.geolocation.clearWatch(watchId);
                    });
                    window.locationWatchIds = [];
                }
            }
            
            // Clear location watching when page unloads (battery saving)
            window.addEventListener('beforeunload', clearLocationWatching);
            window.addEventListener('pagehide', clearLocationWatching);
            
            function updateDriverPosition(position) {
                if (!driverMap) return;
                
                if (driverMarker) {
                    driverMarker.setPosition(position);
                } else {
                    // Create driver marker with custom icon
                    driverMarker = new google.maps.Marker({
                        position: position,
                        map: driverMap,
                        title: 'Ihre Position',
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: 8,
                            fillColor: '#4285f4',
                            fillOpacity: 1,
                            strokeColor: '#ffffff',
                            strokeWeight: 2
                        },
                        zIndex: 1000
                    });
                    
                    // Add pulsing animation
                    const pulseCircle = new google.maps.Circle({
                        strokeColor: '#10B981',
                        strokeOpacity: 0.8,
                        strokeWeight: 2,
                        fillColor: '#10B981',
                        fillOpacity: 0.35,
                        map: driverMap,
                        center: position,
                        radius: 50
                    });
                    
                    // Animate the pulse
                    let radius = 50;
                    let opacity = 0.35;
                    setInterval(() => {
                        radius += 2;
                        opacity -= 0.01;
                        if (radius > 150) {
                            radius = 50;
                            opacity = 0.35;
                        }
                        pulseCircle.setRadius(radius);
                        pulseCircle.setOptions({fillOpacity: opacity});
                    }, 50);
                }
                
                // Send location to server for live tracking
                sendLocationToServer(position.lat, position.lng);
            }
            
            function sendLocationToServer(latitude, longitude) {
                
                fetch(dispatch_ajax.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=dispatch_update_driver_location&driver_id=<?php echo get_current_user_id(); ?>&latitude=${latitude}&longitude=${longitude}&accuracy=10&nonce=<?php echo wp_create_nonce("dispatch_nonce"); ?>`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                    } else {
                        console.warn('❌ Failed to update location:', data.data?.message);
                    }
                })
                .catch(error => {
                    console.error('💥 Error sending location to server:', error);
                });
            }
            
            function updateMapMarkers() {
                if (!driverMap) return;

                // Clear existing customer markers
                customerMarkers.forEach(marker => {
                    driverMap.removeLayer(marker);
                });
                customerMarkers = [];
                
                // Fetch delivery locations
                fetch(dispatch_ajax.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_driver_delivery_locations&nonce=' + dispatch_ajax.nonce + '',
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.locations) {
                        const locations = data.data.locations;
                        const bounds = L.latLngBounds();

                        // Add pickup location (Abholort)
                        if (data.data.pickup_location) {
                            const pickup = data.data.pickup_location;
                            if (pickupMarker) {
                                driverMap.removeLayer(pickupMarker);
                            }

                            // Create custom icon for pickup location
                            const pickupIcon = L.divIcon({
                                html: `<div style="background: #F59E0B; color: white; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3); font-size: 20px;">🏪</div>`,
                                className: '',
                                iconSize: [40, 40],
                                iconAnchor: [20, 20]
                            });

                            pickupMarker = L.marker([parseFloat(pickup.lat), parseFloat(pickup.lng)], {
                                icon: pickupIcon,
                                zIndexOffset: 1000
                            }).addTo(driverMap);

                            pickupMarker.bindPopup(`<strong>Abholort</strong><br>${pickup.name || 'Depot'}<br>${pickup.address || ''}`);
                            bounds.extend(pickupMarker.getLatLng());
                        }

                        // Add customer markers
                        locations.forEach((location, index) => {
                            if (location.lat && location.lng) {
                                const position = [parseFloat(location.lat), parseFloat(location.lng)];

                                // Use index + 1 as the marker number (locations are already sorted by delivery_sequence in backend)
                                const markerNumber = index + 1;

                                // Create numbered marker icon
                                const markerIcon = L.divIcon({
                                    html: `<div style="background: #48BB78; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3); font-weight: bold;">${markerNumber}</div>`,
                                    className: '',
                                    iconSize: [30, 30],
                                    iconAnchor: [15, 15]
                                });

                                const marker = L.marker(position, {
                                    icon: markerIcon,
                                    title: location.customer_name
                                }).addTo(driverMap);

                                // Add click event to show bottom sheet
                                marker.on('click', function() {
                                    showOrderBottomSheet(location);
                                });

                                customerMarkers.push(marker);
                                bounds.extend(position);
                            }
                        });
                        
                        // Include driver position if available
                        if (driverMarker) {
                            bounds.extend(driverMarker.getLatLng());
                        }

                        // Fit map to show all markers
                        if (customerMarkers.length > 0 || pickupMarker) {
                            if (bounds.isValid()) {
                                driverMap.fitBounds(bounds, { padding: [50, 50] });
                            }

                            // Note: Route calculation would need a routing service for Leaflet
                            // For now, just display the markers
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading map markers:', error);
                });
            }

            // Bottom Sheet Functions
            function showOrderBottomSheet(location) {
                const bottomSheet = document.getElementById('order-bottom-sheet');
                const overlay = document.getElementById('bottom-sheet-overlay');
                const content = document.getElementById('bottom-sheet-content');

                // Get delivery time and other info
                const deliveryTime = location.delivery_time || 'Nicht angegeben';
                const loadedTime = location.loaded_time || 'Wann geladen';
                const phoneNumber = location.customer_phone || '';
                const depotName = '<?php echo esc_js(get_option('dispatch_default_depot_name', 'Depot')); ?>';
                const depotAddress = '<?php echo esc_js(get_option('dispatch_default_depot_address', '')); ?>';

                // Build content matching the Bestellungen design
                content.innerHTML = `
                    <div style="padding-bottom: 10px; border-bottom: 1px solid #E5E7EB; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <span style="font-size: 18px; font-weight: 600; color: #1F2937;">Bestellung</span>
                            <span style="background: #FEF3C7; color: #92400E; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">Abgeholt</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 24px; font-weight: bold; color: #1F2937;">#${location.order_number}</span>
                            <span style="font-size: 18px; font-weight: bold; color: #10B981;">€${location.order_total || '0.00'}</span>
                        </div>
                    </div>

                    <div style="margin-bottom: 24px;">
                        <!-- Pickup Location -->
                        <div style="display: flex; align-items: flex-start; margin-bottom: 20px; position: relative;">
                            <div style="position: relative; margin-right: 16px; z-index: 1;">
                                <div style="width: 12px; height: 12px; border-radius: 50%; border: 3px solid #10B981; background: white;"></div>
                                <div style="position: absolute; left: 50%; top: 12px; width: 2px; height: 30px; background: #E5E7EB; transform: translateX(-50%);"></div>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #1F2937; margin-bottom: 4px;">${depotName}</div>
                                <div style="font-size: 14px; color: #6B7280;">${depotAddress}</div>
                            </div>
                            <div style="font-size: 14px; color: #6B7280; white-space: nowrap;">${loadedTime}</div>
                        </div>

                        <!-- Delivery Location -->
                        <div style="display: flex; align-items: flex-start;">
                            <div style="position: relative; margin-right: 16px; z-index: 1;">
                                <div style="width: 12px; height: 12px; border-radius: 50%; border: 3px solid #EF4444; background: white;"></div>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #1F2937; margin-bottom: 4px;">${location.customer_name}</div>
                                <div style="font-size: 14px; color: #6B7280;">${location.address}</div>
                            </div>
                            <div style="font-size: 14px; color: #6B7280; white-space: nowrap;">${deliveryTime}</div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <a href="tel:${phoneNumber.replace(/[^0-9+]/g, '')}"
                           style="flex: 1; padding: 16px; background: white; border: 1px solid #E5E7EB; border-radius: 12px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 600; color: #1F2937;">
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56-.35-.12-.74-.03-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/>
                            </svg>
                            Anrufen
                        </a>
                        <a href="https://www.google.com/maps/dir/?api=1&destination=${location.lat},${location.lng}"
                           target="_blank"
                           style="flex: 1; padding: 16px; background: white; border: 1px solid #E5E7EB; border-radius: 12px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 600; color: #1F2937;">
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M21.71 11.29l-9-9c-.39-.39-1.02-.39-1.41 0l-9 9c-.39.39-.39 1.02 0 1.41l9 9c.39.39 1.02.39 1.41 0l9-9c.39-.38.39-1.01 0-1.41zM14 14.5V12h-4v2H8v-4h6V7.5l3.5 3.5-3.5 3.5z"/>
                            </svg>
                            Navigieren
                        </a>
                    </div>
                `;

                // Show bottom sheet with animation
                overlay.classList.add('active');
                bottomSheet.classList.add('active');
            }

            function closeBottomSheet() {
                const bottomSheet = document.getElementById('order-bottom-sheet');
                const overlay = document.getElementById('bottom-sheet-overlay');

                bottomSheet.classList.remove('active');
                overlay.classList.remove('active');
            }

            function calculateRoute(locations) {
                if (!directionsService || !directionsRenderer || locations.length === 0) return;
                
                // Get pickup location and customer locations with coordinates
                const validLocations = locations.filter(loc => loc.lat && loc.lng);
                if (validLocations.length === 0) return;
                
                // Create waypoints array
                const waypoints = validLocations.slice(1).map(loc => ({
                    location: { lat: parseFloat(loc.lat), lng: parseFloat(loc.lng) },
                    stopover: true
                }));
                
                // Set origin (pickup or driver location) and destination
                let origin = driverMarker ? driverMarker.getPosition() : null;
                if (pickupMarker) {
                    origin = pickupMarker.getPosition();
                }
                
                if (!origin) {
                    console.error('No origin point for route');
                    return;
                }
                
                const destination = { 
                    lat: parseFloat(validLocations[0].lat), 
                    lng: parseFloat(validLocations[0].lng) 
                };
                
                const request = {
                    origin: origin,
                    destination: waypoints.length > 0 ? 
                        { lat: parseFloat(validLocations[validLocations.length - 1].lat), 
                          lng: parseFloat(validLocations[validLocations.length - 1].lng) } : 
                        destination,
                    waypoints: waypoints.length > 0 ? 
                        [{location: destination, stopover: true}, ...waypoints.slice(0, -1)] : 
                        [],
                    optimizeWaypoints: true,
                    travelMode: google.maps.TravelMode.DRIVING,
                    drivingOptions: {
                        departureTime: new Date(),
                        trafficModel: 'bestguess'
                    }
                };
                
                directionsService.route(request, (result, status) => {
                    if (status === 'OK') {
                        directionsRenderer.setDirections(result);
                        
                        // Update route statistics
                        updateRouteStats(result);
                    } else {
                        console.error('Directions request failed:', status);
                    }
                });
            }
            
            function updateRouteStats(directionsResult) {
                let totalDistance = 0;
                let totalDuration = 0;
                
                const route = directionsResult.routes[0];
                for (let i = 0; i < route.legs.length; i++) {
                    totalDistance += route.legs[i].distance.value;
                    totalDuration += route.legs[i].duration.value;
                }
                
                // Convert to km and minutes
                const distanceKm = (totalDistance / 1000).toFixed(1);
                const durationMin = Math.round(totalDuration / 60);
                
                // Update UI
                const routeDistance = document.getElementById('route-distance');
                const routeTime = document.getElementById('route-time');
                
                if (routeDistance) routeDistance.textContent = `${distanceKm} km`;
                if (routeTime) routeTime.textContent = `${durationMin} min`;
            }
            
            function centerMap() {
                if (driverMap && driverMarker) {
                    driverMap.setCenter(driverMarker.getPosition());
                    driverMap.setZoom(15);
                } else {
                    getCurrentLocation();
                }
            }
            
            function toggleMapType() {
                if (!driverMap) return;
                
                mapType = mapType === 'roadmap' ? 'satellite' : 'roadmap';
                driverMap.setMapTypeId(mapType);
            }
            
            function openLocation(orderId) {
                // Future: Show order details or navigate to order
                alert(`Bestellungsdetails für Auftrag #${orderId} werden geöffnet...`);
            }
            
            function openNavigation(address, depotAddress = null) {
                // Show navigation modal instead of direct navigation
                showNavigationModal(address, depotAddress);
                return;
            }

            function openNavigationDirect(address) {
                const preferredApp = localStorage.getItem('preferred_nav_app') || 'google';

                // Plus Code erkennen (Format: XXXX+XX oder XXXXXXXX+XX)
                const isPlusCode = /^[A-Z0-9]{4,8}\+[A-Z0-9]{2,3}(\s|,|$)/i.test(address);

                // Für Plus Codes: Google Maps URL-Format verwenden (nicht Intent)
                // weil der Intent Plus Codes manchmal nicht korrekt interpretiert
                const encodedAddress = encodeURIComponent(address);

                const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
                const isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
                const isAndroid = /Android/i.test(navigator.userAgent);

                let navigationUrl = '';

                if (preferredApp === 'google') {
                    if (isPlusCode) {
                        // Plus Code: Immer URL-Format verwenden für beste Kompatibilität
                        navigationUrl = `https://www.google.com/maps/search/?api=1&query=${encodedAddress}`;
                    } else if (isAndroid) {
                        navigationUrl = `google.navigation:q=${encodedAddress}`;
                    } else if (isIOS) {
                        navigationUrl = `comgooglemaps://?daddr=${encodedAddress}&directionsmode=driving`;
                    } else {
                        navigationUrl = `https://www.google.com/maps/dir/?api=1&destination=${encodedAddress}`;
                    }
                } else if (preferredApp === 'waze') {
                    // Waze versteht Plus Codes
                    navigationUrl = isMobile
                        ? `waze://?q=${encodedAddress}&navigate=yes`
                        : `https://www.waze.com/ul?q=${encodedAddress}&navigate=yes`;
                } else if (preferredApp === 'apple' && isIOS) {
                    navigationUrl = `maps://?daddr=${encodedAddress}`;
                }

                if (navigationUrl) {
                    if (isMobile) {
                        window.location.href = navigationUrl;
                    } else {
                        window.open(navigationUrl, '_blank');
                    }
                }
            }

            // Navigation Modal Function
            window.showNavigationModal = function(customerAddress, depotAddress = null) {
                console.log('showNavigationModal called with:', customerAddress, depotAddress);

                // Remove existing modal if present
                const existingModal = document.getElementById('navigation-modal');
                if (existingModal) {
                    existingModal.remove();
                }

                // Get depot info from settings if not provided
                if (!depotAddress) {
                    depotAddress = '<?php echo esc_js(get_option('dispatch_default_depot_address', 'Depot Adresse nicht konfiguriert')); ?>';
                }
                const depotName = '<?php echo esc_js(get_option('dispatch_default_depot_name', 'Abholstation')); ?>';

                // Create modal HTML
                const modalHtml = `
                    <div id="navigation-modal" class="modal-overlay" style="
                        position: fixed;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: rgba(0, 0, 0, 0.8);
                        display: flex;
                        justify-content: center;
                        align-items: flex-end;
                        z-index: 10000;
                        animation: fadeIn 0.2s ease;
                    " onclick="if(event.target === this) closeNavigationModal()">
                        <div class="modal-content" style="
                            background: #2a2a2a;
                            border-radius: 16px 16px 0 0;
                            padding: 0;
                            width: 100%;
                            max-width: 500px;
                            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.5);
                            animation: slideUp 0.3s ease;
                            padding-bottom: env(safe-area-inset-bottom);
                        ">
                            <div class="modal-header" style="
                                padding: 8px 20px;
                                text-align: center;
                                position: relative;
                            ">
                                <div style="
                                    width: 40px;
                                    height: 4px;
                                    background: #6b7280;
                                    border-radius: 2px;
                                    margin: 0 auto 12px;
                                "></div>
                                <h3 style="
                                    margin: 0;
                                    font-size: 17px;
                                    font-weight: 600;
                                    color: #ffffff;
                                ">Navigation starten</h3>
                            </div>

                            <div class="modal-body" style="padding: 8px 0 8px;">
                                <button onclick="navigateToCustomer('${customerAddress ? customerAddress.replace(/'/g, "\\'") : ''}')" style="
                                    display: flex;
                                    align-items: center;
                                    width: 100%;
                                    padding: 16px 20px;
                                    border: none;
                                    background: transparent;
                                    text-align: left;
                                    cursor: pointer;
                                    transition: background 0.2s;
                                " ontouchstart="this.style.background='rgba(255,255,255,0.1)'" ontouchend="this.style.background='transparent'">
                                    <div style="
                                        width: 40px;
                                        height: 40px;
                                        background: #10b981;
                                        border-radius: 20px;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        margin-right: 12px;
                                    ">
                                        <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div style="color: #ffffff; font-size: 17px;">Zum Kunden</div>
                                        <div style="color: #9ca3af; font-size: 13px; margin-top: 2px; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${customerAddress || 'Keine Adresse'}</div>
                                    </div>
                                </button>

                                <button onclick="navigateToDepot('${depotAddress ? depotAddress.replace(/'/g, "\\'") : ''}')" style="
                                    display: flex;
                                    align-items: center;
                                    width: 100%;
                                    padding: 16px 20px;
                                    border: none;
                                    background: transparent;
                                    text-align: left;
                                    cursor: pointer;
                                    transition: background 0.2s;
                                " ontouchstart="this.style.background='rgba(255,255,255,0.1)'" ontouchend="this.style.background='transparent'">
                                    <div style="
                                        width: 40px;
                                        height: 40px;
                                        background: #3b82f6;
                                        border-radius: 20px;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        margin-right: 12px;
                                    ">
                                        <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                                            <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div style="color: #ffffff; font-size: 17px;">${depotName || 'Zur Abholstation'}</div>
                                        <div style="color: #9ca3af; font-size: 13px; margin-top: 2px; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${depotAddress || 'Keine Adresse konfiguriert'}</div>
                                    </div>
                                </button>
                            </div>

                            <div class="modal-footer" style="
                                padding: 8px 20px 20px;
                            ">
                                <button onclick="closeNavigationModal()" style="
                                    width: 100%;
                                    padding: 14px;
                                    border: none;
                                    border-radius: 12px;
                                    background: #374151;
                                    color: #ffffff;
                                    font-size: 17px;
                                    font-weight: 600;
                                    cursor: pointer;
                                    transition: all 0.2s;
                                " ontouchstart="this.style.background='#4b5563'" ontouchend="this.style.background='#374151'">
                                    Abbrechen
                                </button>
                            </div>
                        </div>
                    </div>

                    <style>
                        @keyframes fadeIn {
                            from { opacity: 0; }
                            to { opacity: 1; }
                        }
                        @keyframes slideUp {
                            from { transform: translateY(100%); }
                            to { transform: translateY(0); }
                        }
                    </style>
                `;

                // Add modal to page
                document.body.insertAdjacentHTML('beforeend', modalHtml);
            };

            window.closeNavigationModal = function() {
                const modal = document.getElementById('navigation-modal');
                if (modal) {
                    modal.remove();
                }
            };

            window.navigateToCustomer = function(address) {
                console.log('Navigating to customer:', address);
                closeNavigationModal();
                if (address && address !== 'Keine Adresse') {
                    openNavigationDirect(address);
                } else {
                    alert('Keine Kundenadresse verfügbar');
                }
            };

            window.navigateToDepot = function(address) {
                console.log('Navigating to depot:', address);
                closeNavigationModal();

                // Clean up the address string
                const cleanAddress = address ? address.trim() : '';

                if (cleanAddress &&
                    cleanAddress !== '' &&
                    cleanAddress !== 'Keine Adresse konfiguriert' &&
                    cleanAddress !== 'Depot Adresse nicht konfiguriert') {
                    // Valid address, navigate
                    openNavigationDirect(cleanAddress);
                } else {
                    alert('Keine Depot-Adresse konfiguriert. Bitte in den Einstellungen konfigurieren.');
                }
            };

            // Make callCustomer globally accessible
            window.callCustomer = function(phone) {
                console.log('callCustomer called with:', phone);

                if (!phone || phone.trim() === '') {
                    alert('Telefonnummer nicht verfügbar');
                    return;
                }

                // Clean phone number - remove spaces, dashes, brackets but keep + and digits
                let cleanPhone = phone.toString().trim();
                cleanPhone = cleanPhone.replace(/[\s\-\(\)\.]/g, ''); // Remove spaces, dashes, brackets, dots
                cleanPhone = cleanPhone.replace(/[^\d\+]/g, ''); // Keep only digits and +

                // Add German country code if missing
                if (cleanPhone && cleanPhone.length >= 6) {
                    // Ensure + is at the beginning if present
                    if (cleanPhone.includes('+') && !cleanPhone.startsWith('+')) {
                        cleanPhone = '+' + cleanPhone.replace(/\+/g, '');
                    }

                    // If no country code, add +49 (Germany) and remove leading 0
                    if (!cleanPhone.startsWith('+')) {
                        if (cleanPhone.startsWith('0')) {
                            // German number with leading 0: remove 0 and add +49
                            cleanPhone = '+49' + cleanPhone.substring(1);
                        } else {
                            // Number without + or 0: add +49
                            cleanPhone = '+49' + cleanPhone;
                        }
                    }

                    // Show contact modal instead of direct call
                    showContactModal(cleanPhone);
                } else {
                    alert(`Ungültige Telefonnummer: "${phone}"\nBereinigt: "${cleanPhone}"`);
                }
            }

            window.showContactModal = function(phone) {
                // Remove existing modal if present
                const existingModal = document.getElementById('contact-modal');
                if (existingModal) {
                    existingModal.remove();
                }

                // Create modal HTML with dark theme for driver app
                const modalHtml = `
                    <div id="contact-modal" class="modal-overlay" style="
                        position: fixed;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: rgba(0, 0, 0, 0.8);
                        display: flex;
                        justify-content: center;
                        align-items: flex-end;
                        z-index: 10000;
                        animation: fadeIn 0.2s ease;
                    " onclick="if(event.target === this) closeContactModal()">
                        <div class="modal-content" style="
                            background: #2a2a2a;
                            border-radius: 16px 16px 0 0;
                            padding: 0;
                            width: 100%;
                            max-width: 500px;
                            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.5);
                            animation: slideUp 0.3s ease;
                            padding-bottom: env(safe-area-inset-bottom);
                        ">
                            <div class="modal-header" style="
                                padding: 8px 20px;
                                text-align: center;
                                position: relative;
                            ">
                                <div style="
                                    width: 40px;
                                    height: 4px;
                                    background: #6b7280;
                                    border-radius: 2px;
                                    margin: 0 auto 12px;
                                "></div>
                                <h3 style="
                                    margin: 0;
                                    font-size: 17px;
                                    font-weight: 600;
                                    color: #ffffff;
                                ">Kunden kontaktieren</h3>
                            </div>

                            <div class="modal-body" style="padding: 8px 0 8px;">
                                <button onclick="contactAction('call', '${phone}')" style="
                                    display: flex;
                                    align-items: center;
                                    width: 100%;
                                    padding: 16px 20px;
                                    border: none;
                                    background: transparent;
                                    text-align: left;
                                    cursor: pointer;
                                    transition: background 0.2s;
                                " ontouchstart="this.style.background='rgba(255,255,255,0.1)'" ontouchend="this.style.background='transparent'">
                                    <div style="
                                        width: 40px;
                                        height: 40px;
                                        background: #10b981;
                                        border-radius: 20px;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        margin-right: 12px;
                                    ">
                                        <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                                            <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56a.977.977 0 0 0-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div style="color: #ffffff; font-size: 17px;">Anrufen</div>
                                        <div style="color: #9ca3af; font-size: 13px; margin-top: 2px;">${phone}</div>
                                    </div>
                                </button>

                                <button onclick="contactAction('sms', '${phone}')" style="
                                    display: flex;
                                    align-items: center;
                                    width: 100%;
                                    padding: 16px 20px;
                                    border: none;
                                    background: transparent;
                                    text-align: left;
                                    cursor: pointer;
                                    transition: background 0.2s;
                                " ontouchstart="this.style.background='rgba(255,255,255,0.1)'" ontouchend="this.style.background='transparent'">
                                    <div style="
                                        width: 40px;
                                        height: 40px;
                                        background: #3b82f6;
                                        border-radius: 20px;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        margin-right: 12px;
                                    ">
                                        <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                                            <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 11H7V9h2v2zm4 0h-2V9h2v2zm4 0h-2V9h2v2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div style="color: #ffffff; font-size: 17px;">Nachricht</div>
                                        <div style="color: #9ca3af; font-size: 13px; margin-top: 2px;">SMS senden</div>
                                    </div>
                                </button>

                                <button onclick="contactAction('whatsapp', '${phone}')" style="
                                    display: flex;
                                    align-items: center;
                                    width: 100%;
                                    padding: 16px 20px;
                                    border: none;
                                    background: transparent;
                                    text-align: left;
                                    cursor: pointer;
                                    transition: background 0.2s;
                                " ontouchstart="this.style.background='rgba(255,255,255,0.1)'" ontouchend="this.style.background='transparent'">
                                    <div style="
                                        width: 40px;
                                        height: 40px;
                                        background: #25D366;
                                        border-radius: 20px;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        margin-right: 12px;
                                    ">
                                        <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.149-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div style="color: #ffffff; font-size: 17px;">WhatsApp</div>
                                        <div style="color: #9ca3af; font-size: 13px; margin-top: 2px;">Chat öffnen</div>
                                    </div>
                                </button>
                            </div>

                            <div class="modal-footer" style="
                                padding: 8px 20px 20px;
                            ">
                                <button onclick="closeContactModal()" style="
                                    width: 100%;
                                    padding: 14px;
                                    border: none;
                                    border-radius: 12px;
                                    background: #374151;
                                    color: #ffffff;
                                    font-size: 17px;
                                    font-weight: 600;
                                    cursor: pointer;
                                    transition: all 0.2s;
                                " ontouchstart="this.style.background='#4b5563'" ontouchend="this.style.background='#374151'">
                                    Abbrechen
                                </button>
                            </div>
                        </div>
                    </div>

                    <style>
                        @keyframes fadeIn {
                            from { opacity: 0; }
                            to { opacity: 1; }
                        }
                        @keyframes slideUp {
                            from { transform: translateY(100%); }
                            to { transform: translateY(0); }
                        }
                    </style>
                `;

                // Add modal to page
                document.body.insertAdjacentHTML('beforeend', modalHtml);

                // Close modal when clicking overlay
                document.getElementById('contact-modal').addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeContactModal();
                    }
                });
            }

            window.closeContactModal = function() {
                const modal = document.getElementById('contact-modal');
                if (modal) {
                    modal.remove();
                }
            }

            window.contactAction = function(type, phone) {
                closeContactModal();

                switch(type) {
                    case 'call':
                        window.location.href = `tel:${phone}`;
                        break;
                    case 'sms':
                        window.location.href = `sms:${phone}`;
                        break;
                    case 'whatsapp':
                        // Remove + from phone number for WhatsApp
                        const whatsappNumber = phone.replace(/\+/g, '');
                        window.open(`https://wa.me/${whatsappNumber}`, '_blank');
                        break;
                }
            }
            
            window.showOrderDetails = function(orderId) {
                
                // Check if dispatchDashboard instance exists
                if (window.dispatchDashboard && typeof window.dispatchDashboard.showOrderDetails === 'function') {
                    window.dispatchDashboard.showOrderDetails(orderId);
                } else {
                    console.error('DispatchDashboard instance not found or method not available');
                    alert('Details können momentan nicht angezeigt werden. Bitte versuchen Sie es später erneut.');
                }
            }
            
            function updateActiveNav(activeTab) {
                try {
                    // Remove active class from all nav items
                    const navItems = document.querySelectorAll('.bottom-navigation .nav-item');
                    navItems.forEach(item => {
                        item.classList.remove('active');
                    });
                    
                    // Add active class to the current tab
                    const activeNavItem = document.querySelector(`.bottom-navigation a[href="#${activeTab}"]`);
                    if (activeNavItem) {
                        activeNavItem.classList.add('active');
                    }
                } catch (error) {
                    console.error('Error in updateActiveNav:', error);
                }
            }
            
            // Make displayScheduledOrders globally available
            window.displayScheduledOrders = function(orders) {
                try {
                    const mainContent = document.querySelector('.main-content');
                    if (!mainContent) return;

                    // Add orders-page class for full width styling
                    mainContent.classList.add('orders-page');

                    if (!orders || orders.length === 0) {
                        window.showScheduledEmptyState();
                        return;
                    }

                    const depotName = '<?php echo esc_js(get_option('dispatch_default_depot_name', 'Lager')); ?>';
                    const depotAddress = '<?php echo esc_js(get_option('dispatch_default_depot_address', '')); ?>';

                    let ordersHTML = '<div class="orders-list-mobile">';

                    orders.forEach((order, index) => {
                        // Geplant badge
                        const statusBadge = `
                            <span class="status-badge geplant" style="background: linear-gradient(135deg, #3B82F6, #2563EB); color: white; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600;">
                                📅 Geplant
                            </span>
                        `;

                        // Action icons (navigation and phone)
                        const actionIcons = `
                            <div class="action-icons">
                                <button class="action-icon nav-icon" onclick="openNavigation('${order.plus_code || order.customer_address}', '${depotAddress}')" title="Navigation">
                                    <svg width="24" height="24" fill="#9CA3AF" viewBox="0 0 24 24">
                                        <path d="M12 2L4.5 20.29l.71.71L12 18l6.79 3 .71-.71z"/>
                                    </svg>
                                </button>
                                <button class="action-icon phone-icon" onclick="callCustomer('${order.customer_phone ? order.customer_phone.replace(/'/g, "\\'") : ''}')" title="Anrufen">
                                    <svg width="24" height="24" fill="#9CA3AF" viewBox="0 0 24 24">
                                        <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56a.977.977 0 0 0-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/>
                                    </svg>
                                </button>
                            </div>
                        `;

                        // Format order total
                        const formatOrderTotal = (total) => {
                            const numericValue = parseFloat(total.replace('€', '').replace(',', '.').trim());
                            return isNaN(numericValue) ? '€0.00' : '€' + numericValue.toFixed(2);
                        };
                        const formattedTotal = formatOrderTotal(order.total);

                        ordersHTML += `
                            <div class="current-order-card" data-order-id="${order.order_id}">
                                <div class="order-header">
                                    <div class="order-header-left">
                                        ${statusBadge}
                                    </div>
                                    ${actionIcons}
                                </div>

                                <div class="order-number-section">
                                    <span class="order-number">#${order.order_number}</span>
                                    <span class="order-total">${formattedTotal}</span>
                                </div>

                                <div class="order-locations">
                                    <!-- Pickup Location -->
                                    <div class="location-item">
                                        <div class="location-marker pickup-marker">
                                            <div class="marker-dot"></div>
                                        </div>
                                        <div class="location-info">
                                            <div class="location-name">${depotName}</div>
                                            <div class="location-address">${depotAddress}</div>
                                        </div>
                                        <div class="location-time">${new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', hour12: false })} Uhr</div>
                                    </div>

                                    <!-- Delivery Location -->
                                    <div class="location-item">
                                        <div class="location-marker delivery-marker">
                                            <div class="marker-dot"></div>
                                        </div>
                                        <div class="location-info">
                                            <div class="location-name">${order.customer_name}</div>
                                            <div class="location-address">${order.customer_address}</div>
                                        </div>
                                        <div class="location-time">📅 ${order.delivery_datetime}</div>
                                    </div>
                                </div>
                            </div>`;
                    });

                    ordersHTML += '</div>';

                    // Add bottom navigation
                    ordersHTML += `
                        <div class="bottom-navigation">
                            <a href="#bestellungen" class="nav-item" onclick="showBestellungen()">
                                <div class="icon">
                                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M8 2v3h8V2H8zM9 9l3 4 4-6 1 1.5L12 15 8 10l1-1z"/>
                                        <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.89-1.99 2L3 19a2 2 0 002 2h14c1.1 0 2-.9 2-2V5c0-1.11-.9-2-2-2zm0 16H5V8h14v11z"/>
                                    </svg>
                                </div>
                                <div class="label" data-i18n="orders">Bestellungen</div>
                            </a>
                            <a href="#karte" class="nav-item" onclick="showKarte()">
                                <div class="icon">
                                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/>
                                    </svg>
                                </div>
                                <div class="label" data-i18n="map">Karte</div>
                            </a>
                            <a href="#warten" class="nav-item active" onclick="showWarten()">
                                <div class="icon">
                                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M15 1H9v2h6V1zm-4 13h2V8h-2v6zm8.03-6.61l1.42-1.42c-.43-.51-.9-.99-1.41-1.41l-1.42 1.42C16.07 4.74 14.12 4 12 4c-4.97 0-9 4.03-9 9s4.02 9 9 9 9-4.03 9-9c0-2.12-.74-4.07-1.97-5.61zM12 20c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/>
                                    </svg>
                                </div>
                                <div class="label" data-i18n="waiting">Warten</div>
                            </a>
                            <a href="#packliste" class="nav-item" onclick="showPackliste()">
                                <div class="icon">
                                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                                    </svg>
                                </div>
                                <div class="label" data-i18n="packlist">Packliste</div>
                            </a>
                        </div>
                    `;

                    mainContent.innerHTML = ordersHTML;

                    // Update active navigation
                    updateActiveNav('warten');
                } catch (error) {
                    console.error('Error in displayScheduledOrders:', error);
                }
            };

            function showWarten() {
                try {
                    // Update URL hash for tracking
                    window.location.hash = 'warten';

                    // Check if driver is online and ensure correct menu is displayed
                    const isOnline = localStorage.getItem('driver_online_status') === 'true';
                    if (isOnline) {
                        // Make sure we have the online menu
                        updateHamburgerMenuForOnlineStatus();
                    }

                    const headerTitle = document.querySelector('.header-title');
                    if (headerTitle) {
                        headerTitle.textContent = translations[currentLanguage].waiting || 'Warten';
                    }

                    mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        // Reset main-content to normal width
                        mainContent.className = 'main-content orders-page';
                        // Show loading first
                        mainContent.innerHTML = `
                            <div class="loading-container" style="display: flex; justify-content: center; align-items: center; height: 200px;">
                                <div class="spinner"></div>
                            </div>
                        `;

                        // Load scheduled (future) orders
                        loadScheduledOrders();

                        // Start auto-refresh for scheduled orders
                        if (scheduledOrderCheckInterval) {
                            clearInterval(scheduledOrderCheckInterval);
                        }
                        scheduledOrderCheckInterval = setInterval(() => {
                            if (window.location.hash === '#warten') {
                                console.log('Auto-refreshing scheduled orders...');
                                loadScheduledOrders();
                            } else {
                                // Stop checking if not on Warten page
                                clearInterval(scheduledOrderCheckInterval);
                                scheduledOrderCheckInterval = null;
                            }
                        }, 10000); // Check every 10 seconds
                    }
                } catch (error) {
                    console.error('Error in showWarten:', error);
                }
            }
            
            // Use interval for auto-refresh
            let scheduledOrderCheckInterval = null;

            function loadScheduledOrders() {
                try {
                    // Check if required data is available
                    if (typeof dispatch_ajax === 'undefined' || !dispatch_ajax || !dispatch_ajax.nonce || !dispatch_ajax.username) {
                        console.error('Cannot load scheduled orders - missing ajax data');
                        showScheduledEmptyState();
                        return;
                    }

                    // Add abort controller for timeout
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout

                    fetch(dispatch_ajax.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=get_driver_scheduled_orders&nonce=' + dispatch_ajax.nonce + '&username=' + encodeURIComponent(dispatch_ajax.username),
                        credentials: 'same-origin',
                        signal: controller.signal
                    })
                    .then(response => {
                        clearTimeout(timeoutId);
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Check for changes in scheduled orders
                            const currentScheduledCount = data.data.orders ? data.data.orders.length : 0;

                            if (lastKnownScheduledOrderCount !== -1 && currentScheduledCount !== lastKnownScheduledOrderCount) {
                                console.log('Scheduled order count changed:', lastKnownScheduledOrderCount, '->', currentScheduledCount);

                                if (currentScheduledCount > lastKnownScheduledOrderCount) {
                                    console.log('New scheduled order detected!');

                                    // Play scheduled order sound
                                    if (window.notificationSound && window.notificationSound.playScheduledSound) {
                                        window.notificationSound.playScheduledSound();
                                    }

                                    // Show notification
                                    try {
                                        if ('Notification' in window && Notification.permission === 'granted') {
                                            new Notification('📅 ' + translations[currentLanguage].newScheduledOrder, {
                                                body: translations[currentLanguage].newOrderAssignedForFutureDelivery,
                                                icon: '/wp-content/plugins/dispatch-dashboard/pwa/icons/icon-192x192.png',
                                                badge: '/wp-content/plugins/dispatch-dashboard/pwa/icons/icon-72x72.png',
                                                vibrate: [200, 100, 200],
                                                tag: 'scheduled-order-' + Date.now()
                                            });
                                        } else {
                                            // Visual notification fallback
                                            const scheduledNotification = document.createElement('div');
                                            scheduledNotification.style.cssText = `
                                                position: fixed;
                                                top: 80px;
                                                left: 50%;
                                                transform: translateX(-50%);
                                                background: linear-gradient(135deg, #3B82F6, #2563EB);
                                                color: white;
                                                padding: 20px 30px;
                                                border-radius: 12px;
                                                box-shadow: 0 8px 30px rgba(59, 130, 246, 0.4);
                                                z-index: 100000;
                                                font-size: 18px;
                                                font-weight: 600;
                                                display: flex;
                                                align-items: center;
                                                gap: 12px;
                                                animation: slideInBounce 0.5s ease;
                                            `;
                                            scheduledNotification.innerHTML = `
                                                <span style="font-size: 24px;">📅</span>
                                                <span>${translations[currentLanguage].newScheduledOrder}!</span>
                                            `;
                                            document.body.appendChild(scheduledNotification);

                                            if ('vibrate' in navigator) {
                                                navigator.vibrate([200, 100, 200]);
                                            }

                                            setTimeout(() => {
                                                scheduledNotification.style.animation = 'slideOutUp 0.5s ease';
                                                setTimeout(() => scheduledNotification.remove(), 500);
                                            }, 6000);
                                        }
                                    } catch (error) {
                                        console.error('Error showing scheduled order notification:', error);
                                    }

                                    // Toast notification removed - using visual banner instead

                                } else if (currentScheduledCount < lastKnownScheduledOrderCount) {
                                    console.log('Scheduled order removed');

                                    // Play removed sound
                                    if (window.notificationSound && window.notificationSound.playRemovedSound) {
                                        window.notificationSound.playRemovedSound();
                                    }

                                    // Show notification
                                    try {
                                        if ('Notification' in window && Notification.permission === 'granted') {
                                            new Notification('❌ Geplanter Auftrag entfernt', {
                                                body: 'Ein geplanter Auftrag wurde aus deiner Liste entfernt',
                                                icon: '/wp-content/plugins/dispatch-dashboard/pwa/icons/icon-192x192.png',
                                                badge: '/wp-content/plugins/dispatch-dashboard/pwa/icons/icon-72x72.png',
                                                vibrate: [200, 100, 200],
                                                tag: 'scheduled-removed-' + Date.now()
                                            });
                                        } else {
                                            // No visual notification - just log
                                            console.log('Visual notification disabled for removed scheduled order');
                                        }
                                    } catch (error) {
                                        console.error('Error showing removed notification:', error);
                                    }

                                    // Toast notification removed - using visual banner instead
                                }
                            }

                            lastKnownScheduledOrderCount = currentScheduledCount;

                            // Display scheduled orders with "Geplant" status
                            window.displayScheduledOrders(data.data.orders);
                        } else {
                            console.error('Failed to load scheduled orders:', data.data);
                            showScheduledEmptyState();
                        }
                    })
                    .catch(error => {
                        if (error.name === 'AbortError') {
                            console.warn('Scheduled orders request timed out, will retry on next interval');
                        } else if (error.message && error.message.includes('Failed to fetch')) {
                            console.warn('Network error loading scheduled orders, will retry');
                        } else {
                            console.error('Error loading scheduled orders:', error);
                        }
                        // Don't show empty state on network errors, just keep the current display
                        // showScheduledEmptyState();
                    });
                } catch (error) {
                    console.error('Error in loadScheduledOrders:', error);
                    // Don't show empty state on errors
                    // showScheduledEmptyState();
                }
            }
            
            // Use the global displayScheduledOrders function
            // Function is defined globally at window.displayScheduledOrders
            
            // Make showScheduledEmptyState globally available
            window.showScheduledEmptyState = function() {
                const mainContent = document.querySelector('.main-content');
                if (!mainContent) return;

                mainContent.innerHTML = `
                    <div class="empty-state-screen">
                        <div class="empty-state-icon">
                            <svg viewBox="0 0 24 24" style="fill: #6B7280; width: 100px; height: 100px;">
                                <path d="M15 1H9v2h6V1zm-4 13h2V8h-2v6zm8.03-6.61l1.42-1.42c-.43-.51-.9-.99-1.41-1.41l-1.42 1.42C16.07 4.74 14.12 4 12 4c-4.97 0-9 4.03-9 9s4.02 9 9 9 9-4.03 9-9c0-2.12-.74-4.07-1.97-5.61zM12 20c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/>
                            </svg>
                        </div>
                        <div style="text-align: center; padding: 20px; color: #6B7280;">
                            <h2 style="margin: 20px 0 10px; font-size: 22px; color: #374151; font-weight: 600;">Keine geplanten Aufträge</h2>
                            <p style="margin: 0; font-size: 16px; line-height: 1.5; max-width: 400px; margin: 0 auto;">Zukünftige Bestellungen werden hier angezeigt, sobald sie Ihnen zugewiesen werden.</p>
                        </div>
                    </div>
                    
                    <!-- Bottom Navigation -->
                    <div class="bottom-navigation">
                        <a href="#bestellungen" class="nav-item" onclick="showBestellungen()">
                            <div class="icon">
                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 2v3h8V2H8zM9 9l3 4 4-6 1 1.5L12 15 8 10l1-1z"/>
                        <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.89-1.99 2L3 19a2 2 0 002 2h14c1.1 0 2-.9 2-2V5c0-1.11-.9-2-2-2zm0 16H5V8h14v11z"/>
                    </svg>
                </div>
                            <div class="label" data-i18n="orders">Bestellungen</div>
                        </a>
                        <a href="#karte" class="nav-item" onclick="showKarte()">
                            <div class="icon">
                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/>
                    </svg>
                </div>
                            <div class="label" data-i18n="map">Karte</div>
                        </a>
                        <a href="#warten" class="nav-item active" onclick="showWarten()">
                            <div class="icon">
                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M15 1H9v2h6V1zm-4 13h2V8h-2v6zm8.03-6.61l1.42-1.42c-.43-.51-.9-.99-1.41-1.41l-1.42 1.42C16.07 4.74 14.12 4 12 4c-4.97 0-9 4.03-9 9s4.02 9 9 9 9-4.03 9-9c0-2.12-.74-4.07-1.97-5.61zM12 20c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/>
                    </svg>
                </div>
                            <div class="label" data-i18n="waiting">Warten</div>
                        </a>
                        <a href="#packliste" class="nav-item" onclick="showPackliste()">
                            <div class="icon">
                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                    </svg>
                </div>
                            <div class="label" data-i18n="packlist">Packliste</div>
                        </a>
                    </div>
                `;
                
                // Update active nav
                updateActiveNav('warten');
            }
            
            function markAsPickedUp(orderId) {
                try {
                    
                    // Show loading state
                    const button = document.querySelector(`[onclick="markAsPickedUp(${orderId})"]`);
                    if (button) {
                        button.disabled = true;
                        button.innerHTML = 'Wird verarbeitet...';
                    }
                    
                    fetch(dispatch_ajax.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=mark_order_picked_up&order_id=${orderId}&nonce=' + dispatch_ajax.nonce + '`,
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            showToast('Auftrag als abgeholt markiert. Kunde wurde per E-Mail benachrichtigt.', 'success');
                            
                            // Reload orders to update display
                            setTimeout(() => {
                                loadDriverOrders();
                            }, 1000);
                            
                        } else {
                            console.error('Failed to mark as picked up:', data.data);
                            showToast('Fehler beim Markieren als abgeholt', 'error');
                            
                            // Reset button
                            if (button) {
                                button.disabled = false;
                                button.innerHTML = 'Als abgeholt markieren →';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error marking as picked up:', error);
                        showToast('Netzwerkfehler', 'error');
                        
                        // Reset button
                        if (button) {
                            button.disabled = false;
                            button.innerHTML = 'Als abgeholt markieren →';
                        }
                    });
                    
                } catch (error) {
                    console.error('Error in markAsPickedUp:', error);
                }
            }
            
            function showToast(message, type = 'info') {
                const toast = document.createElement('div');
                toast.className = `toast toast-${type}`;
                toast.textContent = message;
                toast.style.cssText = `
                    position: fixed;
                    bottom: 100px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: ${type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : '#6B7280'};
                    color: white;
                    padding: 12px 24px;
                    border-radius: 8px;
                    z-index: 10000;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                `;
                
                document.body.appendChild(toast);
                
                // Fade in
                setTimeout(() => {
                    toast.style.opacity = '1';
                }, 10);
                
                // Fade out and remove
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => {
                        document.body.removeChild(toast);
                    }, 300);
                }, 3000);
            }
            
            function showLeistung() {
                try {
                    // Close the menu first
                    if (typeof toggleMenu === 'function') {
                        const menu = document.querySelector('.side-menu');
                        if (menu && menu.classList.contains('open')) {
                            toggleMenu();
                        }
                    }

                    // Check if driver is online and ensure correct menu is displayed
                    const isOnline = localStorage.getItem('driver_online_status') === 'true';
                    if (isOnline) {
                        updateHamburgerMenuForOnlineStatus();
                    }

                    const headerTitle = document.querySelector('.header-title');
                    if (headerTitle) {
                        headerTitle.textContent = translations[currentLanguage].performance;
                    }

                    mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        // Show loading screen
                        mainContent.innerHTML = `
                            <div class="loading-screen-stats">
                                <div class="loading-spinner-stats"></div>
                                <div class="loading-text">${translations[currentLanguage].loadingStatistics}</div>
                            </div>
                        `;

                        // Fetch performance statistics from server
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                action: 'get_driver_performance_stats',
                                driver_id: <?php echo get_current_user_id(); ?>
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                displayPerformanceStats(data.data);
                            } else {
                                throw new Error(data.message || 'Fehler beim Laden der Statistiken');
                            }
                        })
                        .catch(error => {
                            console.error('Error loading performance stats:', error);
                            mainContent.innerHTML = `
                                <div class="empty-state-screen">
                                    <div class="empty-state-icon">
                                        <svg viewBox="0 0 24 24" style="fill: #ef4444;">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                                        </svg>
                                    </div>
                                    <div class="empty-state-message">${translations[currentLanguage].errorLoading}</div>
                                    <div style="margin-top: 10px; color: #9CA3AF; font-size: 14px;">
                                        ${error.message}
                                    </div>
                                </div>
                                ${getBottomNavigation('leistung')}
                            `;
                        });
                    }
                } catch (error) {
                    console.error('Error in showLeistung:', error);
                }
            }

            function displayPerformanceStats(stats) {
                const mainContent = document.querySelector('.main-content');

                mainContent.innerHTML = `
                    <div class="performance-stats-container">
                        <!-- Header Summary Cards -->
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                                        <path d="M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm2-7h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/>
                                    </svg>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value">${stats.today_deliveries}</div>
                                    <div class="stat-label">${translations[currentLanguage].today}</div>
                                </div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                    <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                                        <path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4z"/>
                                    </svg>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value">${stats.week_deliveries}</div>
                                    <div class="stat-label">${translations[currentLanguage].thisWeek}</div>
                                </div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                    <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                                        <path d="M20 6h-2.18c.11-.31.18-.65.18-1 0-1.66-1.34-3-3-3-1.05 0-1.96.54-2.5 1.35l-.5.67-.5-.68C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM9 4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm11 15H4v-2h16v2zm0-5H4V8h5.08L7 10.83 8.62 12 12 7.4l3.38 4.6L17 10.83 14.92 8H20v6z"/>
                                    </svg>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value">${stats.month_deliveries}</div>
                                    <div class="stat-label">${translations[currentLanguage].thisMonth}</div>
                                </div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                                    <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                                    </svg>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value">${stats.avg_rating}</div>
                                    <div class="stat-label">${translations[currentLanguage].rating}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Performance Chart -->
                        <div class="stats-details" style="margin-bottom: 16px;">
                            <h3 class="stats-section-title">📈 ${translations[currentLanguage].development}</h3>
                            <div class="performance-chart">
                                <div class="chart-bar-container">
                                    <div class="chart-bar">
                                        <div class="chart-label">${translations[currentLanguage].today}</div>
                                        <div class="chart-bar-bg">
                                            <div class="chart-bar-fill" style="width: ${Math.max(5, (stats.today_deliveries / Math.max(1, stats.month_deliveries)) * 100)}%"></div>
                                        </div>
                                        <div class="chart-value">${stats.today_deliveries}</div>
                                    </div>
                                    <div class="chart-bar">
                                        <div class="chart-label">${translations[currentLanguage].week}</div>
                                        <div class="chart-bar-bg">
                                            <div class="chart-bar-fill" style="width: ${Math.max(5, (stats.week_deliveries / Math.max(1, stats.month_deliveries)) * 100)}%"></div>
                                        </div>
                                        <div class="chart-value">${stats.week_deliveries}</div>
                                    </div>
                                    <div class="chart-bar">
                                        <div class="chart-label">${translations[currentLanguage].month}</div>
                                        <div class="chart-bar-bg">
                                            <div class="chart-bar-fill" style="width: 100%"></div>
                                        </div>
                                        <div class="chart-value">${stats.month_deliveries}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Detailed Stats Section -->
                        <div class="stats-details">
                            <h3 class="stats-section-title">📊 ${translations[currentLanguage].performanceOverview}</h3>

                            <div class="stat-row">
                                <span class="stat-row-label">${translations[currentLanguage].totalDeliveries}</span>
                                <span class="stat-row-value">${stats.total_deliveries}</span>
                            </div>

                            <div class="stat-row">
                                <span class="stat-row-label">${translations[currentLanguage].successRate}</span>
                                <span class="stat-row-value">${stats.success_rate}%</span>
                            </div>

                            <div class="stat-row">
                                <span class="stat-row-label">${translations[currentLanguage].avgDeliveryTime}</span>
                                <span class="stat-row-value">${stats.avg_delivery_time}</span>
                            </div>

                            <div class="stat-row">
                                <span class="stat-row-label">${translations[currentLanguage].returnedDeposit}</span>
                                <span class="stat-row-value">€${stats.total_pfand_returned}</span>
                            </div>
                        </div>

                        <!-- Recent Ratings -->
                        ${stats.recent_ratings && stats.recent_ratings.length > 0 ? `
                            <div class="stats-details" style="margin-top: 16px;">
                                <h3 class="stats-section-title">⭐ ${translations[currentLanguage].recentRatings}</h3>
                                ${stats.recent_ratings.map(rating => `
                                    <div class="rating-item">
                                        <div class="rating-header">
                                            <div class="rating-stars">
                                                ${'★'.repeat(rating.stars)}${'☆'.repeat(5 - rating.stars)}
                                            </div>
                                            <div class="rating-date">${rating.date}</div>
                                        </div>
                                        ${rating.comment ? `<div class="rating-comment">${rating.comment}</div>` : ''}
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}
                    </div>

                    ${getBottomNavigation('leistung')}
                `;

                // Add CSS for performance stats
                addPerformanceStatsCSS();
            }

            function addPerformanceStatsCSS() {
                if (document.getElementById('performance-stats-css')) return;

                const style = document.createElementranslations[currentLanguage].style;
                style.id = 'performance-stats-css';
                style.textContent = `
                    .performance-stats-container {
                        padding: 16px;
                        padding-bottom: 90px;
                    }

                    .stats-grid {
                        display: grid;
                        grid-template-columns: repeat(2, 1fr);
                        gap: 12px;
                        margin-bottom: 20px;
                    }

                    .stat-card {
                        background: #2d2d2d;
                        border-radius: 12px;
                        padding: 16px;
                        display: flex;
                        align-items: center;
                        gap: 12px;
                    }

                    .stat-icon {
                        width: 48px;
                        height: 48px;
                        border-radius: 12px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        flex-shrink: 0;
                    }

                    .stat-content {
                        flex: 1;
                        min-width: 0;
                    }

                    .stat-value {
                        font-size: 24px;
                        font-weight: 700;
                        color: #ffffff;
                        line-height: 1.2;
                    }

                    .stat-label {
                        font-size: 12px;
                        color: #9CA3AF;
                        margin-top: 2px;
                    }

                    .stats-details {
                        background: #2d2d2d;
                        border-radius: 12px;
                        padding: 16px;
                    }

                    .stats-section-title {
                        font-size: 16px;
                        font-weight: 600;
                        color: #ffffff;
                        margin-bottom: 16px;
                    }

                    .stat-row {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        padding: 12px 0;
                        border-bottom: 1px solid #3d3d3d;
                    }

                    .stat-row:last-child {
                        border-bottom: none;
                    }

                    .stat-row-label {
                        color: #9CA3AF;
                        font-size: 14px;
                    }

                    .stat-row-value {
                        color: #ffffff;
                        font-size: 16px;
                        font-weight: 600;
                    }

                    .rating-item {
                        padding: 12px 0;
                        border-bottom: 1px solid #3d3d3d;
                    }

                    .rating-item:last-child {
                        border-bottom: none;
                    }

                    .rating-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 6px;
                    }

                    .rating-stars {
                        color: #fbbf24;
                        font-size: 16px;
                    }

                    .rating-date {
                        color: #9CA3AF;
                        font-size: 12px;
                    }

                    .rating-comment {
                        color: #d1d5db;
                        font-size: 14px;
                        line-height: 1.4;
                    }

                    .loading-screen-stats {
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        min-height: 400px;
                    }

                    .loading-spinner-stats {
                        width: 48px;
                        height: 48px;
                        border: 4px solid #3d3d3d;
                        border-top-color: #10b981;
                        border-radius: 50%;
                        animation: spin 0.8s linear infinite;
                    }

                    .loading-text {
                        margin-top: 16px;
                        color: #9CA3AF;
                        font-size: 14px;
                    }

                    @keyframes spin {
                        to { transform: rotate(360deg); }
                    }

                    /* Performance Chart Styles */
                    .performance-chart {
                        padding: 8px 0;
                    }

                    .chart-bar-container {
                        display: flex;
                        flex-direction: column;
                        gap: 16px;
                    }

                    .chart-bar {
                        display: grid;
                        grid-template-columns: 60px 1fr 40px;
                        align-items: center;
                        gap: 12px;
                    }

                    .chart-label {
                        color: #9CA3AF;
                        font-size: 13px;
                        text-align: left;
                    }

                    .chart-bar-bg {
                        background: #1a1a1a;
                        height: 32px;
                        border-radius: 6px;
                        overflow: hidden;
                        position: relative;
                    }

                    .chart-bar-fill {
                        background: linear-gradient(90deg, #10b981 0%, #059669 100%);
                        height: 100%;
                        border-radius: 6px;
                        transition: width 0.6s ease-out;
                        min-width: 5%;
                    }

                    .chart-value {
                        color: #ffffff;
                        font-size: 14px;
                        font-weight: 600;
                        text-align: right;
                    }
                `;
                document.head.appendChild(style);
            }

            function getBottomNavigation(hidePage = '') {
                return `
                    <div class="bottom-navigation">
                        <a href="#bestellungen" class="nav-item" onclick="showBestellungen(); return false;">
                            <div class="icon">
                                <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 2v3h8V2H8zM9 9l3 4 4-6 1 1.5L12 15 8 10l1-1z"/>
                                    <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.89-1.99 2L3 19a2 2 0 002 2h14c1.1 0 2-.9 2-2V5c0-1.11-.9-2-2-2zm0 16H5V8h14v11z"/>
                                </svg>
                            </div>
                            <div class="label">${translations[currentLanguage].orders}</div>
                        </a>
                        <a href="#karte" class="nav-item" onclick="showKarte(); return false;">
                            <div class="icon">
                                <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/>
                                </svg>
                            </div>
                            <div class="label">${translations[currentLanguage].map}</div>
                        </a>
                        <a href="#warten" class="nav-item" onclick="showWarten(); return false;">
                            <div class="icon">
                                <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M15 1H9v2h6V1zm-4 13h2V8h-2v6zm8.03-6.61l1.42-1.42c-.43-.51-.9-.99-1.41-1.41l-1.42 1.42C16.07 4.74 14.12 4 12 4c-4.97 0-9 4.03-9 9s4.02 9 9 9 9-4.03 9-9c0-2.12-.74-4.07-1.97-5.61zM12 20c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/>
                                </svg>
                            </div>
                            <div class="label">${translations[currentLanguage].waiting}</div>
                        </a>
                        ${hidePage !== 'leistung' ? `<a href="#leistung" class="nav-item active" onclick="showLeistung(); return false;">
                            <div class="icon">
                                <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M5 9.2h3V19H5zM10.6 5h2.8v14h-2.8zm5.6 8H19v6h-2.8z"/>
                                </svg>
                            </div>
                            <div class="label">${translations[currentLanguage].performance}</div>
                        </a>` : ''}
                        <a href="#packliste" class="nav-item" onclick="showPackliste(); return false;">
                            <div class="icon">
                                <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                                </svg>
                            </div>
                            <div class="label">${translations[currentLanguage].packlist}</div>
                        </a>
                    </div>
                `;
            }
            
            function displayMobileProfile(profileData) {
                mainContent = document.querySelector('.main-content');
                
                // Create mobile profile HTML
                mainContent.innerHTML = `
                    <div class="mobile-profile">
                        <div class="profile-content-mobile">
                            <div class="profile-avatar-mobile">
                                <div class="avatar-circle-mobile">
                                    ${profileData.initials}
                                </div>
                            </div>
                            
                            <h2 class="driver-name-mobile">${profileData.name}</h2>
                            
                            <div class="profile-fields-mobile">
                                <div class="field-group-mobile">
                                    <label>E-Mail</label>
                                    <input type="email" value="${profileData.email}" readonly>

            
            function displayMobileProfile(profileData) {
                mainContent = document.querySelector('.main-content');
                
                // Create mobile profile HTML
                mainContent.innerHTML = `
                    <div class="mobile-profile">
                        <div class="profile-content-mobile">
                            <div class="profile-avatar-mobile">
                                <div class="avatar-circle-mobile">
                                    ${profileData.initials}
                                </div>
                            </div>
                            
                            <h2 class="driver-name-mobile">${profileData.name}</h2>
                            
                            <div class="profile-fields-mobile">
                                <div class="field-group-mobile">
                                    <label>E-Mail</label>
                                    <input type="email" value="${profileData.email}" readonly>
                                </div>
                                
                                <div class="field-group-mobile">
                                    <label>Telefon</label>
                                    <input type="tel" id="mobile_phone" value="${profileData.phone || ''}">
                                </div>
                                
                                <div class="field-group-mobile">
                                    <label>Fahrzeug</label>
                                    <select id="mobile_vehicle">
                                        <option value="pkw" ${profileData.vehicle === 'pkw' ? 'selected' : ''}>PKW</option>
                                        <option value="lkw" ${profileData.vehicle === 'lkw' ? 'selected' : ''}>LKW</option>
                                        <option value="transporter" ${profileData.vehicle === 'transporter' ? 'selected' : ''}>TRANSPORTER</option>
                                        <option value="motorrad" ${profileData.vehicle === 'motorrad' ? 'selected' : ''}>MOTORRAD</option>
                                    </select>
                                </div>
                                
                                <div class="field-group-mobile">
                                    <label>Stadt</label>
                                    <input type="text" id="mobile_city" value="${profileData.city || ''}">
                                </div>
                            </div>
                            
                            <div class="profile-stats-mobile">
                                <div class="stat-item-mobile">
                                    <div class="stat-number-mobile">${profileData.today_orders || 0}</div>
                                    <div class="stat-label-mobile">Aktive Aufträge</div>
                                </div>
                                <div class="stat-item-mobile">
                                    <div class="stat-number-mobile">${profileData.rating_statistics?.average ? profileData.rating_statistics.average.toFixed(1) + ' ⭐' : (profileData.rating || '---')}</div>
                                    <div class="stat-label-mobile">Bewertung</div>
                                </div>
                                <div class="stat-item-mobile">
                                    <div class="stat-number-mobile" id="mobile-status-display">${profileData.online_status || 'Offline'}</div>
                                    <div class="stat-label-mobile">Status</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <style>
                    .mobile-profile {
                        background: #1a1a1a;
                        color: #ffffff;
                        min-height: calc(100vh - 60px);
                    }

                    .profile-content-mobile {
                        padding: 40px 20px;
                        text-align: center;
                    }
                    
                    .profile-avatar-mobile {
                        margin-bottom: 20px;
                    }
                    
                    .avatar-circle-mobile {
                        width: 120px;
                        height: 120px;
                        border-radius: 50%;
                        background: #10b981;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 48px;
                        font-weight: bold;
                        color: #ffffff;
                    }
                    
                    .driver-name-mobile {
                        font-size: 28px;
                        font-weight: 600;
                        margin: 0 0 40px 0;
                        color: #ffffff;
                    }
                    
                    .profile-fields-mobile {
                        text-align: left;
                        margin-bottom: 40px;
                    }
                    
                    .field-group-mobile {
                        margin-bottom: 24px;
                    }
                    
                    .field-group-mobile label {
                        display: block;
                        margin-bottom: 8px;
                        color: #ffffff;
                        font-size: 14px;
                        font-weight: 500;
                    }
                    
                    .field-group-mobile input, .field-group-mobile select {
                        width: 100%;
                        padding: 16px;
                        background: #2a2a2a;
                        border: 1px solid #333;
                        border-radius: 8px;
                        color: #ffffff;
                        font-size: 16px;
                        box-sizing: border-box;
                    }
                    
                    .profile-stats-mobile {
                        display: flex;
                        justify-content: space-around;
                        padding: 20px;
                        background: #2a2a2a;
                        border-radius: 12px;
                    }
                    
                    .stat-item-mobile {
                        text-align: center;
                    }
                    
                    .stat-number-mobile {
                        font-size: 24px;
                        font-weight: bold;
                        color: #10b981;
                        margin-bottom: 4px;
                    }
                    
                    .stat-label-mobile {
                        font-size: 12px;
                        color: #888;
                        text-transform: uppercase;
                    }
                    </style>
                `;
                
                // Update status dynamically
                updateMobileDriverStatus();
            }
            
            function updateMobileDriverStatus() {
                const isOnline = localStorage.getItem('driver_online_status') === 'true';
                const statusElement = document.getElementById('mobile-status-display');
                
                if (statusElement) {
                    if (isOnline) {
                        statusElement.textContent = 'Online';
                        statusElement.style.color = '#10b981';
                    } else {
                        statusElement.textContent = 'Offline';
                        statusElement.style.color = '#ef4444';
                    }
                }
            }
            
            function saveMobileProfile() {
                const data = {
                    action: 'dispatch_update_driver_profile',
                    driver_id: <?php echo get_current_user_id(); ?>,
                    driver_phone: document.getElementById('mobile_phone').value,
                    driver_vehicle: document.getElementById('mobile_vehicle').value,
                    driver_city: document.getElementById('mobile_city').value,
                    nonce: '<?php echo wp_create_nonce("dispatch_nonce"); ?>'
                };
                
                fetch(dispatch_ajax.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: Object.keys(data).map(key => key + '=' + encodeURIComponent(data[key])).join('&')
                })
                .then(response => response.json())
                .then(response => {
                    if (response.success) {
                        alert('Profil gespeichert!');
                    } else {
                        alert('Fehler beim Speichern: ' + (response.data || 'Unbekannter Fehler'));
                    }
                });
            }
            
            
            // Immediate debug log
            
            // Initialize Event-Listener nach DOM-Load
            document.addEventListener('DOMContentLoaded', function() {
                
                // Event-Listener für Online-Button
                const onlineButton = document.getElementById('onlineToggleLarge');
                
                // Initialize button events
                const allButtons = document.querySelectorAll('button');
                // Button initialization complete
                
                if (onlineButton) {
                    onlineButton.addEventListener('click', function() {
                        toggleOnlineStatus();
                    });
                } else {
                    console.error('❌ Online button not found on DOMContentLoaded');
                    
                    // List all elements with ID for debugging
                    const allElements = document.querySelectorAll('*[id]');
                }
                
                // Initialer Status-Check kann hier hinzugefügt werden wenn benötigt
            });
            
            // Also try immediate binding in case DOM is already loaded
            setTimeout(function() {
                const onlineButton = document.getElementById('onlineToggleLarge');
                if (onlineButton && !onlineButton.hasAttribute('data-listener-bound')) {
                    // Add both click and touchend for mobile compatibility
                    onlineButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        toggleOnlineStatus();
                    });
                    onlineButton.addEventListener('touchend', function(e) {
                        e.preventDefault();
                        toggleOnlineStatus();
                    });
                    onlineButton.setAttribute('data-listener-bound', 'true');
                    console.log('Online button event listeners attached');
                }
            }, 1000);

            // PWA Service Worker Registration
            // ENABLED: Handles push notifications and offline functionality
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('<?php echo plugin_dir_url(__FILE__); ?>pwa/service-worker.js?v=<?php echo DISPATCH_VERSION; ?>')
                        .then(registration => {
                            console.log('Service Worker registriert:', registration.scope);

                            // Check for updates every 5 minutes with error handling
                            setInterval(() => {
                                // Check if registration exists and has proper state
                                if (registration && registration.active) {
                                    registration.update().catch(error => {
                                        // Silently handle update errors - this is normal behavior
                                        if (error.name !== 'InvalidStateError') {
                                            console.debug('Service Worker update check:', error.message);
                                        }
                                    });
                                }
                            }, 300000);

                            // Auto-check push subscription for online drivers
                            if (localStorage.getItem('driver_online_status') === 'true') {
                                setTimeout(async () => {
                                    console.log('Checking push subscription status...');

                                    // Check if notification permission is granted
                                    if (Notification.permission === 'granted') {
                                        console.log('Notification permission already granted');

                                        // Check if we have a push subscription
                                        try {
                                            const subscription = await registration.pushManager.getSubscription();
                                            if (!subscription) {
                                                console.log('No push subscription found, subscribing...');
                                                if (typeof subscribeToPush === 'function') {
                                                    subscribeToPush(registration);
                                                }
                                            } else {
                                                console.log('Push subscription exists:', subscription.endpoint);
                                            }
                                        } catch (error) {
                                            console.error('Error checking subscription:', error);
                                        }
                                    } else if (Notification.permission === 'default') {
                                        console.log('Notification permission not yet requested');
                                        // Show friendly reminder banner
                                        showPushReminder(registration);
                                    } else {
                                        console.log('Notification permission denied');
                                    }
                                }, 3000); // Check 3 seconds after page load
                            }
                        })
                        .catch(error => {
                            console.log('Service Worker Registrierung fehlgeschlagen:', error);
                        });
                });

                // Install prompt for PWA
                let deferredPrompt;
                window.addEventListener('beforeinstallprompt', (e) => {
                    e.preventDefault();
                    deferredPrompt = e;

                    // Show install button after 30 seconds
                    setTimeout(() => {
                        if (deferredPrompt && !window.matchMedia('(display-mode: standalone)').matches) {
                            // Create install banner
                            const installBanner = document.createElement('div');
                            installBanner.id = 'pwa-install-banner';
                            installBanner.innerHTML = `
                                <div style="
                                    position: fixed;
                                    bottom: 80px;
                                    left: 50%;
                                    transform: translateX(-50%);
                                    background: #10b981;
                                    color: white;
                                    padding: 15px 20px;
                                    border-radius: 12px;
                                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                                    z-index: 10000;
                                    display: flex;
                                    align-items: center;
                                    gap: 15px;
                                    max-width: 90%;
                                    font-size: 14px;
                                ">
                                    <div>App installieren für bessere Erfahrung!</div>
                                    <button onclick="installPWA()" style="
                                        background: white;
                                        color: #10b981;
                                        border: none;
                                        padding: 8px 16px;
                                        border-radius: 6px;
                                        font-weight: bold;
                                        cursor: pointer;
                                    ">Installieren</button>
                                    <button onclick="dismissInstall()" style="
                                        background: transparent;
                                        color: white;
                                        border: 1px solid white;
                                        padding: 8px 16px;
                                        border-radius: 6px;
                                        cursor: pointer;
                                    ">Später</button>
                                </div>
                            `;
                            document.body.appendChild(installBanner);
                        }
                    }, 30000); // Show after 30 seconds
                });

                // Install function
                window.installPWA = async () => {
                    if (deferredPrompt) {
                        deferredPrompt.prompt();
                        const { outcome } = await deferredPrompt.userChoice;
                        console.log(`User response: ${outcome}`);
                        deferredPrompt = null;
                        const banner = document.getElementById('pwa-install-banner');
                        if (banner) banner.remove();
                    }
                };

                // Dismiss install banner
                window.dismissInstall = () => {
                    const banner = document.getElementById('pwa-install-banner');
                    if (banner) banner.remove();
                    // Don't show again for 7 days
                    localStorage.setItem('pwa_install_dismissed', Date.now());
                };

                // Check if we should show install prompt
                const dismissed = localStorage.getItem('pwa_install_dismissed');
                if (dismissed) {
                    const daysSinceDismissed = (Date.now() - parseInt(dismissed)) / (1000 * 60 * 60 * 24);
                    if (daysSinceDismissed < 7) {
                        // Don't show if dismissed within last 7 days
                        deferredPrompt = null;
                    }
                }
            }
            // End of PWA SW registration

                // Push Notification Functions (already defined above)
                // Commenting out duplicate definition
                /*
                async function requestPushPermission(registration) {
                    // Check if Notification API is available
                    if (!('Notification' in window)) {
                        console.warn('Notification API not available in this browser');
                        return;
                    }

                    // Check if already permitted
                    if (Notification.permission === 'granted') {
                        console.log('Push-Benachrichtigungen bereits erlaubt');
                        subscribeToPush(registration);
                        return;
                    }

                    // Check if already denied
                    if (Notification.permission === 'denied') {
                        console.log('Push-Benachrichtigungen wurden abgelehnt');
                        return;
                    }

                    // Show custom permission request
                    const permissionBanner = document.createElement('div');
                    permissionBanner.id = 'push-permission-banner';
                    permissionBanner.innerHTML = `
                        <div style="
                            position: fixed;
                            top: 80px;
                            left: 50%;
                            transform: translateX(-50%);
                            background: #3b82f6;
                            color: white;
                            padding: 15px 20px;
                            border-radius: 12px;
                            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                            z-index: 10000;
                            max-width: 90%;
                            font-size: 14px;
                        ">
                            <div style="margin-bottom: 10px;">
                                <strong>🔔 Benachrichtigungen aktivieren</strong>
                            </div>
                            <div style="margin-bottom: 15px;">
                                Erhalte Push-Benachrichtigungen bei neuen Bestellungen
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <button onclick="allowPushNotifications()" style="
                                    background: white;
                                    color: #3b82f6;
                                    border: none;
                                    padding: 8px 16px;
                                    border-radius: 6px;
                                    font-weight: bold;
                                    cursor: pointer;
                                    flex: 1;
                                ">Erlauben</button>
                                <button onclick="denyPushNotifications()" style="
                                    background: transparent;
                                    color: white;
                                    border: 1px solid white;
                                    padding: 8px 16px;
                                    border-radius: 6px;
                                    cursor: pointer;
                                    flex: 1;
                                ">Später</button>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(permissionBanner);

                    // Allow function
                    window.allowPushNotifications = async () => {
                        const permission = await Notification.requestPermission();
                        const banner = document.getElementById('push-permission-banner');
                        if (banner) banner.remove();

                        if (permission === 'granted') {
                            console.log('Push-Benachrichtigungen erlaubt!');
                            subscribeToPush(registration);
                            showNotificationToast('✅ Push-Benachrichtigungen aktiviert!', 'success');
                        }
                    };

                    // Deny function
                    window.denyPushNotifications = () => {
                        const banner = document.getElementById('push-permission-banner');
                        if (banner) banner.remove();
                        // Ask again in 3 days
                        localStorage.setItem('push_permission_denied', Date.now());
                    };
                }

                async function subscribeToPush(registration) {
                    try {
                        // VAPID public key (base64 to Uint8Array)
                        if (!vapidPublicKey) {
                            console.error('VAPID public key not configured');
                            return;
                        }
                        const convertedVapidKey = urlBase64ToUint8Array(vapidPublicKey);

                        // Subscribe to push
                        const subscription = await registration.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: convertedVapidKey
                        });

                        console.log('Push Subscription:', subscription);

                        // Send subscription to server
                        await fetch(dispatch_ajax.ajax_url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=save_push_subscription&nonce=' + dispatch_ajax.nonce +
                                  '&subscription=' + encodeURIComponent(JSON.stringify(subscription)),
                            credentials: 'same-origin'
                        });

                        console.log('Push-Subscription gespeichert!');
                    } catch (error) {
                        console.error('Push-Subscription fehlgeschlagen:', error);
                    }
                }

                function urlBase64ToUint8Array(base64String) {
                    const padding = '='.repeat((4 - base64String.length % 4) % 4);
                    const base64 = (base64String + padding)
                        .replace(/\-/g, '+')
                        .replace(/_/g, '/');

                    const rawData = window.atob(base64);
                    const outputArray = new Uint8Array(rawData.length);

                    for (let i = 0; i < rawData.length; ++i) {
                        outputArray[i] = rawData.charCodeAt(i);
                    }
                    return outputArray;
                }
                */ // End of commented duplicate functions

            </script>
        </body>
        </html>
        <?php
        
        } catch (Exception $e) {
            // Clean any output that might have been buffered
            ob_end_clean();
            
            // Log the error for debugging
            
            // Display user-friendly error
            wp_die('Dashboard konnte nicht geladen werden. Bitte versuchen Sie es später erneut.');
        }
        
        // Flush the output buffer
        ob_end_flush();
    }
    
    /**
     * Render Driver Order Details
     */
