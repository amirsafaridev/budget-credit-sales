<?php
/**
 * User credit management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Arta_Budget_User_Credit {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_arta_budget_update_credit', array($this, 'ajax_update_credit'));
        add_action('wp_ajax_arta_budget_get_credit_history', array($this, 'ajax_get_credit_history'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'arta-budget-credit-sales',
            __('مدیریت کاربران و اعتبار', 'arta-budget-credit-sales'),
            __('مدیریت کاربران و اعتبار', 'arta-budget-credit-sales'),
            'manage_options',
            'arta-budget-user-credit',
            array($this, 'render_user_credit_page')
        );
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts($hook) {
        // Check if we're on the user credit management page
        // Hook name format: {parent_slug}_page_{submenu_slug}
        if (strpos($hook, 'arta-budget-user-credit') === false) {
            return;
        }
        
        $js_version = ARTA_BUDGET_VERSION;
        $css_version = ARTA_BUDGET_VERSION;
        
        // Add file modification time to prevent caching issues
        $js_file = ARTA_BUDGET_PLUGIN_DIR . 'assets/js/admin.js';
        $css_file = ARTA_BUDGET_PLUGIN_DIR . 'assets/css/admin.css';
        
        if (file_exists($js_file)) {
            $js_version .= '.' . filemtime($js_file);
        }
        
        if (file_exists($css_file)) {
            $css_version .= '.' . filemtime($css_file);
        }
        
        wp_enqueue_script(
            'arta-budget-admin',
            ARTA_BUDGET_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            $js_version,
            true
        );
        
        wp_localize_script('arta-budget-admin', 'artaBudgetAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('arta_budget_admin_nonce')
        ));
        
        wp_enqueue_style(
            'arta-budget-admin',
            ARTA_BUDGET_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            $css_version
        );
    }
    
    /**
     * AJAX handler for updating credit
     */
    public function ajax_update_credit() {
        check_ajax_referer('arta_budget_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('شما مجاز به انجام این عملیات نیستید.', 'arta-budget-credit-sales')));
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $operation = isset($_POST['operation']) ? sanitize_text_field($_POST['operation']) : 'increase';
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';
        
        if ($user_id <= 0) {
            wp_send_json_error(array('message' => __('شناسه کاربر نامعتبر است.', 'arta-budget-credit-sales')));
        }
        
        if ($amount <= 0) {
            wp_send_json_error(array('message' => __('مقدار باید بیشتر از صفر باشد.', 'arta-budget-credit-sales')));
        }
        
        $current_credit = Arta_Budget_Database::get_user_credit($user_id);
        
        if ($operation === 'increase') {
            $new_credit = $current_credit + $amount;
        } else {
            $new_credit = $current_credit - $amount;
            if ($new_credit < 0) {
                wp_send_json_error(array('message' => __('اعتبار کاربر نمی‌تواند منفی شود.', 'arta-budget-credit-sales')));
            }
        }
        
        Arta_Budget_Database::update_user_credit($user_id, $new_credit, $reason);
        
        $updated_credit = Arta_Budget_Database::get_user_credit($user_id);
        
        wp_send_json_success(array(
            'message' => __('اعتبار کاربر با موفقیت به‌روزرسانی شد.', 'arta-budget-credit-sales'),
            'new_credit' => $updated_credit
        ));
    }
    
    /**
     * AJAX handler for getting credit history
     */
    public function ajax_get_credit_history() {
        check_ajax_referer('arta_budget_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('شما مجاز به انجام این عملیات نیستید.', 'arta-budget-credit-sales')));
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        if ($user_id <= 0) {
            wp_send_json_error(array('message' => __('شناسه کاربر نامعتبر است.', 'arta-budget-credit-sales')));
        }
        
        $credit_history = Arta_Budget_Database::get_credit_history($user_id);
        
        $formatted_history = array();
        foreach ($credit_history as $history) {
            $formatted_history[] = array(
                'date' => date_i18n('Y/m/d H:i', strtotime($history->created_at)),
                'previous_credit' => number_format($history->previous_credit, 2),
                'new_credit' => number_format($history->new_credit, 2),
                'change_amount' => number_format($history->change_amount, 2),
                'change_type' => $history->change_type === 'increase' ? __('افزایش', 'arta-budget-credit-sales') : __('کاهش', 'arta-budget-credit-sales'),
                'reason' => esc_html($history->reason),
                'is_positive' => $history->change_amount > 0
            );
        }
        
        wp_send_json_success(array('history' => $formatted_history));
    }
    
    /**
     * Render user credit management page
     */
    public function render_user_credit_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Ensure scripts and styles are enqueued (fallback)
        $js_version = ARTA_BUDGET_VERSION;
        $css_version = ARTA_BUDGET_VERSION;
        
        // Add file modification time to prevent caching issues
        $js_file = ARTA_BUDGET_PLUGIN_DIR . 'assets/js/admin.js';
        $css_file = ARTA_BUDGET_PLUGIN_DIR . 'assets/css/admin.css';
        
        if (file_exists($js_file)) {
            $js_version .= '.' . filemtime($js_file);
        }
        
        if (file_exists($css_file)) {
            $css_version .= '.' . filemtime($css_file);
        }
        
        wp_enqueue_script(
            'arta-budget-admin',
            ARTA_BUDGET_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            $js_version,
            true
        );
        
        wp_localize_script('arta-budget-admin', 'artaBudgetAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('arta_budget_admin_nonce')
        ));
        
        wp_enqueue_style(
            'arta-budget-admin',
            ARTA_BUDGET_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            $css_version
        );
        
        // Get all users
        $users = get_users(array('number' => 1000));
        
        ?>
        <div class="wrap arta-budget-user-credit-wrap">
            <h1 class="arta-budget-page-title">
                <span class="dashicons dashicons-admin-users"></span>
                <?php _e('مدیریت کاربران و اعتبار', 'arta-budget-credit-sales'); ?>
            </h1>
            
            <div class="arta-budget-user-credit-container">
                <div class="arta-budget-users-list">
                    <div class="arta-budget-table-header">
                        <h2><?php _e('لیست کاربران', 'arta-budget-credit-sales'); ?></h2>
                        <div class="arta-budget-search-box">
                            <input type="text" id="arta-budget-user-search" placeholder="<?php _e('جستجو در کاربران...', 'arta-budget-credit-sales'); ?>" class="arta-budget-search-input">
                            <span class="dashicons dashicons-search"></span>
                        </div>
                    </div>
                    
                    <div class="arta-budget-table-wrapper">
                        <table class="arta-budget-modern-table">
                            <thead>
                                <tr>
                                    <th><?php _e('شناسه', 'arta-budget-credit-sales'); ?></th>
                                    <th><?php _e('نام کاربری', 'arta-budget-credit-sales'); ?></th>
                                    <th><?php _e('نام', 'arta-budget-credit-sales'); ?></th>
                                    <th><?php _e('ایمیل', 'arta-budget-credit-sales'); ?></th>
                                    <th><?php _e('اعتبار فعلی', 'arta-budget-credit-sales'); ?></th>
                                    <th><?php _e('عملیات', 'arta-budget-credit-sales'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (!empty($users)) {
                                    foreach ($users as $user) {
                                        $credit = Arta_Budget_Database::get_user_credit($user->ID);
                                        ?>
                                        <tr data-user-id="<?php echo $user->ID; ?>" data-user-login="<?php echo esc_attr(strtolower($user->user_login)); ?>" data-user-name="<?php echo esc_attr(strtolower($user->display_name)); ?>" data-user-email="<?php echo esc_attr(strtolower($user->user_email)); ?>">
                                            <td><?php echo $user->ID; ?></td>
                                            <td><?php echo esc_html($user->user_login); ?></td>
                                            <td><?php echo esc_html($user->display_name); ?></td>
                                            <td><?php echo esc_html($user->user_email); ?></td>
                                            <td class="arta-budget-credit-cell">
                                                <span class="arta-budget-credit-amount"><?php echo number_format($credit, 2); ?></span>
                                                <span class="arta-budget-currency"><?php _e('تومان', 'arta-budget-credit-sales'); ?></span>
                                            </td>
                                            <td class="arta-budget-actions-cell">
                                                <button type="button" class="arta-budget-btn arta-budget-btn-increase" data-user-id="<?php echo $user->ID; ?>" data-user-name="<?php echo esc_attr($user->display_name); ?>" data-current-credit="<?php echo $credit; ?>">
                                                    <span class="dashicons dashicons-plus-alt"></span>
                                                    <?php _e('افزایش', 'arta-budget-credit-sales'); ?>
                                                </button>
                                                <button type="button" class="arta-budget-btn arta-budget-btn-decrease" data-user-id="<?php echo $user->ID; ?>" data-user-name="<?php echo esc_attr($user->display_name); ?>" data-current-credit="<?php echo $credit; ?>">
                                                    <span class="dashicons dashicons-minus"></span>
                                                    <?php _e('کاهش', 'arta-budget-credit-sales'); ?>
                                                </button>
                                                <button type="button" class="arta-budget-btn arta-budget-btn-history" data-user-id="<?php echo $user->ID; ?>" data-user-name="<?php echo esc_attr($user->display_name); ?>">
                                                    <span class="dashicons dashicons-clock"></span>
                                                    <?php _e('تاریخچه', 'arta-budget-credit-sales'); ?>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                } else {
                                    ?>
                                    <tr>
                                        <td colspan="6" class="arta-budget-empty-state">
                                            <?php _e('هیچ کاربری یافت نشد.', 'arta-budget-credit-sales'); ?>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Popup for Increase Credit -->
        <div id="arta-budget-popup-increase" class="arta-budget-popup">
            <div class="arta-budget-popup-overlay"></div>
            <div class="arta-budget-popup-content">
                <div class="arta-budget-popup-header">
                    <h3><?php _e('افزایش اعتبار', 'arta-budget-credit-sales'); ?></h3>
                    <button type="button" class="arta-budget-popup-close">&times;</button>
                </div>
                <div class="arta-budget-popup-body">
                    <div class="arta-budget-user-info-popup">
                        <p><strong><?php _e('کاربر:', 'arta-budget-credit-sales'); ?></strong> <span id="popup-increase-user-name"></span></p>
                        <p><strong><?php _e('اعتبار فعلی:', 'arta-budget-credit-sales'); ?></strong> <span id="popup-increase-current-credit"></span> <?php _e('تومان', 'arta-budget-credit-sales'); ?></p>
                    </div>
                    <form id="arta-budget-form-increase" class="arta-budget-popup-form">
                        <input type="hidden" name="user_id" id="popup-increase-user-id">
                        <div class="arta-budget-form-group">
                            <label for="popup-increase-amount"><?php _e('مقدار افزایش (تومان)', 'arta-budget-credit-sales'); ?></label>
                            <input type="number" step="0.01" min="0.01" name="amount" id="popup-increase-amount" class="arta-budget-form-input" required>
                        </div>
                        <div class="arta-budget-form-group">
                            <label for="popup-increase-reason"><?php _e('دلیل افزایش (اختیاری)', 'arta-budget-credit-sales'); ?></label>
                            <textarea name="reason" id="popup-increase-reason" rows="3" class="arta-budget-form-textarea" placeholder="<?php _e('دلیل افزایش اعتبار را وارد کنید...', 'arta-budget-credit-sales'); ?>"></textarea>
                        </div>
                        <div class="arta-budget-popup-actions">
                            <button type="submit" class="arta-budget-btn arta-budget-btn-primary">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php _e('افزایش اعتبار', 'arta-budget-credit-sales'); ?>
                            </button>
                            <button type="button" class="arta-budget-btn arta-budget-btn-cancel arta-budget-popup-close">
                                <?php _e('انصراف', 'arta-budget-credit-sales'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Popup for Decrease Credit -->
        <div id="arta-budget-popup-decrease" class="arta-budget-popup">
            <div class="arta-budget-popup-overlay"></div>
            <div class="arta-budget-popup-content">
                <div class="arta-budget-popup-header">
                    <h3><?php _e('کاهش اعتبار', 'arta-budget-credit-sales'); ?></h3>
                    <button type="button" class="arta-budget-popup-close">&times;</button>
                </div>
                <div class="arta-budget-popup-body">
                    <div class="arta-budget-user-info-popup">
                        <p><strong><?php _e('کاربر:', 'arta-budget-credit-sales'); ?></strong> <span id="popup-decrease-user-name"></span></p>
                        <p><strong><?php _e('اعتبار فعلی:', 'arta-budget-credit-sales'); ?></strong> <span id="popup-decrease-current-credit"></span> <?php _e('تومان', 'arta-budget-credit-sales'); ?></p>
                    </div>
                    <form id="arta-budget-form-decrease" class="arta-budget-popup-form">
                        <input type="hidden" name="user_id" id="popup-decrease-user-id">
                        <div class="arta-budget-form-group">
                            <label for="popup-decrease-amount"><?php _e('مقدار کاهش (تومان)', 'arta-budget-credit-sales'); ?></label>
                            <input type="number" step="0.01" min="0.01" name="amount" id="popup-decrease-amount" class="arta-budget-form-input" required>
                        </div>
                        <div class="arta-budget-form-group">
                            <label for="popup-decrease-reason"><?php _e('دلیل کاهش (اختیاری)', 'arta-budget-credit-sales'); ?></label>
                            <textarea name="reason" id="popup-decrease-reason" rows="3" class="arta-budget-form-textarea" placeholder="<?php _e('دلیل کاهش اعتبار را وارد کنید...', 'arta-budget-credit-sales'); ?>"></textarea>
                        </div>
                        <div class="arta-budget-popup-actions">
                            <button type="submit" class="arta-budget-btn arta-budget-btn-primary">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php _e('کاهش اعتبار', 'arta-budget-credit-sales'); ?>
                            </button>
                            <button type="button" class="arta-budget-btn arta-budget-btn-cancel arta-budget-popup-close">
                                <?php _e('انصراف', 'arta-budget-credit-sales'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Popup for Credit History -->
        <div id="arta-budget-popup-history" class="arta-budget-popup">
            <div class="arta-budget-popup-overlay"></div>
            <div class="arta-budget-popup-content arta-budget-popup-content-large">
                <div class="arta-budget-popup-header">
                    <h3><?php _e('تاریخچه تغییرات اعتبار', 'arta-budget-credit-sales'); ?></h3>
                    <button type="button" class="arta-budget-popup-close">&times;</button>
                </div>
                <div class="arta-budget-popup-body">
                    <div class="arta-budget-user-info-popup">
                        <p><strong><?php _e('کاربر:', 'arta-budget-credit-sales'); ?></strong> <span id="popup-history-user-name"></span></p>
                    </div>
                    <div id="arta-budget-history-content" class="arta-budget-history-content">
                        <div class="arta-budget-loading">
                            <span class="spinner is-active"></span>
                            <?php _e('در حال بارگذاری...', 'arta-budget-credit-sales'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <div id="arta-budget-message" class="arta-budget-message" style="display: none;"></div>
        <?php
    }
}

