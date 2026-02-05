<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Subscriber;

use FLIZpay\FlizpayForShopware\Handler\FlizpayPaymentHandler;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaymentMethodFilterSubscriber implements EventSubscriberInterface
{
    private const FLIZPAY_HANDLER = FlizpayPaymentHandler::class;

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Priority 200: run BEFORE PaymentMethodCashbackSubscriber (default 0)
            CheckoutConfirmPageLoadedEvent::class => ['onCheckoutConfirmLoaded', 200],
        ];
    }

    public function onCheckoutConfirmLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();

        if ($this->isFlizpayConfigured($salesChannelId)) {
            return;
        }

        $this->removeFlizpayFromPaymentMethods($event);
    }

    private function isFlizpayConfigured(?string $salesChannelId): bool
    {
        $webhookAlive = $this->systemConfigService->getBool(
            'FlizpayForShopware.config.webhookAlive',
            $salesChannelId
        );
        $apiKey = $this->systemConfigService->getString(
            'FlizpayForShopware.config.apiKey',
            $salesChannelId
        );

        return $webhookAlive && !empty($apiKey);
    }

    private function removeFlizpayFromPaymentMethods(CheckoutConfirmPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $paymentMethods = $page->getPaymentMethods();

        $filtered = $paymentMethods->filter(
            fn($method) => $method->getHandlerIdentifier() !== self::FLIZPAY_HANDLER
        );

        $this->logger->info('FLIZpay filtered from payment methods: not configured');

        $page->setPaymentMethods($filtered);
    }
}
