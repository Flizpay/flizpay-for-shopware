<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class FlizpayCashbackHelper
{
    private const CONFIG_PREFIX = "FlizpayForShopware.config.";

    private SystemConfigService $systemConfig;

    public function __construct(SystemConfigService $systemConfig)
    {
        $this->systemConfig = $systemConfig;
    }

    /**
     * Get cashback data from config
     *
     * @param string|null $salesChannelId
     * @return array|null Returns ['first_purchase_amount' => float, 'standard_amount' => float] or null
     */
    public function getCashbackData(?string $salesChannelId = null): ?array
    {
        $cashbackJson = $this->systemConfig->getString(
            self::CONFIG_PREFIX . "cashbackData",
            $salesChannelId,
        );

        if (empty($cashbackJson)) {
            return null;
        }

        $cashback = json_decode($cashbackJson, true);

        if (!is_array($cashback)) {
            return null;
        }

        // Validate structure
        if (
            !isset($cashback["first_purchase_amount"]) &&
            !isset($cashback["standard_amount"])
        ) {
            return null;
        }

        return $cashback;
    }

    /**
     * Get the display value (max of first purchase or standard amount)
     *
     * @param string|null $salesChannelId
     * @return float|null
     */
    public function getDisplayValue(?string $salesChannelId = null): ?float
    {
        $cashback = $this->getCashbackData($salesChannelId);

        if (!$cashback) {
            return null;
        }

        $firstPurchase = (float) ($cashback["first_purchase_amount"] ?? 0);
        $standard = (float) ($cashback["standard_amount"] ?? 0);

        if ($firstPurchase <= 0 && $standard <= 0) {
            return null;
        }

        return max($firstPurchase, $standard);
    }

    /**
     * Get cashback type: 'both', 'first', or 'standard'
     *
     * @param string|null $salesChannelId
     * @return string|null
     */
    public function getCashbackType(?string $salesChannelId = null): ?string
    {
        $cashback = $this->getCashbackData($salesChannelId);

        if (!$cashback) {
            return null;
        }

        $firstPurchase = (float) ($cashback["first_purchase_amount"] ?? 0);
        $standard = (float) ($cashback["standard_amount"] ?? 0);

        if ($firstPurchase > 0 && $standard > 0) {
            return "both";
        } elseif ($firstPurchase > 0) {
            return "first";
        } elseif ($standard > 0) {
            return "standard";
        }

        return null;
    }

    /**
     * Check if cashback is available and can be displayed
     * Matches WooCommerce validation rules
     *
     * @param string|null $salesChannelId
     * @return bool
     */
    public function isCashbackAvailable(?string $salesChannelId = null): bool
    {
        // Check webhook is alive
        $webhookAlive = $this->systemConfig->getBool(
            self::CONFIG_PREFIX . "webhookAlive",
            $salesChannelId,
        );

        if (!$webhookAlive) {
            return false;
        }

        // Check webhook key is set
        $webhookKey = $this->systemConfig->getString(
            self::CONFIG_PREFIX . "webhookKey",
            $salesChannelId,
        );

        if (empty($webhookKey)) {
            return false;
        }

        // Check webhook URL is set
        $webhookUrl = $this->systemConfig->getString(
            self::CONFIG_PREFIX . "webhookUrl",
            $salesChannelId,
        );

        if (empty($webhookUrl)) {
            return false;
        }

        // Check cashback data exists and has values > 0
        $cashback = $this->getCashbackData($salesChannelId);

        if (!$cashback) {
            return false;
        }

        $firstPurchase = (float) ($cashback["first_purchase_amount"] ?? 0);
        $standard = (float) ($cashback["standard_amount"] ?? 0);

        return $firstPurchase > 0 || $standard > 0;
    }

    /**
     * Format cashback value for locale (German uses comma)
     *
     * @param float $value
     * @param string $locale
     * @return string
     */
    public function formatForLocale(float $value, string $locale): string
    {
        $formatted = number_format($value, 1, ".", "");

        // Remove trailing .0 if whole number
        if (str_ends_with($formatted, ".0")) {
            $formatted = (string) (int) $value;
        }

        // German locale uses comma
        if (str_contains(strtolower($locale), "de")) {
            $formatted = str_replace(".", ",", $formatted);
        }

        return $formatted;
    }

    /**
     * Get cashback title for display
     *
     * @param string $locale
     * @param string|null $salesChannelId
     * @return string
     */
    public function getCashbackTitle(
        string $locale,
        ?string $salesChannelId = null,
    ): string {
        $displayValue = $this->getDisplayValue($salesChannelId);

        if ($displayValue === null) {
            // No cashback - return default title
            if (str_contains(strtolower($locale), "de")) {
                return "FLIZpay";
            }
            return "FLIZpay";
        }

        $formattedValue = $this->formatForLocale($displayValue, $locale);

        // German title
        if (str_contains(strtolower($locale), "de")) {
            return "FLIZpay - Bis zu {$formattedValue}% Rabatt";
        }

        // English title
        return "FLIZpay - Up to {$formattedValue}% Cashback";
    }

    /**
     * Get cashback description for display
     *
     * @param string $shopName
     * @param string $locale
     * @param string|null $salesChannelId
     * @return string
     */
    public function getCashbackDescription(
        string $shopName,
        string $locale,
        ?string $salesChannelId = null,
    ): string {
        $isGerman = str_contains(strtolower($locale), "de");

        if (!$this->isCashbackAvailable($salesChannelId)) {
            return $this->getDefaultDescription($isGerman);
        }

        $cashback = $this->getCashbackData($salesChannelId);
        $type = $this->getCashbackType($salesChannelId);

        if (!$cashback || !$type) {
            return $this->getDefaultDescription($isGerman);
        }

        $standardAmount = $this->formatForLocale(
            (float) ($cashback["standard_amount"] ?? 0),
            $locale,
        );

        switch ($type) {
            case "both":
                if ($isGerman) {
                    return "Sichere Zahlungen in direkter Zusammenarbeit mit deiner Bank. " .
                        "Ab der zweiten Zahlung bei {$shopName} gibt es dauerhaft {$standardAmount}% Rabatt.";
                }
                return "Secure payments in direct collaboration with your bank. " .
                    "After your first FLIZ payment at {$shopName}, you will continue to receive {$standardAmount}% Discount.";

            case "first":
                if ($isGerman) {
                    return "Sichere Zahlungen in direkter Zusammenarbeit mit deiner Bank. " .
                        "Nach deiner ersten FLIZ-Zahlung bei {$shopName} gibt es keinen weiteren Rabatt.";
                }
                return "Secure payments in direct collaboration with your bank. " .
                    "No additional discount after your first FLIZ-payment at {$shopName}.";

            case "standard":
                if ($isGerman) {
                    return "Sichere Zahlungen in direkter Zusammenarbeit mit deiner Bank. " .
                        "{$standardAmount}% Rabatt für jede FLIZ-Zahlung bei {$shopName}.";
                }
                return "Secure payments in direct collaboration with your bank. " .
                    "{$standardAmount}% Discount for every FLIZ-payment at {$shopName}.";
        }

        return $this->getDefaultDescription($isGerman);
    }

    private function getDefaultDescription(bool $isGerman): string
    {
        return $isGerman
            ? "Sichere Zahlungen in direkter Zusammenarbeit mit deiner Bank, deine Daten bleiben privat und in Deutschland, und du unterstützt mit FLIZpay kleine Unternehmen."
            : "Secure payments in direct collaboration with your bank. We support small businesses and keep your data private – stored securely in Germany.";
    }

    /**
     * Check if display cashback in title is enabled
     *
     * @param string|null $salesChannelId
     * @return bool
     */
    public function isDisplayCashbackEnabled(
        ?string $salesChannelId = null,
    ): bool {
        $value = $this->systemConfig->get(
            self::CONFIG_PREFIX . "displayCashbackInTitle",
            $salesChannelId,
        );

        // Default to true if not set
        if ($value === null) {
            return true;
        }

        return (bool) $value;
    }

    /**
     * Check if show logo in checkout is enabled
     *
     * @param string|null $salesChannelId
     * @return bool
     */
    public function isShowLogoEnabled(?string $salesChannelId = null): bool
    {
        $value = $this->systemConfig->get(
            self::CONFIG_PREFIX . "showLogo",
            $salesChannelId,
        );

        // Default to true if not set
        if ($value === null) {
            return true;
        }

        return (bool) $value;
    }

    /**
     * Check if show subtitle is enabled
     *
     * @param string|null $salesChannelId
     * @return bool
     */
    public function isShowSubtitleEnabled(?string $salesChannelId = null): bool
    {
        $value = $this->systemConfig->get(
            self::CONFIG_PREFIX . "showSubtitle",
            $salesChannelId,
        );

        // Default to true if not set
        if ($value === null) {
            return true;
        }

        return (bool) $value;
    }
}
