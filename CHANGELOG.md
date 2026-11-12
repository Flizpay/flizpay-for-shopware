# Changelog

All notable changes to the FLIZpay Payment Gateway for Shopware 6 will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-11-12

### Added

- On-site discount messaging: FLIZpay widgets on product listings, product pages, the cart, the off-canvas cart, checkout and the order confirmation page, rendered by the hosted FLIZpay script
- "On-site Messaging" settings card with a live preview and a toggle per storefront area
- Headline toggle for the checkout payment method, alongside the existing logo and subtitle toggles
- Send the plugin version and the Shopware version with every FLIZpay API request and in the webhook test reply
- `FLIZPAY_API_BASE_URL` and `FLIZPAY_WIDGET_SCRIPT_URL` environment variables for pointing the plugin at a staging environment

### Changed

- Render the checkout payment method headline and description with the hosted `<fliz-checkout>` component, so offer wording and discount logic come straight from FLIZpay
- Replace cashback wording with discount wording throughout the administration, storefront and plugin description
- Label the order discount line "FLIZpay Rabatt" / "FLIZpay Discount", translated into the language the order was placed in
- Rename the order custom fields from `flizpay_cashback_*` to `flizpay_discount_*` and the checkout display settings to `showHeadline` / `showDescription`; previously saved display settings fall back to their defaults
- Ship the payment method without a description, since the hosted component provides the offer text
- Use the hosted component in the administration checkout preview and remove the locally rendered discount rates card
- Remove all plugin configuration on uninstall when user data is not kept, even if FLIZpay cannot be reached

### Fixed

- Save settings checkboxes correctly on Shopware 6.7 (logo and subtitle toggles previously kept their old value)
- Report the real plugin version to FLIZpay instead of always sending `1.0.0`

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
