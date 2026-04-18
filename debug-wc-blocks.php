<?php
/**
 * Debug WooCommerce Blocks Environment
 */
require_once __DIR__ . '/../../../wp-load.php';

if (php_sapi_name() !== 'cli' && !current_user_can('administrator')) {
    wp_die('Access denied.');
}

echo '<h1>WooCommerce Blocks Debug</h1>';

// 1. Check WooCommerce Version
echo '<h2>1. WooCommerce Version</h2>';
if (class_exists('WooCommerce')) {
    echo 'WooCommerce Version: ' . WC()->version . '<br>';
} else {
    echo 'WooCommerce NOT active.<br>';
}

// 2. Check AbstractPaymentMethodType Class
echo '<h2>2. AbstractPaymentMethodType Class</h2>';
$class_name = 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType';
if (class_exists($class_name)) {
    echo "Class $class_name EXISTS.<br>";
} else {
    echo "Class $class_name does NOT exist.<br>";
}

// 3. Check PaymentMethodRegistry Class
echo '<h2>3. PaymentMethodRegistry Class</h2>';
$registry_class = 'Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry';
if (class_exists($registry_class)) {
    echo "Class $registry_class EXISTS.<br>";
} else {
    echo "Class $registry_class does NOT exist.<br>";
}

// 4. Check Hook Firing (Simulated)
echo '<h2>4. Hook Check</h2>';
echo 'Has "woocommerce_blocks_loaded" action been fired? ' . (did_action('woocommerce_blocks_loaded') ? 'YES' : 'NO') . '<br>';
echo 'Has "woocommerce_blocks_payment_method_type_registration" action been fired? ' . (did_action('woocommerce_blocks_payment_method_type_registration') ? 'YES' : 'NO') . '<br>';

// 5. Check Registered Scripts & Dependencies
echo '<h2>5. Registered Scripts & Dependencies</h2>';
global $wp_scripts;
if (empty($wp_scripts)) {
    $wp_scripts = new WP_Scripts();
}

$deps_to_check = array('wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n');
foreach ($deps_to_check as $dep) {
    if (isset($wp_scripts->registered[$dep])) {
        echo "Dependency '$dep' is registered.<br>";
    } else {
        echo "Dependency '$dep' is NOT registered.<br>";
    }
}

if (isset($wp_scripts->registered['wc-xenithpay-blocks-integration'])) {
    echo 'Script "wc-xenithpay-blocks-integration" IS registered.<br>';
    echo 'URL: ' . $wp_scripts->registered['wc-xenithpay-blocks-integration']->src . '<br>';
} else {
    echo 'Script "wc-xenithpay-blocks-integration" is NOT registered.<br>';
}
?>