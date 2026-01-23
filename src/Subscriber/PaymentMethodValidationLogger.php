<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\Event\PaymentMethodRouteResponseEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaymentMethodValidationLogger implements EventSubscriberInterface
{
    private const FLIZPAY_HANDLER = "FLIZpay\\FlizpayForShopware\\Handler\\FlizpayPaymentHandler";

    public function __construct(private readonly LoggerInterface $logger) {}

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentMethodRouteResponseEvent::class => "onPaymentMethodsLoaded",
            CheckoutConfirmPageLoadedEvent::class =>
                "onCheckoutConfirmPageLoaded",
            "payment_method.loaded" => "onPaymentMethodEntityLoaded",
        ];
    }

    public function onCheckoutConfirmPageLoaded(
        CheckoutConfirmPageLoadedEvent $event,
    ): void {
        $page = $event->getPage();
        $context = $event->getSalesChannelContext();
        $paymentMethods = $page->getPaymentMethods();

        $flizpayInList = false;
        foreach ($paymentMethods as $method) {
            if ($method->getHandlerIdentifier() === self::FLIZPAY_HANDLER) {
                $flizpayInList = true;
                break;
            }
        }

        $this->logger->debug("Checkout confirm page loaded", [
            "total_payment_methods" => $paymentMethods->count(),
            "flizpay_in_list" => $flizpayInList,
            "current_payment_method" => $context
                ->getPaymentMethod()
                ->getHandlerIdentifier(),
            "cart_errors" => $page->getCart()->getErrors()->count(),
            "all_methods" => array_map(
                fn($m) => $m->getHandlerIdentifier(),
                $paymentMethods->getElements(),
            ),
        ]);
    }

    public function onPaymentMethodsLoaded(
        PaymentMethodRouteResponseEvent $event,
    ): void {
        $response = $event->getResponse();
        $context = $event->getSalesChannelContext();

        $paymentMethods = $response->getPaymentMethods();
        $flizpayExists = false;

        foreach ($paymentMethods as $method) {
            if ($method->getHandlerIdentifier() === self::FLIZPAY_HANDLER) {
                $flizpayExists = true;
                break;
            }
        }

        $currentPaymentMethod = $context->getPaymentMethod();

        $this->logger->debug("Payment method route response", [
            "total_methods" => $paymentMethods->count(),
            "flizpay_in_list" => $flizpayExists,
            "current_payment_method" => $currentPaymentMethod->getHandlerIdentifier(),
            "all_handlers" => array_map(
                fn($m) => $m->getHandlerIdentifier(),
                $paymentMethods->getElements(),
            ),
        ]);
    }

    public function onPaymentMethodEntityLoaded(EntityLoadedEvent $event): void
    {
        $entities = $event->getEntities();

        foreach ($entities as $entity) {
            if (
                $entity instanceof PaymentMethodEntity &&
                $entity->getHandlerIdentifier() === self::FLIZPAY_HANDLER
            ) {
                $this->logger->debug("FLIZpay entity loaded", [
                    "id" => $entity->getId(),
                    "name" => $entity->getName(),
                    "handler" => $entity->getHandlerIdentifier(),
                    "active" => $entity->getActive(),
                    "availability_rule_id" => $entity->getAvailabilityRuleId(),
                    "plugin" => $entity->getPlugin()?->getName(),
                ]);
            }
        }
    }
}
