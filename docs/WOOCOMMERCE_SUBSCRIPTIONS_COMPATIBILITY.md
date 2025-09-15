# WooCommerce Subscriptions Compatibility Library

## Overview

This document outlines the implementation of a handshake library that makes WPSubscription a drop-in replacement for WooCommerce Subscriptions. The library will intercept all WooCommerce Subscriptions hooks, classes, and functions, translating them to work with the WPSubscription plugin.

## Goals

1. **Drop-in Replacement**: Make WPSubscription fully compatible with WooCommerce Subscriptions
2. **Payment Gateway Support**: Support all payment gateways that work with WooCommerce Subscriptions
3. **Plugin Compatibility**: Ensure third-party plugins expecting WooCommerce Subscriptions work seamlessly
4. **API Compatibility**: Maintain the same API surface as WooCommerce Subscriptions

## Architecture

### Core Components

1. **Bootstrap System** (`includes/Compatibility/Bootstrap.php`)
   - Initializes the compatibility layer
   - Registers all hooks and filters
   - Handles plugin detection and activation

2. **Class Aliases** (`includes/Compatibility/Classes/`)
   - `WC_Subscription` → Maps to WPSubscription's subscription class
   - `WC_Subscriptions_Manager` → Handles subscription management
   - `WC_Subscriptions_Product` → Product subscription functionality
   - `WC_Subscriptions_Order` → Order subscription handling

3. **Hook Translation** (`includes/Compatibility/Hooks/`)
   - Intercepts WooCommerce Subscriptions hooks
   - Translates them to WPSubscription equivalents
   - Maintains backward compatibility

4. **Function Compatibility** (`includes/Compatibility/Functions.php`)
   - Provides all WooCommerce Subscriptions functions
   - Maps to WPSubscription implementations

5. **Data Store Compatibility** (`includes/Compatibility/DataStores/`)
   - Custom data stores for subscription data
   - Maps to WPSubscription's data structure

## Implementation Steps

### Phase 1: Core Infrastructure
- [x] Create documentation structure
- [x] Implement bootstrap system
- [x] Set up class autoloading
- [x] Create basic hook interception

### Phase 2: Core Classes
- [x] Implement `WC_Subscription` class
- [x] Implement `WC_Subscriptions_Manager`
- [x] Implement `WC_Subscriptions_Product`
- [x] Implement `WC_Subscriptions_Order`

### Phase 3: Hook System
- [x] Implement action hooks translation
- [x] Implement filter hooks translation
- [x] Add hook priority management
- [x] Implement conditional hook loading

### Phase 4: Function Compatibility
- [x] Implement core subscription functions
- [x] Implement helper functions
- [x] Implement deprecated function support
- [x] Add function aliases

### Phase 5: Data Compatibility
- [x] Implement custom data stores
- [x] Add meta data mapping
- [x] Implement order type compatibility
- [x] Add post type compatibility

### Phase 6: Payment Gateway Integration
- [x] Implement payment gateway hooks
- [x] Add gateway compatibility layer
- [ ] Test with major payment gateways
- [ ] Add gateway-specific features

### Phase 7: Testing & Optimization
- [ ] Comprehensive testing
- [ ] Performance optimization
- [ ] Memory usage optimization
- [ ] Error handling improvements

## File Structure

```
includes/Compatibility/
├── Bootstrap.php                 # Main bootstrap class
├── Classes/
│   ├── WC_Subscription.php      # Main subscription class
│   ├── WC_Subscriptions_Manager.php
│   ├── WC_Subscriptions_Product.php
│   ├── WC_Subscriptions_Order.php
│   └── WC_Subscriptions_Cart.php
├── Hooks/
│   ├── ActionHooks.php          # Action hooks translation
│   ├── FilterHooks.php          # Filter hooks translation
│   └── HookManager.php          # Hook management
├── Functions/
│   ├── CoreFunctions.php        # Core subscription functions
│   ├── HelperFunctions.php      # Helper functions
│   └── DeprecatedFunctions.php  # Deprecated function support
├── DataStores/
│   ├── SubscriptionDataStore.php
│   └── OrderDataStore.php
├── Gateways/
│   ├── GatewayManager.php       # Payment gateway management
│   └── GatewayCompatibility.php # Gateway compatibility layer
└── Utils/
    ├── CompatibilityChecker.php # Compatibility checking
    └── Logger.php               # Compatibility logging
```

## Key Features

### 1. Class Aliasing
- All WooCommerce Subscriptions classes are aliased to WPSubscription equivalents
- Maintains the same public API
- Handles method calls and property access

### 2. Hook Translation
- Intercepts all WooCommerce Subscriptions hooks
- Translates them to WPSubscription hooks
- Maintains hook priority and arguments

### 3. Function Compatibility
- All WooCommerce Subscriptions functions are available
- Maps to WPSubscription implementations
- Maintains return value compatibility

### 4. Data Compatibility
- Custom data stores for subscription data
- Meta data mapping between systems
- Order type compatibility

### 5. Payment Gateway Support
- Intercepts payment gateway hooks
- Translates gateway calls to WPSubscription
- Maintains gateway compatibility

## Requirements

### WordPress
- WordPress 6.0+
- WooCommerce 6.0+

### PHP
- PHP 7.4+
- Required extensions: json, mbstring, curl

### Dependencies
- WPSubscription plugin active
- WooCommerce plugin active

## Usage

The compatibility library is automatically loaded when WPSubscription is active. No additional configuration is required.

### Manual Initialization
```php
// Initialize compatibility layer
WPSubscription_Compatibility::init();

// Check if compatibility is active
if (WPSubscription_Compatibility::is_active()) {
    // Compatibility layer is running
}
```

## Testing

### Test Coverage
- Unit tests for all classes
- Integration tests with WooCommerce
- Payment gateway compatibility tests
- Third-party plugin compatibility tests

### Test Environment
- WordPress multisite
- Different WooCommerce versions
- Various payment gateways
- Popular subscription-related plugins

## Performance Considerations

### Memory Usage
- Lazy loading of compatibility classes
- Efficient hook management
- Minimal memory footprint

### Performance Impact
- Negligible impact on site performance
- Optimized hook execution
- Cached compatibility checks

## Security

### Data Validation
- All input data is validated
- Sanitization of all outputs
- Nonce verification for admin actions

### Access Control
- Proper capability checks
- User permission validation
- Admin-only functionality protection

## Maintenance

### Updates
- Regular compatibility updates
- WooCommerce version compatibility
- Payment gateway updates

### Monitoring
- Compatibility status monitoring
- Error logging and reporting
- Performance monitoring

## Troubleshooting

### Common Issues
1. **Class Not Found**: Ensure autoloading is working
2. **Hook Conflicts**: Check hook priorities
3. **Data Issues**: Verify data store compatibility

### Debug Mode
Enable debug mode to get detailed logging:
```php
define('WP_SUBSCRIPTION_COMPATIBILITY_DEBUG', true);
```

## Contributing

### Code Standards
- Follow WordPress coding standards
- Use proper documentation
- Include unit tests

### Pull Requests
- Test thoroughly before submitting
- Include documentation updates
- Follow the existing code structure

## License

This compatibility library is part of WPSubscription and follows the same license terms.
