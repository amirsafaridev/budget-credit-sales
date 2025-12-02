<?php
/**
 * Payment handler for split payments
 */

if (!defined('ABSPATH')) {
    exit;
}

class Arta_Budget_Payment_Handler {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook into order payment processing
        add_action('woocommerce_payment_complete', array($this, 'handle_payment_complete'));
        add_action('woocommerce_order_status_processing', array($this, 'handle_order_processing'));
        add_action('woocommerce_order_status_completed', array($this, 'handle_order_completed'));
        
        // Handle Bajet gateway payment
        add_action('woocommerce_api_arta_budget_bajet_payment', array($this, 'handle_bajet_payment_callback'));
    }
    
    /**
     * Handle payment complete
     */
    public function handle_payment_complete($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $sale_type = get_post_meta($order_id, '_arta_budget_sale_type', true);
        if ($sale_type !== 'bajet') {
            return;
        }
        
        $payment_type = get_post_meta($order_id, '_arta_budget_payment_type', true);
        
        if ($payment_type === 'split') {
            $this->process_split_payment($order);
        } elseif ($payment_type === 'full_bajet') {
            $this->process_full_bajet_payment($order);
        }
    }
    
    /**
     * Process full Bajet payment
     */
    private function process_full_bajet_payment($order) {
        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }
        
        $order_total = $order->get_total();
        $user_credit = Arta_Budget_Database::get_user_credit($user_id);
        
        if ($user_credit >= $order_total) {
            // Deduct credit
            $new_credit = $user_credit - $order_total;
            Arta_Budget_Database::update_user_credit(
                $user_id,
                $new_credit,
                sprintf(__('پرداخت سفارش #%s', 'arta-budget-credit-sales'), $order->get_order_number())
            );
            
            // Mark order as paid via Bajet
            update_post_meta($order->get_id(), '_arta_budget_paid_via_bajet', true);
        }
    }
    
    /**
     * Process split payment
     */
    private function process_split_payment($order) {
        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }
        
        $credit_used = get_post_meta($order->get_id(), '_arta_budget_credit_used', true);
        $remaining = get_post_meta($order->get_id(), '_arta_budget_remaining_amount', true);
        
        if (!$credit_used || !$remaining) {
            return;
        }
        
        $user_credit = Arta_Budget_Database::get_user_credit($user_id);
        
        // Check if Bajet portion is already paid
        $bajet_paid = get_post_meta($order->get_id(), '_arta_budget_bajet_paid', true);
        
        if (!$bajet_paid && $user_credit >= $credit_used) {
            // Deduct Bajet credit
            $new_credit = $user_credit - $credit_used;
            Arta_Budget_Database::update_user_credit(
                $user_id,
                $new_credit,
                sprintf(__('پرداخت بخشی سفارش #%s (باجت)', 'arta-budget-credit-sales'), $order->get_order_number())
            );
            
            update_post_meta($order->get_id(), '_arta_budget_bajet_paid', true);
        }
        
        // Check if second payment is needed
        $second_payment_done = get_post_meta($order->get_id(), '_arta_budget_second_payment_done', true);
        
        if (!$second_payment_done && $remaining > 0) {
            // Redirect to second payment gateway
            $this->redirect_to_second_payment($order, $remaining);
        }
    }
    
    /**
     * Redirect to second payment gateway
     */
    private function redirect_to_second_payment($order, $amount) {
        $default_gateway = Arta_Budget_Payment_Gateways::get_default_second_gateway();
        
        if (!$default_gateway) {
            return;
        }
        
        // Get payment gateway
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        
        if (!isset($gateways[$default_gateway])) {
            return;
        }
        
        $gateway = $gateways[$default_gateway];
        
        // Create a temporary order for second payment
        $temp_order_id = $this->create_temp_order_for_second_payment($order, $amount, $default_gateway);
        
        if ($temp_order_id) {
            // Process payment with selected gateway
            $result = $gateway->process_payment($temp_order_id);
            
            if (isset($result['result']) && $result['result'] === 'success') {
                // Redirect to payment page
                wp_redirect($result['redirect']);
                exit;
            }
        }
    }
    
    /**
     * Create temporary order for second payment
     */
    private function create_temp_order_for_second_payment($original_order, $amount, $gateway_id) {
        // Create a new order for the remaining amount
        $order = wc_create_order();
        
        if (is_wp_error($order)) {
            return false;
        }
        
        // Set order details
        $order->set_billing_first_name($original_order->get_billing_first_name());
        $order->set_billing_last_name($original_order->get_billing_last_name());
        $order->set_billing_email($original_order->get_billing_email());
        $order->set_billing_phone($original_order->get_billing_phone());
        $order->set_billing_address_1($original_order->get_billing_address_1());
        $order->set_billing_city($original_order->get_billing_city());
        $order->set_billing_postcode($original_order->get_billing_postcode());
        $order->set_billing_country($original_order->get_billing_country());
        
        // Add fee item for remaining amount
        $fee = new WC_Order_Item_Fee();
        $fee->set_name(__('باقی مبلغ پرداخت', 'arta-budget-credit-sales'));
        $fee->set_amount($amount);
        $fee->set_total($amount);
        $order->add_item($fee);
        
        $order->set_total($amount);
        $order->set_payment_method($gateway_id);
        $order->save();
        
        // Link to original order
        update_post_meta($order->get_id(), '_arta_budget_original_order_id', $original_order->get_id());
        update_post_meta($order->get_id(), '_arta_budget_is_second_payment', true);
        
        return $order->get_id();
    }
    
    /**
     * Handle order processing
     */
    public function handle_order_processing($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Check if this is a second payment order
        $is_second_payment = get_post_meta($order_id, '_arta_budget_is_second_payment', true);
        $original_order_id = get_post_meta($order_id, '_arta_budget_original_order_id', true);
        
        if ($is_second_payment && $original_order_id) {
            // Mark second payment as done
            update_post_meta($original_order_id, '_arta_budget_second_payment_done', true);
            
            // Complete the original order
            $original_order = wc_get_order($original_order_id);
            if ($original_order) {
                $original_order->update_status('completed');
            }
        }
    }
    
    /**
     * Handle order completed
     */
    public function handle_order_completed($order_id) {
        // Finalize any remaining payment logic
    }
    
    /**
     * Handle Bajet payment callback
     */
    public function handle_bajet_payment_callback() {
        // Handle callback from Bajet gateway
        // This should be implemented based on actual Bajet gateway API
    }
}

