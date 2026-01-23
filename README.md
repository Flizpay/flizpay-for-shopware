# FLIZpay Payment Gateway for Shopware 6

Official FLIZpay payment plugin for Shopware 6.7+. Accept payments via FLIZpay and offer cashback rewards to your customers.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Webhook Setup](#webhook-setup)
- [Cashback Feature](#cashback-feature)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Support](#support)
- [License](#license)

## Features

- Seamless FLIZpay payment integration
- Automatic webhook handling for payment confirmation
- Configurable cashback display in checkout
- Support for multiple sales channels
- German and English translations included

## Requirements

- Shopware 6.7 or higher
- PHP 8.2 or higher
- Active FLIZpay merchant account
- SSL certificate (HTTPS required for webhooks)

## Installation

### Via Composer (Recommended)

```bash
composer require flizpay/flizpay-payment
bin/console plugin:refresh
bin/console plugin:install --activate FlizpayForShopware
bin/console cache:clear
```

### Manual Installation

1. Download the plugin package
2. Extract to `custom/plugins/FlizpayForShopware`
3. Run the following commands:

```bash
bin/console plugin:refresh
bin/console plugin:install --activate FlizpayForShopware
bin/console cache:clear
```

## Configuration

### Step 1: Access Plugin Settings

1. Log in to your Shopware Administration panel
2. Navigate to **Settings > Extensions > FLIZpay Payment**

### Step 2: Enter API Credentials

1. Enter your **API Key** from your FLIZpay merchant dashboard
2. The plugin will automatically validate your credentials

### Step 3: Configure Webhook

1. Copy the **Webhook URL** displayed in the settings
2. Add this URL to your FLIZpay merchant dashboard
3. Click **Test Connection** to verify the webhook is working
4. The status indicator will turn green when configured correctly

### Step 4: Enable Payment Method

1. Navigate to **Settings > Shop > Payment Methods**
2. Find "FLIZpay" in the list
3. Enable the payment method for your desired sales channels

## Webhook Setup

The webhook is essential for payment confirmation. FLIZpay sends payment status updates to your shop via webhook.

**Webhook URL format:**

```
https://your-shop.com/api/flizpay/webhook
```

**Requirements:**

- Your shop must be accessible via HTTPS
- The webhook endpoint must be reachable from the internet
- Firewall must allow incoming POST requests from FLIZpay servers

## Cashback Feature

FLIZpay offers cashback rewards to customers. The plugin can display cashback information during checkout.

### Cashback Display Options

Configure in plugin settings:

- **Show Cashback in Checkout**: Display estimated cashback amount
- **Cashback Display Position**: Choose where to show cashback info
- **Custom Cashback Text**: Personalize the cashback message

### How It Works

1. Plugin fetches cashback rates from FLIZpay API
2. Cashback amount is calculated based on order total
3. Information is displayed to customer during checkout
4. Actual cashback is credited after successful payment

## Testing

### Test Checklist

- [ ] Plugin installs without errors
- [ ] API connection test passes
- [ ] Webhook test returns success
- [ ] Payment method appears in checkout
- [ ] Test payment completes successfully
- [ ] Order status updates correctly
- [ ] Webhook receives payment confirmation

## Troubleshooting

### Payment Method Not Visible

1. Verify the payment method is enabled in **Settings > Payment Methods**
2. Check that it's assigned to the correct sales channel
3. Ensure API key is configured and webhook is active
4. Clear the shop cache

### Webhook Not Working

1. Verify the webhook URL is correctly configured in FLIZpay dashboard
2. Check that your server allows incoming POST requests
3. Ensure SSL certificate is valid
4. Check server logs for webhook errors

### Payment Failed

1. Check the order details in Shopware Administration
2. Review logs in `var/log/` for error details
3. Verify customer was redirected back from FLIZpay
4. Contact FLIZpay support with transaction ID

### API Connection Issues

1. Verify API key is correct
2. Check network connectivity to FLIZpay servers
3. Ensure no firewall is blocking outgoing requests
4. Try the connection test in plugin settings

## Logs

Plugin logs are written to Shopware's standard log location:

```
var/log/
```

Look for entries containing "FLIZpay" or "flizpay" for plugin-specific logs.

## Support

- **FLIZpay Support**: support@flizpay.de
- **Documentation**: https://www.docs.flizpay.de/docs/intro
- **Merchant Dashboard**: https://app.flizpay.de

## License

This plugin is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and updates.
