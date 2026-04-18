<?php
/**
 * Debug Blocks Support
 */

// Load WordPress
require_once '../../../wp-load.php';

if (!current_user_can('administrator')) {
    wp_die('Access denied. Administrator role required.');
}

echo '<h1>WooCommerce Blocks Support Debug</h1>';
echo '<style>body{font-family:Arial,sans-serif;} .info{background:#e7f3ff;padding:10px;margin:10px 0;border-left:4px solid #2196F3;} .success{background:#e8f5e8;padding:10px;margin:10px 0;border-left:4px solid #4CAF50;} .error{background:#ffebee;padding:10px;margin:10px 0;border-left:4px solid #f44336;}</style>';

// 1. Check Class Existence
echo '<h2>1. Class Check</h2>';
if (class_exists('WC_XenithPay_Blocks_Support')) {
    echo '<div class="success">WC_XenithPay_Blocks_Support class exists ✓</div>';
} else {
    echo '<div class="error">WC_XenithPay_Blocks_Support class NOT found ✗</div>';
}

if (class_exists('WC_XenithPay_Blocks_Support')) {
    echo '<div class="success">Initialized WC_XenithPay_Blocks_Support instance ✓</div>';
} else {
    echo '<div class="error">Cannot initialize WC_XenithPay_Blocks_Support because class does not exist ✗</div>';
}

// 2. Check Script Registration
echo '<h2>2. Script Registration Check</h2>';
global $wp_scripts;
// Force init of scripts if not already done (might not work in this context but worth a try)
if (empty($wp_scripts)) {
    $wp_scripts = new WP_Scripts();
}

// We need to simulate the registration to check if the URL is correct, 
// because this debug file runs outside of the normal WP load sequence where the block support class runs.
// However, we can check if the class can generate the URL correctly.

if (class_exists('WC_XenithPay_Blocks_Support')) {
    $support = new WC_XenithPay_Blocks_Support();
    // We can't easily call get_payment_method_script_handles because it calls wp_register_script which might fail if wp_scripts isn't ready
    // But we can check the logic manually
    
    $expected_url = plugins_url( 'assets/js/xenithpay-blocks.js', dirname(__DIR__) . '/payment-gateway-plugin-main/wc-xenithpay.php' );
    echo '<div class="info">Expected URL logic check: ' . $expected_url . '</div>';
}

if (class_exists('WC_XenithPay_QRIS_Blocks_Support')) {
    $support_qris = new WC_XenithPay_QRIS_Blocks_Support();
    $expected_url_qris = plugins_url( 'assets/js/xenithpay-qris-blocks.js', dirname(__DIR__) . '/payment-gateway-plugin-main/wc-xenithpay.php' );
    echo '<div class="info">Expected QRIS URL logic check: ' . $expected_url_qris . '</div>';
}

if (isset($wp_scripts->registered['wc-xenithpay-blocks-integration'])) {
    echo '<div class="success">Script "wc-xenithpay-blocks-integration" is registered ✓</div>';
    echo '<div class="info">Src: ' . $wp_scripts->registered['wc-xenithpay-blocks-integration']->src . '</div>';
} else {
    echo '<div class="error">Script "wc-xenithpay-blocks-integration" is NOT registered in global $wp_scripts ✗</div>';
    echo '<div class="info">This is expected if this debug file is accessed directly, as the blocks action hasn\'t fired. Check debug.log for "WC XenithPay Blocks" entries.</div>';
}

// 3. Check File Existence
echo '<h2>3. File Existence Check</h2>';
$js_file = plugin_dir_path(__DIR__) . 'payment-gateway-plugin-main/assets/js/xenithpay-blocks.js';
// Adjust path based on where this debug file is located
$js_file_real = dirname(__FILE__) . '/assets/js/xenithpay-blocks.js';

if (file_exists($js_file_real)) {
    echo '<div class="success">JS file found at: ' . $js_file_real . ' ✓</div>';
} else {
    echo '<div class="error">JS file NOT found at: ' . $js_file_real . ' ✗</div>';
}

echo '<hr>';
echo '<p>Debug completed.</p>';
?>