# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.6] - 2026-01-13

### Fixed
- Added missing SFTP error codes (`CONNECTION_RESET`, `PROTOCOL_ERROR`) for better error handling

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
