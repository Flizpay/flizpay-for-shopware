<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use FLIZpay\FlizpayForShopware\Handler\FlizpayPaymentHandler;

class PaymentMethodInstaller
{
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
            \FLIZpay\FlizpayForShopware\FlizpayForShopware::class,
            $context,
        );

        $paymentData = [
            "handlerIdentifier" => FlizpayPaymentHandler::class,
            "name" => "FLIZpay",
            "description" => "Kostenlose Zahlungsmethode mit Cashback",
            "pluginId" => $pluginId,
            "afterOrderEnabled" => true,
            "technicalName" => "flizpay_payment",
        ];

        $this->paymentMethodRepository->create([$paymentData], $context);
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
