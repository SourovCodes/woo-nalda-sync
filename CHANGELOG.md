# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.15] - 2026-01-15

### Improved
- Enhanced dashboard scheduled sync status cards with pending/overdue detection
- Added WordPress cron status alert with troubleshooting guidance when syncs are pending
- Improved "Next Export/Import" display to show "Pending..." when syncs are overdue
- Better WordPress timezone handling using `current_time('timestamp')`

## [1.0.14] - 2026-01-15

### Changed
- Reverted billing email back to orders@nalda.com

## [1.0.13] - 2026-01-15

### Fixed
- Fixed "New Order" email showing 0 amount by disabling emails during import and triggering after order is fully saved
- Changed billing email from orders@nalda.com to orders@sourov.me

## [1.0.12] - 2026-01-15

### Fixed
- Fixed "New Order" email showing 0 amount - order status is now set after items and totals are calculated

## [1.0.11] - 2026-01-15

### Fixed
- Fixed Nalda order \"State\" not being set from API `deliveryStatus` during initial import
- New orders now always have a state set (defaults to "IN_PREPARATION" if not provided by API)

## [1.0.10] - 2026-01-14

### Changed
- Version bump

## [1.0.9] - 2026-01-14

### Added
- Custom delivery note logo setting in Advanced Settings (with WordPress media uploader)
- Nalda logo displayed below order info on delivery notes for Nalda orders
- German (de_DE) translations for delivery note PDF
- Dynamic shipping methods from WooCommerce shipping zones on delivery notes

### Changed
- Delivery note PDF now shows Nalda customer price (what the end client paid) instead of seller net price
- Delivery note logo uses custom setting with fallback to store name text

### Removed
- Removed "Measure unit" column from delivery note PDF
- Removed "Number of packages" field from delivery note PDF
- Removed "Comments" field from delivery note PDF
- Removed "Received in good condition" signature section from delivery note PDF
- Removed "Thank you" message from delivery note PDF
- Removed hardcoded delivery type options (now uses WooCommerce shipping methods)

## [1.0.6] - 2026-01-13

### Removed
- Removed unused "Create Customer Accounts" setting (Nalda orders always use Nalda as the billing customer, not end buyers)

### Fixed
- Added missing SFTP error codes (`CONNECTION_RESET`, `PROTOCOL_ERROR`) for better error handling
- Fixed logs page to display all 3 sync types: Product Export, Order Import, and Order Status Export
- Fixed CSV Upload History on dashboard to show both product exports and order status exports

## [1.0.5] - 2026-01-13

### Added
- Order status export to Nalda via CSV upload
- Scheduled/automatic order status export with configurable intervals
- Order Status Export settings section in Settings page
- Dashboard panel for Order Status Export with auto/manual badge

### Changed
- Renamed "Product Sync" to "Product Export" for clarity (data goes TO Nalda)
- Renamed "Order Sync" to "Order Import" for clarity (data comes FROM Nalda)
- Updated all user-facing labels to clarify data flow direction
- Renamed internal keys: `product_sync_*` → `product_export_*`, `order_sync_*` → `order_import_*`
- Renamed cron hooks: `woo_nalda_sync_product_sync` → `woo_nalda_sync_product_export`, `woo_nalda_sync_order_sync` → `woo_nalda_sync_order_import`
- Renamed files: `class-product-sync.php` → `class-product-export.php`, `class-order-sync.php` → `class-order-import.php`
- Renamed classes: `Woo_Nalda_Sync_Product_Sync` → `Woo_Nalda_Sync_Product_Export`, `Woo_Nalda_Sync_Order_Sync` → `Woo_Nalda_Sync_Order_Import`

## [1.0.4] - 2026-01-12

### Fixed
- Fixed GitHub username for update checker

## [1.0.3] - 2026-01-12

### Changed
- Version bump

## [1.0.1] - 2026-01-12

### Added
- Plugin auto-update system with GitHub releases
- Update check and update now functionality in Settings page
- GitHub Actions workflow for automatic releases on version change

### Changed
- Improved admin UI for better user experience

## [1.0.0] - 2026-01-01

### Added
- Initial release
- Product sync via SFTP
- Order import from Nalda API
- License management system
- Sync logging and history
- Admin dashboard with statistics
