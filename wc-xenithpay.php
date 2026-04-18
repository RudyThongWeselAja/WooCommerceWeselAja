<?php
/**
 * Plugin Name: WooCommerce XenithPay
 * Description: Custom payment gateway for WooCommerce integrating XenithPay.
 * Version:     1.0.2
 * Author:      XenithPay
 * Author URI:  https://xenithpay.com
 * License:     GPLv3
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WC_XENITH_MAIN_FILE', __FILE__);

/**
 * Run init slightly after default so WooCommerce has a chance to load.
 * Priority 11 is safer than 0 for normal initialization.
 */
add_action('plugins_loaded', 'wc_xenithpay_init', 11);

add_action('woocommerce_api_wc_xenith_callback', function () {
    global $xenith_raw_body;
    $xenith_raw_body = file_get_contents('php://input');
}, 1);

add_action('woocommerce_api_wc_xenith_callback', 'wc_xenith_handle_webhook', 10);

function wc_xenithpay_init() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('WC XenithPay: Initializing XenithPay Gateway plugin.');
        error_log('WC XenithPay: WooCommerce active? ' . (class_exists('WooCommerce') ? 'YES' : 'NO'));
        error_log('WC XenithPay: WC_Payment_Gateway exists? ' . (class_exists('WC_Payment_Gateway') ? 'YES' : 'NO'));
    }
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', 'wc_xenithpay_woocommerce_missing_notice');
        error_log('WC XenithPay: WooCommerce not active or WC_Payment_Gateway not found.');
        return;
    }

    $file = plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-xenithpay.php';
    if (!file_exists($file)) {
        error_log('WC XenithPay: includes file not found: ' . $file);
        add_action('admin_notices', function() use ($file) {
            echo '<div class="notice notice-error"><p>WC XenithPay: missing file ' . esc_html($file) . '</p></div>';
        });
        return;
    }

    require_once $file;

    if (!class_exists('WC_Gateway_XenithPay')) {
        error_log('WC XenithPay: class WC_Gateway_XenithPay not found after include.');
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>WC XenithPay: gateway class not found. Periksa includes/class-wc-gateway-xenithpay.php</p></div>';
        });
        return;
    }

    add_filter('woocommerce_payment_gateways', 'wc_xenithpay_add_gateway');
}

function wc_xenithpay_add_gateway($methods) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('WC XenithPay: Adding gateway to WooCommerce payment methods.');
        error_log('WC XenithPay: Current methods count: ' . count($methods));
        error_log('WC XenithPay: Gateway class exists? ' . (class_exists('WC_Gateway_XenithPay') ? 'YES' : 'NO'));
    }
    $methods[] = 'WC_Gateway_XenithPay';
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('WC XenithPay: Methods after adding XenithPay: ' . count($methods));
    }
    return $methods;
}

// Debug available gateways
add_filter('woocommerce_available_payment_gateways', 'wc_xenithpay_debug_available_gateways');
function wc_xenithpay_debug_available_gateways($available_gateways) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('WC XenithPay: Checking available gateways filter.');
        if (isset($available_gateways['xenithpay'])) {
            error_log('WC XenithPay: Gateway IS in available_gateways list.');
        } else {
            error_log('WC XenithPay: Gateway is NOT in available_gateways list.');
            $gateway = new WC_Gateway_XenithPay();
            error_log('WC XenithPay: Manual check - Enabled: ' . $gateway->enabled);
            error_log('WC XenithPay: Manual check - Is Available: ' . ($gateway->is_available() ? 'YES' : 'NO'));
        }
    }
    return $available_gateways;
}

/**
 * Webhook handler
 * - Uses early-captured raw body from $GLOBALS['xenith_raw_body'] if available
 * - Builds canonical payload using literal "\n"
 * - Validates HMAC SHA256, base64 encoded
 */
function wc_xenith_handle_webhook() {
    try {
        // Load settings (gateway settings saved by WooCommerce admin)
        $settings = get_option('woocommerce_xenithpay_settings', array());
        error_log("[Xenith] Webhook received.");
        $webhook_secret_key = isset($settings['webhook_secret_key']) ? $settings['webhook_secret_key'] : '';

        if (empty($webhook_secret_key)) {
            error_log("[Xenith] Missing webhook signing key");
            status_header(400);
            echo "Webhook not configured";
            exit;
        }

        // Headers
        $signature  = $_SERVER['HTTP_X_XENITH_SIGNATURE'] ?? '';
        $timestamp  = $_SERVER['HTTP_X_XENITH_TIMESTAMP'] ?? '';

        if (!$signature || !$timestamp) {
            error_log("[Xenith] Missing signature headers");
            status_header(400);
            echo "Missing signature headers";
            exit;
        }

        // Get raw body from global captured earlier; fallback to php://input (last resort)
//         global $xenith_raw_body;
		$raw_body = file_get_contents('php://input');
		
//         // ensure we have something
//         if (!is_string($raw_body) || $raw_body === '') {
//             error_log("[Xenith] Missing webhook body (empty after capture).");
//             status_header(400);
//             echo 'Missing webhook body';
//             exit;
//         }

        // Method and uri used for canonical string
        $method = $_SERVER['REQUEST_METHOD'];
        // use captured request URI if available (preserves query string exactly)
        $uri = $_SERVER['REQUEST_URI'];
		error_log('[Xenith] SERVER REQUEST_URI = ' . $uri);
		error_log('[Xenith] SERVER PATH_INFO = ' . ($_SERVER['PATH_INFO'] ?? 'NULL'));
		error_log('[Xenith] SERVER SCRIPT_NAME = ' . $_SERVER['SCRIPT_NAME']);

        // Build signature payload using literal backslash-n sequences
        $signature_payload = $method . "\\n" . $uri . "\\n" . (!empty($raw_body) ? ($raw_body) : '') . "\\n" . $timestamp;

        // Compute expected signature
        $expected_signature = base64_encode(hash_hmac('sha256', $signature_payload, $webhook_secret_key, true));

        error_log("[Xenith] Expected: " . $expected_signature);
        error_log("[Xenith] Received: " . $signature);
		
		error_log("[Xenith] Signature Payload: " . $signature_payload);

        // Compare signatures in constant-time
        if (!hash_equals($expected_signature, $signature)) {
            error_log("[Xenith] Invalid signature");
            status_header(400);
            echo "Invalid signature";
            exit;
        }

        error_log("[Xenith] VALID SIGNATURE");

        // Now decode JSON (we validated using raw JSON)
        $payload = json_decode($raw_body, true);
        if (!$payload || !isset($payload['data'])) {
            error_log("[Xenith] Invalid payload JSON after signature verification");
            status_header(400);
            echo 'Invalid payload';
            exit;
        }

        $data = $payload['data'];

        // Extract fields
        $customer_reference = sanitize_text_field($data['customerReference'] ?? '');
        $status            = sanitize_text_field($data['status'] ?? '');
        $payment_amount    = isset($data['paymentAmount']) ? floatval($data['paymentAmount']) : 0;
        $reference_code    = sanitize_text_field($data['referenceCode'] ?? '');
        $payin_id          = sanitize_text_field($data['id'] ?? '');

        // Get order id from referenceCode (recommended)
        $order_id = intval(preg_replace('/[^0-9]/', '', $reference_code));
        if (!$order_id) {
            error_log("[Xenith] Invalid referenceCode (cannot extract order id): $reference_code");
            status_header(404);
            echo "Order not found";
            exit;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("[Xenith] Order not found: $order_id");
            status_header(404);
            echo "Order not found";
            exit;
        }

        // Handle statuses
        if ($status === "SUCCESS") {
            if ($order->is_paid()) {
                error_log("[Xenith] Order $order_id already paid, ignoring");
            } else {
                $notes = "Xenith payment SUCCESS.<br>".
                         "Reference Code: $reference_code<br>".
                         "Paid Amount: $payment_amount<br>".
                         "Payin ID: $payin_id";

                $order->add_order_note('<b>Xenith payment successful.</b><br>' . $notes);

                // payment_complete will set status to processing/completed and reduce stock
                $order->payment_complete($payin_id);

                error_log("[Xenith] Order $order_id marked as PAID and PROCESSING");
            }
        } elseif ($status === "FAILED") {
            $notes = "Xenith payment FAILED. Reference: $reference_code";
            $order->update_status('failed', $notes);
            error_log("[Xenith] Order $order_id FAILED");
        } elseif ($status === "PENDING") {
            $order->add_order_note("Xenith payment PENDING. Reference: $reference_code");
            error_log("[Xenith] Order $order_id PENDING");
        } else {
            error_log("[Xenith] Unknown status '$status' for order $order_id");
        }

        // Return OK
        // Use simple echo/die to avoid wp_json altering output after we already validated raw body earlier.
        echo 'Success';
        exit;

    } catch (Exception $e) {
        error_log("[Xenith] Webhook error: " . $e->getMessage());
        status_header(500);
        echo "Webhook error";
        exit;
    }
}

function wc_xenithpay_woocommerce_missing_notice() {
    echo '<div class="notice notice-error"><p>WooCommerce XenithPay Gateway: WooCommerce tidak aktif. Aktifkan WooCommerce terlebih dahulu.</p></div>';
}

/**
 * WooCommerce Blocks Support
 */
add_action( 'woocommerce_blocks_loaded', 'wc_xenithpay_blocks_support' );

function wc_xenithpay_blocks_support() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('WC XenithPay: wc_xenithpay_blocks_support called.');
    }
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WC XenithPay: AbstractPaymentMethodType class missing.');
        }
        return;
    }

    require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-xenithpay-blocks-support.php';

    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WC XenithPay: Registering block support class.');
            }
            $payment_method_registry->register( new WC_XenithPay_Blocks_Support );
        }
    );
}

/**
 * Declare compatibility with Cart & Checkout Blocks
 */
add_action( 'before_woocommerce_init', 'wc_xenithpay_blocks_compatibility' );

function wc_xenithpay_blocks_compatibility() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            true
        );
    }
}
