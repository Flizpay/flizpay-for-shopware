# FLIZpay Cashback Feature - Technical Documentation

This document describes how the cashback functionality works in the FLIZpay Shopware 6 plugin.

## Overview

The cashback system allows merchants to offer percentage-based discounts to customers who pay with FLIZpay. Cashback rates are configured in the FLIZpay merchant dashboard and automatically synchronized with the Shopware plugin.

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        FLIZpay Backend                              │
│  - Stores cashback configuration                                    │
│  - Sends webhook updates when rates change                          │
│  - Calculates final payment amount with cashback applied            │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              │ Webhooks + API
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     Shopware 6 Plugin                               │
│                                                                     │
│  ┌─────────────────┐    ┌──────────────────┐    ┌────────────────┐ │
│  │ FlizpayCashback │    │ WebhookService   │    │ Cashback       │ │
│  │ Helper          │◄───│ (receives rates) │    │ Subscriber     │ │
│  │ (reads config)  │    └──────────────────┘    │ (modifies UI)  │ │
│  └────────┬────────┘                            └───────┬────────┘ │
│           │                                             │          │
│           ▼                                             ▼          │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │              SystemConfig (stores cashback data)            │   │
│  └─────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        Storefront                                   │
│  - Payment method shows cashback percentage                         │
│  - Logo and subtitle displayed based on settings                    │
└─────────────────────────────────────────────────────────────────────┘
```

## Components

### 1. FlizpayCashbackHelper (`src/Service/FlizpayCashbackHelper.php`)

Central service for reading and formatting cashback data.

**Key Methods:**

| Method                     | Purpose                                                    |
| -------------------------- | ---------------------------------------------------------- |
| `getCashbackData()`        | Retrieves raw cashback data from SystemConfig              |
| `getDisplayValue()`        | Returns the maximum cashback value (for display)           |
| `getCashbackType()`        | Returns 'both', 'first', or 'standard'                     |
| `isCashbackAvailable()`    | Checks if cashback can be displayed                        |
| `getCashbackTitle()`       | Generates localized title (e.g., "FLIZpay - Up to 5% off") |
| `getCashbackDescription()` | Generates localized description                            |
| `formatForLocale()`        | Formats numbers for locale (5.5 vs 5,5)                    |

**Configuration Keys:**

```php
FlizpayForShopware.config.cashbackData          // JSON string with rates
FlizpayForShopware.config.webhookAlive          // Connection status
FlizpayForShopware.config.webhookKey            // HMAC key
FlizpayForShopware.config.webhookUrl            // Registered URL
FlizpayForShopware.config.displayCashbackInTitle // Show % in title
FlizpayForShopware.config.showLogo              // Show logo badge
FlizpayForShopware.config.showDescriptionInTitle // Show description
FlizpayForShopware.config.showSubtitle          // Show subtitle
```

### 2. PaymentMethodCashbackSubscriber (`src/Subscriber/PaymentMethodCashbackSubscriber.php`)

Event subscriber that modifies the FLIZpay payment method display at checkout.

**Subscribed Events:**

- `CheckoutConfirmPageLoadedEvent` - Triggered when checkout page loads

**Behavior:**

1. Finds FLIZpay payment method in the list
2. Modifies translated name/description based on settings
3. Adds `flizpayCashback` page extension for template use

**Page Extension Data:**

```php
[
    'enabled' => bool,           // Is cashback available?
    'displayValue' => float,     // Max cashback percentage
    'formattedValue' => string,  // Locale-formatted value
    'title' => string,           // Full title with cashback
    'description' => string,     // Cashback description
    'type' => string,            // 'both', 'first', 'standard'
    'locale' => string,          // Current locale
    'showLogo' => bool,          // Show logo setting
    'showDescriptionInTitle' => bool,
    'showSubtitle' => bool,
]
```

### 3. FlizpayWebhookService (`src/Service/FlizpayWebhookService.php`)

Handles incoming webhooks from FLIZpay backend.

**Webhook Types:**

| Webhook          | Payload                                     | Handler                   |
| ---------------- | ------------------------------------------- | ------------------------- |
| Test             | `{ "test": true }`                          | `handleTest()`            |
| Cashback Update  | `{ "updateCashbackInfo": true, ... }`       | `handleCashbackUpdate()`  |
| Payment Complete | `{ "metadata": { "orderId": "..." }, ... }` | `handlePaymentComplete()` |

**Cashback Update Webhook:**

```json
{
  "updateCashbackInfo": true,
  "firstPurchaseAmount": 10.0,
  "amount": 5.0
}
```

**Payment Complete with Cashback:**

```json
{
  "metadata": { "orderId": "abc123" },
  "status": "completed",
  "transactionId": "tx_123",
  "originalAmount": 100.0,
  "amount": 95.0,
  "currency": "EUR"
}
```

### 4. Order Cashback Application

When a payment webhook includes cashback (`originalAmount > amount`), the webhook service:

1. **Calculates discount percentage:**

   ```php
   $discount = $originalAmount - $finalAmount;
   $cashbackPercent = ($discount / $originalAmount) * 100;
   ```

2. **Updates line items proportionally:**

   ```php
   foreach ($lineItems as $lineItem) {
       $itemTotal = $lineItem->getTotalPrice();
       $discountAmount = ($itemTotal * $cashbackPercent) / 100;
       $newTotal = round($itemTotal - $discountAmount, 2);
       // Update line item
   }
   ```

3. **Updates order with final amount and metadata:**
   ```php
   [
       'id' => $order->getId(),
       'amountTotal' => $finalAmount,
       'customFields' => [
           'flizpay_cashback_applied' => $discount,
           'flizpay_cashback_percent' => round($cashbackPercent, 2),
           'flizpay_cashback_currency' => $currency,
           'flizpay_original_amount' => $originalAmount,
       ],
   ]
   ```

## Data Flow

### Initial Configuration

```
1. Admin enters API key
2. Plugin calls FLIZpay API: /business/cashback
3. Response contains active cashback rates
4. Rates stored in SystemConfig as JSON
```

### Real-time Updates

```
1. Merchant changes rates in FLIZpay dashboard
2. FLIZpay sends webhook: POST /flizpay/webhook
   Headers: X-FLIZ-SIGNATURE: <hmac-sha256>
   Body: { "updateCashbackInfo": true, "firstPurchaseAmount": 10, "amount": 5 }
3. Plugin validates signature
4. Plugin updates SystemConfig with new rates
```

### Checkout Display

```
1. Customer visits checkout
2. CheckoutConfirmPageLoadedEvent fires
3. PaymentMethodCashbackSubscriber:
   a. Reads cashback data from SystemConfig
   b. Checks display settings
   c. Modifies payment method title/description
   d. Adds page extension for template
4. Template renders payment method with cashback info
```

### Payment with Cashback

```
1. Customer selects FLIZpay, completes payment
2. FLIZpay calculates cashback on their end
3. Webhook sent with originalAmount and amount (discounted)
4. Plugin calculates difference as cashback
5. Order line items adjusted proportionally
6. Order total updated to match settled amount
7. Custom fields record cashback details
```

## Storefront Template

The payment method template (`payment-method.html.twig`) extends the default Shopware template:

```twig
{% set isFlizpay = payment.handlerIdentifier == 'FLIZpay\\...' %}
{% set flizpayCashback = page.extensions.flizpayCashback %}

{# Show logo badge if enabled #}
{% if isFlizpay and flizpayCashback.showLogo %}
    <img src="{{ asset('bundles/flizpayforshopware/fliz-checkout-logo.svg', 'asset') }}">
{% endif %}

{# Show subtitle when selected #}
{% if isFlizpay and payment.id is same as(selectedPaymentMethodId) %}
    {% if flizpayCashback.showSubtitle and payment.translated.description %}
        <p class="flizpay-description-text">{{ payment.translated.description }}</p>
    {% endif %}
{% endif %}
```

## Admin Settings UI

The admin panel includes:

1. **Live Preview Box** - Shows how payment method will appear
2. **Toggle Settings:**
   - Show Logo (checkbox)
   - Show Description in Title (checkbox)
   - Show Subtitle (checkbox)
3. **Cashback Info Card** - Displays current rates from FLIZpay

Preview updates in real-time as settings are toggled.

## Localization

Cashback text is localized:

| Locale | Title Format                    | Number Format |
| ------ | ------------------------------- | ------------- |
| de-DE  | "FLIZpay - Bis zu 5,5% Rabatt"  | Comma decimal |
| en-GB  | "FLIZpay - Up to 5.5% Cashback" | Dot decimal   |

## Configuration Options

| Setting                  | Default | Description                              |
| ------------------------ | ------- | ---------------------------------------- |
| `showLogo`               | true    | Display FLIZpay logo in checkout         |
| `showDescriptionInTitle` | true    | Append cashback % to payment method name |
| `showSubtitle`           | true    | Show detailed description when selected  |
| `displayCashbackInTitle` | true    | Legacy setting (backward compat)         |

## Security

- All webhooks validated with HMAC-SHA256 signature
- Signature header: `X-FLIZ-SIGNATURE`
- Validation uses `hash_equals()` for timing-safe comparison
- Invalid signatures return 401 Unauthorized

## Troubleshooting

### Cashback not showing

1. Check `webhookAlive` is true in SystemConfig
2. Verify cashback data exists: `FlizpayForShopware.config.cashbackData`
3. Ensure cashback values are > 0
4. Check display settings are enabled

### Order total not updated

1. Check webhook logs for `handlePaymentComplete`
2. Verify `originalAmount` and `amount` are in webhook payload
3. Check for errors in `applyCashbackToOrder`

### Wrong locale formatting

1. Check request locale is detected correctly
2. Verify `formatForLocale()` receives correct locale string
