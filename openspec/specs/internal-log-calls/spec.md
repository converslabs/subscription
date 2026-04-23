## ADDED Requirements

### Requirement: Internal code uses canonical log functions

Free plugin source files SHALL call `subscrpt_write_log()` and `subscrpt_write_debug_log()` directly. No internal file (outside of `LegacyCompat.php`) SHALL call the deprecated `wp_subscrpt_write_log()` or `wp_subscrpt_write_debug_log()` aliases.

#### Scenario: No deprecated log calls in plugin source

- **WHEN** searching the `includes/` directory for `wp_subscrpt_write_log` or `wp_subscrpt_write_debug_log`
- **THEN** the only matches found SHALL be inside `LegacyCompat.php` (the wrapper declarations themselves)

### Requirement: Deprecated wrapper emits deprecation notice

The `wp_subscrpt_write_log()` wrapper in `LegacyCompat.php` SHALL call `_deprecated_function()` before delegating, consistent with the other three wrappers in the same file.

#### Scenario: Deprecation notice fired when deprecated wrapper called

- **WHEN** any code calls `wp_subscrpt_write_log()`
- **THEN** WordPress SHALL emit a deprecation notice identifying `subscrpt_write_log` as the replacement
