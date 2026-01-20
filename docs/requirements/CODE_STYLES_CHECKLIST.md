# FlizpayForShopware - Code Styles Checklist for Shopware Store Submission

## Overview

This checklist covers all code style and quality requirements for the FlizpayForShopware plugin to be accepted in the Shopware Store.

---

## 1. PHP Code Standards

### PSR Standards Compliance

| Requirement | Status | Notes |
|------------|--------|-------|
| PSR-1: Basic Coding Standard | PASS | Class naming, file encoding |
| PSR-4: Autoloading Standard | PASS | Proper namespace structure |
| PSR-12: Extended Coding Style | PASS | Consistent code style |
| strict_types declaration | PASS | All files declare strict_types=1 |

### PHP Version Compatibility

| Requirement | Status | Notes |
|------------|--------|-------|
| PHP 8.1+ compatible | PASS | Modern PHP features used |
| No deprecated functions | VERIFY | Run static analysis |
| Type declarations used | PASS | Parameters and returns typed |
| Nullable types properly declared | VERIFY | Check all methods |

### Prohibited Code Patterns

| Requirement | Status | Notes |
|------------|--------|-------|
| No die() statements | PASS | Not found in codebase |
| No exit() statements | PASS | Not found in codebase |
| No var_dump() statements | PASS | Not found in codebase |
| No print_r() statements | PASS | Not found in codebase |
| No commented-out code | VERIFY | Review all files |
| No TODO/FIXME in production | VERIFY | Search for markers |

### Code Organization

| Requirement | Status | Notes |
|------------|--------|-------|
| One class per file | PASS | Proper file structure |
| Namespace matches directory | PASS | PSR-4 compliant |
| Imports properly ordered | VERIFY | Run PHP-CS-Fixer |
| No unused imports | VERIFY | Static analysis needed |

---

## 2. Shopware-Specific Standards

### Plugin Structure

| Requirement | Status | Notes |
|------------|--------|-------|
| Bootstrap class in correct location | PASS | src/FlizpayForShopware.php |
| composer.json properly configured | PASS | Valid structure |
| Technical name matches folder | PASS | FlizpayForShopware |
| Case-sensitive naming correct | PASS | Matches exactly |

### Services Configuration

| Requirement | Status | Notes |
|------------|--------|-------|
| services.xml valid | PASS | Proper DI configuration |
| All services properly tagged | PASS | Payment handler tagged |
| No circular dependencies | VERIFY | Test with Shopware DI |
| Correct argument injection | PASS | Type-hinted arguments |

### Routes Configuration

| Requirement | Status | Notes |
|------------|--------|-------|
| routes.xml valid | PASS | Imports attribute routes |
| Controller routes properly scoped | PASS | API and Storefront separated |
| No conflicting routes | VERIFY | Test route registration |

### Event Subscribers

| Requirement | Status | Notes |
|------------|--------|-------|
| Properly tagged as subscriber | PASS | kernel.event_subscriber |
| getSubscribedEvents() implemented | PASS | Both subscribers |
| Event names correct | PASS | Uses Shopware events |

---

## 3. JavaScript/TypeScript Standards

### TypeScript Configuration

| Requirement | Status | Notes |
|------------|--------|-------|
| TypeScript used for admin | PASS | .ts files throughout |
| Strict mode enabled | VERIFY | Check tsconfig |
| Proper type definitions | PASS | flizpay.types.ts |
| No any types | VERIFY | Static analysis needed |

### ESLint Compliance

| Requirement | Status | Notes |
|------------|--------|-------|
| ESLint configured | PASS | .eslintrc present |
| No ESLint errors | VERIFY | Run npm run lint |
| Shopware ESLint config used | PASS | @shopware-ag/eslint-config-base |

### Code Organization

| Requirement | Status | Notes |
|------------|--------|-------|
| Module structure correct | PASS | Proper Shopware admin module |
| Components properly registered | PASS | index.ts exports |
| Services properly initialized | PASS | init/api-service.init.ts |

### Build Process

| Requirement | Status | Notes |
|------------|--------|-------|
| Uncompiled source included | PASS | src/ directory present |
| Compiled assets present | PASS | public/administration/ |
| Build artifacts not in git | FAIL | .vite/ directories committed |
| No node_modules in distribution | VERIFY | Check .gitignore |

---

## 4. CSS/SCSS Standards

### Storefront Styles

| Requirement | Status | Notes |
|------------|--------|-------|
| SCSS used (not CSS) | PASS | .scss files |
| No inline CSS in templates | PASS | Separate style files |
| No !important usage | VERIFY | Search stylesheets |
| Variables used appropriately | PASS | variables.scss exists |

### Admin Styles

| Requirement | Status | Notes |
|------------|--------|-------|
| Follows Shopware admin patterns | PASS | Standard components |
| No conflicting global styles | VERIFY | Review styles |

---

## 5. Template Standards

### Twig Templates

| Requirement | Status | Notes |
|------------|--------|-------|
| Extends Shopware base templates | PASS | Proper inheritance |
| Uses block overrides correctly | PASS | sw_block usage |
| No h1-h6 tags (use span.h2) | VERIFY | Check all templates |
| Images have alt attributes | FAIL | Missing alt text |
| Links have title attributes | VERIFY | Check all links |

### Template Organization

| Requirement | Status | Notes |
|------------|--------|-------|
| Correct directory structure | PASS | Resources/views/ |
| Proper naming conventions | PASS | component/payment/ |
| No duplicate templates | PASS | Single template file |

---

## 6. Translations/Snippets

### Admin Snippets

| Requirement | Status | Notes |
|------------|--------|-------|
| en-GB.json present | PASS | Complete translations |
| de-DE.json present | PASS | Complete translations |
| All keys translated | PASS | Matching keys |
| No HTML in snippets | WARN | Some HTML present |
| Placeholders used for variables | PASS | Proper placeholder usage |

### Storefront Snippets

| Requirement | Status | Notes |
|------------|--------|-------|
| English fallback available | PASS | Via cashback helper |
| German translations complete | PASS | Full coverage |

---

## 7. Documentation

### Required Files

| Requirement | Status | Notes |
|------------|--------|-------|
| LICENSE file present | FAIL | Missing - MUST ADD |
| README.md comprehensive | FAIL | Only 362 bytes |
| CHANGELOG.md present | FAIL | Missing - MUST ADD |
| Installation instructions | FAIL | Not in README |

### Code Documentation

| Requirement | Status | Notes |
|------------|--------|-------|
| PHPDoc on all classes | PARTIAL | Some classes missing |
| PHPDoc on public methods | PARTIAL | Needs improvement |
| TSDoc on TypeScript | VERIFY | Check coverage |
| Complex logic documented | PASS | CASHBACK_FEATURE.md |

---

## 8. Security Standards

### Input Validation

| Requirement | Status | Notes |
|------------|--------|-------|
| All inputs sanitized | PASS | Uses Shopware methods |
| SQL injection prevented | WARN | Direct SQL in cleanup |
| XSS prevention | PASS | Twig auto-escaping |
| CSRF protection | PASS | Shopware built-in |

### Authentication/Authorization

| Requirement | Status | Notes |
|------------|--------|-------|
| Admin routes protected | PASS | Requires authentication |
| API routes properly secured | PASS | Uses Shopware auth |
| Webhook signature validation | PASS | HMAC-SHA256 |

### Sensitive Data

| Requirement | Status | Notes |
|------------|--------|-------|
| API keys encrypted | FAIL | Plain text storage |
| No credentials in code | PASS | Config-based |
| Logs sanitized | VERIFY | Check log content |

---

## 9. Error Handling

### Exception Handling

| Requirement | Status | Notes |
|------------|--------|-------|
| Custom exceptions used | PARTIAL | Some generic exceptions |
| Exceptions properly caught | PASS | Try-catch blocks |
| User-friendly error messages | VERIFY | Check error display |

### Logging

| Requirement | Status | Notes |
|------------|--------|-------|
| Uses Shopware logger | PASS | Injected LoggerInterface |
| Appropriate log levels | FAIL | Critical used for debug |
| Log files in /var/log/ | PASS | Standard location |
| Sensitive data not logged | VERIFY | Review log statements |

---

## 10. Performance Standards

### Code Efficiency

| Requirement | Status | Notes |
|------------|--------|-------|
| No N+1 queries | VERIFY | Profile database |
| Efficient loops | PASS | Standard patterns |
| Lazy loading used | PASS | Shopware ORM |

### Resource Usage

| Requirement | Status | Notes |
|------------|--------|-------|
| No memory leaks | VERIFY | Long-running tests |
| Reasonable timeout | WARN | 30s hardcoded |
| Caching implemented | FAIL | No caching layer |

---

## 11. Distribution Requirements

### Files to Include

| Requirement | Status | Notes |
|------------|--------|-------|
| composer.json | PASS | Present and valid |
| src/ directory | PASS | All source files |
| Resources/ directory | PASS | Config, views, assets |
| LICENSE file | FAIL | Missing |
| README.md | PASS | Present (needs expansion) |

### Files to Exclude

| Requirement | Status | Notes |
|------------|--------|-------|
| .git directory | PASS | In .gitignore |
| tests/ directory | N/A | No tests exist |
| .editorconfig | VERIFY | Check inclusion |
| phpstan.neon | N/A | Not present |
| phpunit.xml | N/A | Not present |
| node_modules/ | PASS | In .gitignore |
| package-lock.json | VERIFY | Check distribution |
| composer.lock | VERIFY | Should not include |
| .vite/ directories | FAIL | Currently committed |

---

## Critical Issues Summary

### Must Fix Before Submission

1. **Missing LICENSE file**
   - Action: Add MIT license text file

2. **Build artifacts committed**
   - Location: .vite/ directories
   - Action: Add to .gitignore, remove from repo

3. **Log level misuse**
   - Location: FlizpayCartValidator.php
   - Action: Change critical to debug/info

4. **Missing alt attributes**
   - Location: payment-method.html.twig
   - Action: Add meaningful alt text to images

5. **Plain text API key storage**
   - Location: FlizpayConfigController.php
   - Action: Use encryption service

6. **Inadequate README**
   - Action: Expand with installation and usage instructions

7. **Missing CHANGELOG**
   - Action: Create CHANGELOG.md with version history

### Recommended Improvements

1. Add PHPStan for static analysis
2. Add PHP-CS-Fixer for code formatting
3. Improve PHPDoc coverage
4. Add response caching
5. Remove HTML from translation snippets

---

## Static Analysis Commands

Run these before submission:


Deprecated: Case statements followed by a semicolon (;) are deprecated, use a colon (:) instead in phar:///usr/local/bin/composer/vendor/react/promise/src/functions.php on line 300

Deprecated: Case statements followed by a semicolon (;) are deprecated, use a colon (:) instead in phar:///usr/local/bin/composer/vendor/react/promise/src/functions.php on line 300

---

## File Structure Verification

Expected structure:


---

Last Updated: January 2025
Plugin Version: 1.0.0
