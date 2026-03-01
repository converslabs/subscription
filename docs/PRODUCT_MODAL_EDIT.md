# WPSubscription Product Modal Edit Feature

## 🎯 Overview

A simplified modal-based editor for subscription settings that allows quick editing without leaving the products list page.

## ✨ Features

### 1. **Modal Edit Interface**

- ✅ Click "Edit" to open modal with subscription settings
- ✅ Quick access to subscription-specific options
- ✅ No page reload required
- ✅ Clean, focused editing experience

### 2. **Dual Edit Links**

- ✅ **"Edit"**: Opens modal for subscription settings
- ✅ **"Edit in WooCommerce"**: Opens full WooCommerce product editor
- ✅ Clear separation of concerns

### 3. **Modal Features**

- ✅ Responsive design (mobile-friendly)
- ✅ Keyboard shortcuts (ESC to close)
- ✅ Click outside to close
- ✅ Loading states
- ✅ Success/error notifications
- ✅ Auto-refresh after save

## 📁 Files Created

### JavaScript

**`assets/js/admin-product-list.js`**

- Modal open/close functionality
- AJAX loading and saving
- Form validation
- Dynamic field toggling (recurring vs split)
- Error/success handling

### CSS

**`assets/css/admin-product-list.css`**

- Modal overlay and content styling
- Responsive design
- Loading states
- Form styling

### PHP

**`includes/Admin/ProductList.php`**

- `ajax_get_product_settings()`: Get current settings
- `ajax_save_product_settings()`: Save updated settings
- Asset enqueuing with localization

## 🎨 Modal Fields

### Available Settings

| Field                  | Type            | Options                       | Visibility     |
| ---------------------- | --------------- | ----------------------------- | -------------- |
| Enable Subscription    | Checkbox        | On/Off                        | Always         |
| Payment Type           | Select          | Recurring / Split             | Always         |
| Billing Interval       | Number + Select | 1-∞ + days/weeks/months/years | Recurring only |
| Number of Installments | Number          | 2-12                          | Split only     |
| User Can Cancel        | Checkbox        | Yes/No                        | Recurring only |

### Dynamic Fields

**When "Recurring" selected:**

- Shows: Billing Interval
- Shows: User Can Cancel
- Hides: Number of Installments

**When "Split" selected:**

- Shows: Number of Installments
- Hides: Billing Interval
- Hides: User Can Cancel

## 🔧 Technical Implementation

### JavaScript Object Structure

```javascript
const WPSubscriptionProductList = {
    init()                          // Initialize the module
    bindEvents()                    // Bind all event handlers
    openEditModal()                 // Open modal and load settings
    renderEditForm()                // Render form with settings
    saveSettings()                  // Save settings via AJAX
    showModal()                     // Show modal overlay
    closeModal()                    // Close and cleanup modal
    showLoading()                   // Show loading spinner
    showError()                     // Show error message
    showSuccess()                   // Show success message
};
```

### AJAX Endpoints

**Get Settings:**

```
Action: subscrpt_get_product_settings
Params: product_id, nonce
Returns: {enabled, payment_type, subscription_time, subscription_type, installments, user_cancel}
```

**Save Settings:**

```
Action: subscrpt_save_product_settings
Params: product_id, nonce, subscrpt_enabled, subscrpt_payment_type, subscription_time, subscription_type, subscrpt_installment, subscrpt_user_cancel
Returns: {message}
```

### Security

- ✅ Nonce verification: `subscrpt_product_settings`
- ✅ Capability check: Uses WooCommerce permissions
- ✅ Input sanitization: `sanitize_text_field()`, `absint()`
- ✅ Output escaping: All displayed data escaped

## 🎯 User Experience

### Edit Flow

1. **Click "Edit" link** on any product
2. **Modal opens** with loading spinner
3. **Settings load** via AJAX
4. **Edit settings** in clean form
5. **Click "Save Settings"**
6. **Settings save** via AJAX
7. **Success message** shows
8. **Page refreshes** automatically

### Alternative: WooCommerce Edit

1. **Click "Edit in WooCommerce"** link
2. **Opens** full WooCommerce product editor
3. **Edit** all product settings (price, images, etc.)

## 🎨 Modal Styling

### Design

- **Max Width**: 600px
- **Max Height**: 90vh
- **Position**: Centered on screen
- **Overlay**: Dark transparent background (70% opacity)
- **Shadow**: Subtle drop shadow
- **Border Radius**: 4px

### Header

- Product name as title
- Close button with icon
- Bottom border separator

### Body

- Scrollable content area
- WordPress form table styling
- Responsive two-column layout (stacks on mobile)

### Footer

- Cancel and Save buttons
- Right-aligned layout
- Stacks on mobile

## 📱 Responsive Design

### Desktop (> 782px)

- Modal: 600px width
- Form: Two-column layout (label | field)
- Buttons: Horizontal layout

### Mobile (≤ 782px)

- Modal: 95% width
- Form: Single-column layout (stacked)
- Buttons: Full-width, stacked vertically

## 🔑 Key Features

### 1. **Keyboard Support**

- `ESC` key closes modal
- Tab navigation within form
- Enter key submits form

### 2. **Click Outside to Close**

- Click overlay to close
- Click close button to close
- Content area click doesn't close

### 3. **Loading States**

- Spinner while loading settings
- "Saving..." text on button
- Button disabled during save

### 4. **Error Handling**

- Network errors caught
- Validation errors displayed
- User-friendly error messages

### 5. **Success Feedback**

- Success message displayed
- Auto-refresh after 1 second
- Updated data shown immediately

## 🔍 Row Actions

```
Edit | Edit in WooCommerce | View | View Subscriptions
^      ^                     ^      ^
|      |                     |      |
|      |                     |      └─ Shows active subscriptions (if > 0)
|      |                     └─ View product on frontend
|      └─ Edit full product in WooCommerce
└─ Quick edit subscription settings in modal
```

## 🚀 Usage Examples

### Opening Modal

```javascript
// Programmatically open modal
jQuery(".edit-subscription-settings").first().trigger("click");
```

### Closing Modal

```javascript
// Programmatically close modal
WPSubscriptionProductList.closeModal();
```

### Custom Validation

```javascript
// Add custom validation before save
$(document).on("click", "#save-subscription-settings", function (e) {
  if (!customValidation()) {
    e.stopImmediatePropagation();
    return false;
  }
});
```

## 📊 Meta Keys Used

| Meta Key                 | Type   | Description                        |
| ------------------------ | ------ | ---------------------------------- |
| `_subscrpt_enabled`      | string | '1' = enabled, '0' = disabled      |
| `_subscrpt_payment_type` | string | 'recurring' or 'split'             |
| `_subscription_time`     | int    | Billing interval number            |
| `_subscription_type`     | string | 'days', 'weeks', 'months', 'years' |
| `_subscrpt_installment`  | int    | Number of installments (2-12)      |
| `_subscrpt_user_cancel`  | string | 'yes' or 'no'                      |

## 🐛 Troubleshooting

### Modal Not Opening

**Check:**

- JavaScript file loaded correctly
- No console errors
- WP_SUBSCRIPTION_ASSETS constant defined
- jQuery available

### Settings Not Saving

**Check:**

- Nonce validation passing
- Product ID valid
- AJAX URL correct
- User has permissions

### Modal Not Closing

**Check:**

- Close button clickable
- ESC key handler working
- Overlay click handler working

## 🔒 Security

### Nonce Verification

```php
check_ajax_referer( 'subscrpt_product_settings', 'nonce' );
```

### Input Sanitization

```php
absint( $_POST['product_id'] )
sanitize_text_field( $_POST['subscrpt_payment_type'] )
absint( $_POST['subscription_time'] )
```

### Capability Check

- Uses WooCommerce `manage_woocommerce` capability
- Only authorized users can access

## 📈 Future Enhancements

Possible additions:

- [ ] Add trial period settings
- [ ] Add synchronization settings
- [ ] Add signup fee settings
- [ ] Add limit settings
- [ ] Bulk edit modal for multiple products
- [ ] Preview changes before saving

---

**Version**: 1.6.0  
**Last Updated**: October 7, 2025  
**Status**: ✅ Complete and Ready for Testing
