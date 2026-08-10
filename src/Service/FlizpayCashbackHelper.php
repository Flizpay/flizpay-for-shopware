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
        $cashback = $this->getCashbackData($salesChannelId);
        $type = $this->getCashbackType($salesChannelId);

        if (!$cashback || !$type) {
            return "FLIZpay";
        }

        $isGerman = str_contains(strtolower($locale), "de");
        $firstPurchaseAmount = $this->formatForLocale(
            (float) ($cashback["first_purchase_amount"] ?? 0),
            $locale,
        );

        if ($type === "first") {
            return $isGerman
                ? "FLIZpay – Spare {$firstPurchaseAmount}% bei deiner ersten Zahlung"
                : "FLIZpay – Save {$firstPurchaseAmount}% on your first payment";
        }

        if ($type === "both") {
            return $isGerman
                ? "FLIZpay – Spare bis zu {$firstPurchaseAmount}%"
                : "FLIZpay – Save up to {$firstPurchaseAmount}%";
        }

        $displayValue = $this->getDisplayValue($salesChannelId);
        $formattedValue = $this->formatForLocale(
            $displayValue ?? 0,
            $locale,
        );

        return $isGerman
            ? "FLIZpay - Bis zu {$formattedValue}% Rabatt"
            : "FLIZpay - Up to {$formattedValue}% Cashback";
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
                $firstPurchaseAmount = $this->formatForLocale(
                    (float) ($cashback["first_purchase_amount"] ?? 0),
                    $locale,
                );

                if ($isGerman) {
                    return "Erhalte {$firstPurchaseAmount}% Rabatt auf deine erste Zahlung, " .
                        "danach {$standardAmount}% auf jede weitere Zahlung bei {$shopName}.";
                }
                return "Get {$firstPurchaseAmount}% discount on your first payment, " .
                    "then {$standardAmount}% on every payment after that {$shopName}.";

            case "first":
                $firstPurchaseAmount = $this->formatForLocale(
                    (float) ($cashback["first_purchase_amount"] ?? 0),
                    $locale,
                );

                if ($isGerman) {
                    return "Erhalte {$firstPurchaseAmount}% Rabatt auf deine erste Zahlung bei {$shopName}.";
                }
                return "Get {$firstPurchaseAmount}% discount on your first payment at {$shopName}.";

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
            ? "Bezahle mit FLIZ. Schluss mit versteckten Kosten. Die europäische Lösung."
            : "Pay with FLIZ. Stop carrying the hidden cost of payments. The European solution.";
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
