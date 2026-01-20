# FlizpayForShopware - Shopware Store Quality Guidelines Checklist

## Overview

This checklist is based on the official Shopware Store Quality Guidelines for plugin submission.
Reference: https://developer.shopware.com/docs/resources/guidelines/testing/store/quality-guidelines-plugins/

---

## 1. Extension Master Data

### Naming Requirements

| Requirement | Status | Notes |
|------------|--------|-------|
| Display name does not contain "plugin" | PASS | "FLIZpay Payment" |
| Display name does not contain "shopware" | PASS | No Shopware reference |
| Store name matches composer.json | VERIFY | Check consistency |
| Store name matches config.xml (if exists) | VERIFY | Check config.xml |
| Technical name is case-sensitive correct | PASS | FlizpayForShopware |

### Description Requirements

| Requirement | Status | Notes |
|------------|--------|-------|
| Short description min 150 chars | VERIFY | Check store listing |
| Short description max 185 chars | VERIFY | Check store listing |
| Short description is unique | VERIFY | Not copy of long desc |
| Long description min 200 chars | VERIFY | Check store listing |
| Only allowed HTML tags used | VERIFY | a, p, br, b, strong, i, ul, ol, li, h2-h5 |
| Max 2 YouTube videos embedded | PASS | Verify in description |

### Images and Media

| Requirement | Status | Notes |
|------------|--------|-------|
| plugin.png favicon (112x112 px) | FAIL | Missing in src/Resources/config/ |
| Screenshots in English | VERIFY | Prepare for submission |
| Screenshots show functionality | VERIFY | Mobile and desktop views |
| No advertising in screenshots | VERIFY | Only extension features |
| Preview images represent features | VERIFY | Accurate representation |

---

## 2. License Requirements

### License Configuration

| Requirement | Status | Notes |
|------------|--------|-------|
| Valid license in composer.json | PASS | MIT declared |
| LICENSE file present | FAIL | Missing - MUST ADD |
| License matches account settings | VERIFY | Check Shopware account |
| License cannot change after submission | INFO | First submission |

---

## 3. Code Quality - Automatic Review

### PhpStan/SonarQube Checks

| Requirement | Status | Notes |
|------------|--------|-------|
| No die; statements | PASS | None found |
| No exit; statements | PASS | None found |
| No var_dump statements | PASS | None found |
| No commented-out code | VERIFY | Manual review needed |
| No unused files/folders | VERIFY | Check distribution |

### Prohibited Files in Binary

| Requirement | Status | Notes |
|------------|--------|-------|
| No tests/ directory | PASS | N/A (no tests) |
| No .git files | PASS | Excluded via .gitignore |
| No .editorconfig | VERIFY | Check inclusion |
| No .eslintrc.js | VERIFY | Check inclusion |
| No .travis.yml | PASS | Not present |
| No package.json in root | PASS | Only in admin/src |
| No package-lock.json | VERIFY | Check distribution |
| No composer.lock | VERIFY | Should not include |
| No phpstan.neon | PASS | Not present |
| No phpunit.xml.dist | PASS | Not present |
| No webpack.config.js | VERIFY | Check inclusion |
| No .zip/.tar.gz/.phar files | VERIFY | Check for archives |

---

## 4. JavaScript Requirements

### Source Code

| Requirement | Status | Notes |
|------------|--------|-------|
| Uncompiled JS in binary | PASS | src/ directory present |
| TypeScript source available | PASS | .ts files included |
| Compiled code minified | VERIFY | Check public/administration |
| Proper build process used | PASS | Vite configured |

---

## 5. Security Requirements

### External Links

| Requirement | Status | Notes |
|------------|--------|-------|
| External links have rel="noopener" | VERIFY | Check all templates |
| External links have target="_blank" | VERIFY | Check all templates |
| External services disclosed | VERIFY | FLIZpay API mentioned |

### Data Protection

| Requirement | Status | Notes |
|------------|--------|-------|
| Personal data processing disclosed | VERIFY | Check store description |
| Data protection info provided | VERIFY | Required if processing data |
| GDPR compliance documented | VERIFY | Payment data handling |

### Cookies

| Requirement | Status | Notes |
|------------|--------|-------|
| Cookies registered in Consent Manager | VERIFY | Check cookie usage |
| Cookies set as secure | VERIFY | If any cookies used |
| Cookie categories defined | VERIFY | Technical/Marketing/Comfort |

### Error Handling

| Requirement | Status | Notes |
|------------|--------|-------|
| Logs only in /var/log/ | PASS | Uses Shopware logger |
| Log file naming correct | PASS | Standard Shopware |
| No 500 errors | VERIFY | Complete testing needed |
| API test button for credentials | PASS | Connection test exists |

---

## 6. Testing Requirements

### Installation Testing

| Requirement | Status | Notes |
|------------|--------|-------|
| Installation without errors | VERIFY | Fresh install test |
| Configuration without errors | VERIFY | Settings test |
| Uninstallation without errors | VERIFY | Clean uninstall test |
| Reinstallation without errors | VERIFY | Full cycle test |
| No 400/500 errors in Extension Manager | VERIFY | Console check |

### Version Compatibility

| Requirement | Status | Notes |
|------------|--------|-------|
| Tested with latest Shopware 6 CE | VERIFY | Manual testing |
| Highest supported version tested | VERIFY | 6.7.x testing |
| Minimum version requirement met | PASS | 6.7 in composer.json |

### Storefront Testing

| Requirement | Status | Notes |
|------------|--------|-------|
| Lighthouse Audit performed | VERIFY | A/B testing |
| Schema.org validation | VERIFY | Structured data check |
| No browser console errors | VERIFY | Full storefront check |
| All viewports tested | VERIFY | Mobile/tablet/desktop |
| No styling errors | VERIFY | CSS review |

### Composer Requirements

| Requirement | Status | Notes |
|------------|--------|-------|
| Dependencies in composer.json | PASS | shopware/core required |
| composer.lock not in archive | VERIFY | Check .gitignore |
| Technical name matches store | VERIFY | FlizpayForShopware |

---

## 7. Documentation Requirements

### Configuration Manual

| Requirement | Status | Notes |
|------------|--------|-------|
| Step-by-step setup instructions | FAIL | Incomplete README |
| Technical explanation provided | PARTIAL | CASHBACK_FEATURE.md |
| Usage instructions included | FAIL | Not documented |
| Clean HTML source code | VERIFY | Check documentation |
| Screenshots of backend | VERIFY | Prepare for submission |
| Screenshots of storefront | VERIFY | Prepare for submission |

### Translations

| Requirement | Status | Notes |
|------------|--------|-------|
| Works in non-EN/DE languages | VERIFY | Fallback testing |
| English translation as fallback | PASS | en-GB snippets |
| Translation languages declared | VERIFY | Account settings |

### Manufacturer Profile

| Requirement | Status | Notes |
|------------|--------|-------|
| English description provided | VERIFY | Shopware account |
| German description provided | VERIFY | Shopware account |
| Manufacturer logo uploaded | VERIFY | Shopware account |
| Profile accessible | VERIFY | Extension Partner area |

---

## 8. Storefront Guidelines

### Content Standards

| Requirement | Status | Notes |
|------------|--------|-------|
| No hX tags in templates | VERIFY | Use span class="h2" |
| No inline CSS in templates | PASS | Separate SCSS files |
| No !important usage | VERIFY | Check all stylesheets |
| Custom URLs have X-Robots-Tag | N/A | No custom pages |
| Proper canonical tags | N/A | No custom pages |

### Media Standards

| Requirement | Status | Notes |
|------------|--------|-------|
| Images have alt attributes | FAIL | Missing in template |
| Links have title attributes | VERIFY | Check all links |
| Assets from media manager | PASS | Uses asset filter |

---

## 9. Administration Guidelines

### Menu and Navigation

| Requirement | Status | Notes |
|------------|--------|-------|
| No main menu entries | PASS | Settings subitem only |
| Extension Manager not modified | PASS | No overwrites |
| No file reloading during install | PASS | Standard installation |

### Media and CMS

| Requirement | Status | Notes |
|------------|--------|-------|
| Custom folders have thumbnails | N/A | No custom folders |
| Data removable on uninstall | PASS | User choice respected |
| Shopping Experiences compatible | N/A | No CMS elements |

---

## 10. Data Management

### Installation/Uninstallation

| Requirement | Status | Notes |
|------------|--------|-------|
| User chooses data deletion | VERIFY | Check context handling |
| Text snippets retainable | PASS | Standard behavior |
| Custom tables documented | N/A | No custom tables |
| Database verified via Adminer | VERIFY | Post-install check |

### Message Queue

| Requirement | Status | Notes |
|------------|--------|-------|
| Messages under 256 KB | PASS | Standard messages |
| No excessive queue usage | PASS | No queue implemented |

---

## 11. Sales Channel Configuration

| Requirement | Status | Notes |
|------------|--------|-------|
| Per-channel configuration | VERIFY | Check implementation |
| Settings scoped correctly | VERIFY | SalesChannelId usage |

---

## 12. Payment Plugin Specific

### Payment Handler

| Requirement | Status | Notes |
|------------|--------|-------|
| Extends AbstractPaymentHandler | PASS | Correct inheritance |
| Tagged as payment.method | PASS | services.xml correct |
| pay() method implemented | PASS | Redirect flow |
| finalize() method implemented | PASS | Return handling |
| supports() returns correct value | FAIL | Always returns false |
| Payment method not deleted on uninstall | PASS | Only deactivated |

### Transaction Handling

| Requirement | Status | Notes |
|------------|--------|-------|
| Order state transitions correct | PASS | Uses state machine |
| Transaction states updated | PASS | Proper flow |
| Webhook handling secure | PASS | HMAC validation |

---

## Critical Blockers for Store Submission

### Must Fix (Blocking)

1. **Missing LICENSE file**
   - Action: Create LICENSE file with MIT license text
   - Priority: CRITICAL

2. **Missing plugin.png favicon**
   - Action: Create 112x112 px icon in src/Resources/config/
   - Priority: CRITICAL

3. **PaymentHandler::supports() returns false**
   - Location: src/Handler/FlizpayPaymentHandler.php
   - Action: Return true for PaymentHandlerType::PAYMENT
   - Priority: CRITICAL

4. **Incomplete documentation**
   - Action: Expand README.md with installation/usage instructions
   - Priority: CRITICAL

5. **Missing alt attributes on images**
   - Location: payment-method.html.twig
   - Action: Add meaningful alt text
   - Priority: HIGH

6. **Build artifacts in repository**
   - Action: Add .vite/ to .gitignore, remove from repo
   - Priority: HIGH

### Should Fix (High Priority)

7. **API key plain text storage**
   - Action: Use Shopware encryption service
   - Priority: HIGH

8. **Log level misuse**
   - Action: Change critical to debug/info
   - Priority: MEDIUM

9. **Missing CHANGELOG.md**
   - Action: Create version history
   - Priority: MEDIUM

10. **Hardcoded staging API URL**
    - Action: Make configurable
    - Priority: HIGH

---

## Pre-Submission Checklist

### Account Setup

- [ ] Shopware Extension Partner account active
- [ ] Manufacturer profile complete (EN/DE)
- [ ] Manufacturer logo uploaded
- [ ] License type selected

### Plugin Package

- [ ] LICENSE file added
- [ ] plugin.png (112x112) created
- [ ] README.md expanded
- [ ] CHANGELOG.md created
- [ ] Build artifacts removed
- [ ] composer.lock removed
- [ ] All prohibited files excluded

### Store Listing

- [ ] Short description (150-185 chars)
- [ ] Long description (200+ chars)
- [ ] English screenshots prepared
- [ ] Feature documentation ready
- [ ] Installation manual ready
- [ ] Data protection info (if needed)

### Testing Complete

- [ ] Fresh installation tested
- [ ] Configuration tested
- [ ] Payment flow tested
- [ ] Webhook handling tested
- [ ] Uninstall/reinstall tested
- [ ] Lighthouse audit passed
- [ ] Browser console clear
- [ ] All viewports tested

### Code Quality

- [ ] PHPStan analysis passed
- [ ] ESLint passed
- [ ] No prohibited code patterns
- [ ] All critical issues fixed

---

## Useful Links

- Quality Guidelines: https://developer.shopware.com/docs/resources/guidelines/testing/store/quality-guidelines-plugins/
- Plugin Structure: https://developer.shopware.com/docs/guides/plugins/plugins/plugin-base-guide
- Payment Plugin Guide: https://developer.shopware.com/docs/guides/plugins/plugins/checkout/payment/add-payment-plugin
- Code Review Config: https://github.com/shopwareLabs/store-plugin-codereview
- Lighthouse Audit: https://developer.chrome.com/docs/lighthouse
- Schema.org Validator: https://validator.schema.org/

---

## Estimated Effort to Store Ready

| Task | Hours | Priority |
|------|-------|----------|
| Create LICENSE file | 0.5 | Critical |
| Create plugin.png favicon | 1 | Critical |
| Fix supports() method | 0.5 | Critical |
| Expand README.md | 2 | Critical |
| Add image alt attributes | 0.5 | High |
| Remove build artifacts | 0.5 | High |
| Encrypt API key storage | 3 | High |
| Fix log levels | 1 | Medium |
| Create CHANGELOG.md | 1 | Medium |
| Make API URL configurable | 2 | High |
| Prepare store screenshots | 2 | Required |
| Write installation manual | 3 | Required |
| Complete testing | 4 | Required |
| TOTAL | 21 hours | - |

---

Last Updated: January 2025
Plugin Version: 1.0.0
Shopware Version: 6.7+
