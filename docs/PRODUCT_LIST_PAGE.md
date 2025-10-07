# WPSubscription Products List Page

A comprehensive products list page that displays all WooCommerce products with WPSubscription enabled.

## 🎯 Features

### Display Information

- **Product Image**: Thumbnail preview
- **Product Name**: With edit and view links
- **Product Type**: Simple or Variable (with badges)
- **Payment Type**: Recurring or Split Payment (with badges)
- **Price**: Product price display
- **Status**: Published, Draft, Private
- **Active Subscriptions**: Count with link to view subscriptions

### User Interface

- ✅ **WordPress Native Styles**: Uses WP core table styles
- ✅ **WooCommerce Integration**: Consistent with WooCommerce admin UI
- ✅ **Responsive Design**: Mobile-friendly with proper breakpoints
- ✅ **WPSubscription Consistency**: Matches existing plugin markup

### Actions Available

- **Edit Product**: Direct link to edit product page
- **View Product**: View product on frontend (opens in new tab)
- **View Subscriptions**: View all active subscriptions for the product (if any exist)
- **Add Product**: Quick link to create new WooCommerce product

## 📍 Access

**URL**: `http://your-site.com/wp-admin/admin.php?page=wp-subscription-products`

**Menu Location**: `WP Admin → Subscriptions → Products`

## 🔍 How It Works

### Product Detection

The page finds products based on the `_enable_subscription` meta key:

```php
'meta_query' => array(
    array(
        'key'     => '_enable_subscription',
        'value'   => 'yes',
        'compare' => '=',
    ),
)
```

### Payment Type Display

**Recurring Products:**

- Shows "Recurring" badge in blue
- Displays billing interval (e.g., "Every 1 Month(s)")

**Split Payment Products:**

- Shows "Split Payment" badge in red
- Displays number of installments (e.g., "3 installments")

### Product Type Badges

- **Simple**: Dark gray badge
- **Variable**: Darker gray badge

### Status Display

Uses WooCommerce order status styling:

- **Published**: Green
- **Draft**: Gray
- **Pending**: Orange
- **Private**: Purple

## 📱 Responsive Design

### Desktop View (> 782px)

All columns visible:

- Image (52px)
- Product Name (primary column)
- Type (12%)
- Payment Type (15%)
- Price (15%)
- Status (10%)
- Active Subscriptions (12%)

### Mobile View (≤ 782px)

Only primary column visible:

- Product Name with all information
- Other data accessible via row toggle

## 🎨 Styling

### Color Scheme

**Payment Type Badges:**

```css
.payment-type-recurring {
  background-color: #2271b1; /* Blue */
  color: #fff;
}
.payment-type-split {
  background-color: #d63638; /* Red */
  color: #fff;
}
```

**Product Type Badges:**

```css
.product-type-simple {
  background-color: #50575e; /* Gray */
  color: #fff;
}
.product-type-variable {
  background-color: #2c3338; /* Darker Gray */
  color: #fff;
}
```

### Images

- **Size**: 32x32 pixels
- **Border Radius**: 3px
- **Object Fit**: Cover (maintains aspect ratio)

## 🔧 Technical Details

### File Structure

```
wp-content/plugins/subscription/
├── includes/
│   ├── Admin/
│   │   └── ProductList.php          # Main list page class
│   └── Admin.php                     # Loads ProductList class
```

### Class Structure

```php
namespace SpringDevs\Subscription\Admin;

class ProductList {
    public function add_menu_page()              // Registers submenu page
    public function enqueue_scripts()            // Loads WooCommerce styles
    public function render_page()                // Main page render
    private function render_products_table()     // Renders table structure
    private function render_product_row()        // Renders single product row
    private function get_subscription_products() // Gets products from database
    private function get_payment_type_label()    // Returns payment type label
    private function get_payment_details()       // Returns payment details
    private function get_price_display()         // Returns formatted price
    private function get_status_label()          // Returns status label
    private function get_active_subscriptions_count() // Returns subscription count
}
```

### Database Query

**Products Query:**

```php
$args = array(
    'post_type'      => 'product',
    'posts_per_page' => -1,
    'post_status'    => array( 'publish', 'draft', 'private' ),
    'meta_query'     => array(
        array(
            'key'     => '_enable_subscription',
            'value'   => 'yes',
            'compare' => '=',
        ),
    ),
    'orderby'        => 'title',
    'order'          => 'ASC',
);
```

**Subscriptions Count Query:**

```php
$args = array(
    'post_type'      => 'subscrpt_order',
    'post_status'    => 'active',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => array(
        array(
            'key'     => 'product_id',
            'value'   => $product_id,
            'compare' => '=',
        ),
    ),
);
```

## 📊 Data Display

### Payment Type Details

**Recurring:**

- Meta keys: `_subscription_time`, `_subscription_type`
- Display: "Every [X] [days/weeks/months/years]"

**Split Payment:**

- Meta key: `_subscrpt_installment`
- Display: "[X] installments"

### Active Subscriptions

- Queries `subscrpt_order` post type
- Filters by `product_id` meta
- Shows count with link to filtered subscriptions list
- Displays "0" in gray if no active subscriptions

## 🔐 Capabilities

**Required Capability**: `manage_woocommerce`

This ensures only users with WooCommerce management permissions can access the page.

## 📝 Localization

All strings are translatable using the `sdevs_wc_subs` text domain:

```php
__( 'Subscription Products', 'sdevs_wc_subs' )
__( 'Add Product', 'sdevs_wc_subs' )
__( 'Product Name', 'sdevs_wc_subs' )
// ... etc
```

## 🎯 Empty State

When no subscription products exist:

- Shows informative notice
- Provides "Create your first subscription product" link
- Links directly to WooCommerce add product page

## 🔗 Integration Points

### WooCommerce Integration

- Uses WooCommerce product objects (`wc_get_product()`)
- Leverages WooCommerce price formatting
- Uses WooCommerce admin styles
- Integrates with WooCommerce product editing

### WordPress Integration

- Uses `WP_Query` for product queries
- Follows WordPress coding standards
- Uses WordPress list table markup
- Implements WordPress admin notices

### WPSubscription Integration

- Reads subscription-specific meta data
- Links to subscription management pages
- Uses consistent WPS styling
- Follows WPS naming conventions

## 🚀 Usage Examples

### Viewing Products

1. Navigate to **Subscriptions → Products**
2. See all subscription-enabled products
3. Click product name to edit
4. Click "View" to see on frontend
5. Click subscription count to see active subscriptions

### Quick Actions

- **Add New Product**: Click "Add Product" button at top
- **Edit Product**: Click product name or "Edit" link
- **View Product**: Click "View" in row actions
- **View Subscriptions**: Click subscription count or "View Subscriptions" link

## 🎨 Customization

### Styling

All styles are inline for simplicity and to avoid additional CSS files. Styles follow WordPress and WooCommerce conventions.

### Extending

To add custom columns, modify:

1. `render_products_table()` - Add table header
2. `render_product_row()` - Add table cell
3. Add responsive CSS rule if needed

### Filtering Products

To filter by additional criteria, modify the `get_subscription_products()` method's `$args` array.

## 🐛 Troubleshooting

### Products Not Showing

**Check:**

- Product has `_enable_subscription` meta set to `yes`
- Product status is `publish`, `draft`, or `private`
- WooCommerce is active

### Subscriptions Count Incorrect

**Check:**

- Subscription post type is `subscrpt_order`
- Subscription status is `active`
- Product ID meta is correctly set on subscriptions

### Styling Issues

**Check:**

- WooCommerce admin styles are enqueued
- No caching issues
- Browser console for CSS errors

---

**Created**: October 7, 2025  
**Version**: 1.6.0  
**Compatibility**: WordPress 5.0+, WooCommerce 5.0+
