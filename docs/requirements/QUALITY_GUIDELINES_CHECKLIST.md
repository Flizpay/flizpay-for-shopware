# FlizpayForShopware - Shopware Store Quality Guidelines Checklist

## Overview

This checklist is based on the official Shopware Store Quality Guidelines for plugin submission.
Reference: https://developer.shopware.com/docs/resources/guidelines/testing/store/quality-guidelines-plugins/

**Status Legend:**

- **PASS** = Requirement met
- **FAIL** = Requirement not met, action needed
- **N/A** = Not applicable

---

## 1. Extension Master Data

### Naming Requirements

| Requirement                              | Status | Notes                     |
| ---------------------------------------- | ------ | ------------------------- |
| Display name does not contain "plugin"   | PASS   | "FLIZpay Payment"         |
| Display name does not contain "shopware" | PASS   | No Shopware reference     |
| Store name matches composer.json         | PASS   | "flizpay/flizpay-payment" |
| Technical name is case-sensitive correct | PASS   | FlizpayForShopware        |

### Images and Media

| Requirement                     | Status | Notes                                |
| ------------------------------- | ------ | ------------------------------------ |
| plugin.png favicon (112x112 px) | PASS   | Created from fliz-icon.png (112x112) |

---

## 2. License Requirements

| Requirement                    | Status | Notes                |
| ------------------------------ | ------ | -------------------- |
| Valid license in composer.json | PASS   | MIT declared         |
| LICENSE file present           | PASS   | Created January 2025 |

---

## 3. Code Quality - Automatic Review

### PhpStan/SonarQube Checks

| Requirement             | Status | Notes                       |
| ----------------------- | ------ | --------------------------- |
| No die; statements      | PASS   | None found                  |
| No exit; statements     | PASS   | None found                  |
| No var_dump statements  | PASS   | None found                  |
| No commented-out code   | PASS   | Only documentation comments |
| No unused files/folders | PASS   | Clean structure             |

### Prohibited Files in Binary

| Requirement                 | Status | Notes                   |
| --------------------------- | ------ | ----------------------- |
| No tests/ directory         | PASS   | Not present             |
| No .git files               | PASS   | Excluded via .gitignore |
| No .editorconfig            | PASS   | Not present             |
| No .eslintrc.js             | PASS   | Not present             |
| No .travis.yml              | PASS   | Not present             |
| No package.json in root     | PASS   | Only in admin/src       |
| No package-lock.json        | PASS   | Excluded via .gitignore |
| No composer.lock            | PASS   | Excluded via .gitignore |
| No phpstan.neon             | PASS   | Not present             |
| No phpunit.xml.dist         | PASS   | Not present             |
| No webpack.config.js        | PASS   | Not present             |
| No .zip/.tar.gz/.phar files | PASS   | None found              |

---

## 4. JavaScript Requirements

| Requirement                 | Status | Notes                  |
| --------------------------- | ------ | ---------------------- |
| Uncompiled JS in binary     | PASS   | src/ directory present |
| TypeScript source available | PASS   | .ts files included     |
| Compiled code minified      | PASS   | Vite build output      |
| Proper build process used   | PASS   | Vite configured        |

---

## 5. Security Requirements

### External Links

| Requirement                          | Status | Notes                            |
| ------------------------------------ | ------ | -------------------------------- |
| External links have rel="noopener"   | PASS   | Added to all external links      |
| External links have target="\_blank" | PASS   | All external links have it       |
| External services disclosed          | PASS   | FLIZpay API documented in README |

### Cookies

| Requirement                           | Status | Notes           |
| ------------------------------------- | ------ | --------------- |
| Cookies registered in Consent Manager | N/A    | No cookies used |
| Cookies set as secure                 | N/A    | No cookies used |
| Cookie categories defined             | N/A    | No cookies used |

### Error Handling

| Requirement                     | Status | Notes                  |
| ------------------------------- | ------ | ---------------------- |
| Logs only in /var/log/          | PASS   | Uses Shopware logger   |
| Log file naming correct         | PASS   | Standard Shopware      |
| API test button for credentials | PASS   | Connection test exists |

---

## 6. Testing Requirements

### Composer Requirements

| Requirement                     | Status | Notes                   |
| ------------------------------- | ------ | ----------------------- |
| Dependencies in composer.json   | PASS   | shopware/core required  |
| composer.lock not in archive    | PASS   | Excluded via .gitignore |
| Technical name matches store    | PASS   | FlizpayForShopware      |
| Minimum version requirement met | PASS   | 6.7 in composer.json    |

---

## 7. Documentation Requirements

| Requirement                     | Status | Notes                           |
| ------------------------------- | ------ | ------------------------------- |
| Step-by-step setup instructions | PASS   | README.md has full guide        |
| Technical explanation provided  | PASS   | README.md + CASHBACK_FEATURE.md |
| Usage instructions included     | PASS   | README.md includes usage        |
| CHANGELOG.md present            | PASS   | Created January 2025            |
| English translation as fallback | PASS   | en-GB snippets complete         |

---

## 8. Storefront Guidelines

### Content Standards

| Requirement                | Status | Notes                  |
| -------------------------- | ------ | ---------------------- |
| No hX tags in templates    | PASS   | Using proper structure |
| No inline CSS in templates | PASS   | Separate SCSS files    |
| No !important usage        | PASS   | None found             |

### Media Standards

| Requirement                | Status | Notes                  |
| -------------------------- | ------ | ---------------------- |
| Images have alt attributes | PASS   | Added to all templates |
| Assets from media manager  | PASS   | Uses asset filter      |

---

## 9. Administration Guidelines

| Requirement                      | Status | Notes                 |
| -------------------------------- | ------ | --------------------- |
| No main menu entries             | PASS   | Settings subitem only |
| Extension Manager not modified   | PASS   | No overwrites         |
| No file reloading during install | PASS   | Standard installation |
| Data removable on uninstall      | PASS   | User choice respected |

---

## 10. Data Management

| Requirement                | Status | Notes                        |
| -------------------------- | ------ | ---------------------------- |
| User chooses data deletion | PASS   | Context handling implemented |
| Text snippets retainable   | PASS   | Standard behavior            |
| Custom tables documented   | N/A    | No custom tables             |
| Messages under 256 KB      | PASS   | Standard messages            |
| No excessive queue usage   | PASS   | No queue implemented         |

---

## 11. Sales Channel Configuration

| Requirement               | Status | Notes                      |
| ------------------------- | ------ | -------------------------- |
| Per-channel configuration | PASS   | SalesChannelId supported   |
| Settings scoped correctly | PASS   | Proper scoping implemented |

---

## 12. Payment Plugin Specific

### Payment Handler

| Requirement                             | Status | Notes                         |
| --------------------------------------- | ------ | ----------------------------- |
| Extends AbstractPaymentHandler          | PASS   | Correct inheritance           |
| Tagged as payment.method                | PASS   | services.xml correct          |
| pay() method implemented                | PASS   | Redirect flow                 |
| finalize() method implemented           | PASS   | Return handling               |
| supports() returns correct value        | PASS   | Returns true for PAYMENT type |
| Payment method not deleted on uninstall | PASS   | Only deactivated              |

### Transaction Handling

| Requirement                     | Status | Notes              |
| ------------------------------- | ------ | ------------------ |
| Order state transitions correct | PASS   | Uses state machine |
| Transaction states updated      | PASS   | Proper flow        |
| Webhook handling secure         | PASS   | HMAC validation    |

---

## Summary

### Completed in January 2025

| Item                       | Action Taken                                    |
| -------------------------- | ----------------------------------------------- |
| LICENSE file               | Created MIT license                             |
| PaymentHandler::supports() | Fixed to return true for PAYMENT type           |
| README.md                  | Expanded with full documentation                |
| CHANGELOG.md               | Created with v1.0.0 entry                       |
| Alt attributes             | Added to admin and storefront templates         |
| Log levels                 | Changed 8 instances from critical to debug/info |
| API URL                    | Changed from staging to production              |
| Build artifacts            | Verified excluded via .gitignore                |

### Still Needs Work

All code-related items are now complete.

---

## Useful Links

- Quality Guidelines: https://developer.shopware.com/docs/resources/guidelines/testing/store/quality-guidelines-plugins/
- Plugin Structure: https://developer.shopware.com/docs/guides/plugins/plugins/plugin-base-guide
- Payment Plugin Guide: https://developer.shopware.com/docs/guides/plugins/plugins/checkout/payment/add-payment-plugin

---

Last Updated: January 2025
Plugin Version: 1.0.0
Shopware Version: 6.7+
