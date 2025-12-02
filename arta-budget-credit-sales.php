<?php
/**
 * Plugin Name: Arta Budget Credit Sales
 * Plugin URI: https://example.com
 * Description: افزونه فروش اعتباری باجت برای ووکامرس - امکان فروش اعتباری و مدیریت درگاه‌های پرداخت
 * Version: 1.0.0
 * Author: Arta
 * Author URI: https://example.com
 * Text Domain: arta-budget-credit-sales
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('ARTA_BUDGET_VERSION', '1.0.0');
define('ARTA_BUDGET_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ARTA_BUDGET_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ARTA_BUDGET_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class Arta_Budget_Credit_Sales {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Register activation hook early
        register_activation_hook(ARTA_BUDGET_PLUGIN_FILE, array($this, 'activate_plugin'));
        
        // Check if WooCommerce is active
        add_action('plugins_loaded', array($this, 'check_woocommerce'));
        
        // Load plugin files
        $this->load_dependencies();
        
        // Initialize components
        add_action('init', array($this, 'init_components'));
    }
    
    /**
     * Plugin activation
     */
    public function activate_plugin() {
        // Load database class to create tables
        require_once ARTA_BUDGET_PLUGIN_DIR . 'includes/class-database.php';
        Arta_Budget_Database::create_tables();
    }
    
    /**
     * Check if WooCommerce is active
     */
    public function check_woocommerce() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('افزونه فروش اعتباری باجت نیاز به ووکامرس دارد. لطفاً ابتدا ووکامرس را نصب و فعال کنید.', 'arta-budget-credit-sales'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once ARTA_BUDGET_PLUGIN_DIR . 'includes/class-database.php';
        require_once ARTA_BUDGET_PLUGIN_DIR . 'includes/class-checkout.php';
        require_once ARTA_BUDGET_PLUGIN_DIR . 'includes/class-payment-gateways.php';
        require_once ARTA_BUDGET_PLUGIN_DIR . 'includes/class-payment-handler.php';
        require_once ARTA_BUDGET_PLUGIN_DIR . 'includes/class-shortcode.php';
        require_once ARTA_BUDGET_PLUGIN_DIR . 'includes/admin/class-admin-settings.php';
        require_once ARTA_BUDGET_PLUGIN_DIR . 'includes/admin/class-user-credit.php';
    }
    
    /**
     * Initialize components
     */
    public function init_components() {
        // Initialize database
        Arta_Budget_Database::get_instance();
        
        // Initialize checkout modifications
        Arta_Budget_Checkout::get_instance();
        
        // Initialize payment gateway control
        Arta_Budget_Payment_Gateways::get_instance();
        
        // Initialize payment handler
        Arta_Budget_Payment_Handler::get_instance();
        
        // Initialize shortcode
        Arta_Budget_Shortcode::get_instance();
        
        // Initialize admin
        if (is_admin()) {
            Arta_Budget_Admin_Settings::get_instance();
            Arta_Budget_User_Credit::get_instance();
        }
    }
}

/**
 * Initialize the plugin
 */
function arta_budget_credit_sales_init() {
    return Arta_Budget_Credit_Sales::get_instance();
}

// Start the plugin
arta_budget_credit_sales_init();

