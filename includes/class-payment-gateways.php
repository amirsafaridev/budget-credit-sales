<?php
/**
 * Payment gateway control class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Arta_Budget_Payment_Gateways {
    
    private static $instance = null;
    private static $filtering_in_progress = false; // Flag to prevent infinite loop
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Filter available payment gateways based on sale type
        add_filter('woocommerce_available_payment_gateways', array($this, 'filter_payment_gateways'));
        
        // Handle split payment logic
        add_action('woocommerce_checkout_process', array($this, 'process_split_payment'));
        
        // Store payment info in order
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_payment_info'));
    }
    
    /**
     * Filter payment gateways based on sale type
     */
    public function filter_payment_gateways($available_gateways) {
        // Prevent infinite loop
        if (self::$filtering_in_progress || is_admin()) {
            return $available_gateways;
        }
        
        $sale_type = $this->get_sale_type();
        
        // Set flag to prevent recursion
        self::$filtering_in_progress = true;
        
        // Get settings
        $settings = get_option('arta_budget_gateway_settings', array());
        
        if ($sale_type === 'bajet') {
            // If no settings configured, show all gateways
            if (empty($settings['enabled_gateways'])) {
                self::$filtering_in_progress = false;
                return $available_gateways;
            }
            
            // Get allowed gateways for Bajet mode
            $allowed_gateways = $this->get_allowed_bajet_gateways($available_gateways);
            
            // If no gateways are allowed, return empty
            if (empty($allowed_gateways)) {
                self::$filtering_in_progress = false;
                return array();
            }
            
            // Filter gateways - only keep allowed ones
            $filtered_gateways = array();
            foreach ($available_gateways as $gateway_id => $gateway) {
                if (in_array($gateway_id, $allowed_gateways)) {
                    $filtered_gateways[$gateway_id] = $gateway;
                }
            }
            
            // Reset flag
            self::$filtering_in_progress = false;
            
            return $filtered_gateways;
        } else {
            // Normal mode - filter based on enabled_gateways_normal settings
            // If no settings configured, show all gateways (default behavior)
            if (empty($settings['enabled_gateways_normal'])) {
                self::$filtering_in_progress = false;
                return $available_gateways;
            }
            
            // Get allowed gateways for Normal mode
            $allowed_gateways = $this->get_allowed_normal_gateways($available_gateways);
            
            // If no gateways are allowed, return empty
            if (empty($allowed_gateways)) {
                self::$filtering_in_progress = false;
                return array();
            }
            
            // Filter gateways - only keep allowed ones
            $filtered_gateways = array();
            foreach ($available_gateways as $gateway_id => $gateway) {
                if (in_array($gateway_id, $allowed_gateways)) {
                    $filtered_gateways[$gateway_id] = $gateway;
                }
            }
            
            // Reset flag
            self::$filtering_in_progress = false;
            
            return $filtered_gateways;
        }
    }
    
    /**
     * Get allowed payment gateways for Bajet mode
     */
    private function get_allowed_bajet_gateways($available_gateways = null) {
        $settings = get_option('arta_budget_gateway_settings', array());
        $allowed = array();
        
        // Use available gateways if provided, otherwise get all registered gateways
        if ($available_gateways !== null && is_array($available_gateways)) {
            $all_gateways = $available_gateways;
        } else {
            // Get all registered gateways (not just available ones)
            $payment_gateways_obj = WC()->payment_gateways();
            $all_gateways = array();
            
            // Try to get all gateways from property
            if (isset($payment_gateways_obj->payment_gateways)) {
                $all_gateways = $payment_gateways_obj->payment_gateways;
            } else {
                // Fallback: use reflection to access protected property
                try {
                    $reflection = new ReflectionClass($payment_gateways_obj);
                    $property = $reflection->getProperty('payment_gateways');
                    $property->setAccessible(true);
                    $all_gateways = $property->getValue($payment_gateways_obj);
                } catch (Exception $e) {
                    // If reflection fails, temporarily remove filter to get all available gateways
                    remove_filter('woocommerce_available_payment_gateways', array($this, 'filter_payment_gateways'));
                    $all_gateways = WC()->payment_gateways->get_available_payment_gateways();
                    add_filter('woocommerce_available_payment_gateways', array($this, 'filter_payment_gateways'));
                }
            }
            
            // Ensure all_gateways is an associative array with gateway_id as key
            // Sometimes it might be numeric indexed, so we need to rebuild it
            if (!empty($all_gateways) && is_array($all_gateways)) {
                $first_key = array_key_first($all_gateways);
                // If first key is numeric, rebuild array with gateway_id as key
                if (is_numeric($first_key)) {
                    $rebuilt_gateways = array();
                    foreach ($all_gateways as $gateway) {
                        if (is_object($gateway)) {
                            // Try different methods to get gateway ID
                            $gateway_id = null;
                            if (method_exists($gateway, 'id')) {
                                $gateway_id = $gateway->id;
                            } elseif (isset($gateway->id)) {
                                $gateway_id = $gateway->id;
                            } elseif (method_exists($gateway, 'get_id')) {
                                $gateway_id = $gateway->get_id();
                            }
                            
                            if ($gateway_id) {
                                $rebuilt_gateways[$gateway_id] = $gateway;
                            }
                        }
                    }
                    $all_gateways = $rebuilt_gateways;
                }
            }
        }
        
        // Always include Bajet gateway (Kalanu) if it exists in available gateways
        $bajet_gateway_ids = array('kalanu', 'bajet', 'bajet_credit', 'arta_bajet');
        foreach ($bajet_gateway_ids as $gateway_id) {
            if (isset($all_gateways[$gateway_id])) {
                $allowed[] = $gateway_id;
                break;
            }
        }
        
        // Get enabled gateways from settings
        if (isset($settings['enabled_gateways']) && is_array($settings['enabled_gateways']) && !empty($settings['enabled_gateways'])) {
            foreach ($settings['enabled_gateways'] as $gateway_id => $enabled) {
                // Check if gateway exists in available gateways
                // $enabled can be true or the gateway_id itself (depending on how it's stored)
                $is_enabled = ($enabled === true || $enabled === '1' || $enabled === 1);
                
                if ($is_enabled && isset($all_gateways[$gateway_id])) {
                    // Avoid duplicates
                    if (!in_array($gateway_id, $allowed)) {
                        $allowed[] = $gateway_id;
                    }
                }
            }
        } else {
            // Default allowed gateways (only if they exist in available gateways)
            $default_gateways = array('mellat', 'asanpardakht', 'bankmelli');
            foreach ($default_gateways as $gateway_id) {
                if (isset($all_gateways[$gateway_id]) && !in_array($gateway_id, $allowed)) {
                    $allowed[] = $gateway_id;
                }
            }
        }
        
        return $allowed;
    }
    
    /**
     * Get allowed payment gateways for Normal mode
     */
    private function get_allowed_normal_gateways($available_gateways = null) {
        $settings = get_option('arta_budget_gateway_settings', array());
        $allowed = array();
        
        // Use available gateways if provided, otherwise get all registered gateways
        if ($available_gateways !== null && is_array($available_gateways)) {
            $all_gateways = $available_gateways;
        } else {
            // Get all registered gateways (not just available ones)
            $payment_gateways_obj = WC()->payment_gateways();
            $all_gateways = array();
            
            // Try to get all gateways from property
            if (isset($payment_gateways_obj->payment_gateways)) {
                $all_gateways = $payment_gateways_obj->payment_gateways;
            } else {
                // Fallback: use reflection to access protected property
                try {
                    $reflection = new ReflectionClass($payment_gateways_obj);
                    $property = $reflection->getProperty('payment_gateways');
                    $property->setAccessible(true);
                    $all_gateways = $property->getValue($payment_gateways_obj);
                } catch (Exception $e) {
                    // If reflection fails, temporarily remove filter to get all available gateways
                    remove_filter('woocommerce_available_payment_gateways', array($this, 'filter_payment_gateways'));
                    $all_gateways = WC()->payment_gateways->get_available_payment_gateways();
                    add_filter('woocommerce_available_payment_gateways', array($this, 'filter_payment_gateways'));
                }
            }
            
            // Ensure all_gateways is an associative array with gateway_id as key
            // Sometimes it might be numeric indexed, so we need to rebuild it
            if (!empty($all_gateways) && is_array($all_gateways)) {
                $first_key = array_key_first($all_gateways);
                // If first key is numeric, rebuild array with gateway_id as key
                if (is_numeric($first_key)) {
                    $rebuilt_gateways = array();
                    foreach ($all_gateways as $gateway) {
                        if (is_object($gateway)) {
                            // Try different methods to get gateway ID
                            $gateway_id = null;
                            if (method_exists($gateway, 'id')) {
                                $gateway_id = $gateway->id;
                            } elseif (isset($gateway->id)) {
                                $gateway_id = $gateway->id;
                            } elseif (method_exists($gateway, 'get_id')) {
                                $gateway_id = $gateway->get_id();
                            }
                            
                            if ($gateway_id) {
                                $rebuilt_gateways[$gateway_id] = $gateway;
                            }
                        }
                    }
                    $all_gateways = $rebuilt_gateways;
                }
            }
        }
        
        // Get enabled gateways from settings
        if (isset($settings['enabled_gateways_normal']) && is_array($settings['enabled_gateways_normal']) && !empty($settings['enabled_gateways_normal'])) {
            foreach ($settings['enabled_gateways_normal'] as $gateway_id => $enabled) {
                // Check if gateway exists in available gateways
                // $enabled can be true or the gateway_id itself (depending on how it's stored)
                $is_enabled = ($enabled === true || $enabled === '1' || $enabled === 1);
                
                if ($is_enabled && isset($all_gateways[$gateway_id])) {
                    // Avoid duplicates
                    if (!in_array($gateway_id, $allowed)) {
                        $allowed[] = $gateway_id;
                    }
                }
            }
        }
        
        return $allowed;
    }
    
    /**
     * Get sale type
     */
    private function get_sale_type() {
        if (WC()->session) {
            $sale_type = WC()->session->get('arta_budget_sale_type');
            return $sale_type ? $sale_type : 'normal';
        }
        return 'normal';
    }
    
    /**
     * Process split payment logic
     */
    public function process_split_payment() {
        $sale_type = $this->get_sale_type();
        
        if ($sale_type !== 'bajet') {
            return;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }
        
        $user_credit = Arta_Budget_Database::get_user_credit($user_id);
        $cart_total = WC()->cart->get_total('edit');
        
        // Store payment info in session for later use
        WC()->session->set('arta_budget_user_credit', $user_credit);
        WC()->session->set('arta_budget_cart_total', $cart_total);
        
        if ($user_credit >= $cart_total) {
            // Full payment via Bajet
            WC()->session->set('arta_budget_payment_type', 'full_bajet');
        } else {
            // Split payment
            WC()->session->set('arta_budget_payment_type', 'split');
            WC()->session->set('arta_budget_credit_used', $user_credit);
            WC()->session->set('arta_budget_remaining_amount', $cart_total - $user_credit);
        }
    }
    
    /**
     * Save payment information to order
     */
    public function save_payment_info($order_id) {
        $payment_type = WC()->session->get('arta_budget_payment_type');
        
        if ($payment_type) {
            update_post_meta($order_id, '_arta_budget_payment_type', $payment_type);
            
            if ($payment_type === 'split') {
                $credit_used = WC()->session->get('arta_budget_credit_used');
                $remaining = WC()->session->get('arta_budget_remaining_amount');
                
                update_post_meta($order_id, '_arta_budget_credit_used', $credit_used);
                update_post_meta($order_id, '_arta_budget_remaining_amount', $remaining);
            }
        }
    }
    
    /**
     * Get default gateway for second payment step
     */
    public static function get_default_second_gateway() {
        $settings = get_option('arta_budget_gateway_settings', array());
        return isset($settings['default_second_gateway']) ? $settings['default_second_gateway'] : 'mellat';
    }
}

