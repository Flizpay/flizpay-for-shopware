<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;

class FlizpayWebhookService
{
    private const FLIZ_SIGNATURE_HEADER = "X-FLIZ-SIGNATURE";
    private const CONFIG_PREFIX = "FlizpayForShopware.config.";

    private SystemConfigService $systemConfig;
    private EntityRepository $orderRepository;
    private EntityRepository $orderLineItemRepository;
    private OrderTransactionStateHandler $transactionStateHandler;
    private LoggerInterface $logger;

    /**
     * Initialize webhook service
     *
     * @param $systemConfig
     * @param $orderRepository
     * @param $orderLineItemRepository
     * @param $transactionStateHandler
     * @param $logger
     *
     * @since 1.0.0
     */
    public function __construct(
        SystemConfigService $systemConfig,
        EntityRepository $orderRepository,
        EntityRepository $orderLineItemRepository,
        OrderTransactionStateHandler $transactionStateHandler,
        LoggerInterface $logger,
    ) {
        $this->systemConfig = $systemConfig;
        $this->orderRepository = $orderRepository;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->logger = $logger;
    }

    /**
     * Webhook handler
     *
     * @param $request
     * @return JsonResponse
     *
     * @since 1.0.0
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->getContent();
            $signature = $request->headers->get(self::FLIZ_SIGNATURE_HEADER);

            // Validate signature BEFORE decoding JSON
            if (!$this->validateSignature($payload, $signature)) {
                $this->logger->error("Invalid webhook signature", [
                    "signature" => $signature,
                    "payload_length" => strlen($payload),
                ]);
                return new JsonResponse(
                    ["error" => "Invalid signature"],
                    Response::HTTP_UNAUTHORIZED,
                );
            }

            $data = json_decode($payload, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error("Invalid JSON payload", [
                    "error" => json_last_error_msg(),
                ]);
                return new JsonResponse(
                    ["error" => "Invalid JSON"],
                    Response::HTTP_BAD_REQUEST,
                );
            }

            return $this->routeWebhook($data);
        } catch (\Exception $e) {
            $this->logger->error("Webhook processing failed", [
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
            ]);
            return new JsonResponse(
                ["error" => "Internal server error"],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    /**
     * Validates payload with signature header
     *
     * @param $payload - raw payload string
     * @param $signature - FLIZ signature header
     * @return bool
     *
     * @since 1.0.0
     *
     */
    private function validateSignature(
        string $payload,
        ?string $signature,
    ): bool {
        if (!$signature) {
            return false;
        }

        $webhookKey = $this->systemConfig->getString(
            "FlizpayForShopware.config.webhookKey",
        );

        if (!$webhookKey) {
            $this->logger->error("Webhook key not configured");
            return false;
        }

        // Payload is already a JSON string, use it directly for HMAC
        $expectedSignature = hash_hmac("sha256", $payload, $webhookKey);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Webhook router handler
     * Routes to a respective handler based on payload content
     *
     * @param $data
     * @return JsonResponse
     *
     * @since 1.0.0
     */
    private function routeWebhook(array $data): JsonResponse
    {
        if (isset($data["test"])) {
            return $this->handleTest($data);
        }

        if (isset($data["updateCashbackInfo"])) {
            return $this->handleCashbackUpdate($data);
        }

        return $this->handlePaymentComplete($data);
    }

    /**
     * Handle cashback update webhook
     * Updates stored cashback data from FLIZpay
     *
     * @param array $data
     * @return JsonResponse
     *
     * @since 1.1.0
     */
    private function handleCashbackUpdate(array $data): JsonResponse
    {
        $this->logger->info("Cashback update webhook received", [
            "data" => $data,
        ]);

        $firstPurchaseAmount = (float) ($data["firstPurchaseAmount"] ?? 0);
        $standardAmount = (float) ($data["amount"] ?? 0);

        $cashbackData = json_encode([
            "first_purchase_amount" => $firstPurchaseAmount,
            "standard_amount" => $standardAmount,
        ]);

        $this->systemConfig->set(
            self::CONFIG_PREFIX . "cashbackData",
            $cashbackData,
        );

        $this->logger->info("Cashback data updated", [
            "first_purchase_amount" => $firstPurchaseAmount,
            "standard_amount" => $standardAmount,
        ]);

        return new JsonResponse(
            [
                "success" => true,
                "message" => "Cashback information updated",
            ],
            Response::HTTP_OK,
        );
    }

    /**
     * Test store webhook handler
     *
     * @param $data
     * @return JsonResponse
     *
     * @since 1.0.0
     */
    private function handleTest(array $data): JsonResponse
    {
        $this->logger->info("Webhook test received", ["data" => $data]);

        // Set webhook as alive - connection verified!
        $this->systemConfig->set(
            "FlizpayForShopware.config.webhookAlive",
            true,
        );

        $this->logger->info(
            "Webhook connection verified - payment method enabled",
        );

        return new JsonResponse(
            [
                "success" => true,
                "alive" => true,
                "message" => "Test webhook received successfully",
                "timestamp" => time(),
            ],
            Response::HTTP_OK,
        );
    }

    /**
     * Payment complete handler
     *
     * @param $data
     * @return JsonResponse
     *
     * @since 1.0.0
     */
    public function handlePaymentComplete(array $data): JsonResponse
    {
        if (!isset($data["metadata"]["orderId"]) || !isset($data["status"])) {
            return new JsonResponse(["error" => "Missing orderId"], 400);
        }

        $orderId = $data["metadata"]["orderId"];
        $transactionId = $data["transactionId"] ?? null;
        $context = Context::createDefaultContext();

        try {
            $criteria = new Criteria([$orderId]);
            $criteria->addAssociation("transactions");
            $criteria->addAssociation("lineItems");

            $order = $this->orderRepository
                ->search($criteria, $context)
                ->first();

            if (!$order) {
                $this->logger->error("Order not found", [
                    "orderId" => $orderId,
                ]);
                return new JsonResponse(["error" => "Order not found"], 404);
            }

            $transaction = $order->getTransactions()->first();

            if (!$transaction) {
                $this->logger->error("Transaction not found", [
                    "orderId" => $orderId,
                ]);
                return new JsonResponse(
                    ["error" => "Transaction not found"],
                    404,
                );
            }

            // Mark as paid
            $this->transactionStateHandler->paid(
                $transaction->getId(),
                $context,
            );

            // Apply cashback if applicable
            if (isset($data["originalAmount"]) && isset($data["amount"])) {
                $discount =
                    (float) $data["originalAmount"] - (float) $data["amount"];
                if ($discount > 0) {
                    $this->applyCashbackToOrder(
                        $order,
                        $data,
                        $discount,
                        $context,
                    );
                }
            }

            $this->logger->info("Payment completed", [
                "orderId" => $orderId,
                "transactionId" => $transactionId,
            ]);

            return new JsonResponse(["success" => true], Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error("Failed to process payment webhook", [
                "orderId" => $orderId,
                "error" => $e->getMessage(),
            ]);

            return new JsonResponse(
                ["error" => "Processing failed"],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    /**
     * Apply cashback discount to order (matching WooCommerce behavior)
     * Updates line items proportionally and sets order total to settled amount
     *
     * @param OrderEntity $order
     * @param array $data
     * @param float $discount
     * @param Context $context
     *
     * @since 1.1.0
     */
    private function applyCashbackToOrder(
        OrderEntity $order,
        array $data,
        float $discount,
        Context $context,
    ): void {
        $originalAmount = (float) $data["originalAmount"];
        $finalAmount = (float) $data["amount"];
        $currency = $data["currency"] ?? "EUR";

        // Calculate cashback percentage
        $cashbackPercent = ($discount / $originalAmount) * 100;

        $this->logger->info("Applying cashback to order", [
            "orderId" => $order->getId(),
            "originalAmount" => $originalAmount,
            "finalAmount" => $finalAmount,
            "discount" => $discount,
            "cashbackPercent" => $cashbackPercent,
        ]);

        // Get line items and apply proportional discount (like WooCommerce)
        $lineItems = $order->getLineItems();
        $lineItemUpdates = [];

        if ($lineItems) {
            foreach ($lineItems as $lineItem) {
                $itemTotal = $lineItem->getTotalPrice();
                $discountAmount = ($itemTotal * $cashbackPercent) / 100;
                $newTotal = round($itemTotal - $discountAmount, 2);

                $lineItemUpdates[] = [
                    "id" => $lineItem->getId(),
                    "totalPrice" => $newTotal,
                    "unitPrice" =>
                        $lineItem->getQuantity() > 0
                            ? round($newTotal / $lineItem->getQuantity(), 2)
                            : $newTotal,
                ];
            }
        }

        // Update line items
        if (!empty($lineItemUpdates)) {
            $this->orderLineItemRepository->update($lineItemUpdates, $context);
        }

        // Update order total to match FLIZpay settled amount
        // Also store cashback details in custom fields for reference
        $this->orderRepository->update(
            [
                [
                    "id" => $order->getId(),
                    "amountTotal" => $finalAmount,
                    "customFields" => [
                        "flizpay_cashback_applied" => $discount,
                        "flizpay_cashback_percent" => round(
                            $cashbackPercent,
                            2,
                        ),
                        "flizpay_cashback_currency" => $currency,
                        "flizpay_original_amount" => $originalAmount,
                    ],
                ],
            ],
            $context,
        );

        $this->logger->info("FLIZ Cashback Applied", [
            "orderId" => $order->getId(),
            "originalAmount" => $originalAmount,
            "finalAmount" => $finalAmount,
            "discount" => $discount,
            "cashbackPercent" => round($cashbackPercent, 2),
            "currency" => $currency,
        ]);
    }
}
