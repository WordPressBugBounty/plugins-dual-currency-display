<?php
/**
 * Plugin Name: Dual Currency Display
 * Plugin URI: https://ignatovdesigns.com/dual-currency
 * Description: Display prices in both BGN and EUR currencies with flexible conversion tools.
 * Version: 1.0.3
 * Author: IgnatovDesigns.com
 * Author URI: https://ignatovdesigns.com
 * Text Domain: dual-currency-display
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 8.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * License: GPL v2 or later
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Define plugin constants
define('DUAL_CURRENCY_VERSION', '1.0.3');  // Update from 1.0.0 to 1.0.3
define('DUAL_CURRENCY_PATH', plugin_dir_path(__FILE__));
define('DUAL_CURRENCY_URL', plugin_dir_url(__FILE__));
define('DUAL_CURRENCY_BASENAME', plugin_basename(__FILE__));

// Option name for the exchange rate
define('DUAL_CURRENCY_BGN_EUR_RATE_OPTION', 'dual_currency_bgn_eur_rate');
define('DUAL_CURRENCY_ENABLE_OPTION', 'dual_currency_enable');

// Default exchange rate
define('DUAL_CURRENCY_BGN_EUR_RATE_DEFAULT', 1.95583);

/**
 * Class Dual_Currency_Display
 * Main plugin class that initializes everything
 */
class Dual_Currency_Display {
   
    /**
     * Constructor
     */
    public function __construct() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_notice'));
            return;
        }
       
        // Initialize the plugin
        $this->init();
       
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Check if WooCommerce is active
     * 
     * @return bool True if WooCommerce is active, false otherwise
     */
    private function is_woocommerce_active() {
        $active_plugins = (array) get_option('active_plugins', array());
        
        if (is_multisite()) {
            $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
        }
        
        return in_array('woocommerce/woocommerce.php', $active_plugins) || array_key_exists('woocommerce/woocommerce.php', $active_plugins);
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        
        // Include files
        $this->include_files();
        
        // Initialize admin menu and settings
        new Dual_Currency_Admin();
        
        // Initialize cart improvements
        new Dual_Currency_Cart_Improvements();
        
        // Add currency display filters
        $this->add_currency_filters();
        
        // Add CSS for consistent styling of currencies
        add_action('wp_enqueue_scripts', array($this, 'add_frontend_styles'));
    }
    
  /**
 * Add frontend styles for currency display
 */
public function add_frontend_styles() {
    // Enqueue the frontend CSS file
    wp_enqueue_style(
        'dual-currency-frontend-styles',
        DUAL_CURRENCY_URL . 'css/frontend-styles.css',
        array(),
        DUAL_CURRENCY_VERSION
    );
}
    
    /**
     * Include required files
     */
    private function include_files() {
        require_once DUAL_CURRENCY_PATH . 'includes/class-dual-currency-admin.php';
        require_once DUAL_CURRENCY_PATH . 'includes/class-dual-currency-converter.php';
        require_once DUAL_CURRENCY_PATH . 'includes/class-cart-improvements.php'; // Add this line
    }
    
    /**
     * Add currency display filters to WooCommerce
     */
    private function add_currency_filters() {
        add_filter('woocommerce_get_price_html', array($this, 'add_secondary_currency_price'), 10, 2);
        add_filter('woocommerce_cart_item_price', array($this, 'add_secondary_currency_to_cart'), 10, 3);
        add_filter('woocommerce_cart_item_subtotal', array($this, 'add_secondary_currency_to_cart_subtotal'), 10, 3);
        add_filter('woocommerce_cart_totals_subtotal_html', array($this, 'add_secondary_currency_to_cart_subtotal_display'), 10, 1);
        add_filter('woocommerce_cart_totals_order_total_html', array($this, 'add_secondary_currency_to_cart_total'), 10, 1);
        add_filter('woocommerce_get_formatted_order_total', array($this, 'add_secondary_currency_to_order_total'), 10, 2);
        add_filter('woocommerce_checkout_totals_order_total_html', array($this, 'add_secondary_currency_to_cart_total'), 10, 1);
        add_filter('woocommerce_order_formatted_line_subtotal', array($this, 'add_secondary_currency_to_order_line_subtotal'), 10, 3);
        add_filter('woocommerce_cart_subtotal', array($this, 'add_secondary_currency_to_cart_subtotal_widget'), 10, 3);
        add_filter('woocommerce_cart_total', array($this, 'add_secondary_currency_to_cart_total_widget'), 10, 1);
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create backup table if needed
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_price_backup';
        
        if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $wpdb->query("
                CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wc_price_backup (
                    backup_id bigint(20) NOT NULL AUTO_INCREMENT,
                    post_id bigint(20) NOT NULL,
                    meta_key varchar(255) NOT NULL,
                    price_value decimal(15,4) NOT NULL,
                    currency varchar(10) NOT NULL,
                    backup_date datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (backup_id),
                    KEY post_id (post_id),
                    KEY meta_key (meta_key)
                )
            ");
        }
        
        // Set default options
        add_option(DUAL_CURRENCY_BGN_EUR_RATE_OPTION, DUAL_CURRENCY_BGN_EUR_RATE_DEFAULT);
        add_option(DUAL_CURRENCY_ENABLE_OPTION, 'yes');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Nothing to do on deactivation yet
    }
    
    /**
     * WooCommerce not active notice
     */
    public function woocommerce_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Dual Currency Display requires WooCommerce to be installed and active.', 'dual-currency-display'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Function to get the exchange rate from the options table
     */
    public static function get_bgn_eur_rate() {
        return get_option(DUAL_CURRENCY_BGN_EUR_RATE_OPTION, DUAL_CURRENCY_BGN_EUR_RATE_DEFAULT);
    }
    
    /**
     * Check if dual currency display is enabled
     * 
     * @return bool True if dual currency display is enabled, false otherwise
     */
    public static function is_dual_currency_enabled() {
        return get_option(DUAL_CURRENCY_ENABLE_OPTION, 'yes') === 'yes';
    }
    
    /**
     * Convert between BGN and EUR
     * 
     * @param float $amount Amount to convert
     * @param string $from_currency Source currency (BGN or EUR)
     * @return float Converted amount
     */
    public static function convert_currency($amount, $from_currency = 'BGN') {
        $exchange_rate = self::get_bgn_eur_rate();
        if ($from_currency === 'BGN') {
            // BGN to EUR
            return number_format($amount / $exchange_rate, 2, '.', '');
        } else {
            // EUR to BGN
            return number_format($amount * $exchange_rate, 2, '.', '');
        }
    }
    
   /**
 * Add secondary currency to product price display with consistent styling
 * Updated to handle variable products properly
 */
public function add_secondary_currency_price($price, $product) {
    // Return original price if dual currency is disabled
    if (!self::is_dual_currency_enabled()) {
        return $price;
    }
    
    $currency = get_woocommerce_currency();
    
    // Special handling for variable products with price ranges
    if ($product->is_type('variable')) {
        $min_price = floatval($product->get_variation_price('min', true));
        $max_price = floatval($product->get_variation_price('max', true));
        
        if ($currency === 'BGN') {
            // Convert min and max prices
            $min_price_eur = self::convert_currency($min_price, 'BGN');
            $max_price_eur = self::convert_currency($max_price, 'BGN');
            
            // If min and max are the same
            if ($min_price === $max_price) {
                return '<span class="dual-currency-wrapper">' . $price . ' <span class="secondary-currency">(' . wc_price($min_price_eur, array('currency' => 'EUR')) . ')</span></span>';
            }
            
            // Format as price range
            $price_range_eur = wc_price($min_price_eur, array('currency' => 'EUR')) . ' - ' . wc_price($max_price_eur, array('currency' => 'EUR'));
            return '<span class="dual-currency-wrapper">' . $price . ' <span class="secondary-currency">(' . $price_range_eur . ')</span></span>';
        } else if ($currency === 'EUR') {
            // Convert min and max prices
            $min_price_bgn = self::convert_currency($min_price, 'EUR');
            $max_price_bgn = self::convert_currency($max_price, 'EUR');
            
            // If min and max are the same
            if ($min_price === $max_price) {
                return '<span class="dual-currency-wrapper">' . $price . ' <span class="secondary-currency">(' . wc_price($min_price_bgn, array('currency' => 'BGN')) . ')</span></span>';
            }
            
            // Format as price range
            $price_range_bgn = wc_price($min_price_bgn, array('currency' => 'BGN')) . ' - ' . wc_price($max_price_bgn, array('currency' => 'BGN'));
            return '<span class="dual-currency-wrapper">' . $price . ' <span class="secondary-currency">(' . $price_range_bgn . ')</span></span>';
        }
    } 
    // Regular product price handling (non-variable)
    else {
        $product_price = floatval($product->get_price());
        
        if ($currency === 'BGN') {
            // Main: BGN, Secondary: EUR
            $secondary_price = self::convert_currency($product_price, 'BGN');
            // Use a span to wrap both currencies with consistent styling
            return '<span class="dual-currency-wrapper">' . $price . ' <span class="secondary-currency">(' . wc_price($secondary_price, array('currency' => 'EUR')) . ')</span></span>';
        } else if ($currency === 'EUR') {
            // Main: EUR, Secondary: BGN
            $secondary_price = self::convert_currency($product_price, 'EUR');
            // Use a span to wrap both currencies with consistent styling
            return '<span class="dual-currency-wrapper">' . $price . ' <span class="secondary-currency">(' . wc_price($secondary_price, array('currency' => 'BGN')) . ')</span></span>';
        }
    }
    
    return $price;
}
    
    /**
     * Add secondary currency to cart item price with consistent styling
     */
    public function add_secondary_currency_to_cart($price, $cart_item, $cart_item_key) {
        // Return original price if dual currency is disabled
        if (!self::is_dual_currency_enabled()) {
            return $price;
        }
        
        $currency = get_woocommerce_currency();
        $product_price = floatval($cart_item['data']->get_price());
        
        if ($currency === 'BGN') {
            // Main: BGN, Secondary: EUR
            $secondary_price = self::convert_currency($product_price, 'BGN');
            // Use a span to wrap both currencies with consistent styling
            return '<span class="dual-currency-wrapper">' . $price . ' <span class="secondary-currency">(' . wc_price($secondary_price, array('currency' => 'EUR')) . ')</span></span>';
        } else if ($currency === 'EUR') {
            // Main: EUR, Secondary: BGN
            $secondary_price = self::convert_currency($product_price, 'EUR');
            // Use a span to wrap both currencies with consistent styling
            return '<span class="dual-currency-wrapper">' . $price . ' <span class="secondary-currency">(' . wc_price($secondary_price, array('currency' => 'BGN')) . ')</span></span>';
        }
        
        return $price;
    }
    
    /**
     * Add secondary currency to cart item subtotal with consistent styling
     */
    public function add_secondary_currency_to_cart_subtotal($subtotal, $cart_item, $cart_item_key) {
        // Return original subtotal if dual currency is disabled
        if (!self::is_dual_currency_enabled()) {
            return $subtotal;
        }
        
        $currency = get_woocommerce_currency();
        $product_total = floatval($cart_item['line_total']);
        
        if ($currency === 'BGN') {
            // Main: BGN, Secondary: EUR
            $secondary_price = self::convert_currency($product_total, 'BGN');
            // Use a span to wrap both currencies with consistent styling
            return '<span class="dual-currency-wrapper">' . $subtotal . ' <span class="secondary-currency">(' . wc_price($secondary_price, array('currency' => 'EUR')) . ')</span></span>';
        } else if ($currency === 'EUR') {
            // Main: EUR, Secondary: BGN
            $secondary_price = self::convert_currency($product_total, 'EUR');
            // Use a span to wrap both currencies with consistent styling
            return '<span class="dual-currency-wrapper">' . $subtotal . ' <span class="secondary-currency">(' . wc_price($secondary_price, array('currency' => 'BGN')) . ')</span></span>';
        }
        
        return $subtotal;
    }
    
    /**
     * Add secondary currency to cart subtotal display with consistent styling
     */
    public function add_secondary_currency_to_cart_subtotal_display($subtotal) {
        // Return original subtotal if dual currency is disabled
        if (!self::is_dual_currency_enabled()) {
            return $subtotal;
        }
        
        $currency = get_woocommerce_currency();
        $cart_subtotal = WC()->cart->get_subtotal() + WC()->cart->get_subtotal_tax();
        
        if ($currency === 'BGN') {
            // Main: BGN, Secondary: EUR
            $secondary_price = self::convert_currency($cart_subtotal, 'BGN');
            // Use a span to wrap both currencies with consistent styling
            return '<span class="dual-currency-wrapper">' . wc_price($cart_subtotal) . ' <span class="secondary-currency">(' . wc_price($secondary_price, array('currency' => 'EUR')) . ')</span></span>';
        } else if ($currency === 'EUR') {
            // Main: EUR, Secondary: BGN
            $secondary_price = self::convert_currency($cart_subtotal, 'EUR');
            // Use a span to wrap both currencies with consistent styling
            return '<span class="dual-currency-wrapper">' . wc_price($cart_subtotal) . ' <span class="secondary-currency">(' . wc_price($secondary_price, array('currency' => 'BGN')) . ')</span></span>';
        }
        
        return $subtotal;
    }
    
    /**
     * Add secondary currency to cart total with consistent styling
     */
    public function add_secondary_currency_to_cart_total($total) {
        // Return original total if dual currency is disabled
        if (!self::is_dual_currency_enabled()) {
            return $total;
        }
        
        $currency = get_woocommerce_currency();
        $cart_total = WC()->cart->get_total('raw');
        
        if ($currency === 'BGN') {
            // Main: BGN, Secondary: EUR
            $secondary_price = self::convert_currency($cart_total, 'BGN');
            // Use a span to wrap both currencies with consistent styling
            return '<span class="dual-currency-wrapper">' . wc_price($cart_total) . ' <span class="secondary-currency">(' . wc_price($secondary_price, array('currency' => 'EUR')) . ')</span></span>';
        } else if ($currency === 'EUR') {
            // Main: EUR, Secondary: BGN
            $secondary_price = self::convert_currency($cart_total, 'EUR');
            // Use a span to wrap both currencies with consistent styling
            return '<span class="dual-currency-wrapper">' . wc_price($cart_total) . ' <span class="secondary-currency">(' . wc_price($secondary_price, array('currency' => 'BGN')) . ')</span></span>';
        }
        
        return $total;
    }
    
    /**
     * Add secondary currency to order total with consistent styling
     * HPOS compatible
     */
    public function add_secondary_currency_to_order_total($total, $order) {
        // Return original total if dual currency is disabled
        if (!self::is_dual_currency_enabled()) {
            return $total;
        }
        
        $currency = $order->get_currency();
        $order_total = floatval($order->get_total());
        
        if ($currency === 'BGN') {
            // Main: BGN, Secondary: EUR
            $secondary_price = self::convert_currency($order_total, 'BGN');
            // Use a span to wrap both currencies with consistent styling
            return '<span class="dual-currency-wrapper">' . wc_price($order_total, array('currency' => $currency)) . ' <span class="secondary-currency">(' . wc_price($secondary_price, array('currency' => 'EUR')) . ')</span></span>';
        } else if ($currency === 'EUR') {
            // Main: EUR, Secondary: BGN
            $secondary_price = self::convert_currency($order_total, 'EUR');
            // Use a span to wrap both currencies with consistent styling
            return '<span class="dual-currency-wrapper">' . wc_price($order_total, array('currency' => $currency)) . ' <span class="secondary-currency">(' . wc_price($secondary_price, array('currency' => 'BGN')) . ')</span></span>';
        }
        
        return $total;
    }
    
    /**
     * Add secondary currency to order line subtotal with consistent styling
     * HPOS compatible
     */
    public function add_secondary_currency_to_order_line_subtotal($subtotal, $item, $order) {
        // Return original subtotal if dual currency is disabled
        if (!self::is_dual_currency_enabled()) {
            return $subtotal;
        }
        
        $currency = $order->get_currency();
        $line_total = floatval($item->get_total());
        
        if ($currency === 'BGN') {
            // Main: BGN, Secondary: EUR
            $secondary_price = self::convert_currency($line_total, 'BGN');
            // Use a span to wrap both currencies with consistent styling
            return '<span class="dual-currency-wrapper">' . $subtotal . ' <span class="secondary-currency">(' . wc_price($secondary_price, array('currency' => 'EUR')) . ')</span></span>';
        } else if ($currency === 'EUR') {
            // Main: EUR, Secondary: BGN
            $secondary_price = self::convert_currency($line_total, 'EUR');
            // Use a span to wrap both currencies with consistent styling
            return '<span class="dual-currency-wrapper">' . $subtotal . ' <span class="secondary-currency">(' . wc_price($secondary_price, array('currency' => 'BGN')) . ')</span></span>';
        }
        
        return $subtotal;
    }
    
    /**
     * Add secondary currency to cart subtotal widget with consistent styling
     */
    public function add_secondary_currency_to_cart_subtotal_widget($subtotal, $compound, $cart) {
        // Return original subtotal if dual currency is disabled
        if (!self::is_dual_currency_enabled()) {
            return $subtotal;
        }
        
        $currency = get_woocommerce_currency();
        $cart_subtotal = $cart->get_subtotal();
        
        if ($currency === 'BGN') {
            // Main: BGN, Secondary: EUR
            $secondary_price = self::convert_currency($cart_subtotal, 'BGN');
            // Use a span to wrap both currencies with consistent styling
            return '<span class="dual-currency-wrapper">' . $subtotal . ' <span class="secondary-currency">(' . wc_price($secondary_price, array('currency' => 'EUR')) . ')</span></span>';
        } else if ($currency === 'EUR') {
            // Main: EUR, Secondary: BGN
            $secondary_price = self::convert_currency($cart_subtotal, 'EUR');
            // Use a span to wrap both currencies with consistent styling
            return '<span class="dual-currency-wrapper">' . $subtotal . ' <span class="secondary-currency">(' . wc_price($secondary_price, array('currency' => 'BGN')) . ')</span></span>';
        }
        
        return $subtotal;
    }
    
    /**
     * Add secondary currency to cart total widget with consistent styling
     */
    public function add_secondary_currency_to_cart_total_widget($total) {
        // Return original total if dual currency is disabled
        if (!self::is_dual_currency_enabled()) {
            return $total;
        }
        
        $currency = get_woocommerce_currency();
        $cart_total = WC()->cart->get_total('raw');
        
        if ($currency === 'BGN') {
            // Main: BGN, Secondary: EUR
            $secondary_price = self::convert_currency($cart_total, 'BGN');
            // Use a span to wrap both currencies with consistent styling
            return '<span class="dual-currency-wrapper">' . $total . ' <span class="secondary-currency">(' . wc_price($secondary_price, array('currency' => 'EUR')) . ')</span></span>';
        } else if ($currency === 'EUR') {
            // Main: EUR, Secondary: BGN
            $secondary_price = self::convert_currency($cart_total, 'EUR');
            // Use a span to wrap both currencies with consistent styling
            return '<span class="dual-currency-wrapper">' . $total . ' <span class="secondary-currency">(' . wc_price($secondary_price, array('currency' => 'BGN')) . ')</span></span>';
        }
        
        return $total;
    }
}

// Initialize the plugin
new Dual_Currency_Display();