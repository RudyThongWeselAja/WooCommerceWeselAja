<?php
/**
 * Refresh WooCommerce Payment Gateways Cache
 * Run this after making changes to payment gateways
 */

// Load WordPress
require_once '../../../wp-load.php';

if (!current_user_can('administrator')) {
    wp_die('Access denied. Administrator role required.');
}

echo '<h1>WooCommerce Cache Refresh</h1>';
echo '<style>body{font-family:Arial,sans-serif;} .info{background:#e7f3ff;padding:10px;margin:10px 0;border-left:4px solid #2196F3;} .success{background:#e8f5e8;padding:10px;margin:10px 0;border-left:4px solid #4CAF50;}</style>';

// 1. Clear WooCommerce cache
if (function_exists('wc_clear_template_cache')) {
    wc_clear_template_cache();
    echo '<div class="success">✓ WooCommerce template cache cleared</div>';
}

// 2. Delete transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'");
echo '<div class="success">✓ All transients cleared</div>';

// 3. Reload payment gateways
if (class_exists('WC_Payment_Gateways')) {
    WC()->payment_gateways()->init();
    echo '<div class="success">✓ Payment gateways reinitialized</div>';
}

// 4. Force plugin reload
do_action('init');
do_action('plugins_loaded');
echo '<div class="success">✓ Plugins reloaded</div>';

// 5. Check current status
echo '<h2>Current Status Check</h2>';
$gateways = WC()->payment_gateways()->payment_gateways();
$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

echo '<div class="info">Total registered gateways: ' . count($gateways) . '</div>';
echo '<div class="info">Available gateways: ' . count($available_gateways) . '</div>';

if (isset($gateways['xenithpay'])) {
    $xenith = $gateways['xenithpay'];
    echo '<div class="success">✓ XenithPay gateway found</div>';
    echo '<div class="info">Enabled: ' . $xenith->enabled . '</div>';
    echo '<div class="info">Available: ' . ($xenith->is_available() ? 'YES' : 'NO') . '</div>';
    
    if (isset($available_gateways['xenithpay'])) {
        echo '<div class="success">✓ XenithPay is available for checkout</div>';
    } else {
        echo '<div class="info">XenithPay registered but not available for checkout</div>';
    }
} else {
    echo '<div class="error">✗ XenithPay gateway not found</div>';
}

echo '<hr>';
echo '<p>Cache refresh completed. Try accessing your checkout page now.</p>';
echo '<p><a href="' . home_url('/checkout/') . '">Go to Checkout</a> | <a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">Payment Settings</a></p>';
?>