/**
 * Dual Currency Display Admin Scripts
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Confirmation dialog for conversion actions
        $('.ignatov-currency-form').on('submit', function(e) {
            // Only show confirmation for conversion forms, not for updating exchange rate
            if ($(this).find('input[name="convert_currency"]').length) {
                if (!confirm('Are you sure you want to convert your product prices? This action will modify your product data. We recommend making a database backup first.')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        // Confirmation dialog for restore action
        $('.ignatov-currency-form').on('submit', function(e) {
            if ($(this).find('input[name="restore_prices"]').length) {
                if (!confirm('Are you sure you want to restore your product prices from backup? This will revert all current prices.')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    });

})(jQuery);