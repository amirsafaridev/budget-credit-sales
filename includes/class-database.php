<?php
/**
 * Database management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Arta_Budget_Database {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'maybe_create_tables'));
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'arta_budget_credit_history';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            previous_credit decimal(15,2) NOT NULL DEFAULT 0.00,
            new_credit decimal(15,2) NOT NULL DEFAULT 0.00,
            change_amount decimal(15,2) NOT NULL DEFAULT 0.00,
            change_type varchar(20) NOT NULL,
            reason text,
            admin_user_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Store user credits in user meta
        // This is more efficient than a separate table for current credits
    }
    
    /**
     * Check and create tables if needed
     */
    public function maybe_create_tables() {
        $version = get_option('arta_budget_db_version', '0');
        if (version_compare($version, ARTA_BUDGET_VERSION, '<')) {
            self::create_tables();
            update_option('arta_budget_db_version', ARTA_BUDGET_VERSION);
        }
    }
    
    /**
     * Get user credit
     */
    public static function get_user_credit($user_id) {
        $credit = get_user_meta($user_id, 'arta_budget_credit', true);
        return $credit ? floatval($credit) : 0.00;
    }
    
    /**
     * Update user credit
     */
    public static function update_user_credit($user_id, $new_credit, $reason = '', $admin_user_id = null) {
        global $wpdb;
        
        $previous_credit = self::get_user_credit($user_id);
        $change_amount = $new_credit - $previous_credit;
        
        // Update user meta
        update_user_meta($user_id, 'arta_budget_credit', $new_credit);
        
        // Log in history
        if ($change_amount != 0) {
            $table_name = $wpdb->prefix . 'arta_budget_credit_history';
            
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'previous_credit' => $previous_credit,
                    'new_credit' => $new_credit,
                    'change_amount' => $change_amount,
                    'change_type' => $change_amount > 0 ? 'increase' : 'decrease',
                    'reason' => $reason,
                    'admin_user_id' => $admin_user_id ? $admin_user_id : get_current_user_id(),
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%f', '%f', '%f', '%s', '%s', '%d', '%s')
            );
        }
        
        return true;
    }
    
    /**
     * Get credit history for a user
     */
    public static function get_credit_history($user_id, $limit = 50) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'arta_budget_credit_history';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id,
            $limit
        ));
    }
}

