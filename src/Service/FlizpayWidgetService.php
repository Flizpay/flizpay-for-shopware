<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Service;

use FLIZpay\FlizpayForShopware\FlizpayForShopware;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * One hosted bundle registers both <fliz-widget> and <fliz-checkout>. The storefront only prints the
 * elements and what it already knows; FLIZpay decides what each one shows.
 *
 * @since 1.1.0
 */
class FlizpayWidgetService
{
    private const CONFIG_PREFIX = "FlizpayForShopware.config.";
    private const RETRY_CACHE_KEY = "flizpay_public_id_retry";
    private const SCRIPT_URL = "https://app.flizpay.de/web-components/v1/flizpay.js";

    /** Widget slot => the merchant setting that switches it on. Slots not listed follow any enabled area. */
    public const SLOT_AREAS = [
        "listing-item" => "widgetListing",
        "product-price" => "widgetProduct",
        "product-page" => "widgetProduct",
        "cart" => "widgetCart",
        "mini-cart" => "widgetMiniCart",
    ];

    public const SLOTS = [
        "listing-item",
        "product-price",
        "product-page",
        "cart",
        "mini-cart",
        "checkout",
        "order-received",
    ];

    public function __construct(
        private readonly SystemConfigService $systemConfig,
        private readonly FlizpayApiService $apiService,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Everything the on-site messaging templates need, or null when nothing should render.
     *
     * Areas being switched off no longer stops this: the same script powers the checkout text.
     */
    public function getWidget(SalesChannelContext $context, Request $request): ?array
    {
        $salesChannelId = $context->getSalesChannelId();
        $publicId = $this->resolvePublicId($salesChannelId);

        if ($publicId === null) {
            return null;
        }

        $slots = [];
        foreach (self::SLOTS as $slot) {
            $area = self::SLOT_AREAS[$slot] ?? null;
            $slots[$slot] = $area === null || $this->settingEnabled($area, $salesChannelId);
        }

        $currency = $context->getCurrency();

        return [
            "scriptUrl" => $this->getScriptUrl(),
            "publicId" => $publicId,
            "locale" => $request->getLocale(),
            "currency" => $currency->getIsoCode(),
            "decimals" => $currency->getItemRounding()->getDecimals(),
            "platform" => "shopware",
            "pluginVersion" => FlizpayForShopware::getVersion(),
            "slots" => $slots,
        ];
    }

    /**
     * Everything the checkout templates need. The display toggles are always present; an empty
     * `publicId` means the element cannot render and the templates keep the plain label.
     *
     * Carries no credentials and no locally stored discount rules; <fliz-checkout> owns the offer text.
     *
     * @param float|null $total Gross total of the cart or order being paid, in the sales channel currency.
     */
    public function getCheckout(SalesChannelContext $context, Request $request, ?float $total): array
    {
        $salesChannelId = $context->getSalesChannelId();

        return [
            "publicId" => $this->resolvePublicId($salesChannelId) ?? "",
            "locale" => $request->getLocale(),
            "amount" => $this->toMinorUnits($total, $context->getCurrency()->getIsoCode()),
            "showHeadline" => $this->settingEnabled("showHeadline", $salesChannelId),
            // Still stored as `showSubtitle`, so merchants who switched it off keep that choice.
            "showDescription" => $this->settingEnabled("showSubtitle", $salesChannelId),
            "showLogo" => $this->settingEnabled("showLogo", $salesChannelId),
        ];
    }

    /** FLIZPAY_WIDGET_SCRIPT_URL points at a staging build. */
    public function getScriptUrl(): string
    {
        return getenv("FLIZPAY_WIDGET_SCRIPT_URL") ?: self::SCRIPT_URL;
    }

    /**
     * Fetch and store the public id when missing. Throttled callers back off for an hour after a failure.
     */
    public function ensurePublicId(?string $salesChannelId, bool $throttled = false): string
    {
        $publicId = $this->systemConfig->getString(self::CONFIG_PREFIX . "publicId", $salesChannelId);
        if ($publicId !== "") {
            return $publicId;
        }

        $retry = $this->cache->getItem(self::RETRY_CACHE_KEY);
        if ($throttled && $retry->isHit()) {
            return "";
        }

        try {
            $publicId = $this->apiService->fetch_public_id() ?? "";
        } catch (\Throwable) {
            $publicId = "";
        }

        if ($publicId === "") {
            $this->cache->save($retry->set(true)->expiresAfter(3600));
            return "";
        }

        $this->systemConfig->set(self::CONFIG_PREFIX . "publicId", $publicId, $salesChannelId);

        return $publicId;
    }

    /** The public id both components need, or null when this sales channel has nothing to show. */
    private function resolvePublicId(?string $salesChannelId): ?string
    {
        if ($this->systemConfig->getString(self::CONFIG_PREFIX . "apiKey", $salesChannelId) === "") {
            return null;
        }

        $publicId = $this->ensurePublicId($salesChannelId, true);

        return $publicId === "" ? null : $publicId;
    }

    /**
     * <fliz-checkout> takes integer cents and formats them as euros, so other currencies send no
     * amount at all and get the offer text without a sample saving.
     */
    private function toMinorUnits(?float $total, string $currencyIso): ?int
    {
        if ($total === null || $currencyIso !== "EUR" || !is_finite($total)) {
            return null;
        }

        $cents = round($total * 100);

        return $cents >= 0 && $cents <= 9007199254740991 ? (int) $cents : null;
    }

    /** Display settings default to on, like their settings fields. */
    private function settingEnabled(string $setting, ?string $salesChannelId): bool
    {
        $value = $this->systemConfig->get(self::CONFIG_PREFIX . $setting, $salesChannelId);

        return $value === null || (bool) $value;
    }
}
