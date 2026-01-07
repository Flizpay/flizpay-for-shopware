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

class FlizpayPaymentHandler extends AbstractPaymentHandler
{
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
        // This handler does not support recurring payments or refunds
        // Regular payments use the pay() method directly
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
                "FlizpayPayment.config.webhookAlive",
            );

            $apiKey = $this->systemConfigService->getString(
                "FlizpayPayment.config.apiKey",
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

            // Mark as in_progress
            $this->transactionStateHandler->process($transactionId, $context);

            // Create FLIZpay transaction
            $redirectUrl = $this->apiService->create_transaction(
                $order,
                "plugin",
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

        $this->logger->info("Customer returned from FLIZpay", [
            "transactionId" => $transactionId,
            "currentState" => $orderTransaction
                ->getStateMachineState()
                ?->getTechnicalName(),
        ]);

        // Webhook handles actual payment confirmation
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
