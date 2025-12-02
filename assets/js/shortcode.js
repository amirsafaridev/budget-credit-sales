/**
 * Shortcode JavaScript for sale type selector
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        var $selector = $('#arta-budget-sale-type-select');
        var $message = $('.arta-budget-message');
        var $spinner = $('.arta-budget-loading-spinner');
        
        if ($selector.length) {
            $selector.on('change', function() {
                // Prevent multiple clicks
                if ($selector.prop('disabled')) {
                    return;
                }
                
                var saleType = $(this).val();
                var cartItemCount = getCartItemCount();
                
                // Check if cart is not empty
                if (cartItemCount > 0) {
                    $message
                        .text(artaBudgetShortcode.cartNotEmptyMessage)
                        .addClass('error')
                        .fadeIn();
                    
                    // Reset to previous value
                    setTimeout(function() {
                        var previousType = $selector.data('previous-value') || 'normal';
                        $selector.val(previousType);
                        $message.fadeOut();
                    }, 3000);
                    
                    return;
                }
                
                // Disable selector and show loading
                $selector.prop('disabled', true);
                $spinner.fadeIn();
                
                // Store previous value
                $selector.data('previous-value', $selector.val());
                
                // Update sale type (will save to cookie and reload page)
                updateSaleType(saleType);
            });
            
            // Store initial value
            $selector.data('previous-value', $selector.val());
        }
        
        /**
         * Get cart item count
         */
        function getCartItemCount() {
            var count = 0;
            
            // Try to get from WooCommerce cart fragments
            if (typeof wc_add_to_cart_params !== 'undefined') {
                var $cartCount = $('.cart-contents-count, .cart-count, .woocommerce-cart-count, .header-cart-count');
                if ($cartCount.length) {
                    var countText = $cartCount.text().trim();
                    count = parseInt(countText) || 0;
                }
            }
            
            // Fallback: check cart via AJAX if WooCommerce is available
            if (count === 0 && typeof wc_add_to_cart_params !== 'undefined') {
                $.ajax({
                    url: wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'get_cart'),
                    type: 'POST',
                    async: false,
                    success: function(response) {
                        if (response && response.fragments) {
                            // Try to extract count from fragments
                            var fragmentHtml = $(response.fragments['div.widget_shopping_cart_content'] || '');
                            if (fragmentHtml.length) {
                                var itemCount = fragmentHtml.find('.cart_list .cart_item').length;
                                count = itemCount || 0;
                            }
                        }
                    }
                });
            }
            
            return count;
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
            // Save to cookie first
            setCookie('arta_budget_sale_type', saleType, 30); // 30 days
            
            // Update checkout field if exists on the same page
            var $checkoutField = $('#arta_budget_sale_type');
            if ($checkoutField.length && $checkoutField.val() !== saleType) {
                $checkoutField.val(saleType).trigger('change');
            }
            
            $.ajax({
                url: artaBudgetShortcode.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arta_budget_update_sale_type',
                    sale_type: saleType,
                    nonce: artaBudgetShortcode.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload page after successful update
                        window.location.reload();
                    } else {
                        $message
                            .text('خطا در به‌روزرسانی نوع فروش.')
                            .addClass('error')
                            .fadeIn();
                    }
                },
                error: function(xhr, status, error) {
                    // Even on error, reload to apply cookie
                    window.location.reload();
                }
            });
        }
        
        /**
         * Update product prices display (percentage increase for Bajet)
         * Note: This function is not used anymore since page reloads after sale type change
         */
        function updatePrices(saleType) {
            // Skip on cart and checkout pages - prices are handled server-side
            if ($('body').hasClass('woocommerce-cart') || $('body').hasClass('woocommerce-checkout')) {
                return;
            }
            
            var bajetPercent = artaBudgetShortcode.bajetPercent || 12;
            var multiplier = saleType === 'bajet' ? (1 + (bajetPercent / 100)) : 1.0;
            
            // Update price elements (only on shop/product pages, not cart/checkout)
            $('.price, .woocommerce-Price-amount, .amount').not('.cart .price, .cart .woocommerce-Price-amount, .cart .amount, .woocommerce-cart .price, .woocommerce-cart .woocommerce-Price-amount, .woocommerce-cart .amount, .woocommerce-checkout .price, .woocommerce-checkout .woocommerce-Price-amount, .woocommerce-checkout .amount').each(function() {
                var $element = $(this);
                var originalPrice = $element.data('original-price');
                
                if (!originalPrice) {
                    // Store original price
                    var priceText = $element.text().replace(/[^\d,.]/g, '').replace(/,/g, '');
                    var price = parseFloat(priceText);
                    if (!isNaN(price) && price > 0) {
                        $element.data('original-price', price);
                        originalPrice = price;
                    }
                }
                
                if (originalPrice) {
                    var newPrice = originalPrice * multiplier;
                    var formattedPrice = formatPrice(newPrice);
                    $element.html($element.html().replace(/\d+([,.]\d+)?/g, formattedPrice));
                }
            });
        }
        
        /**
         * Format price
         */
        function formatPrice(price) {
            return Math.round(price).toLocaleString('fa-IR');
        }
    });
    
})(jQuery);

