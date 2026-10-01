<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Service;

use FLIZpay\FlizpayForShopware\FlizpayForShopware;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * On-site messaging: the storefront only prints <fliz-placement> slots, FLIZpay decides what each one shows.
 *
 * @since 1.1.0
 */
class FlizpayPlacementService
{
    private const CONFIG_PREFIX = "FlizpayForShopware.config.";
    private const RETRY_CACHE_KEY = "flizpay_public_id_retry";
    private const SCRIPT_URL = "https://app.flizpay.de/web-components/v1/flizpay.js";

    /** Placement slot => the merchant setting that switches it on. Slots not listed follow any enabled area. */
    public const SLOT_AREAS = [
        "listing-item" => "placementListing",
        "product-price" => "placementProduct",
        "product-page" => "placementProduct",
        "cart" => "placementCart",
        "mini-cart" => "placementMiniCart",
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
     * Everything the templates need, or null when nothing should render.
     */
    public function getPlacement(SalesChannelContext $context, Request $request): ?array
    {
        $salesChannelId = $context->getSalesChannelId();

        if (
            $this->systemConfig->getString(self::CONFIG_PREFIX . "apiKey", $salesChannelId) === "" ||
            !$this->anyAreaEnabled($salesChannelId)
        ) {
            return null;
        }

        $publicId = $this->ensurePublicId($salesChannelId, true);
        if ($publicId === "") {
            return null;
        }

        $slots = [];
        foreach (self::SLOTS as $slot) {
            $area = self::SLOT_AREAS[$slot] ?? null;
            $slots[$slot] = $area === null || $this->areaEnabled($area, $salesChannelId);
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

    /** FLIZPAY_PLACEMENT_SCRIPT_URL points at a staging build. */
    public function getScriptUrl(): string
    {
        return getenv("FLIZPAY_PLACEMENT_SCRIPT_URL") ?: self::SCRIPT_URL;
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

    /** Areas default to on, like their settings fields. */
    private function areaEnabled(string $area, ?string $salesChannelId): bool
    {
        $value = $this->systemConfig->get(self::CONFIG_PREFIX . $area, $salesChannelId);

        return $value === null || (bool) $value;
    }

    private function anyAreaEnabled(?string $salesChannelId): bool
    {
        foreach (array_unique(self::SLOT_AREAS) as $area) {
            if ($this->areaEnabled($area, $salesChannelId)) {
                return true;
            }
        }

        return false;
    }
}
