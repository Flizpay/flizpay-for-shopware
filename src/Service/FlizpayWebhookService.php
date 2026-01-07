<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
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

    private SystemConfigService $systemConfig;
    private EntityRepository $orderRepository;
    private OrderTransactionStateHandler $transactionStateHandler;
    private LoggerInterface $logger;

    /**
     * Initialize webhook service
     *
     * @param $systemConfig
     * @param $orderRepository
     * @param $transactionStateHandler
     * @param $logger
     *
     * @since 1.0.0
     */
    public function __construct(
        SystemConfigService $systemConfig,
        EntityRepository $orderRepository,
        OrderTransactionStateHandler $transactionStateHandler,
        LoggerInterface $logger,
    ) {
        $this->systemConfig = $systemConfig;
        $this->orderRepository = $orderRepository;
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

        return $this->handlePaymentComplete($data);
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
                    // $this->applyCashback($order, $discount, $context);
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
}
