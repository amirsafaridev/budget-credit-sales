# Arta Budget Credit Sales for WooCommerce

A comprehensive WordPress plugin that adds credit sales functionality to WooCommerce checkout process, enabling customers to purchase using their available budget credit with automatic price adjustments and flexible payment gateway management.

## 📋 Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Usage](#usage)
- [Configuration](#configuration)
- [Shortcodes](#shortcodes)
- [File Structure](#file-structure)
- [Requirements](#requirements)
- [Technical Details](#technical-details)
- [Screenshots](#screenshots)
- [Support](#support)
- [License](#license)

## ✨ Features

### Front-End Features

#### 1. Sale Type Selection in Checkout
- **Normal Sale** option (default): Standard checkout process
- **Budget Sale** option (credit sale): Credit-based purchase with automatic 12% price markup
- Real-time price calculation and display
- Seamless integration with WooCommerce checkout flow

#### 2. Payment Gateway Control
- **Normal Mode**: All active payment gateways are available
- **Budget Mode**: Only authorized payment gateways are enabled
- Dynamic gateway filtering based on selected sale type
- Configurable gateway permissions from admin panel

#### 3. Hybrid Payment System
- **Full Credit Payment**: If user credit ≥ purchase amount, complete payment via budget credit
- **Partial Credit Payment**: If user credit < purchase amount, partial payment from credit + redirect to alternative payment gateway
- Automatic credit deduction and balance calculation
- Seamless transition between payment methods

#### 4. Sale Type Selector Shortcode
- Shortcode: `[arta_budget_sale_type_selector]`
- Display in site header or any widget area
- Real-time price display with 12% markup in budget mode
- Cart protection: Prevents sale type change when items exist in cart
- Responsive design for all devices

### Back-End Features

#### 1. Payment Gateway Settings
- Complete list of all active WooCommerce payment gateways
- Enable/disable gateways for budget sale mode
- Set default gateway for second-stage payments (hybrid payments)
- Real-time gateway status management
- Intuitive admin interface

#### 2. User Credit Management
- Comprehensive user list with current credit balance
- Manual credit editing and adjustment
- Credit history tracking and audit trail
- Transaction log with timestamps
- User search and filtering capabilities

## 🚀 Installation

### Prerequisites

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.2 or higher

### Installation Steps

1. **Download the Plugin**
   - Clone or download this repository
   - Extract the files if compressed

2. **Upload to WordPress**
   - Navigate to your WordPress installation
   - Upload the plugin folder to `wp-content/plugins/`
   - Ensure the folder is named `arta-budget-credit-sales`

3. **Activate the Plugin**
   - Go to **Plugins** in WordPress admin panel
   - Find "Arta Budget Credit Sales"
   - Click **Activate**

4. **Verify WooCommerce**
   - Ensure WooCommerce is installed and activated
   - The plugin will display a notice if WooCommerce is missing

## 📖 Usage

### Initial Configuration

1. **Configure Payment Gateways**
   - Navigate to **Budget Credit Sales > Gateway Settings** in WordPress admin
   - Review the list of all active payment gateways
   - Enable/disable gateways for budget sale mode
   - Set the default gateway for second-stage payments
   - Save your settings

2. **Verify Budget Gateway ID**
   - Check the budget gateway (Kalano) ID in gateway settings
   - Update if necessary to match your payment gateway configuration

### Managing User Credits

1. **Access Credit Management**
   - Go to **Budget Credit Sales > User Credit Management**
   - Browse the list of all users

2. **Edit User Credit**
   - Select the desired user
   - Enter the new credit amount
   - Save changes
   - View credit history in the transaction log

3. **View Credit History**
   - All credit changes are logged in `wp_arta_budget_credit_history` table
   - Includes timestamps, previous balance, new balance, and change reason

### Using the Shortcode

Place the shortcode `[arta_budget_sale_type_selector]` anywhere in your theme:

**Example: In Header**
```php
<?php echo do_shortcode('[arta_budget_sale_type_selector]'); ?>
```

**Example: In Widget**
- Add a Text widget to your sidebar
- Insert: `[arta_budget_sale_type_selector]`

**Example: In Page/Post**
- Simply add: `[arta_budget_sale_type_selector]` in the content editor

## ⚙️ Configuration

### Gateway Settings

The plugin automatically detects all active WooCommerce payment gateways. Configure them as follows:

- **Enable for Budget Sales**: Check this option to allow the gateway in budget mode
- **Default for Second Payment**: Select the gateway used for remaining balance payments
- **Gateway ID**: Verify the budget gateway ID matches your payment provider

### Credit System

- Credit is stored per user in the WordPress user meta
- All credit transactions are logged for audit purposes
- Credit cannot go negative (validated during checkout)
- Credit is automatically deducted upon successful budget purchase

## 📁 File Structure

```
arta-budget-credit-sales/
├── arta-budget-credit-sales.php    # Main plugin file
├── README.md                        # Documentation
├── includes/
│   ├── class-database.php          # Database management & table creation
│   ├── class-checkout.php          # Checkout page modifications
│   ├── class-payment-gateways.php  # Payment gateway control logic
│   ├── class-payment-handler.php   # Hybrid payment processing
│   ├── class-shortcode.php         # Shortcode implementation
│   └── admin/
│       ├── class-admin-settings.php # Admin settings page
│       └── class-user-credit.php   # User credit management interface
└── assets/
    ├── js/
    │   ├── checkout.js             # Checkout page JavaScript
    │   ├── shortcode.js            # Shortcode JavaScript functionality
    │   └── admin.js                # Admin panel JavaScript
    └── css/
        ├── checkout.css            # Checkout page styles
        ├── shortcode.css           # Shortcode styles
        └── admin.css               # Admin panel styles
```

## 🔧 Requirements

| Requirement | Minimum Version |
|------------|----------------|
| WordPress  | 5.0+           |
| WooCommerce| 3.0+           |
| PHP        | 7.2+           |
| MySQL      | 5.6+           |

## 🔍 Technical Details

### Database Tables

The plugin creates the following database table upon activation:

**`wp_arta_budget_credit_history`**
- Stores complete credit transaction history
- Fields: `id`, `user_id`, `previous_balance`, `new_balance`, `change_amount`, `reason`, `created_at`

### Hooks and Filters

The plugin uses standard WordPress and WooCommerce hooks:

- `woocommerce_checkout_fields` - Adds sale type field
- `woocommerce_cart_calculate_fees` - Applies 12% markup
- `woocommerce_available_payment_gateways` - Filters payment gateways
- `woocommerce_checkout_process` - Validates credit availability
- `woocommerce_payment_complete` - Processes credit deduction

### Price Calculation

- **Normal Mode**: Standard WooCommerce pricing
- **Budget Mode**: Base price + 12% markup
- Calculation formula: `Budget Price = Original Price × 1.12`
- Applied to cart total, not individual items

### Payment Flow

1. Customer selects sale type (Normal/Budget)
2. If Budget: System checks user credit balance
3. If credit sufficient: Full payment via credit
4. If credit insufficient: Partial credit payment + redirect to gateway
5. Order completion and credit deduction
6. Transaction logged in history

## 📸 Screenshots

### Checkout Page
The checkout page displays a sale type selector with real-time price updates.

### Admin Settings
Comprehensive gateway management interface with enable/disable options.

### Credit Management
User-friendly interface for managing user credits with transaction history.

## ⚠️ Important Notes

1. **Payment Gateway Setup**: All payment gateways must be installed and activated in WooCommerce before configuring them in this plugin.

2. **Budget Gateway ID**: The budget gateway (Kalano) ID should be verified in gateway settings and updated if it doesn't match your payment provider's gateway ID.

3. **Credit History**: All credit changes are permanently logged in the `wp_arta_budget_credit_history` database table for audit and tracking purposes.

4. **Cart Protection**: Once items are added to the cart, the sale type cannot be changed to prevent pricing inconsistencies.

5. **Price Markup**: The 12% markup is automatically applied to the total cart amount when budget mode is selected.

## 🐛 Troubleshooting

### Plugin Not Activating
- Ensure WooCommerce is installed and activated
- Check PHP version (minimum 7.2)
- Verify WordPress version (minimum 5.0)

### Payment Gateways Not Showing
- Verify gateways are active in WooCommerce settings
- Check gateway permissions in plugin settings
- Clear WordPress cache if using caching plugins

### Credit Not Deducting
- Check user credit balance in admin panel
- Verify order completion status
- Review error logs for payment handler issues

## 📞 Support

For bug reports, feature requests, or technical support, please contact the development team.

## 📄 License

This plugin is proprietary software. All rights reserved.

---

**Version**: 1.0.0  
**Last Updated**: 2024  
**Author**: Arta Development Team
