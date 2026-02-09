<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Handler;

use FLIZpay\FlizpayForShopware\Service\FlizpayApiService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles FLIZpay payment processing for Shopware checkout. (Asynchronous)
 *
 * This handler implements the redirect-based payment flow:
 * 1. Customer initiates checkout with FLIZpay payment method
 * 2. Handler creates a transaction via FLIZpay API and redirects to payment page
 * 3. Customer completes payment on FLIZpay's hosted page
 * 4. Customer is redirected back to the shop (finalize method)
 * 5. Webhook confirms actual payment status asynchronously
 *
 * Note: This handler does not support recurring payments or refunds.
 * Primary cart validation occurs in FlizpayCartValidator before order creation.
 */
class FlizpayPaymentHandler extends AbstractPaymentHandler
{
    /**
     * Initializes the payment handler with required services.
     *
     * @param FlizpayApiService $apiService Handles communication with the FLIZpay API
     * @param OrderTransactionStateHandler $transactionStateHandler Manages payment state transitions (open, in_progress, paid, failed)
     * @param LoggerInterface $logger PSR-3 logger for debugging and error tracking
     * @param SystemConfigService $systemConfigService Provides access to plugin configuration (API key, webhook status)
     * @param EntityRepository $orderTransactionRepository Repository for fetching order transaction entities with associations
     */
    public function __construct(
        private readonly FlizpayApiService $apiService,
        private readonly OrderTransactionStateHandler $transactionStateHandler,
        private readonly LoggerInterface $logger,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $orderTransactionRepository,
    ) {}

    public function supports(
        PaymentHandlerType $type,
        string $paymentMethodId,
        Context $context,
    ): bool {
        // This payment handler does not support recurring payments nor refunds
        return false;
    }

    /**
     * This method is called during checkout to process the payment.
     * Returns a RedirectResponse to the FLIZpay payment page.
     * After redirect, the finalize method will be called.
     *
     * Note: Primary validation happens in FlizpayCartValidator which runs
     * before order creation. This validation serves as a safety fallback.
     */
    public function pay(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
        ?Struct $validateStruct,
    ): ?RedirectResponse {
        $transactionId = $transaction->getOrderTransactionId();

        try {
            // Fetch order and transaction from repository
            [$orderTransaction, $order] = $this->fetchOrderTransaction(
                $transactionId,
                $context,
            );

            // Safety fallback validation (primary validation is in FlizpayCartValidator)
            $webhookAlive = $this->systemConfigService->getBool(
                "FlizpayForShopware.config.webhookAlive",
            );

            $apiKey = $this->systemConfigService->getString(
                "FlizpayForShopware.config.apiKey",
            );

            if (!$webhookAlive || !$apiKey) {
                throw PaymentException::asyncProcessInterrupted(
                    $transactionId,
                    "FLIZpay payment method not configured. Please complete the setup in admin settings.",
                );
            }

            $this->logger->info("FLIZpay payment initiated", [
                "orderId" => $order->getId(),
                "transactionId" => $transactionId,
            ]);

            // Returns Shopware order finalized url
            // In case of `paid` order status -> order completed page
            // When order still having `open` status -> /account/order page,
            // where user can re-initiate payment transaction
            $returnUrl = $transaction->getReturnUrl();

            // Create FLIZpay transaction
            $redirectUrl = $this->apiService->create_transaction(
                $order,
                "plugin",
                $returnUrl,
            );

            if (!$redirectUrl) {
                throw new \RuntimeException(
                    "Failed to create FLIZpay transaction",
                );
            }

            return new RedirectResponse($redirectUrl);
        } catch (\Throwable $e) {
            $this->logger->error("FLIZpay payment failed", [
                "error" => $e->getMessage(),
                "transactionId" => $transactionId,
            ]);

            $this->transactionStateHandler->fail($transactionId, $context);

            throw $e;
        }
    }

    /**
     * This method will be called after redirect from the external payment provider.
     *
     * Checks if the payment was completed (webhook would have set status to 'paid').
     * If payment is still in 'open' state, the customer either:
     * - Clicked the cancel button on FLIZpay checkout page
     * - The webhook hasn't arrived yet
     *
     * We throw an exception to redirect the customer to the order edit page
     * where they can retry payment. The transaction stays in 'open' state
     * to allow retry.
     */
    public function finalize(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
    ): void {
        $transactionId = $transaction->getOrderTransactionId();

        [$orderTransaction, $order] = $this->fetchOrderTransaction(
            $transactionId,
            $context,
        );

        $currentState = $orderTransaction
            ->getStateMachineState()
            ?->getTechnicalName();

        $this->logger->info("Customer returned from FLIZpay", [
            "transactionId" => $transactionId,
            "currentState" => $currentState,
        ]);

        // Check if webhook already confirmed the payment
        if ($currentState === "paid") {
            $this->logger->info("FLIZpay payment confirmed by webhook", [
                "transactionId" => $transactionId,
            ]);
            return;
        }

        // Payment not completed - customer clicked cancel or webhook hasn't arrived
        $this->logger->info("FLIZpay payment not completed", [
            "transactionId" => $transactionId,
            "state" => $currentState,
        ]);

        throw PaymentException::asyncFinalizeInterrupted(
            $transactionId,
            "Payment was not completed. Please try again.",
        );
    }

    /**
     * Fetch order transaction and order entities from the repository.
     *
     * @return array{0: OrderTransactionEntity, 1: OrderEntity}
     */
    private function fetchOrderTransaction(
        string $transactionId,
        Context $context,
    ): array {
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation("order");
        $criteria->addAssociation("order.currency");
        $criteria->addAssociation("order.lineItems");
        $criteria->addAssociation("order.orderCustomer");
        $criteria->addAssociation("stateMachineState");

        $transaction = $this->orderTransactionRepository
            ->search($criteria, $context)
            ->first();
        \assert($transaction instanceof OrderTransactionEntity);

        $order = $transaction->getOrder();
        \assert($order instanceof OrderEntity);

        return [$transaction, $order];
    }
}
