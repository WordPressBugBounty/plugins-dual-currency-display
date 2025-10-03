<?php
/**
 * Admin functionality for Dual Currency Display
 *
 * @package Dual_Currency_Display
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Dual_Currency_Admin
 * Handles admin pages and functionality
 */
class Dual_Currency_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add admin menu items
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add admin styles
        add_action('admin_enqueue_scripts', array($this, 'add_admin_styles'));
        
        // Add admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Add main menu item
        add_menu_page(
            'Currency Conversion',
            'Currency Conversion',
            'manage_options',
            'dual-currency-conversion',
            array($this, 'render_currency_converter_page'),
            'dashicons-money-alt',
            58  // Position after WooCommerce
        );

        // Add submenu items
        add_submenu_page(
            'dual-currency-conversion',
            'BGN to EUR Converter',
            'BGN to EUR Converter',
            'manage_options',
            'dual-currency-conversion',  // Same as parent to make this the default page
            array($this, 'render_currency_converter_page')
        );

        add_submenu_page(
            'dual-currency-conversion',
            'Update Exchange Rate',
            'Update Exchange Rate',
            'manage_options',
            'dual-currency-update-rate',
            array($this, 'render_update_exchange_rate_page')
        );

        add_submenu_page(
            'dual-currency-conversion',
            'Restore BGN Prices',
            'Restore BGN Prices',
            'manage_options',
            'dual-currency-restore-prices',
            array($this, 'render_restore_bgn_prices_page')
        );

        add_submenu_page(
            'dual-currency-conversion',
            'EUR to BGN Converter',
            'EUR to BGN Converter',
            'manage_options',
            'dual-currency-eur-to-bgn',
            array($this, 'render_eur_to_bgn_converter_page')
        );
    }

    /**
     * Convert all product prices to Euro, preserving sale and regular prices
     * 
     * @param float $exchange_rate Exchange rate (BGN/EUR)
     * @return array Result information
     */
    public static function convert_all_to_euro($exchange_rate = null) {
        // Validate exchange rate
        if (!is_numeric($exchange_rate) || $exchange_rate <= 0) {
            return [
                'count' => 0,
                'time' => 0,
                'error' => 'Invalid exchange rate'
            ];
        }

        // Start performance tracking
        $start_time = microtime(true);
        $converted_count = 0;

        try {
            // Get all published products
            $product_ids = wc_get_products([
                'status' => 'publish',
                'limit' => -1,
                'return' => 'ids',
            ]);

            // Loop through all products
            foreach ($product_ids as $product_id) {
                $product = wc_get_product($product_id);
                
                if (!$product) {
                    continue;
                }

                // Track if this product was modified
                $product_modified = false;

                // Convert regular price
                $current_regular_price = $product->get_regular_price();
                if (!empty($current_regular_price)) {
                    $converted_regular_price = round($current_regular_price / $exchange_rate, 2);
                    $product->set_regular_price($converted_regular_price);
                    $product_modified = true;
                }

                // Convert sale price
                $current_sale_price = $product->get_sale_price();
                if (!empty($current_sale_price)) {
                    $converted_sale_price = round($current_sale_price / $exchange_rate, 2);
                    $product->set_sale_price($converted_sale_price);
                    $product_modified = true;
                }

                // For variable products, convert variation prices
                if ($product->is_type('variable')) {
                    $variations = $product->get_children();
                    foreach ($variations as $variation_id) {
                        $variation = wc_get_product($variation_id);
                        
                        if (!$variation) {
                            continue;
                        }
                        
                        // Convert variation regular price
                        $var_regular_price = $variation->get_regular_price();
                        if (!empty($var_regular_price)) {
                            $var_converted_regular_price = round($var_regular_price / $exchange_rate, 2);
                            $variation->set_regular_price($var_converted_regular_price);
                            $product_modified = true;
                        }
                        
                        // Convert variation sale price
                        $var_sale_price = $variation->get_sale_price();
                        if (!empty($var_sale_price)) {
                            $var_converted_sale_price = round($var_sale_price / $exchange_rate, 2);
                            $variation->set_sale_price($var_converted_sale_price);
                            $product_modified = true;
                        }
                        
                        $variation->save();
                    }
                }

                // Save the product if modified
                if ($product_modified) {
                    $product->save();
                    $converted_count++;
                }
            }

            // Update WooCommerce currency to EUR
            update_option('woocommerce_currency', 'EUR');

            // Calculate execution time
            $execution_time = microtime(true) - $start_time;

            return [
                'count' => $converted_count,
                'time' => round($execution_time, 2)
            ];

        } catch (Exception $e) {
            error_log('Error converting all prices to Euro: ' . $e->getMessage());
            return [
                'count' => 0,
                'time' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Add admin styles
     */
    public function add_admin_styles() {
        $screen = get_current_screen();
        
        // Check for any of our plugin's pages
        if (strpos($screen->id, 'dual-currency') !== false) {
            wp_enqueue_style(
                'dual-currency-admin-styles',
                DUAL_CURRENCY_URL . 'css/admin-styles.css',
                array(),
                DUAL_CURRENCY_VERSION
            );
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Check for any of our plugin's pages
        if (strpos($hook, 'dual-currency') !== false) {
            wp_enqueue_script(
                'dual-currency-admin',
                DUAL_CURRENCY_URL . 'js/admin-scripts.js',
                array('jquery'),
                DUAL_CURRENCY_VERSION,
                true
            );
            
            // Add inline script to handle toggle state
            wp_add_inline_script('dual-currency-admin', '
                jQuery(document).ready(function($) {
                    // Make sure checkboxes reflect their actual state
                    $(".ignatov-switch input[type=checkbox]").each(function() {
                        $(this).prop("checked", $(this).attr("checked") === "checked");
                    });
                });
            ');
        }
    }
    
    /**
     * Add Bulgarian translations box to admin pages
     */
    public function add_bulgarian_translations_box() {
        // Get current screen
        $screen = get_current_screen();
        
        // Check if we're on one of our plugin's pages
        if (strpos($screen->id, 'dual-currency-conversion') === false) {
            return;
        }
        
        // Get current page
        $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'dual-currency-conversion';
        
        // Bulgarian translations for each page
        $translations = array(
            'dual-currency-conversion' => '
                <h3>Конвертор от Лева в Евро</h3>
                <p>С този инструмент можете да конвертирате всички цени на продуктите от български лева (BGN) в евро (EUR) използвайки валутния курс 1 EUR = 1.95583 BGN.</p>
                <ul>
                    <li><strong>Create a backup of price data before conversion  / Създаване на резервно копие</strong> - Създава резервно копие на цените преди конверсията</li>
                    <li><strong>Update WooCommerce currency setting to EUR  / Обновяване на валутната настройка</strong> - Променя валутата на WooCommerce на EUR и вторичната остава BGN</li>
                    <li><strong>Disable dual currency display (show EUR only) ON / Чрез тази опция, ако е включена може да деактивирате двойното показване на цените (цените остават само EUR)</li>
                    <li><strong>Disable dual currency display (show EUR only) OFF / Ако е изключена ще се покажат и двете валути</li>
                    <li><strong>Convert All prices to EUR / Конвертиране на всички цени в EUR</strong> - Стартира процеса на конвертиране</li>
                </ul>
                <div class="ignatov-currency-blue-notice">
    <p><strong>Забележка:</strong> Когато конвертирате от BGN към EUR препоръчваме да оставите опцията "Disable dual currency display" изключена, за да се показват цените и в двете валути.</p>
</div>
            ',
            'dual-currency-update-rate' => '
                <h3>Обновяване на валутния курс</h3>
                <p>Тук можете да обновите валутния курс между български лев (BGN) и евро (EUR). Официалният курс е фиксиран на 1.95583.</p>
                <p>Променяйте този курс само ако имате основателна причина. За повечето български магазини е препоръчително да използвате фиксирания курс.</p>
            ',
            'dual-currency-restore-prices' => '
                <h3>Възстановяване на цените в лева</h3>
                <p>Този инструмент ви позволява да възстановите оригиналните цени от резервното копие, създадено по време на конверсията.</p>
                <ul>
                    <li><strong>Restore currency setting to / Възстановяване на валутната настройка/strong> - Изберете валутата, към която да се върнете (BGN или EUR)</li>
                    <li><strong>Enable dual currency display after restoration ON / Активиране на двойното показване</strong> - Показва цените и в двете валути след възстановяването</li>
                    <li><strong>Enable dual currency display after restoration OFF / Активиране на двойното показване</strong> - Се показва само тази валута която сте избрали </li>
                </ul>
            ',
            'dual-currency-eur-to-bgn' => '
                <h3>Конвертор от Евро в Лева</h3>
                <p>С този инструмент можете да конвертирате всички цени на продуктите от евро (EUR) в български лева (BGN) използвайки валутния курс 1 EUR = 1.95583 BGN.</p>
                <ul>
                    <li><strong>Създаване на резервно копие</strong> - Създава резервно копие на цените преди конверсията</li>
                    <li><strong>Обновяване на валутната настройка</strong> - Променя валутата на WooCommerce на BGN</li>
                    <li><strong>Convert All prices to EUR / Конвертиране на всички цени в EUR</strong> - Стартира процеса на конвертиране</li>
                </ul>
            '
        );
        
        // Display the translation box if we have content for this page
        if (isset($translations[$current_page])) {
            ?>
            <div class="ignatov-currency-box ignatov-currency-translation-box">
                <h2>Информация на български (Bulgarian)</h2>
                <div class="ignatov-currency-translation">
                    <?php echo wp_kses_post($translations[$current_page]); ?>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Render the Update Exchange Rate admin page
     */
    public function render_update_exchange_rate_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'dual-currency-display'));
        }
        
        // Add Bulgarian translations box
        $this->add_bulgarian_translations_box();
        
        // Process form submission
        if (isset($_POST['update_exchange_rate'])) {
            if (!isset($_POST['exchange_rate_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['exchange_rate_nonce'])), 'update_exchange_rate')) {
                wp_die('Invalid nonce.');
            }

            // Sanitize the exchange rate
            $exchange_rate = sanitize_text_field(wp_unslash($_POST['bgn_eur_rate']));

            // Validate the exchange rate
            if (is_numeric($exchange_rate) && $exchange_rate > 0 && preg_match('/^\d+(\.\d{1,5})?$/', $exchange_rate)) {
                // Update the exchange rate in the options table
                update_option(DUAL_CURRENCY_BGN_EUR_RATE_OPTION, $exchange_rate);

                // Display a success message
                $message = '<div class="notice notice-success"><p>' . esc_html__('Exchange rate updated successfully!', 'dual-currency-display') . '</p></div>';
            } else {
                // Display an error message
                $message = '<div class="notice notice-error"><p>' . esc_html__('Invalid exchange rate. Please enter a valid number.', 'dual-currency-display') . '</p></div>';
            }
        }

        // Display the admin interface
        ?>
        <div class="wrap ignatov-currency-page">
            <div class="ignatov-currency-header">
                <h1><?php echo esc_html__('Update BGN to EUR Exchange Rate', 'dual-currency-display'); ?></h1>
                <div class="ignatov-currency-branding">by IgnatovDesigns.com</div>
            </div>
            
            <?php if (isset($message)) echo wp_kses_post($message); ?>
            
            <div class="ignatov-currency-container">
                <div class="ignatov-currency-box">
                    <h2><?php echo esc_html__('Current Exchange Rate', 'dual-currency-display'); ?></h2>
                    
                    <div class="ignatov-currency-rate">
                        1 EUR = <?php echo esc_html(Dual_Currency_Display::get_bgn_eur_rate()); ?> BGN
                    </div>
                    
                    <div class="ignatov-currency-info">
                        <p><?php echo esc_html__('This exchange rate is used for all currency conversions throughout your store.', 'dual-currency-display'); ?></p>
                    </div>
                    
                    <form method="post" class="ignatov-currency-form">
                        <?php wp_nonce_field('update_exchange_rate', 'exchange_rate_nonce'); ?>

                        <label for="bgn_eur_rate"><?php echo esc_html__('New Exchange Rate (BGN/EUR):', 'dual-currency-display'); ?></label>
                        <input type="text" id="bgn_eur_rate" name="bgn_eur_rate" value="<?php echo esc_attr(Dual_Currency_Display::get_bgn_eur_rate()); ?>" />

                        <div class="ignatov-currency-submit">
                            <input type="submit" name="update_exchange_rate" class="button button-primary" value="<?php echo esc_attr__('Update Exchange Rate', 'dual-currency-display'); ?>">
                        </div>
                    </form>
                </div>
                
                <div class="ignatov-currency-box">
                    <h2><?php echo esc_html__('Exchange Rate Information', 'dual-currency-display'); ?></h2>
                    <p><?php echo esc_html__('The official BGN/EUR exchange rate is fixed at 1.95583 as Bulgaria uses a currency board arrangement.', 'dual-currency-display'); ?></p>
                    <p><?php echo esc_html__('You can modify this rate if needed, but for most Bulgarian stores, using the fixed rate is recommended.', 'dual-currency-display'); ?></p>
                    
                    <div class="ignatov-currency-notice">
                        <p><?php echo esc_html__('Note: Changing the exchange rate will affect how prices are displayed, but will not automatically convert your product prices.', 'dual-currency-display'); ?></p>
                    </div>
                    
                    <p><?php echo esc_html__('To convert your actual product prices, use the BGN to EUR or EUR to BGN conversion tools.', 'dual-currency-display'); ?></p>
                </div>
            </div>
            
            <div class="ignatov-currency-footer">
                Dual Currency Display System &copy; <?php echo esc_html(gmdate('Y')); ?> IgnatovDesigns.com - All rights reserved.
            </div>
        </div>
        <?php
    }

    /**
     * Render the BGN to EUR converter admin page
     */
    public function render_currency_converter_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'dual-currency-display'));
        }
        
        // Add Bulgarian translations box
        $this->add_bulgarian_translations_box();
        
        // Process form submission
        if (isset($_POST['convert_currency'])) {
            if (!isset($_POST['conversion_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['conversion_nonce'])), 'bgn_to_eur_conversion')) {
                wp_die('Invalid nonce.');
            }

            // Create backup if requested
            if (isset($_POST['create_backup']) && sanitize_text_field(wp_unslash($_POST['create_backup'])) == 'yes') {
                Dual_Currency_Converter::backup_prices();
            }

            // Run the conversion
            $results = Dual_Currency_Converter::bgn_to_eur_convert_prices(Dual_Currency_Display::get_bgn_eur_rate());

            // Update currency setting if requested
            if (isset($_POST['update_currency_setting']) && sanitize_text_field(wp_unslash($_POST['update_currency_setting'])) == 'yes') {
                update_option('woocommerce_currency', 'EUR');
            }
            
            // Handle dual currency display setting
            if (isset($_POST['disable_dual_currency']) && sanitize_text_field(wp_unslash($_POST['disable_dual_currency'])) == 'yes') {
                // Disable dual currency
                update_option('dual_currency_enable', 'no');
                $dual_currency_status = 'disabled';
            } else {
                // Enable dual currency
                update_option('dual_currency_enable', 'yes');
                $dual_currency_status = 'enabled';
            }
        }

        // Get current dual currency status for the toggle
        $dual_currency_disabled = get_option('dual_currency_enable', 'yes') === 'no';

        // Display the admin interface
        ?>
        <div class="wrap ignatov-currency-page">
            <div class="ignatov-currency-header">
                <h1>WooCommerce BGN to EUR Conversion Tool</h1>
                <div class="ignatov-currency-branding">by IgnatovDesigns.com</div>
            </div>

            <?php if (isset($results)): ?>
                <div class="notice notice-success">
                    <p>Conversion completed! <?php echo esc_html($results['count']); ?> products updated from BGN to EUR.
                    <?php if (isset($dual_currency_status) && $dual_currency_status === 'disabled'): ?>
                        Dual currency display has been disabled.
                    <?php elseif (isset($dual_currency_status) && $dual_currency_status === 'enabled'): ?>
                        Dual currency display has been enabled.
                    <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="ignatov-currency-container">
                <div class="ignatov-currency-box">
                    <h2>Convert Products from BGN to EUR</h2>
                    
                    <div class="ignatov-currency-info">
                        <p>This tool will convert all your product prices from Bulgarian Leva (BGN) to Euro (EUR) using the exchange rate of 1 EUR = <?php echo esc_html(Dual_Currency_Display::get_bgn_eur_rate()); ?> BGN.</p>
                    </div>
                    
                    <div class="ignatov-currency-notice">
                        <p><strong>IMPORTANT:</strong> Please make a full database backup before proceeding. This operation cannot be easily undone.</p>
                    </div>

                    <form method="post" class="ignatov-currency-form">
                        <?php wp_nonce_field('bgn_to_eur_conversion', 'conversion_nonce'); ?>

                        <div class="ignatov-currency-checkbox">
                            <label class="ignatov-switch">
                                <input type="checkbox" name="create_backup" value="yes" checked>
                                <span class="ignatov-slider"></span>
                            </label>
                            Create a backup of price data before conversion
                        </div>

                        <div class="ignatov-currency-checkbox">
                            <label class="ignatov-switch">
                                <input type="checkbox" name="update_currency_setting" value="yes" checked>
                                <span class="ignatov-slider"></span>
                            </label>
                            Update WooCommerce currency setting to EUR
                        </div>
                        
                        <div class="ignatov-currency-checkbox">
                            <label class="ignatov-switch">
                                <input type="checkbox" name="disable_dual_currency" value="yes" <?php if ($dual_currency_disabled) echo 'checked'; ?>>
                                <span class="ignatov-slider"></span>
                            </label>
                            Disable dual currency display (show EUR only)
                        </div>

                        <div class="ignatov-currency-submit">
                            <input type="submit" name="convert_currency" class="button button-primary" value="Convert All Prices to EUR">
                        </div>
                    </form>
                </div>
                
                <div class="ignatov-currency-box">
                    <h2>After Conversion</h2>
                    <p>After converting your prices, the store will:</p>
                    
                    <ol class="ignatov-currency-steps">
                        <li>Show EUR as the main currency</li>
                        <li>
                            <?php if (!$dual_currency_disabled): ?>
                                Display prices like: €10.00 (<?php echo esc_html(number_format(10 * Dual_Currency_Display::get_bgn_eur_rate(), 2, '.', '')); ?> лв.) unless you disable dual currency
                            <?php else: ?>
                                Display prices like: €10.00 (dual currency is currently disabled)
                            <?php endif; ?>
                        </li>
                        <?php if (!$dual_currency_disabled): ?>
                        <li>Continue to show dual currency throughout the store unless you disable it</li>
                        <?php endif; ?>
                    </ol>
                    
                    <div class="ignatov-currency-info">
                        <p>If you ever need to revert your changes, you can use the "Restore BGN Prices" tool.</p>
                    </div>
                </div>
            </div>
            
            <div class="ignatov-currency-footer">
                Dual Currency Display System &copy; <?php echo esc_html(gmdate('Y')); ?> IgnatovDesigns.com - All rights reserved.
            </div>
        </div>
        <?php
    }

    /**
     * Render the EUR to BGN converter admin page
     */
    public function render_eur_to_bgn_converter_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'dual-currency-display'));
        }
        
        // Add Bulgarian translations box
        $this->add_bulgarian_translations_box();
        
        // Process form submission
        if (isset($_POST['convert_currency'])) {
            if (!isset($_POST['conversion_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['conversion_nonce'])), 'eur_to_bgn_conversion')) {
                wp_die('Invalid nonce.');
            }

            // Create backup if requested
            if (isset($_POST['create_backup']) && sanitize_text_field(wp_unslash($_POST['create_backup'])) == 'yes') {
                Dual_Currency_Converter::backup_prices();  // Use the same backup function
            }

            // Run the conversion
            $results = Dual_Currency_Converter::eur_to_bgn_convert_prices(Dual_Currency_Display::get_bgn_eur_rate());

            // Update currency setting if requested
            if (isset($_POST['update_currency_setting']) && sanitize_text_field(wp_unslash($_POST['update_currency_setting'])) == 'yes') {
                update_option('woocommerce_currency', 'BGN');
            }
            
            // Always enable dual currency for BGN to EUR conversion
            update_option('dual_currency_enable', 'yes');
        }

        // Display the admin interface
        ?>
        <div class="wrap ignatov-currency-page">
            <div class="ignatov-currency-header">
                <h1>WooCommerce EUR to BGN Conversion Tool</h1>
                <div class="ignatov-currency-branding">by IgnatovDesigns.com</div>
            </div>

            <?php if (isset($results)): ?>
                <div class="notice notice-success">
                    <p>Conversion completed! <?php echo esc_html($results['count']); ?> products updated from EUR to BGN.</p>
                </div>
            <?php endif; ?>

            <div class="ignatov-currency-container">
                <div class="ignatov-currency-box">
                    <h2>Convert Products from EUR to BGN</h2>
                    
                    <div class="ignatov-currency-info">
                        <p>This tool will convert all your product prices from Euro (EUR) to Bulgarian Leva (BGN) using the exchange rate of 1 EUR = <?php echo esc_html(Dual_Currency_Display::get_bgn_eur_rate()); ?> BGN.</p>
                    </div>
                    
                    <div class="ignatov-currency-notice">
                        <p><strong>IMPORTANT:</strong> Please make a full database backup before proceeding. This operation cannot be easily undone.</p>
                    </div>

                    <form method="post" class="ignatov-currency-form">
                        <?php wp_nonce_field('eur_to_bgn_conversion', 'conversion_nonce'); ?>

                        <div class="ignatov-currency-checkbox">
                            <label class="ignatov-switch">
                                <input type="checkbox" name="create_backup" value="yes" checked>
                                <span class="ignatov-slider"></span>
                            </label>
                            Create a backup of price data before conversion
                        </div>

                        <div class="ignatov-currency-checkbox">
                            <label class="ignatov-switch">
                                <input type="checkbox" name="update_currency_setting" value="yes" checked>
                                <span class="ignatov-slider"></span>
                            </label>
                            Update WooCommerce currency setting to BGN
                        </div>

                        <div class="ignatov-currency-submit">
                            <input type="submit" name="convert_currency" class="button button-primary" value="Convert All Prices to BGN">
                        </div>
                    </form>
                </div>
                
                <div class="ignatov-currency-box">
                    <h2>After Conversion</h2>
                    <p>After converting your prices, the store will:</p>
                    
                    <ol class="ignatov-currency-steps">
                    <li>Show BGN as the main currency and EUR as the secondary currency</li>
                        <li>Display prices like: 19.56 лв. (€10.00)</li>
                        <li>Continue to show dual currency throughout the store</li>
                    </ol>
                    
                    <div class="ignatov-currency-info">
                        <p>If you ever need to revert your changes, you can use the "Restore BGN Prices" tool.</p>
                    </div>
                </div>
            </div>
            
            <div class="ignatov-currency-footer">
                Dual Currency Display System &copy; <?php echo esc_html(gmdate('Y')); ?> IgnatovDesigns.com - All rights reserved.
            </div>
        </div>
        <?php
    }
    
    /**
     * Render the Restore BGN Prices admin page
     */
    public function render_restore_bgn_prices_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'dual-currency-display'));
        }
        
        // Add Bulgarian translations box
        $this->add_bulgarian_translations_box();
        
        global $wpdb;

        // Check if backup table exists
        $cache_key = 'dual_currency_table_exists';
        $table_exists = wp_cache_get($cache_key);

        if (false === $table_exists) {
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'wc_price_backup'));
            wp_cache_set($cache_key, $table_exists, 'dual_currency', 3600); // Cache for 1 hour
        }

        if (!$table_exists) {
            $message = '<div class="notice notice-error"><p>No backup table found. Cannot restore prices.</p></div>';
        }

        // Get distinct currencies in backup
        $currencies = array();
        if ($table_exists) {
            $cache_key = 'dual_currency_currencies_list';
            $currencies = wp_cache_get($cache_key);

            if (false === $currencies) {
                $currencies = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT currency FROM {$wpdb->prefix}wc_price_backup"));
                wp_cache_set($cache_key, $currencies, 'dual_currency', 3600); // Cache for 1 hour
            }
        }

        // Process restore request
        if (isset($_POST['restore_prices']) && isset($_POST['restore_nonce']) &&
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['restore_nonce'])), 'restore_prices')) {

            // Validate currency input
            if (isset($_POST['restore_currency']) && in_array(sanitize_text_field(wp_unslash($_POST['restore_currency'])), array('BGN', 'EUR'), true)) {
                try {
                    // Check if we should enable dual currency (if the checkbox is checked)
                    $enable_dual_currency = isset($_POST['enable_dual_currency']) && sanitize_text_field(wp_unslash($_POST['enable_dual_currency'])) == 'yes';
                    
                    // Restore prices from backup with the dual currency setting
                    $restored = Dual_Currency_Converter::restore_prices(sanitize_text_field(wp_unslash($_POST['restore_currency'])), $enable_dual_currency);

                    $message = '<div class="notice notice-success"><p>Prices have been restored! ' . intval($restored) . ' price values reverted.';
                    if ($enable_dual_currency) {
                        $message .= ' Dual currency display has been enabled.';
                    } else {
                        $message .= ' Dual currency display has been disabled.';
                    }
                    $message .= '</p></div>';
                } catch (Exception $e) {
                    error_log('Error restoring prices: ' . $e->getMessage());
                    $message = '<div class="notice notice-error"><p>Error restoring prices: ' . esc_html($e->getMessage()) . '</p></div>';
                }
            } else {
                $message = '<div class="notice notice-error"><p>Invalid currency selection.</p></div>';
            }
        }

        // Get current dual currency status
        $dual_currency_enabled = get_option('dual_currency_enable', 'yes') === 'yes';

        // Display the admin interface
        ?>
        <div class="wrap ignatov-currency-page">
            <div class="ignatov-currency-header">
                <h1>Restore Original Prices</h1>
                <div class="ignatov-currency-branding">by IgnatovDesigns.com</div>
            </div>
            
            <?php if (isset($message)) echo wp_kses_post($message); ?>
            
            <?php if ($table_exists): ?>
            <div class="ignatov-currency-container">
                <div class="ignatov-currency-box">
                    <h2>Restore Backed Up Prices</h2>
                    
                    <div class="ignatov-currency-info">
                        <p>This tool will restore your original prices from the backup created during conversion.</p>
                        <p>Current dual currency status: <strong><?php echo $dual_currency_enabled ? 'Enabled' : 'Disabled'; ?></strong></p>
                    </div>

                    <form method="post" class="ignatov-currency-form">
                        <?php wp_nonce_field('restore_prices', 'restore_nonce'); ?>

                        <label for="restore_currency">Restore currency setting to:</label>
                        <select name="restore_currency" id="restore_currency">
                            <?php foreach ($currencies as $currency): ?>
                            <option value="<?php echo esc_attr($currency); ?>"><?php echo esc_html($currency); ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <div class="ignatov-currency-checkbox">
                            <label class="ignatov-switch">
                                <input type="checkbox" name="enable_dual_currency" value="yes" <?php if ($dual_currency_enabled) echo 'checked'; ?>>
                                <span class="ignatov-slider"></span>
                            </label>
                            Enable dual currency display after restoration
                        </div>

                        <div class="ignatov-currency-submit">
                            <input type="submit" name="restore_prices" class="button button-primary" value="Restore Original Prices">
                        </div>
                    </form>
                </div>
                
                <div class="ignatov-currency-box">
                    <h2>Backup Information</h2>
                    
                    <?php
                    // Count backup records
                    $backup_count = 0;
                    $latest_backup = '';
                    if ($table_exists) {
                        $backup_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}wc_price_backup"));
                        $latest_backup = $wpdb->get_var($wpdb->prepare("SELECT MAX(backup_date) FROM {$wpdb->prefix}wc_price_backup"));
                    }
                    ?>
                    
                    <p>You have <strong><?php echo intval($backup_count); ?></strong> price records backed up.</p>
                    
                    <?php if (!empty($latest_backup)): ?>
                    <p>Latest backup was created on: <strong><?php echo esc_html($latest_backup); ?></strong></p>
                    <?php endif; ?>
                    
                    <div class="ignatov-currency-notice">
                        <p><strong>Note:</strong> Restoring prices will revert all product prices to their backed-up values. This action cannot be undone.</p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="ignatov-currency-container">
                <div class="ignatov-currency-box">
                    <h2>No Backup Available</h2>
                    <p>No price backup table was found in the database. You need to run a conversion with the "Create a backup" option enabled first.</p>
                    
                    <p><a href="<?php echo esc_url(admin_url('admin.php?page=dual-currency-conversion')); ?>" class="button">Go to BGN to EUR Converter</a></p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="ignatov-currency-footer">
                Dual Currency Display System &copy; <?php echo esc_html(gmdate('Y')); ?> IgnatovDesigns.com - All rights reserved.
            </div>
        </div>
        <?php
    }
}