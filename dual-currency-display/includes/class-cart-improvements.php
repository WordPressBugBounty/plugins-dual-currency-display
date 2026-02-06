<?php
/**
 * Cart Improvements for Dual Currency Display
 *
 * @package Dual_Currency_Display
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Dual_Currency_Cart_Improvements
 * Handles cart display improvements
 */
class Dual_Currency_Cart_Improvements {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add filters to hide redundant totals
        add_filter('woocommerce_update_order_review_fragments', array($this, 'modify_cart_totals'));
        add_filter('woocommerce_add_to_cart_fragments', array($this, 'modify_cart_totals'));
        add_action('wp_footer', array($this, 'add_cart_scripts'));
        add_filter('woocommerce_get_order_item_totals', array($this, 'maybe_remove_subtotal_row'), 20, 2);
    }
    
    /**
     * Hide redundant cart totals when subtotal equals total
     */
    public function modify_cart_totals($fragments) {
        // Only proceed if WooCommerce is active
        if (!function_exists('WC')) {
            return $fragments;
        }
        
        $cart = WC()->cart;
        
        // Check if subtotal equals total (no taxes, shipping, or discounts applied)
        if (round($cart->get_subtotal(), 2) == round($cart->get_total('edit'), 2)) {
            // Add custom script to hide the duplicate total row
            ob_start();
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Hide the subtotal row in cart and checkout
                    $('.cart-subtotal').hide();
                    
                    // For cart totals table, rename "Total" to "Subtotal" to avoid confusion
                    $('.order-total th').text('<?php echo esc_js(__('Total', 'dual-currency-display')); ?>');
                });
            </script>
            <?php
            $script = ob_get_clean();
            
            // Add the script to fragments to ensure it's loaded on cart/checkout updates
            $fragments['div.ignatov-cart-script'] = '<div class="ignatov-cart-script" style="display:none;">' . $script . '</div>';
        }
        
        return $fragments;
    }
    
    /**
     * Add script to the cart and checkout pages to hide duplicate totals
     */
    public function add_cart_scripts() {
        // Only add on cart and checkout pages
        if (is_cart() || is_checkout()) {
            // Only proceed if WooCommerce is active
            if (!function_exists('WC')) {
                return;
            }
            
            $cart = WC()->cart;
            
            // Check if subtotal equals total (no taxes, shipping, or discounts applied)
            if (round($cart->get_subtotal(), 2) == round($cart->get_total('edit'), 2)) {
                ?>
                <div class="ignatov-cart-script" style="display:none;">
                    <script type="text/javascript">
                        jQuery(document).ready(function($) {
                            // Hide the subtotal row in cart and checkout
                            $('.cart-subtotal').hide();
                            
                            // For cart totals table, rename "Total" to "Subtotal" to avoid confusion
                            $('.order-total th').text('<?php echo esc_js(__('Total', 'dual-currency-display')); ?>');
                        });
                    </script>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Remove the subtotal row from order review when it equals the total
     */
    public function maybe_remove_subtotal_row($total_rows, $order) {
        // Check if subtotal equals total
        if (round($order->get_subtotal(), 2) == round($order->get_total(), 2)) {
            // Remove the subtotal row
            unset($total_rows['cart_subtotal']);
        }
        
        return $total_rows;
    }
}