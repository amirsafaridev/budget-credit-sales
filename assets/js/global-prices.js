/**
 * Global price update script - applies Bajet price increase across all pages
 */
(function($) {
    'use strict';
    
    /**
     * Get cookie value
     */
    function getCookie(name) {
        var nameEQ = name + '=';
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }
    
    /**
     * Update prices based on sale type
     */
    function updatePrices() {
        var saleType = getCookie('arta_budget_sale_type') || 'normal';
        var bajetPercent = (typeof artaBudgetGlobal !== 'undefined' && artaBudgetGlobal.bajetPercent) ? artaBudgetGlobal.bajetPercent : 12;
        var multiplier = saleType === 'bajet' ? (1 + (bajetPercent / 100)) : 1.0;
        
        // Skip on cart and checkout pages - prices are handled server-side
        if ($('body').hasClass('woocommerce-cart') || $('body').hasClass('woocommerce-checkout')) {
            return;
        }
        
        // Update price elements (only on shop/product pages, not cart/checkout)
        $('.price, .woocommerce-Price-amount, .amount, .product-price, .price-wrapper, span.price').not('.cart .price, .cart .woocommerce-Price-amount, .cart .amount, .woocommerce-cart .price, .woocommerce-cart .woocommerce-Price-amount, .woocommerce-cart .amount, .woocommerce-checkout .price, .woocommerce-checkout .woocommerce-Price-amount, .woocommerce-checkout .amount').each(function() {
            var $element = $(this);
            var originalPrice = $element.data('original-price');
            var originalHtml = $element.data('original-html');
            
            // Skip if element is empty
            if (!$element.text().trim()) {
                return;
            }
            
            // Store original price and HTML if not already stored
            if (!originalPrice || !originalHtml) {
                var priceText = $element.text().replace(/[^\d,.]/g, '').replace(/,/g, '');
                var price = parseFloat(priceText);
                if (!isNaN(price) && price > 0) {
                    $element.data('original-price', price);
                    $element.data('original-html', $element.html());
                    originalPrice = price;
                    originalHtml = $element.html();
                } else {
                    return;
                }
            }
            
            if (originalPrice && originalHtml) {
                // If normal, restore original HTML
                if (saleType === 'normal') {
                    $element.html(originalHtml);
                    $element.data('price-updated', false);
                } else {
                    // If bajet, apply multiplier
                    var updatedHtml = originalHtml.replace(/\d+([,.]\d+)?/g, function(match) {
                        var num = parseFloat(match.replace(/,/g, ''));
                        if (!isNaN(num) && num > 0) {
                            return formatPrice(num * multiplier);
                        }
                        return match;
                    });
                    
                    $element.html(updatedHtml);
                    $element.data('price-updated', true);
                }
            }
        });
    }
    
    /**
     * Format price
     */
    function formatPrice(price) {
        return Math.round(price).toLocaleString('fa-IR');
    }
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Update prices on page load
        updatePrices();
        
        // Update prices when WooCommerce fragments are updated (but not on cart/checkout)
        $(document.body).on('updated_wc_div', function() {
            if (!$('body').hasClass('woocommerce-cart') && !$('body').hasClass('woocommerce-checkout')) {
                setTimeout(updatePrices, 100);
            }
        });
    });
    
    /**
     * Also update on window load (for dynamically loaded content)
     */
    $(window).on('load', function() {
        setTimeout(updatePrices, 200);
    });
    
})(jQuery);

