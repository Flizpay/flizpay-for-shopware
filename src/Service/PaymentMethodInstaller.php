<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Service;

use FLIZpay\FlizpayForShopware\FlizpayForShopware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use FLIZpay\FlizpayForShopware\Handler\FlizpayPaymentHandler;

class PaymentMethodInstaller
{
    /**
     * Shipped as the description until <fliz-checkout> took over the offer text. Cleared on update,
     * but only when the merchant never edited it.
     */
    private const LEGACY_DESCRIPTION = "Kostenlose Zahlungsmethode mit Cashback";

    private EntityRepository $paymentMethodRepository;
    private PluginIdProvider $pluginIdProvider;

    public function __construct(
        EntityRepository $paymentMethodRepository,
        PluginIdProvider $pluginIdProvider,
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->pluginIdProvider = $pluginIdProvider;
    }

    public function install(Context $context): void
    {
        if ($this->getPaymentMethodId($context)) {
            return; // Already exists
        }

        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass(
            FlizpayForShopware::class,
            $context,
        );

        $paymentData = [
            "handlerIdentifier" => FlizpayPaymentHandler::class,
            "name" => "FLIZpay",
            // Kept blank on purpose: <fliz-checkout> renders the offer text at checkout.
            "description" => "",
            "pluginId" => $pluginId,
            "afterOrderEnabled" => true,
            "technicalName" => "flizpay_payment",
        ];

        $this->paymentMethodRepository->create([$paymentData], $context);
    }

    /**
     * Drop the description we used to ship, so Shopware stops printing the old cashback wording
     * underneath the offer text. A description the merchant wrote themselves is left alone.
     */
    public function update(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter("handlerIdentifier", FlizpayPaymentHandler::class),
        );
        $criteria->addAssociation("translations");
        $criteria->setLimit(1);

        $paymentMethod = $this->paymentMethodRepository
            ->search($criteria, $context)
            ->first();

        if (!$paymentMethod) {
            return;
        }

        $translations = [];
        foreach ($paymentMethod->getTranslations() ?? [] as $translation) {
            if ($translation->getDescription() === self::LEGACY_DESCRIPTION) {
                $translations[$translation->getLanguageId()] = [
                    "description" => "",
                ];
            }
        }

        if (!$translations) {
            return;
        }

        $this->paymentMethodRepository->update(
            [
                [
                    "id" => $paymentMethod->getId(),
                    "translations" => $translations,
                ],
            ],
            $context,
        );
    }

    public function setIsActive(bool $active, Context $context): void
    {
        $paymentMethodId = $this->getPaymentMethodId($context);

        if (!$paymentMethodId) {
            return;
        }

        $this->paymentMethodRepository->update(
            [
                [
                    "id" => $paymentMethodId,
                    "active" => $active,
                ],
            ],
            $context,
        );
    }

    private function getPaymentMethodId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter("handlerIdentifier", FlizpayPaymentHandler::class),
        );

        return $this->paymentMethodRepository
            ->searchIds($criteria, $context)
            ->firstId();
    }
}
