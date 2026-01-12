<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Subscriber;

use FLIZpay\FlizpayForShopware\Service\FlizpayCashbackHelper;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

class PaymentMethodCashbackSubscriber implements EventSubscriberInterface
{
    private const FLIZPAY_HANDLER = "FLIZpay\FlizpayForShopware\Handler\FlizpayPaymentHandler";

    private FlizpayCashbackHelper $cashbackHelper;
    private LoggerInterface $logger;

    public function __construct(
        FlizpayCashbackHelper $cashbackHelper,
        LoggerInterface $logger,
    ) {
        $this->cashbackHelper = $cashbackHelper;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => "onCheckoutConfirmLoaded",
        ];
    }

    /**
     * Modify FLIZpay payment method display at checkout to show cashback info
     */
    public function onCheckoutConfirmLoaded(
        CheckoutConfirmPageLoadedEvent $event,
    ): void {
        $salesChannelContext = $event->getSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        // Check if cashback display is enabled and available
        $displayEnabled = $this->cashbackHelper->isDisplayCashbackEnabled(
            $salesChannelId,
        );
        $cashbackAvailable = $this->cashbackHelper->isCashbackAvailable(
            $salesChannelId,
        );

        if (!$displayEnabled || !$cashbackAvailable) {
            return;
        }

        // Get locale from request
        $locale = $event->getRequest()->getLocale() ?? "de-DE";

        // Get shop name from sales channel
        $shopName =
            $salesChannelContext->getSalesChannel()->getName() ?? "Shop";

        // Get cashback display data
        $displayValue = $this->cashbackHelper->getDisplayValue($salesChannelId);
        $cashbackTitle = $this->cashbackHelper->getCashbackTitle(
            $locale,
            $salesChannelId,
        );
        $cashbackDescription = $this->cashbackHelper->getCashbackDescription(
            $shopName,
            $locale,
            $salesChannelId,
        );

        // Find and modify the FLIZpay payment method in the list
        $page = $event->getPage();
        $paymentMethods = $page->getPaymentMethods();

        foreach ($paymentMethods as $paymentMethod) {
            if (
                $paymentMethod->getHandlerIdentifier() === self::FLIZPAY_HANDLER
            ) {
                // Modify the payment method's translated name to include cashback info
                $this->modifyPaymentMethodDisplay(
                    $paymentMethod,
                    $cashbackTitle,
                    $cashbackDescription,
                );

                $this->logger->info(
                    "FLIZpay payment method modified with cashback info",
                    [
                        "title" => $cashbackTitle,
                        "displayValue" => $displayValue,
                    ],
                );
                break;
            }
        }

        // Also add as page extension for any custom template usage
        $page->addExtension(
            "flizpayCashback",
            new ArrayStruct([
                "enabled" => true,
                "displayValue" => $displayValue,
                "formattedValue" => $this->cashbackHelper->formatForLocale(
                    $displayValue,
                    $locale,
                ),
                "title" => $cashbackTitle,
                "description" => $cashbackDescription,
                "type" => $this->cashbackHelper->getCashbackType(
                    $salesChannelId,
                ),
                "locale" => $locale,
            ]),
        );
    }

    /**
     * Modify the payment method entity to display cashback information
     */
    private function modifyPaymentMethodDisplay(
        PaymentMethodEntity $paymentMethod,
        string $cashbackTitle,
        string $cashbackDescription,
    ): void {
        // Get current translated data or create new array
        $translated = $paymentMethod->getTranslated() ?? [];

        // Update the name to include cashback info (like WooCommerce does)
        $translated["name"] = $cashbackTitle;

        // Update description to include cashback details
        $translated["description"] = $cashbackDescription;

        // Set the modified translations back
        $paymentMethod->setTranslated($translated);
    }
}
