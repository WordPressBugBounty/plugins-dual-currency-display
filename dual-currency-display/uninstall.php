<?php
/**
 * Uninstall functionality for Dual Currency Display for WooCommerce
 *
 * @package Dual_Currency_Display
 */

// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('dual_currency_bgn_eur_rate');

// The backup table will be left intact to prevent accidental data loss
// If you want to remove it, uncomment the lines below

/*
global $wpdb;
$table_name = $wpdb->prefix . 'wc_price_backup';
$wpdb->query("DROP TABLE IF EXISTS $table_name");
*/