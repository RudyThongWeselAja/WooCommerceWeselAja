<?php
/**
 * Debug Payment Gateways - untuk troubleshooting
 * 
 * Akses file ini melalui browser: 
 * http://your-site.com/wp-content/plugins/payment-gateway-plugin-main/debug-payment-gateways.php
 */

// Load WordPress
require_once '../../../wp-load.php';

if (!current_user_can('administrator')) {
    wp_die('Access denied. Administrator role required.');
}

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
    echo '<h1>WooCommerce Debug</h1>';
    echo '<p style="color: red;">WooCommerce is NOT active!</p>';
    exit;
}

echo '<h1>Payment Gateway Debug Information</h1>';
echo '<style>body{font-family:Arial,sans-serif;} .info{background:#e7f3ff;padding:10px;margin:10px 0;border-left:4px solid #2196F3;} .error{background:#ffebee;padding:10px;margin:10px 0;border-left:4px solid #f44336;} .success{background:#e8f5e8;padding:10px;margin:10px 0;border-left:4px solid #4CAF50;}</style>';

// 1. Check WooCommerce
echo '<h2>1. WooCommerce Status</h2>';
echo '<div class="success">WooCommerce is active ✓</div>';
echo '<div class="info">WooCommerce Version: ' . WC()->version . '</div>';

// 2. Check if our plugin is loaded
echo '<h2>2. XenithPay Plugin Status</h2>';
if (class_exists('WC_Gateway_XenithPay')) {
    echo '<div class="success">XenithPay Gateway Class exists ✓</div>';
} else {
    echo '<div class="error">XenithPay Gateway Class NOT found ✗</div>';
}

// 3. Check registered payment gateways
echo '<h2>3. All Registered Payment Gateways</h2>';
$gateways = WC()->payment_gateways()->payment_gateways();
echo '<div class="info">Total registered gateways: ' . count($gateways) . '</div>';

foreach ($gateways as $gateway_id => $gateway) {
    $is_xenith = ($gateway_id === 'xenithpay');
    $class_name = get_class($gateway);
    $enabled = $gateway->enabled;
    $available = $gateway->is_available();
    
    echo '<div class="' . ($is_xenith ? 'success' : 'info') . '">';
    echo '<strong>' . $gateway_id . '</strong> (' . $class_name . ')<br>';
    echo 'Title: ' . $gateway->get_title() . '<br>';
    echo 'Enabled: ' . ($enabled === 'yes' ? 'YES' : 'NO') . '<br>';
    echo 'Available: ' . ($available ? 'YES' : 'NO') . '<br>';
    if ($is_xenith) {
        echo 'API Key: ' . (empty($gateway->api_key) ? 'NOT SET' : (($gateway->api_key === 'YOUR_API_KEY_HERE') ? 'DEFAULT/NOT CONFIGURED' : 'SET')) . '<br>';
        echo 'Secret Key: ' . (empty($gateway->secret_key) ? 'NOT SET' : (($gateway->secret_key === 'YOUR_SECRET_KEY_HERE') ? 'DEFAULT/NOT CONFIGURED' : 'SET')) . '<br>';
    }
    echo '</div>';
}

// 4. Check XenithPay specific
echo '<h2>4. XenithPay Gateway Detailed Check</h2>';
if (isset($gateways['xenithpay'])) {
    $xenith_gateway = $gateways['xenithpay'];
    echo '<div class="success">XenithPay gateway found in registered gateways ✓</div>';
    
    // Check settings
    echo '<h3>Settings:</h3>';
    $settings = $xenith_gateway->settings;
    echo '<div class="info"><pre>' . print_r($settings, true) . '</pre></div>';
    
    // Check why not available (if not available)
    if (!$xenith_gateway->is_available()) {
        echo '<div class="error">Gateway is NOT available. Checking reasons:</div>';
        $enabled = $xenith_gateway->enabled;
        $api_key = $xenith_gateway->api_key;
        $secret_key = $xenith_gateway->secret_key;
        
        echo '<div class="info">';
        echo 'Enabled setting: ' . $enabled . '<br>';
        echo 'API Key: ' . $api_key . '<br>';
        echo 'Secret Key: ' . (empty($secret_key) ? 'EMPTY' : 'SET') . '<br>';
        echo '</div>';
        
        if ($enabled !== 'yes') {
            echo '<div class="error">❌ Gateway is disabled in settings</div>';
        }
        if (empty($api_key) || $api_key === 'YOUR_API_KEY_HERE') {
            echo '<div class="error">❌ API Key not configured properly</div>';
        }
        if (empty($secret_key) || $secret_key === 'YOUR_SECRET_KEY_HERE') {
            echo '<div class="error">❌ Secret Key not configured properly</div>';
        }
    } else {
        echo '<div class="success">Gateway is available for use ✓</div>';
    }
} else {
    echo '<div class="error">XenithPay gateway NOT found in registered gateways ✗</div>';
}

// 5. Check available gateways for checkout
echo '<h2>5. Available Gateways for Checkout</h2>';
$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
echo '<div class="info">Available gateways for checkout: ' . count($available_gateways) . '</div>';

if (empty($available_gateways)) {
    echo '<div class="error">NO payment gateways are available for checkout!</div>';
    echo '<div class="info">This is why you see "There are no payment methods available"</div>';
} else {
    foreach ($available_gateways as $gateway_id => $gateway) {
        $is_xenith = ($gateway_id === 'xenithpay');
        echo '<div class="' . ($is_xenith ? 'success' : 'info') . '">';
        echo '<strong>' . $gateway_id . '</strong>: ' . $gateway->get_title();
        echo '</div>';
    }
}

// 6. Quick fix suggestions
echo '<h2>6. Quick Fix Suggestions</h2>';
if (!isset($gateways['xenithpay'])) {
    echo '<div class="error">❌ XenithPay gateway not registered. Check if plugin is active and files exist.</div>';
} elseif (!$gateways['xenithpay']->is_available()) {
    echo '<div class="error">❌ XenithPay gateway registered but not available. Configure API keys in WooCommerce > Settings > Payments > XenithPay</div>';
} elseif (!isset($available_gateways['xenithpay'])) {
    echo '<div class="error">❌ XenithPay gateway available but not showing in checkout. Check cart/checkout requirements.</div>';
} else {
    echo '<div class="success">✅ XenithPay gateway should be working correctly!</div>';
}

// 7. Recent error logs
echo '<h2>7. Recent Error Logs (XenithPay related)</h2>';
$log_file = WP_CONTENT_DIR . '/debug.log';
if (file_exists($log_file)) {
    $logs = file_get_contents($log_file);
    $lines = explode("\n", $logs);
    $xenith_logs = array_filter($lines, function($line) {
        return strpos($line, 'XenithPay') !== false || strpos($line, 'WC XenithPay') !== false;
    });
    
    if (!empty($xenith_logs)) {
        $recent_logs = array_slice($xenith_logs, -20); // Last 20 XenithPay related logs
        echo '<div class="info"><pre>' . implode("\n", $recent_logs) . '</pre></div>';
    } else {
        echo '<div class="info">No XenithPay related logs found recently.</div>';
    }
} else {
    echo '<div class="error">Debug log file not found at: ' . $log_file . '</div>';
}

echo '<hr>';
echo '<p><em>Debug completed at: ' . date('Y-m-d H:i:s') . '</em></p>';
echo '<p><a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=xenithpay') . '">Go to XenithPay Settings</a></p>';
?>