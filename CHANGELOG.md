# Changelog

All notable changes to the FLIZpay Payment Gateway for Shopware 6 will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
