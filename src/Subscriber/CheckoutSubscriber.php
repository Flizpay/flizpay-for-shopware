<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Subscriber;

use FLIZpay\FlizpayForShopware\Service\FlizpayWidgetService;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPage;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Page;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Makes `flizpayCheckout` available wherever the payment method list is rendered.
 *
 * The payment method keeps its plain name and no description; <fliz-checkout> owns the offer text,
 * so nothing here builds or translates it.
 *
 * @since 1.1.0
 */
class CheckoutSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly FlizpayWidgetService $widgetService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => "onPageLoaded",
            AccountEditOrderPageLoadedEvent::class => "onPageLoaded",
        ];
    }

    public function onPageLoaded(PageLoadedEvent $event): void
    {
        $page = $event->getPage();

        $page->addExtension(
            "flizpayCheckout",
            new ArrayStruct(
                $this->widgetService->getCheckout(
                    $event->getSalesChannelContext(),
                    $event->getRequest(),
                    $this->getTotal($page),
                ),
            ),
        );
    }

    /** The amount being paid, or null when the page does not know one. */
    private function getTotal(Page $page): ?float
    {
        if ($page instanceof CheckoutConfirmPage) {
            return $page->getCart()->getPrice()->getTotalPrice();
        }

        if ($page instanceof AccountEditOrderPage) {
            return $page->getOrder()->getPrice()->getTotalPrice();
        }

        return null;
    }
}
