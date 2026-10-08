<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Subscriber;

use FLIZpay\FlizpayForShopware\Service\FlizpayWidgetService;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Makes `flizpayWidget` available to every storefront template.
 */
class WidgetSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly FlizpayWidgetService $widgetService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [StorefrontRenderEvent::class => "onStorefrontRender"];
    }

    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $event->setParameter(
            "flizpayWidget",
            $this->widgetService->getWidget($event->getSalesChannelContext(), $event->getRequest()),
        );
    }
}
