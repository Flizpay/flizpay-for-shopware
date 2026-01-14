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

        // Get display settings
        $showLogo = $this->cashbackHelper->isShowLogoEnabled($salesChannelId);
        $showDescriptionInTitle = $this->cashbackHelper->isShowDescriptionInTitleEnabled(
            $salesChannelId,
        );
        $showSubtitle = $this->cashbackHelper->isShowSubtitleEnabled(
            $salesChannelId,
        );

        // Check if cashback is available
        $cashbackAvailable = $this->cashbackHelper->isCashbackAvailable(
            $salesChannelId,
        );

        // Get locale from request
        $locale = $event->getRequest()->getLocale() ?? "de-DE";

        // Get shop name from sales channel
        $shopName =
            $salesChannelContext->getSalesChannel()->getName() ?? "Shop";

        // Get cashback display data
        $displayValue = $this->cashbackHelper->getDisplayValue($salesChannelId);

        // Build title based on settings
        $cashbackTitle =
            $showDescriptionInTitle && $cashbackAvailable
                ? $this->cashbackHelper->getCashbackTitle(
                    $locale,
                    $salesChannelId,
                )
                : "FLIZpay";

        // Build description based on settings
        $cashbackDescription = $showSubtitle
            ? $this->cashbackHelper->getCashbackDescription(
                $shopName,
                $locale,
                $salesChannelId,
            )
            : null;

        // Find and modify the FLIZpay payment method in the list
        $page = $event->getPage();
        $paymentMethods = $page->getPaymentMethods();

        foreach ($paymentMethods as $paymentMethod) {
            if (
                $paymentMethod->getHandlerIdentifier() === self::FLIZPAY_HANDLER
            ) {
                // Modify the payment method's translated name/description
                $this->modifyPaymentMethodDisplay(
                    $paymentMethod,
                    $cashbackTitle,
                    $cashbackDescription,
                );

                $this->logger->info(
                    "FLIZpay payment method modified with checkout settings",
                    [
                        "title" => $cashbackTitle,
                        "showLogo" => $showLogo,
                        "showDescriptionInTitle" => $showDescriptionInTitle,
                        "showSubtitle" => $showSubtitle,
                    ],
                );
                break;
            }
        }

        // Add page extension for template usage
        $page->addExtension(
            "flizpayCashback",
            new ArrayStruct([
                "enabled" => $cashbackAvailable,
                "displayValue" => $displayValue,
                "formattedValue" => $displayValue
                    ? $this->cashbackHelper->formatForLocale(
                        $displayValue,
                        $locale,
                    )
                    : null,
                "title" => $cashbackTitle,
                "description" => $cashbackDescription,
                "type" => $this->cashbackHelper->getCashbackType(
                    $salesChannelId,
                ),
                "locale" => $locale,
                "showLogo" => $showLogo,
                "showDescriptionInTitle" => $showDescriptionInTitle,
                "showSubtitle" => $showSubtitle,
            ]),
        );
    }

    /**
     * Modify the payment method entity to display cashback information
     */
    private function modifyPaymentMethodDisplay(
        PaymentMethodEntity $paymentMethod,
        string $cashbackTitle,
        ?string $cashbackDescription,
    ): void {
        // Get current translated data or create new array
        $translated = $paymentMethod->getTranslated() ?? [];

        // Update the name
        $translated["name"] = $cashbackTitle;

        // Update description if provided
        if ($cashbackDescription !== null) {
            $translated["description"] = $cashbackDescription;
        }

        // Set the modified translations back
        $paymentMethod->setTranslated($translated);
    }
}
