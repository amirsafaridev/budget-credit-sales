<?php
/**
 * Shortcode class for sale type selector
 */

if (!defined('ABSPATH')) {
    exit;
}

class Arta_Budget_Shortcode {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register shortcode
        add_shortcode('arta_budget_sale_type_selector', array($this, 'render_selector'));
        
        // Enqueue scripts for shortcode
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Sync cookie to WooCommerce session
        add_action('init', array($this, 'sync_cookie_to_session'), 20);
        
        // Modify product prices for Bajet sales (shop and product pages)
        add_filter('woocommerce_product_get_price', array($this, 'modify_product_price_display'), 10, 2);
        add_filter('woocommerce_product_variation_get_price', array($this, 'modify_product_price_display'), 10, 2);
        add_filter('woocommerce_product_get_regular_price', array($this, 'modify_product_price_display'), 10, 2);
        add_filter('woocommerce_product_get_sale_price', array($this, 'modify_product_price_display'), 10, 2);
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'arta-budget-shortcode',
            ARTA_BUDGET_PLUGIN_URL . 'assets/js/shortcode.js',
            array('jquery'),
            ARTA_BUDGET_VERSION,
            true
        );
        
        // Get Bajet price increase percentage from settings
        $settings = get_option('arta_budget_gateway_settings', array());
        $bajet_percent = isset($settings['bajet_price_increase_percent']) ? floatval($settings['bajet_price_increase_percent']) : 12;
        
        wp_localize_script('arta-budget-shortcode', 'artaBudgetShortcode', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('arta_budget_nonce'),
            'cartNotEmptyMessage' => __('برای تغییر نوع فروش، ابتدا باید سبد کالای خود را خالی کنید.', 'arta-budget-credit-sales'),
            'bajetPercent' => $bajet_percent
        ));
        
        wp_enqueue_style(
            'arta-budget-shortcode',
            ARTA_BUDGET_PLUGIN_URL . 'assets/css/shortcode.css',
            array(),
            ARTA_BUDGET_VERSION
        );
    }
    
    /**
     * Render sale type selector shortcode
     */
    public function render_selector($atts) {
        $atts = shortcode_atts(array(
            'class' => 'arta-budget-sale-type-selector'
        ), $atts);
        
        $current_type = $this->get_current_sale_type();
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>">
            <label for="arta-budget-sale-type-select"><?php _e('نوع فروش:', 'arta-budget-credit-sales'); ?></label>
            <div class="arta-budget-select-wrapper" style="display: inline-block; position: relative;">
                <select id="arta-budget-sale-type-select" name="arta_budget_sale_type_select">
                    <option value="normal" <?php selected($current_type, 'normal'); ?>><?php _e('عادی', 'arta-budget-credit-sales'); ?></option>
                    <option value="bajet" <?php selected($current_type, 'bajet'); ?>><?php _e('باجت', 'arta-budget-credit-sales'); ?></option>
                </select>
                <span class="arta-budget-loading-spinner" style="display: none;"></span>
            </div>
            <div class="arta-budget-message" style="display: none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Sync cookie to WooCommerce session
     */
    public function sync_cookie_to_session() {
        if (!WC() || !WC()->session) {
            return;
        }
        
        // Get sale type from cookie
        $cookie_value = isset($_COOKIE['arta_budget_sale_type']) ? sanitize_text_field($_COOKIE['arta_budget_sale_type']) : '';
        
        // Validate cookie value
        if (in_array($cookie_value, array('normal', 'bajet'))) {
            // Set in WooCommerce session
            WC()->session->set('arta_budget_sale_type', $cookie_value);
        } else {
            // Default to 'normal' if cookie is invalid or doesn't exist
            $current_session = WC()->session->get('arta_budget_sale_type');
            if (!$current_session) {
                WC()->session->set('arta_budget_sale_type', 'normal');
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
     * Modify product price for display (shop and product pages)
     * Note: This does NOT affect cart prices - cart prices are handled in class-checkout.php
     */
    public function modify_product_price_display($price, $product) {
        // Skip in admin
        if (is_admin()) {
            return $price;
        }
        
        // Skip if price is empty or zero
        if (!$price || $price <= 0) {
            return $price;
        }
        
        // Skip on cart and checkout pages - prices are handled by class-checkout.php
        if (is_cart() || is_checkout()) {
            return $price;
        }
        
        // Get sale type
        $sale_type = $this->get_current_sale_type();
        
        // Only modify if sale type is bajet
        if ($sale_type === 'bajet') {
            $percent = $this->get_bajet_percent();
            $multiplier = 1 + ($percent / 100);
            $price = $price * $multiplier;
        }
        
        return $price;
    }
    
    /**
     * Get current sale type
     */
    private function get_current_sale_type() {
        // First check cookie
        $cookie_value = isset($_COOKIE['arta_budget_sale_type']) ? sanitize_text_field($_COOKIE['arta_budget_sale_type']) : '';
        if (in_array($cookie_value, array('normal', 'bajet'))) {
            return $cookie_value;
        }
        
        // Fallback to session
        if (WC() && WC()->session) {
            $sale_type = WC()->session->get('arta_budget_sale_type');
            return $sale_type ? $sale_type : 'normal';
        }
        
        return 'normal';
    }
}

