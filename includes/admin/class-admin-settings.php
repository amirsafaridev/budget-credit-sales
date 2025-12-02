<?php
/**
 * Admin settings page for gateway configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Arta_Budget_Admin_Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('فروش اعتباری باجت', 'arta-budget-credit-sales'),
            __('فروش اعتباری باجت', 'arta-budget-credit-sales'),
            'manage_options',
            'arta-budget-credit-sales',
            array($this, 'render_gateway_settings_page'),
            'dashicons-money-alt',
            56
        );
        
        add_submenu_page(
            'arta-budget-credit-sales',
            __('تنظیمات درگاه‌ها', 'arta-budget-credit-sales'),
            __('تنظیمات درگاه‌ها', 'arta-budget-credit-sales'),
            'manage_options',
            'arta-budget-credit-sales',
            array($this, 'render_gateway_settings_page')
        );
    }
    
    /**
     * Render gateway settings page
     */
    public function render_gateway_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Save settings
        if (isset($_POST['arta_budget_save_gateways']) && check_admin_referer('arta_budget_gateway_settings')) {
            $settings = array();
            
            // Get enabled gateways for Bajet mode
            if (isset($_POST['enabled_gateways']) && is_array($_POST['enabled_gateways'])) {
                $settings['enabled_gateways'] = array();
                foreach ($_POST['enabled_gateways'] as $gateway_id) {
                    $settings['enabled_gateways'][sanitize_text_field($gateway_id)] = true;
                }
            }
            
            // Get enabled gateways for Normal mode
            if (isset($_POST['enabled_gateways_normal']) && is_array($_POST['enabled_gateways_normal'])) {
                $settings['enabled_gateways_normal'] = array();
                foreach ($_POST['enabled_gateways_normal'] as $gateway_id) {
                    $settings['enabled_gateways_normal'][sanitize_text_field($gateway_id)] = true;
                }
            }
            
            // Get default second gateway
            if (isset($_POST['default_second_gateway'])) {
                $settings['default_second_gateway'] = sanitize_text_field($_POST['default_second_gateway']);
            }
            
            // Get Bajet price increase percentage
            if (isset($_POST['bajet_price_increase_percent'])) {
                $percent = floatval($_POST['bajet_price_increase_percent']);
                // Validate: must be between 0 and 100
                if ($percent >= 0 && $percent <= 100) {
                    $settings['bajet_price_increase_percent'] = strval($percent);
                }
            }
            // Save settings - always delete and add to ensure all values are saved correctly
            delete_option('arta_budget_gateway_settings');
            add_option('arta_budget_gateway_settings', $settings, '', 'no');
            
            echo '<div class="notice notice-success"><p>' . __('تنظیمات با موفقیت ذخیره شد.', 'arta-budget-credit-sales') . '</p></div>';
        }
        
        $settings = get_option('arta_budget_gateway_settings', array());
        $enabled_gateways = isset($settings['enabled_gateways']) ? $settings['enabled_gateways'] : array();
        $enabled_gateways_normal = isset($settings['enabled_gateways_normal']) ? $settings['enabled_gateways_normal'] : array();
        $default_second_gateway = isset($settings['default_second_gateway']) ? $settings['default_second_gateway'] : '';
        $bajet_price_percent = isset($settings['bajet_price_increase_percent']) ? floatval($settings['bajet_price_increase_percent']) : 12; // Default 12%
        
        // Get all WooCommerce payment gateways (both enabled and disabled)
        $payment_gateways_obj = WC()->payment_gateways();
        
        // Try to get all gateways from property
        $all_gateways = array();
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
                // If reflection fails, use available gateways only
                $all_gateways = WC()->payment_gateways->get_available_payment_gateways();
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
        
        ?>
        <div class="wrap">
            <h1><?php _e('تنظیمات درگاه‌های پرداخت', 'arta-budget-credit-sales'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('arta_budget_gateway_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('درگاه‌های مجاز در حالت فروش باجت', 'arta-budget-credit-sales'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <span><?php _e('درگاه‌های مجاز', 'arta-budget-credit-sales'); ?></span>
                                </legend>
                                <?php
                                if (!empty($all_gateways)) {
                                    foreach ($all_gateways as $key => $gateway) {
                                        // Get gateway ID - handle both associative and numeric arrays
                                        $gateway_id = $key;
                                        if (is_numeric($key) && is_object($gateway)) {
                                            // If key is numeric, get ID from gateway object
                                            if (method_exists($gateway, 'id')) {
                                                $gateway_id = $gateway->id;
                                            } elseif (isset($gateway->id)) {
                                                $gateway_id = $gateway->id;
                                            } elseif (method_exists($gateway, 'get_id')) {
                                                $gateway_id = $gateway->get_id();
                                            }
                                        }
                                        
                                        $checked = isset($enabled_gateways[$gateway_id]) ? checked($enabled_gateways[$gateway_id], true, false) : '';
                                        
                                        // Initialize gateway settings if needed
                                        if (method_exists($gateway, 'init_settings')) {
                                            $gateway->init_settings();
                                        }
                                        
                                        // Check if gateway is enabled
                                        $is_enabled = false;
                                        // Try gateway's enabled property first
                                        if (isset($gateway->enabled)) {
                                            $is_enabled = $gateway->enabled === 'yes';
                                        } else {
                                            // Fallback: check WooCommerce settings directly
                                            $gateway_settings = get_option('woocommerce_' . $gateway_id . '_settings', array());
                                            if (!empty($gateway_settings) && isset($gateway_settings['enabled'])) {
                                                $is_enabled = $gateway_settings['enabled'] === 'yes';
                                            }
                                        }
                                        
                                        $status_text = $is_enabled ? '<span style="color: #00a32a; font-weight: 600;">✓ فعال</span>' : '<span style="color: #d63638; font-weight: 600;">✗ غیرفعال</span>';
                                        ?>
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input type="checkbox" name="enabled_gateways[]" value="<?php echo esc_attr($gateway_id); ?>" <?php echo $checked; ?>>
                                            <strong><?php echo esc_html($gateway->get_title()); ?></strong> 
                                            (<code><?php echo esc_html($gateway_id); ?></code>)
                                            - <?php echo $status_text; ?>
                                        </label>
                                        <?php
                                    }
                                } else {
                                    echo '<p>' . __('هیچ درگاه پرداختی یافت نشد.', 'arta-budget-credit-sales') . '</p>';
                                }
                                ?>
                            </fieldset>
                            <p class="description">
                                <?php _e('درگاه‌هایی که در حالت فروش باجت فعال باشند را انتخاب کنید. درگاه باجت (کالانو) به صورت خودکار فعال است.', 'arta-budget-credit-sales'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('درگاه‌های مجاز در حالت فروش عادی', 'arta-budget-credit-sales'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <span><?php _e('درگاه‌های مجاز در حالت عادی', 'arta-budget-credit-sales'); ?></span>
                                </legend>
                                <?php
                                if (!empty($all_gateways)) {
                                    foreach ($all_gateways as $key => $gateway) {
                                        // Get gateway ID - handle both associative and numeric arrays
                                        $gateway_id = $key;
                                        if (is_numeric($key) && is_object($gateway)) {
                                            // If key is numeric, get ID from gateway object
                                            if (method_exists($gateway, 'id')) {
                                                $gateway_id = $gateway->id;
                                            } elseif (isset($gateway->id)) {
                                                $gateway_id = $gateway->id;
                                            } elseif (method_exists($gateway, 'get_id')) {
                                                $gateway_id = $gateway->get_id();
                                            }
                                        }
                                        
                                        $checked = isset($enabled_gateways_normal[$gateway_id]) ? checked($enabled_gateways_normal[$gateway_id], true, false) : '';
                                        
                                        // Initialize gateway settings if needed
                                        if (method_exists($gateway, 'init_settings')) {
                                            $gateway->init_settings();
                                        }
                                        
                                        // Check if gateway is enabled
                                        $is_enabled = false;
                                        // Try gateway's enabled property first
                                        if (isset($gateway->enabled)) {
                                            $is_enabled = $gateway->enabled === 'yes';
                                        } else {
                                            // Fallback: check WooCommerce settings directly
                                            $gateway_settings = get_option('woocommerce_' . $gateway_id . '_settings', array());
                                            if (!empty($gateway_settings) && isset($gateway_settings['enabled'])) {
                                                $is_enabled = $gateway_settings['enabled'] === 'yes';
                                            }
                                        }
                                        
                                        $status_text = $is_enabled ? '<span style="color: #00a32a; font-weight: 600;">✓ فعال</span>' : '<span style="color: #d63638; font-weight: 600;">✗ غیرفعال</span>';
                                        ?>
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input type="checkbox" name="enabled_gateways_normal[]" value="<?php echo esc_attr($gateway_id); ?>" <?php echo $checked; ?>>
                                            <strong><?php echo esc_html($gateway->get_title()); ?></strong> 
                                            (<code><?php echo esc_html($gateway_id); ?></code>)
                                            - <?php echo $status_text; ?>
                                        </label>
                                        <?php
                                    }
                                } else {
                                    echo '<p>' . __('هیچ درگاه پرداختی یافت نشد.', 'arta-budget-credit-sales') . '</p>';
                                }
                                ?>
                            </fieldset>
                            <p class="description">
                                <?php _e('درگاه‌هایی که در حالت فروش عادی فعال باشند را انتخاب کنید. اگر هیچ درگاهی انتخاب نشود، تمام درگاه‌های فعال ووکامرس نمایش داده می‌شوند.', 'arta-budget-credit-sales'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="default_second_gateway"><?php _e('درگاه پیش‌فرض برای پرداخت مرحله دوم', 'arta-budget-credit-sales'); ?></label>
                        </th>
                        <td>
                            <select name="default_second_gateway" id="default_second_gateway">
                                <option value=""><?php _e('-- انتخاب کنید --', 'arta-budget-credit-sales'); ?></option>
                                <?php
                                if (!empty($all_gateways)) {
                                    foreach ($all_gateways as $key => $gateway) {
                                        // Get gateway ID - handle both associative and numeric arrays
                                        $gateway_id = $key;
                                        if (is_numeric($key) && is_object($gateway)) {
                                            // If key is numeric, get ID from gateway object
                                            if (method_exists($gateway, 'id')) {
                                                $gateway_id = $gateway->id;
                                            } elseif (isset($gateway->id)) {
                                                $gateway_id = $gateway->id;
                                            } elseif (method_exists($gateway, 'get_id')) {
                                                $gateway_id = $gateway->get_id();
                                            }
                                        }
                                        
                                        $selected = selected($default_second_gateway, $gateway_id, false);
                                        
                                        // Initialize gateway settings if needed
                                        if (method_exists($gateway, 'init_settings')) {
                                            $gateway->init_settings();
                                        }
                                        
                                        // Check if gateway is enabled
                                        $is_enabled = false;
                                        // Try gateway's enabled property first
                                        if (isset($gateway->enabled)) {
                                            $is_enabled = $gateway->enabled === 'yes';
                                        } else {
                                            // Fallback: check WooCommerce settings directly
                                            $gateway_settings = get_option('woocommerce_' . $gateway_id . '_settings', array());
                                            if (!empty($gateway_settings) && isset($gateway_settings['enabled'])) {
                                                $is_enabled = $gateway_settings['enabled'] === 'yes';
                                            }
                                        }
                                        
                                        $status_label = $is_enabled ? ' (فعال)' : ' (غیرفعال)';
                                        ?>
                                        <option value="<?php echo esc_attr($gateway_id); ?>" <?php echo $selected; ?>>
                                            <?php echo esc_html($gateway->get_title() . $status_label); ?>
                                        </option>
                                        <?php
                                    }
                                }
                                ?>
                            </select>
                            <p class="description">
                                <?php _e('در صورتی که اعتبار کاربر کمتر از مبلغ خرید باشد، این درگاه برای پرداخت باقی مبلغ استفاده می‌شود.', 'arta-budget-credit-sales'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="bajet_price_increase_percent"><?php _e('درصد افزایش قیمت در حالت باجت', 'arta-budget-credit-sales'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   name="bajet_price_increase_percent" 
                                   id="bajet_price_increase_percent" 
                                   value="<?php echo esc_attr($bajet_price_percent); ?>" 
                                   min="0" 
                                   max="100" 
                                   step="0.01" 
                                   class="small-text" 
                                   required>
                            <span>%</span>
                            <p class="description">
                                <?php _e('درصدی که در حالت فروش باجت به مبلغ سبد خرید افزوده می‌شود. مقدار پیش‌فرض: 12%', 'arta-budget-credit-sales'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('ذخیره تنظیمات', 'arta-budget-credit-sales'), 'primary', 'arta_budget_save_gateways'); ?>
            </form>
            
            <!-- Help Section -->
            <div class="arta-budget-help-section" style="margin-top: 40px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h2 style="margin-top: 0;"><?php _e('راهنمای استفاده از افزونه', 'arta-budget-credit-sales'); ?></h2>
                
                <div style="margin-bottom: 30px;">
                    <h3><?php _e('نحوه عملکرد', 'arta-budget-credit-sales'); ?></h3>
                    <ol style="line-height: 2;">
                        <li><?php _e('در صفحه تسویه حساب (Checkout)، مشتری می‌تواند نوع فروش را انتخاب کند: «عادی» یا «باجت»', 'arta-budget-credit-sales'); ?></li>
                        <li><?php _e('در حالت «باجت»، درصد تعیین شده به مبلغ سبد خرید افزوده می‌شود', 'arta-budget-credit-sales'); ?></li>
                        <li><?php _e('در حالت «باجت»، فقط درگاه‌های مجاز (که در بالا انتخاب کرده‌اید) نمایش داده می‌شوند', 'arta-budget-credit-sales'); ?></li>
                        <li><?php _e('اگر اعتبار کاربر بیشتر یا مساوی مبلغ خرید باشد، پرداخت کامل از طریق درگاه باجت انجام می‌شود', 'arta-budget-credit-sales'); ?></li>
                        <li><?php _e('اگر اعتبار کاربر کمتر از مبلغ خرید باشد، ابتدا از اعتبار کاربر کسر می‌شود و سپس کاربر به درگاه پیش‌فرض (که در بالا انتخاب کرده‌اید) هدایت می‌شود', 'arta-budget-credit-sales'); ?></li>
                    </ol>
                </div>
                
                <div style="margin-bottom: 30px;">
                    <h3><?php _e('شورتکد انتخاب نوع فروش', 'arta-budget-credit-sales'); ?></h3>
                    <p><?php _e('برای نمایش انتخاب‌گر نوع فروش در هدر سایت یا هر جای دیگری، از شورتکد زیر استفاده کنید:', 'arta-budget-credit-sales'); ?></p>
                    <div style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin: 15px 0;">
                        <code style="font-size: 16px; color: #2271b1;">[arta_budget_sale_type_selector]</code>
                        <button type="button" 
                                class="button button-small" 
                                onclick="copyToClipboard('[arta_budget_sale_type_selector]')" 
                                style="margin-right: 10px;">
                            <?php _e('کپی', 'arta-budget-credit-sales'); ?>
                        </button>
                    </div>
                    <p class="description">
                        <?php _e('این شورتکد یک ComboBox با گزینه‌های «عادی» و «باجت» ایجاد می‌کند. در حالت باجت، قیمت محصولات با درصد تعیین شده افزایش می‌یابد.', 'arta-budget-credit-sales'); ?>
                    </p>
                    <p><strong><?php _e('نکته مهم:', 'arta-budget-credit-sales'); ?></strong> <?php _e('اگر سبد خرید حاوی کالا باشد، کاربر نمی‌تواند نوع فروش را تغییر دهد و باید ابتدا سبد خرید را خالی کند.', 'arta-budget-credit-sales'); ?></p>
                </div>
                
                <div style="margin-bottom: 30px;">
                    <h3><?php _e('مدیریت اعتبار کاربران', 'arta-budget-credit-sales'); ?></h3>
                    <p><?php _e('برای مدیریت اعتبار کاربران، به منوی', 'arta-budget-credit-sales'); ?> <strong><?php _e('فروش اعتباری باجت > مدیریت کاربران و اعتبار', 'arta-budget-credit-sales'); ?></strong> <?php _e('بروید.', 'arta-budget-credit-sales'); ?></p>
                    <p><?php _e('در آنجا می‌توانید:', 'arta-budget-credit-sales'); ?></p>
                    <ul style="line-height: 2; margin-right: 20px;">
                        <li><?php _e('لیست تمام کاربران و اعتبار فعلی آن‌ها را مشاهده کنید', 'arta-budget-credit-sales'); ?></li>
                        <li><?php _e('اعتبار هر کاربر را افزایش یا کاهش دهید', 'arta-budget-credit-sales'); ?></li>
                        <li><?php _e('تاریخچه تغییرات اعتبار هر کاربر را مشاهده کنید', 'arta-budget-credit-sales'); ?></li>
                    </ul>
                </div>
                
                <div>
                    <h3><?php _e('تنظیمات', 'arta-budget-credit-sales'); ?></h3>
                    <ul style="line-height: 2; margin-right: 20px;">
                        <li><strong><?php _e('درگاه‌های مجاز در حالت باجت:', 'arta-budget-credit-sales'); ?></strong> <?php _e('درگاه‌هایی که در حالت فروش باجت فعال باشند را انتخاب کنید. درگاه باجت (کالانو) به صورت خودکار فعال است.', 'arta-budget-credit-sales'); ?></li>
                        <li><strong><?php _e('درگاه‌های مجاز در حالت عادی:', 'arta-budget-credit-sales'); ?></strong> <?php _e('درگاه‌هایی که در حالت فروش عادی فعال باشند را انتخاب کنید. اگر هیچ درگاهی انتخاب نشود، تمام درگاه‌های فعال ووکامرس نمایش داده می‌شوند.', 'arta-budget-credit-sales'); ?></li>
                        <li><strong><?php _e('درگاه پیش‌فرض:', 'arta-budget-credit-sales'); ?></strong> <?php _e('در صورتی که اعتبار کاربر کمتر از مبلغ خرید باشد، این درگاه برای پرداخت باقی مبلغ استفاده می‌شود.', 'arta-budget-credit-sales'); ?></li>
                        <li><strong><?php _e('درصد افزایش قیمت:', 'arta-budget-credit-sales'); ?></strong> <?php _e('درصدی که در حالت فروش باجت به مبلغ سبد خرید افزوده می‌شود. مقدار پیش‌فرض: 12%', 'arta-budget-credit-sales'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <script>
        function copyToClipboard(text) {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                alert('<?php _e('شورتکد کپی شد!', 'arta-budget-credit-sales'); ?>');
            } catch (err) {
                alert('<?php _e('خطا در کپی کردن. لطفاً به صورت دستی کپی کنید.', 'arta-budget-credit-sales'); ?>');
            }
            document.body.removeChild(textarea);
        }
        </script>
        <?php
    }
}

