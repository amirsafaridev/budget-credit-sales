/**
 * Checkout page JavaScript
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        var $saleTypeField = $('#arta_budget_sale_type');
        var $spinner = $('.arta-budget-checkout-loading-spinner');
        
        if ($saleTypeField.length) {
            // Handle sale type change
            $saleTypeField.on('change', function() {
                // Prevent multiple clicks
                if ($saleTypeField.prop('disabled')) {
                    return;
                }
                
                var saleType = $(this).val();
                updateSaleType(saleType);
            });
        }
        
        /**
         * Set cookie helper function
         */
        function setCookie(name, value, days) {
            var expires = '';
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = '; expires=' + date.toUTCString();
            }
            document.cookie = name + '=' + (value || '') + expires + '; path=/';
        }
        
        /**
         * Update sale type via AJAX and save to cookie
         */
        function updateSaleType(saleType) {
            // Disable field and show loading
            $saleTypeField.prop('disabled', true);
            $spinner.fadeIn();
            
            // Save to cookie first to sync with shortcode
            setCookie('arta_budget_sale_type', saleType, 30); // 30 days
            
            // Update shortcode selector if exists on the same page
            var $shortcodeSelector = $('#arta-budget-sale-type-select');
            if ($shortcodeSelector.length && $shortcodeSelector.val() !== saleType) {
                $shortcodeSelector.val(saleType);
            }
            
            $.ajax({
                url: artaBudget.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arta_budget_update_sale_type',
                    sale_type: saleType,
                    nonce: artaBudget.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Trigger cart update
                        $('body').trigger('update_checkout');
                        
                        // Re-enable after checkout update
                        $(document.body).one('updated_checkout', function() {
                            $saleTypeField.prop('disabled', false);
                            $spinner.fadeOut();
                        });
                    } else {
                        // Re-enable on error
                        $saleTypeField.prop('disabled', false);
                        $spinner.fadeOut();
                        console.error('Error updating sale type');
                    }
                },
                error: function() {
                    // Re-enable on error
                    $saleTypeField.prop('disabled', false);
                    $spinner.fadeOut();
                    console.error('Error updating sale type');
                }
            });
        }
    });
    
    // Update payment gateways when sale type changes
    $(document.body).on('updated_checkout', function() {
        // Payment gateways will be filtered server-side
    });
    
})(jQuery);

