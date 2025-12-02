<?php
/**
 * Checkout modifications class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Arta_Budget_Checkout {
    
    private static $instance = null;
    private $sale_type_field_name = 'arta_budget_sale_type';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add sale type field to checkout - before payment methods section
        add_action('woocommerce_review_order_before_payment', array($this, 'add_sale_type_field'));
        
        // Save sale type to order
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_sale_type_to_order'));
        
        // Modify product prices for Bajet sales
        add_action('woocommerce_before_calculate_totals', array($this, 'modify_cart_item_prices'), 99, 1);
        
        // Apply credit discount in Bajet mode
        add_action('woocommerce_cart_calculate_fees', array($this, 'apply_credit_discount'), 10, 1);
        
        // Process checkout - check if credit covers full amount
        add_action('woocommerce_checkout_process', array($this, 'process_checkout_with_credit'));
        
        // Complete order automatically if credit covers full amount
        add_action('woocommerce_checkout_order_processed', array($this, 'auto_complete_order_with_credit'), 10, 1);
        
        // Store sale type in session
        add_action('wp_ajax_arta_budget_update_sale_type', array($this, 'ajax_update_sale_type'));
        add_action('wp_ajax_nopriv_arta_budget_update_sale_type', array($this, 'ajax_update_sale_type'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        if (is_checkout()) {
            wp_enqueue_script(
                'arta-budget-checkout',
                ARTA_BUDGET_PLUGIN_URL . 'assets/js/checkout.js',
                array('jquery', 'wc-checkout'),
                ARTA_BUDGET_VERSION,
                true
            );
            
            wp_localize_script('arta-budget-checkout', 'artaBudget', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('arta_budget_nonce'),
                'saleTypeNormal' => 'normal',
                'saleTypeBajet' => 'bajet'
            ));
            
            wp_enqueue_style(
                'arta-budget-checkout',
                ARTA_BUDGET_PLUGIN_URL . 'assets/css/checkout.css',
                array(),
                ARTA_BUDGET_VERSION
            );
        }
    }
    
    /**
     * Add sale type field to checkout - in payment section
     */
    public function add_sale_type_field() {
        $current_type = $this->get_session_sale_type();
        
        // Get user credit if logged in
        $user_credit = 0;
        $user_credit_formatted = '';
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $user_credit = Arta_Budget_Database::get_user_credit($user_id);
            $user_credit_formatted = wc_price($user_credit);
        }
        ?>
        <div class="arta-budget-sale-type-wrapper" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
            <h3 style="margin-top: 0; margin-bottom: 15px;"><?php _e('نوع فروش', 'arta-budget-credit-sales'); ?></h3>
            <?php if (is_user_logged_in() && $user_credit > 0): ?>
                <div class="arta-budget-user-credit-display" style="margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.7;">
                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                    <span style="font-size: 14px; opacity: 0.8;"><?php _e('اعتبار شما:', 'arta-budget-credit-sales'); ?></span>
                    <strong style="font-size: 15px; font-weight: 700;"><?php echo $user_credit_formatted; ?></strong>
                </div>
            <?php endif; ?>
            <div class="arta-budget-checkout-select-wrapper" style="position: relative;">
                <?php
                woocommerce_form_field(
                    $this->sale_type_field_name,
                    array(
                        'type' => 'select',
                        'label' => false,
                        'required' => true,
                        'options' => array(
                            'normal' => __('عادی', 'arta-budget-credit-sales'),
                            'bajet' => __('باجت', 'arta-budget-credit-sales')
                        ),
                        'default' => $current_type ? $current_type : 'normal',
                        'class' => array('form-row-wide', 'arta-budget-sale-type')
                    ),
                    $current_type ? $current_type : 'normal'
                );
                ?>
                <span class="arta-budget-checkout-loading-spinner" style="display: none;"></span>
            </div>
            <?php
            // Get percentage from settings for description
            $settings = get_option('arta_budget_gateway_settings', array());
            $percent = isset($settings['bajet_price_increase_percent']) ? floatval($settings['bajet_price_increase_percent']) : 12;
            ?>
            <p class="description" style="margin-top: 10px; margin-bottom: 0; font-size: 13px; color: #666;">
                <?php printf(__('در حالت باجت، %s%% به مبلغ سفارش افزوده می‌شود و فقط درگاه‌های مجاز نمایش داده می‌شوند.', 'arta-budget-credit-sales'), $percent); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Save sale type to order
     */
    public function save_sale_type_to_order($order_id) {
        if (isset($_POST[$this->sale_type_field_name])) {
            $sale_type = sanitize_text_field($_POST[$this->sale_type_field_name]);
            update_post_meta($order_id, '_arta_budget_sale_type', $sale_type);
            
            if ($sale_type === 'bajet') {
                $user_id = get_current_user_id();
                $user_credit = Arta_Budget_Database::get_user_credit($user_id);
                $order = wc_get_order($order_id);
                $order_total = $order->get_total();
                
                update_post_meta($order_id, '_arta_budget_user_credit_used', $user_credit);
                update_post_meta($order_id, '_arta_budget_order_total', $order_total);
            }
        }
    }
    
    /**
     * Get Bajet price increase percentage
     */
    private function get_bajet_percent() {
        $settings = get_option('arta_budget_gateway_settings', array());
        return isset($settings['bajet_price_increase_percent']) ? floatval($settings['bajet_price_increase_percent']) : 12;
    }
    
    /**
     * Modify cart item prices before calculation
     */
    public function modify_cart_item_prices($cart) {
        
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        // Prevent infinite loop
        if (isset($cart->arta_budget_calculating)) {
            return;
        }
        $cart->arta_budget_calculating = true;
        
        $sale_type = $this->get_session_sale_type();
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['data']) && isset($cart_item['product_id'])) {
                $product = $cart_item['data'];
                $product_id = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
                
                // Get original product directly from database to avoid any modifications
                $original_product = wc_get_product($product_id);
                if (!$original_product) {
                    continue;
                }
                
                // Get original price from fresh product object
                // First try to get from cart item data (if already stored)
              
                $original_price = floatval($original_product->get_regular_price());
                // Calculate expected price based on sale type
                if ($sale_type === 'bajet' && $original_price > 0) {
                    $percent = $this->get_bajet_percent();
                    $multiplier = 1 + ($percent / 100);
                    $expected_price = $original_price * $multiplier;
                    $product->set_price($expected_price);
                } else {
                    // Normal mode - restore original price
                    if ($original_price > 0) {
                        $product->set_price($original_price);
                    }
                }
            }
        }
        
        unset($cart->arta_budget_calculating);
    }
    
    /**
     * Apply credit discount in Bajet mode
     */
    public function apply_credit_discount($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        $sale_type = $this->get_session_sale_type();
        
        // Only apply in Bajet mode and if user is logged in
        if ($sale_type !== 'bajet' || !is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $user_credit = Arta_Budget_Database::get_user_credit($user_id);
        
        if ($user_credit <= 0) {
            return;
        }
        
        // Get cart total (subtotal + tax - any existing credit fees)
        $cart_total = $cart->get_subtotal() + $cart->get_subtotal_tax();
        
        // Subtract any existing credit discount fees (to avoid double discount)
        foreach ($cart->get_fees() as $fee) {
            if (strpos($fee->name, __('کسر از اعتبار باجت', 'arta-budget-credit-sales')) !== false) {
                $cart_total += abs($fee->amount); // Add back the discount amount
            }
        }
        
        // Apply credit discount (up to cart total)
        $discount_amount = min($user_credit, $cart_total);
        
        if ($discount_amount > 0) {
            $cart->add_fee(
                __('کسر از اعتبار باجت', 'arta-budget-credit-sales'),
                -$discount_amount,
                false,
                ''
            );
        }
    }
    
    /**
     * Process checkout with credit - complete order if credit covers full amount
     */
    public function process_checkout_with_credit() {
        $sale_type = $this->get_session_sale_type();
        
        // Only process in Bajet mode and if user is logged in
        if ($sale_type !== 'bajet' || !is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $user_credit = Arta_Budget_Database::get_user_credit($user_id);
        
        if ($user_credit <= 0) {
            return;
        }
        
        // Get cart total after all calculations
        $cart_total = WC()->cart->get_total('edit');
        
        // If credit covers full amount, mark for auto-completion
        if ($user_credit >= $cart_total) {
            // Store in session to use after order creation
            WC()->session->set('arta_budget_full_credit_payment', true);
            WC()->session->set('arta_budget_credit_amount_used', $cart_total);
        }
    }
    
    /**
     * Auto-complete order if credit covers full amount
     */
    public function auto_complete_order_with_credit($order_id) {
        $sale_type = WC()->session->get('arta_budget_sale_type');
        
        // Only process in Bajet mode and if user is logged in
        if ($sale_type !== 'bajet' || !is_user_logged_in()) {
            return;
        }
        
        // Check if this is a full credit payment
        $full_credit_payment = WC()->session->get('arta_budget_full_credit_payment');
        if (!$full_credit_payment) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $user_id = get_current_user_id();
        $user_credit = Arta_Budget_Database::get_user_credit($user_id);
        $order_total = $order->get_total();
        
        // Double check that credit still covers the amount
        if ($user_credit >= $order_total && $order_total > 0) {
            // Deduct credit
            $new_credit = $user_credit - $order_total;
            Arta_Budget_Database::update_user_credit(
                $user_id,
                $new_credit,
                sprintf(__('پرداخت سفارش #%s از اعتبار باجت', 'arta-budget-credit-sales'), $order->get_order_number())
            );
            
            // Mark order as paid
            $order->payment_complete();
            
            // Set payment method
            $order->set_payment_method_title(__('پرداخت از اعتبار باجت', 'arta-budget-credit-sales'));
            $order->save();
            
            // Store payment info
            update_post_meta($order_id, '_arta_budget_payment_type', 'full_credit');
            update_post_meta($order_id, '_arta_budget_credit_used', $order_total);
            update_post_meta($order_id, '_arta_budget_paid_via_credit', true);
            
            // Clear session flags
            WC()->session->__unset('arta_budget_full_credit_payment');
            WC()->session->__unset('arta_budget_credit_amount_used');
        }
    }
    
    /**
     * Get sale type from session or cookie
     */
    public function get_session_sale_type() {
        // First check cookie
        $cookie_value = isset($_COOKIE['arta_budget_sale_type']) ? sanitize_text_field($_COOKIE['arta_budget_sale_type']) : '';
        if (in_array($cookie_value, array('normal', 'bajet'))) {
            // Sync to session
            if (WC() && WC()->session) {
                WC()->session->set('arta_budget_sale_type', $cookie_value);
            }
            return $cookie_value;
        }
        
        // Fallback to session
        if (!WC()->session) {
            return 'normal';
        }
        
        $sale_type = WC()->session->get('arta_budget_sale_type');
        return $sale_type ? $sale_type : 'normal';
    }
    
    /**
     * Set sale type in session
     */
    public function set_session_sale_type($sale_type) {
        if (WC()->session) {
            WC()->session->set('arta_budget_sale_type', $sale_type);
        }
    }
    
    /**
     * AJAX handler for updating sale type
     */
    public function ajax_update_sale_type() {
        check_ajax_referer('arta_budget_nonce', 'nonce');
        
        $sale_type = isset($_POST['sale_type']) ? sanitize_text_field($_POST['sale_type']) : 'normal';
        
        if (!in_array($sale_type, array('normal', 'bajet'))) {
            wp_send_json_error(array('message' => __('نوع فروش نامعتبر است.', 'arta-budget-credit-sales')));
        }
        
        $this->set_session_sale_type($sale_type);
        
        // Trigger cart update
        WC()->cart->calculate_totals();
        
        wp_send_json_success(array(
            'message' => __('نوع فروش به‌روزرسانی شد.', 'arta-budget-credit-sales'),
            'sale_type' => $sale_type
        ));
    }
}

