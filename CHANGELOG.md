# Changelog

All notable changes to the FLIZpay Payment Gateway for Shopware 6 will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-08-10

### Changed

- Update the checkout payment method title and description in German and English
- Clarify the discount wording in the administration settings
- Point shopper information links to the correct FLIZpay discount page

### Fixed

- Fetch and display discount information when initially connecting an API key
- Refresh discount information immediately in the administration without requiring a page reload

## [1.0.3] - 2026-08-04

### Changed

- Clarify that automatic installation allows merchants to skip steps 1–5
- Always show the cashback title at checkout when cashback is available
- Remove the option to hide the cashback title

### Fixed

- Show the configured cashback percentage in the settings preview subtitle

## [1.0.2] - 2026-07-30

### Changed

- Update the Shopware configuration guide in German and English
- Match checkout messaging to the merchant's configured cashback scenario
- Add a shopper information link to the checkout description
- Replace the outdated Shopware extension icon

### Fixed

- Stop sending the obsolete `needsShipping` field when creating transactions

## [1.0.1] - 2026-07-29

### Changed

- Ship compiled administration assets in the repository so the plugin is fully functional when installed via Composer/Packagist

## [1.0.0] - 2025-01-23

### Added

- Initial release of FLIZpay payment plugin for Shopware 6.7+
- Payment processing via FLIZpay redirect flow
- Webhook handling for payment confirmation
- Cashback display feature in checkout
- Configurable checkout appearance (logo, subtitle, cashback info)
- Sandbox mode support for testing
- German and English translations
- Admin configuration panel with connection testing
- Automatic payment method registration during installation

### Security

- HMAC-based webhook validation
- Secure API communication over HTTPS

### Technical

- Shopware 6.7+ compatibility
- PHP 8.2+ requirement
- PSR-4 autoloading
- Shopware state machine integration for order status updates
