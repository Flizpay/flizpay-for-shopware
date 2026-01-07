<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Checkout\Cart\Validation;

use FLIZpay\FlizpayForShopware\Handler\FlizpayPaymentHandler;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class FlizpayCartValidator implements CartValidatorInterface
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
    ) {}

    public function validate(
        Cart $cart,
        ErrorCollection $errors,
        SalesChannelContext $context,
    ): void {
        $paymentMethod = $context->getPaymentMethod();

        $this->logger->critical("=== FLIZPAY CART VALIDATOR ===", [
            "selected_handler" => $paymentMethod->getHandlerIdentifier(),
            "expected_handler" => FlizpayPaymentHandler::class,
            "is_flizpay" =>
                $paymentMethod->getHandlerIdentifier() ===
                FlizpayPaymentHandler::class,
        ]);

        // Only validate if FLIZpay is selected
        if (
            $paymentMethod->getHandlerIdentifier() !==
            FlizpayPaymentHandler::class
        ) {
            return;
        }

        $name = (string) $paymentMethod->getTranslation("name");
        $id = $paymentMethod->getId();

        // Check if webhook is alive (verified connection)
        $webhookAlive = $this->systemConfigService->getBool(
            "FlizpayForShopware.config.webhookAlive",
        );

        // Check if API key is configured
        $apiKey = $this->systemConfigService->getString(
            "FlizpayForShopware.config.apiKey",
        );

        $this->logger->critical("=== FLIZPAY CART VALIDATOR CONFIG ===", [
            "webhookAlive" => $webhookAlive,
            "apiKey_exists" => !empty($apiKey),
        ]);

        if (!$webhookAlive) {
            $this->logger->critical(
                "=== FLIZPAY BLOCKED: webhook not alive ===",
            );
            $errors->add(
                new PaymentMethodBlockedError($name, "not configured", $id),
            );
            return;
        }

        if (!$apiKey) {
            $this->logger->critical("=== FLIZPAY BLOCKED: no API key ===");
            $errors->add(
                new PaymentMethodBlockedError($name, "not configured", $id),
            );
            return;
        }

        $this->logger->critical("=== FLIZPAY CART VALIDATOR: PASSED ===");
    }
}
