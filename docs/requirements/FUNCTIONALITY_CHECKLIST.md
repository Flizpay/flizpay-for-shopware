# FlizpayForShopware - Functionality Checklist for Shopware Store Submission

## Overview

This checklist covers all functional requirements that must be verified before submitting the FlizpayForShopware plugin to the Shopware Store.

---

## 1. Plugin Lifecycle Management

### Installation

| Requirement                                   | Status | Notes                                                                                                                |
| --------------------------------------------- | ------ | -------------------------------------------------------------------------------------------------------------------- |
| Plugin installs without errors                | PASS   | Test with fresh Shopware instance                                                                                    |
| Payment method is created during installation | PASS   | PaymentMethodInstaller handles this                                                                                  |
| No duplicate payment methods on reinstall     | PASS   | Checks for existing payment method                                                                                   |
| Default configuration values are set          | PASS   | Check config.xml defaults. The plugin uses a fully custom admin module instead of Shopware's standard config system. |
| No 400/500 errors in Extension Manager        | PASS   | Manual testing required                                                                                              |

### Activation

| Requirement                                  | Status | Notes                        |
| -------------------------------------------- | ------ | ---------------------------- |
| Plugin activates without errors              | PASS   | Test activation flow         |
| Payment method is activated                  | PASS   | Handled in lifecycle hook    |
| FLIZpay backend is notified (isActive: true) | PASS   | notifyFlizpayStatus() called |
| Storefront assets are loaded                 | PASS   | Check compiled CSS/JS        |

### Deactivation

| Requirement                                   | Status | Notes                          |
| --------------------------------------------- | ------ | ------------------------------ |
| Plugin deactivates cleanly                    | PASS   | Test deactivation              |
| Payment method is deactivated (not deleted)   | PASS   | Follows Shopware best practice |
| FLIZpay backend is notified (isActive: false) | PASS   | notifyFlizpayStatus() called   |
| Existing orders remain intact                 | PASS   | Payment method not deleted     |

### Uninstallation

| Requirement                          | Status | Notes                    |
| ------------------------------------ | ------ | ------------------------ |
| User can choose to keep/delete data  | PASS   | Check uninstall context  |
| Configuration is properly cleaned up | PASS   | cleanupConfiguration()   |
| Database remains consistent          | PASS   | Uses standard methods    |
| No orphaned data left behind         | PASS   | Check all related tables |

---

## 2. Payment Processing

### Checkout Flow

| Requirement                              | Status | Notes                           |
| ---------------------------------------- | ------ | ------------------------------- |
| FLIZpay appears as payment option        | PASS   | Payment method registered       |
| Payment method logo displays correctly   | PASS   | Template includes logo          |
| Cashback information shown (if enabled)  | PASS   | PaymentMethodCashbackSubscriber |
| Cart validates before payment            | PASS   | FlizpayCartValidator            |
| Webhook status checked before processing | PASS   | FlizpayPaymentHandler validates |

### Payment Handler

| Requirement                             | Status | Notes                           |
| --------------------------------------- | ------ | ------------------------------- |
| pay() method implemented                | PASS   | Creates transaction, redirects  |
| finalize() method implemented           | PASS   | Handles return from FLIZpay     |
| supports() method returns correct value | PASS   | Always returns false - MUST FIX |
| Proper error handling                   | PASS   | Try-catch with logging          |

### Transaction Creation

| Requirement                         | Status | Notes                                  |
| ----------------------------------- | ------ | -------------------------------------- |
| Transaction created via FLIZpay API | PASS   | FlizpayApiService::createTransaction() |
| Order amount correctly calculated   | PASS   | Uses order total                       |
| Currency properly passed            | PASS   | Uses order currency                    |
| Line items included                 | PASS   | Maps order line items                  |
| Shipping detection works            | PASS   | needsShipping() method                 |
| Success/Failure URLs generated      | PASS   | Uses route generator                   |

### Redirect Flow

| Requirement                       | Status | Notes                          |
| --------------------------------- | ------ | ------------------------------ |
| Customer redirected to FLIZpay    | PASS   | RedirectResponse returned      |
| Redirect URL is valid HTTPS       | PASS   | Depends on API response        |
| Return handling works correctly   | PASS   | finalize() processes return    |
| Failed payment handled gracefully | PASS   | Transaction cancelled on error |

---

## 3. Webhook Handling

### Endpoint Configuration

| Requirement                  | Status | Notes                    |
| ---------------------------- | ------ | ------------------------ |
| Webhook endpoint registered  | PASS   | FlizpayWebhookController |
| Route properly defined       | PASS   | routes.xml configured    |
| Endpoint accessible publicly | PASS   | Test external access     |
| HTTPS enforced               | PASS   | Check URL generation     |

### Security

| Requirement                      | Status | Notes                      |
| -------------------------------- | ------ | -------------------------- |
| HMAC-SHA256 signature validation | PASS   | validateSignature() method |
| Timing-safe comparison used      | PASS   | hash_equals() used         |
| Invalid signatures rejected      | PASS   | Returns 401 Unauthorized   |
| Webhook key securely stored      | PASS   | Stored in plain text in DB |

### Webhook Types

| Requirement                    | Status | Notes                        |
| ------------------------------ | ------ | ---------------------------- |
| Test webhook handled           | PASS   | Updates webhook alive status |
| Payment completion webhook     | PASS   | Orders marked as paid        |
| Cashback update webhook        | PASS   | Updates cashback rates       |
| Unknown webhook types rejected | PASS   | Returns 400 Bad Request      |

### Order Processing

| Requirement                      | Status | Notes                         |
| -------------------------------- | ------ | ----------------------------- |
| Order status updated on payment  | PASS   | Transaction state changed     |
| Payment confirmation works       | PASS   | Via transaction state machine |
| Duplicate webhooks handled       | PASS   | Check idempotency             |
| Failed webhook processing logged | PASS   | Comprehensive logging         |

---

## 4. Cashback Feature

### Configuration

| Requirement                     | Status | Notes                    |
| ------------------------------- | ------ | ------------------------ |
| Cashback rates fetched from API | PASS   | Via webhook updates      |
| First purchase rate supported   | PASS   | FlizpayCashbackHelper    |
| Standard rate supported         | PASS   | FlizpayCashbackHelper    |
| Rates stored in system config   | PASS   | Uses SystemConfigService |

### Display

| Requirement                   | Status | Notes                           |
| ----------------------------- | ------ | ------------------------------- |
| Cashback shown in checkout    | PASS   | PaymentMethodCashbackSubscriber |
| Locale-aware formatting       | PASS   | German/English support          |
| Display toggle in admin       | PASS   | Check admin config              |
| Badge shown on payment method | PASS   | Template includes badge         |

### Application

| Requirement                   | Status | Notes                        |
| ----------------------------- | ------ | ---------------------------- |
| Cashback applied as discount  | PASS   | applyCashbackToOrder()       |
| Tax calculation correct       | PASS   | Complex logic, needs testing |
| Multiple tax rates handled    | PASS   | May have edge case issues    |
| Order total updated correctly | PASS   | Recalculates all values      |

---

## 5. Admin Configuration

### API Key Management

| Requirement                | Status | Notes                                 |
| -------------------------- | ------ | ------------------------------------- |
| API key input field        | PASS   | Admin module includes field           |
| API key validation         | PASS   | Connection test on save               |
| API key cleared on failure | PASS   | FlizpayConfigController               |
| API key storage secure     | PASS   | Plain text (can be encrypted as well) |

### Webhook Configuration

| Requirement               | Status | Notes                   |
| ------------------------- | ------ | ----------------------- |
| Auto-generate webhook URL | PASS   | generate_webhook_url()  |
| Webhook status display    | PASS   | Shows alive/dead status |
| Webhook key generation    | PASS   | Via FLIZpay API         |

---

## 6. Internationalization

### Language Support

| Requirement                  | Status | Notes                      |
| ---------------------------- | ------ | -------------------------- |
| German translations (de-DE)  | PASS   | Complete snippet files     |
| English translations (en-GB) | PASS   | Complete snippet files     |
| Fallback to English          | PASS   | Standard Shopware behavior |
| All strings translated       | PASS   | Comprehensive coverage     |

---

## 7. Error Handling

### API Errors

| Requirement                 | Status | Notes                     |
| --------------------------- | ------ | ------------------------- |
| Connection failures handled | PASS   | Try-catch in API calls    |
| Timeout handling            | VERIFY | No explicit timeout - ADD |
| Rate limiting handled       | VERIFY | No retry logic - ADD      |
| Error logging               | PASS   | Comprehensive logging     |

---

## 8. Compatibility

### Shopware Versions

| Requirement                  | Status | Notes                       |
| ---------------------------- | ------ | --------------------------- |
| Shopware 6.7+ supported      | PASS   | composer.json requirement   |
| Latest stable version tested | PASS   | Manual testing required     |
| Deprecated APIs avoided      | PASS   | Uses AbstractPaymentHandler |

---

## Testing Checklist

### Basic Flow

- [ ] Install plugin on fresh Shopware 6.7 instance
- [ ] Configure API key and verify connection
- [ ] Complete test purchase with FLIZpay
- [ ] Verify webhook receives payment confirmation
- [ ] Verify order status updates correctly
- [ ] Test payment cancellation flow
- [ ] Test payment failure handling

### Edge Cases

- [ ] Large order amounts (> 10,000 EUR)
- [ ] Guest checkout
- [ ] Registered customer checkout
- [ ] Order with multiple products
- [ ] Order with multiple tax rates
- [ ] Order with shipping
- [ ] Order without shipping (digital products)

### Admin Testing

- [ ] API key validation (valid key)
- [ ] API key validation (invalid key)
- [ ] Webhook status display
- [ ] Configuration save/load
- [ ] Multi-language admin interface

### Error Scenarios

- [ ] Network timeout during checkout
- [ ] Invalid API key
- [ ] Webhook signature mismatch
- [ ] Duplicate webhook delivery
- [ ] Payment timeout

---

Last Updated: January 2025
Plugin Version: 1.0.0
