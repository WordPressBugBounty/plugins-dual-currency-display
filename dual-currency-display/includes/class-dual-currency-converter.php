<?php
/**
 * Currency conversion functionality for Dual Currency Display
 *
 * @package Dual_Currency_Display
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Dual_Currency_Converter
 * Handles currency conversion functionality
 */
class Dual_Currency_Converter {
    
    /**
     * Back up price data before conversion - HPOS compatible
     */
    public static function backup_prices() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'dual-currency-display'));
        }
        
        global $wpdb;

        // Create backup table if it doesn't exist
        $table_name = $wpdb->prefix . 'wc_price_backup';
        $cache_key = 'dual_currency_backup_table_exists';
        $table_exists = wp_cache_get($cache_key);

        if (false === $table_exists) {
            $table_exists = get_option('dual_currency_table_exists_' . $table_name, false);
            wp_cache_set($cache_key, $table_exists, 'dual_currency', 3600);
        }

        if($table_exists != $table_name) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            $sql = "CREATE TABLE {$wpdb->prefix}wc_price_backup (
                backup_id bigint(20) NOT NULL AUTO_INCREMENT,
                post_id bigint(20) NOT NULL,
                meta_key varchar(255) NOT NULL,
                price_value decimal(15,4) NOT NULL,
                currency varchar(10) NOT NULL,
                backup_date datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (backup_id),
                KEY post_id (post_id),
                KEY meta_key (meta_key)
            )";
            
            dbDelta($sql);
            
            // Store that the table now exists
            update_option('dual_currency_table_exists_' . $table_name, $table_name);
        }

        // Get current currency (only need this once)
        $currency = get_woocommerce_currency();
        
        try {
            // Get all products
            $product_ids = wc_get_products(array(
                'status' => 'publish',
                'limit' => -1,
                'return' => 'ids',
            ));
            
            // Prepare batch insert data
            $insert_data = array();
            
            foreach ($product_ids as $product_id) {
                $product = wc_get_product($product_id);
                
                if (!$product) {
                    continue;
                }
                
                // Regular price
                $regular_price = $product->get_regular_price();
                if (!empty($regular_price)) {
                    $insert_data[] = $wpdb->prepare(
                        "(%d, %s, %s, %s)",
                        $product_id,
                        '_regular_price',
                        $regular_price,
                        $currency
                    );
                }
                
                // Sale price
                $sale_price = $product->get_sale_price();
                if (!empty($sale_price)) {
                    $insert_data[] = $wpdb->prepare(
                        "(%d, %s, %s, %s)",
                        $product_id,
                        '_sale_price',
                        $sale_price,
                        $currency
                    );
                }
                
                // For variable products, backup variation prices
                if ($product->is_type('variable')) {
                    $variations = $product->get_children();
                    foreach ($variations as $variation_id) {
                        $variation = wc_get_product($variation_id);
                        
                        if (!$variation) {
                            continue;
                        }
                        
                        $var_regular_price = $variation->get_regular_price();
                        if (!empty($var_regular_price)) {
                            $insert_data[] = $wpdb->prepare(
                                "(%d, %s, %s, %s)",
                                $variation_id,
                                '_regular_price',
                                $var_regular_price,
                                $currency
                            );
                        }
                        
                        $var_sale_price = $variation->get_sale_price();
                        if (!empty($var_sale_price)) {
                            $insert_data[] = $wpdb->prepare(
                                "(%d, %s, %s, %s)",
                                $variation_id,
                                '_sale_price',
                                $var_sale_price,
                                $currency
                            );
                        }
                    }
                }
            }
            
            // Insert in batches if there's data
            if (!empty($insert_data)) {
                // Create the base SQL query with the table name
                $sql = "INSERT INTO {$wpdb->prefix}wc_price_backup 
                        (post_id, meta_key, price_value, currency)
                        VALUES " . implode(',', $insert_data);
                
                // Use a direct query since values are already prepared individually
                $wpdb->query($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            }

            return true;
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Error backing up prices: ' . $e->getMessage());
            }
            throw new Exception('Error backing up prices: ' . esc_html($e->getMessage()));
        }
    }

    /**
     * Convert all prices from BGN to EUR - HPOS compatible
     * Stores original BGN prices to avoid rounding errors on display
     * 
     * @param float $exchange_rate Exchange rate (BGN/EUR)
     * @return array Result information
     */
    public static function bgn_to_eur_convert_prices($exchange_rate) {
        // Validate exchange rate
        if (!is_numeric($exchange_rate) || $exchange_rate <= 0) {
            return array(
                'count' => 0,
                'time' => 0,
                'error' => 'Invalid exchange rate'
            );
        }

        // Start counting time for performance reporting
        $start_time = microtime(true);

        // Counter for products updated
        $count = 0;

        try {
            // Get all products
            $product_ids = wc_get_products(array(
                'status' => 'publish',
                'limit' => -1,
                'return' => 'ids',
            ));

            // Loop through products and update prices
            foreach ($product_ids as $product_id) {
                $product = wc_get_product($product_id);
                
                if (!$product) {
                    continue;
                }
                
                // Regular price
                $bgn_regular_price = $product->get_regular_price();
                if (!empty($bgn_regular_price)) {
                    $eur_regular_price = round($bgn_regular_price / $exchange_rate, 2);
                    $product->set_regular_price($eur_regular_price);
                    // Store original BGN price for accurate display
                    update_post_meta($product_id, '_original_bgn_regular_price', $bgn_regular_price);
                }
                
                // Sale price
                $bgn_sale_price = $product->get_sale_price();
                if (!empty($bgn_sale_price)) {
                    $eur_sale_price = round($bgn_sale_price / $exchange_rate, 2);
                    $product->set_sale_price($eur_sale_price);
                    // Store original BGN sale price for accurate display
                    update_post_meta($product_id, '_original_bgn_sale_price', $bgn_sale_price);
                }
                
                // For variable products, update variation prices
                if ($product->is_type('variable')) {
                    $variations = $product->get_children();
                    foreach ($variations as $variation_id) {
                        $variation = wc_get_product($variation_id);
                        
                        if (!$variation) {
                            continue;
                        }
                        
                        $var_regular_price = $variation->get_regular_price();
                        if (!empty($var_regular_price)) {
                            $var_eur_regular_price = round($var_regular_price / $exchange_rate, 2);
                            $variation->set_regular_price($var_eur_regular_price);
                            // Store original BGN price for variation
                            update_post_meta($variation_id, '_original_bgn_regular_price', $var_regular_price);
                        }
                        
                        $var_sale_price = $variation->get_sale_price();
                        if (!empty($var_sale_price)) {
                            $var_eur_sale_price = round($var_sale_price / $exchange_rate, 2);
                            $variation->set_sale_price($var_eur_sale_price);
                            // Store original BGN sale price for variation
                            update_post_meta($variation_id, '_original_bgn_sale_price', $var_sale_price);
                        }
                        
                        $variation->save();
                    }
                }
                
                // Save the product
                $product->save();
                $count++;
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Error converting prices: ' . esc_html($e->getMessage()));
            }
            return array(
                'count' => 0,
                'time' => 0,
                'error' => $e->getMessage()
            );
        }

        // Get execution time
        $execution_time = microtime(true) - $start_time;

        return array(
            'count' => $count,
            'time' => round($execution_time, 2)
        );
    }

    /**
     * Convert all prices from EUR to BGN - HPOS compatible
     * Stores original EUR prices to avoid rounding errors on display
     * 
     * @param float $exchange_rate Exchange rate (BGN/EUR)
     * @return array Result information
     */
    public static function eur_to_bgn_convert_prices($exchange_rate) {
        // Validate exchange rate
        if (!is_numeric($exchange_rate) || $exchange_rate <= 0) {
            return array(
                'count' => 0,
                'time' => 0,
                'error' => 'Invalid exchange rate'
            );
        }

        // Start counting time for performance reporting
        $start_time = microtime(true);

        try {
            // Get all products
            $product_ids = wc_get_products(array(
                'status' => 'publish',
                'limit' => -1,
                'return' => 'ids',
            ));

            // Counter for products updated
            $count = 0;

            // Loop through products and update prices
            foreach ($product_ids as $product_id) {
                $product = wc_get_product($product_id);
                
                if (!$product) {
                    continue;
                }
                
                // Regular price
                $eur_regular_price = $product->get_regular_price();
                if (!empty($eur_regular_price)) {
                    $bgn_regular_price = round($eur_regular_price * $exchange_rate, 2);
                    $product->set_regular_price($bgn_regular_price);
                    // Store original EUR price for accurate display
                    update_post_meta($product_id, '_original_eur_regular_price', $eur_regular_price);
                }
                
                // Sale price
                $eur_sale_price = $product->get_sale_price();
                if (!empty($eur_sale_price)) {
                    $bgn_sale_price = round($eur_sale_price * $exchange_rate, 2);
                    $product->set_sale_price($bgn_sale_price);
                    // Store original EUR sale price for accurate display
                    update_post_meta($product_id, '_original_eur_sale_price', $eur_sale_price);
                }
                
                // For variable products, update variation prices
                if ($product->is_type('variable')) {
                    $variations = $product->get_children();
                    foreach ($variations as $variation_id) {
                        $variation = wc_get_product($variation_id);
                        
                        if (!$variation) {
                            continue;
                        }
                        
                        $var_regular_price = $variation->get_regular_price();
                        if (!empty($var_regular_price)) {
                            $var_bgn_regular_price = round($var_regular_price * $exchange_rate, 2);
                            $variation->set_regular_price($var_bgn_regular_price);
                            // Store original EUR price for variation
                            update_post_meta($variation_id, '_original_eur_regular_price', $var_regular_price);
                        }
                        
                        $var_sale_price = $variation->get_sale_price();
                        if (!empty($var_sale_price)) {
                            $var_bgn_sale_price = round($var_sale_price * $exchange_rate, 2);
                            $variation->set_sale_price($var_bgn_sale_price);
                            // Store original EUR sale price for variation
                            update_post_meta($variation_id, '_original_eur_sale_price', $var_sale_price);
                        }
                        
                        $variation->save();
                    }
                }
                
                // Save the product
                $product->save();
                $count++;
            }

            // Get execution time
            $execution_time = microtime(true) - $start_time;

            return array(
                'count' => $count,
                'time' => round($execution_time, 2)
            );
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Error converting prices from EUR to BGN: ' . $e->getMessage());
            }
            return array(
                'count' => 0,
                'time' => 0,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Restore prices from backup - HPOS compatible
     * Also clears stored original prices
     * 
     * @param string $currency Currency to restore (BGN or EUR)
     * @param bool $enable_dual_currency Whether to enable dual currency display after restoration
     * @return int Number of records restored
     */
    public static function restore_prices($currency, $enable_dual_currency = false) {
        global $wpdb;
        $restored = 0;
        
        try {
            // Get all backup records
            $backup_records = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT post_id, meta_key, price_value FROM {$wpdb->prefix}wc_price_backup WHERE currency = %s",
                    $currency
                )
            );
            
            // Loop through each record and restore the price
            foreach ($backup_records as $record) {
                $product = wc_get_product($record->post_id);
                
                if (!$product) {
                    continue;
                }
                
                if ($record->meta_key === '_regular_price') {
                    $product->set_regular_price($record->price_value);
                    $restored++;
                    // Clear stored original prices
                    delete_post_meta($record->post_id, '_original_bgn_regular_price');
                    delete_post_meta($record->post_id, '_original_eur_regular_price');
                } else if ($record->meta_key === '_sale_price') {
                    $product->set_sale_price($record->price_value);
                    $restored++;
                    // Clear stored original sale prices
                    delete_post_meta($record->post_id, '_original_bgn_sale_price');
                    delete_post_meta($record->post_id, '_original_eur_sale_price');
                }
                
                $product->save();
            }
            
            // Update currency setting
            update_option('woocommerce_currency', sanitize_text_field($currency));
            
            // Set dual currency option based on parameter
            update_option('dual_currency_enable', $enable_dual_currency ? 'yes' : 'no');
            
            return $restored;
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Error restoring prices: ' . esc_html($e->getMessage()));
            }
            throw new Exception(esc_html($e->getMessage()));
        }
    }
}