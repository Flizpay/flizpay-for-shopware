<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Subscriber;

use FLIZpay\FlizpayForShopware\Service\FlizpayPlacementService;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Makes `flizpayPlacement` available to every storefront template.
 */
class PlacementSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly FlizpayPlacementService $placementService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [StorefrontRenderEvent::class => "onStorefrontRender"];
    }

    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $event->setParameter(
            "flizpayPlacement",
            $this->placementService->getPlacement($event->getSalesChannelContext(), $event->getRequest()),
        );
    }
}
