<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Service;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use FLIZpay\FlizpayForShopware\Service\FlizpaySentryReporter;
use Psr\Log\LoggerInterface;

/**
 * Handles FLIZpay webhook events including payment completion and cashback application.
 *
 * The cashback implementation follows below approach:
 * 1. Add a credit line item to show the discount (like WooCommerce's "Rabatt" row)
 * 2. Update the order total to reflect the final amount paid
 *
 * This ensures the customer sees a clear breakdown:
 * - Original product price (unchanged)
 * - FLIZpay Cashback discount (negative line item)
 * - Final total (reduced by cashback)
 */

class FlizpayWebhookService
{
    private const FLIZ_SIGNATURE_HEADER = "X-FLIZ-SIGNATURE";
    private const CONFIG_PREFIX = "FlizpayForShopware.config.";

    private SystemConfigService $systemConfig;
    private EntityRepository $orderRepository;
    private EntityRepository $orderLineItemRepository;
    private OrderTransactionStateHandler $transactionStateHandler;
    private LoggerInterface $logger;
    private FlizpaySentryReporter $sentryReporter;

    /**
     * Initialize webhook service
     *
     * @param $systemConfig
     * @param $orderRepository
     * @param $orderLineItemRepository
     * @param $transactionStateHandler
     * @param $logger
     * @param $sentryReporter
     *
     * @since 1.0.0
     */
    public function __construct(
        SystemConfigService $systemConfig,
        EntityRepository $orderRepository,
        EntityRepository $orderLineItemRepository,
        OrderTransactionStateHandler $transactionStateHandler,
        LoggerInterface $logger,
        FlizpaySentryReporter $sentryReporter,
    ) {
        $this->systemConfig = $systemConfig;
        $this->orderRepository = $orderRepository;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->logger = $logger;
        $this->sentryReporter = $sentryReporter;
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

            $this->sentryReporter->report($e);

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
        // Log the complete webhook payload for debugging
        $this->logger->info("Payment webhook received", [
            "payload" => $data,
            "hasOriginalAmount" => isset($data["originalAmount"]),
            "hasAmount" => isset($data["amount"]),
            "originalAmount" => $data["originalAmount"] ?? "NOT_SET",
            "amount" => $data["amount"] ?? "NOT_SET",
        ]);

        if (!isset($data["metadata"]["orderId"]) || !isset($data["status"])) {
            $this->logger->error("Payment webhook missing required fields", [
                "hasOrderId" => isset($data["metadata"]["orderId"]),
                "hasStatus" => isset($data["status"]),
                "payload" => $data,
            ]);
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

            // Idempotency guard: if cashback was already applied, this is a duplicate webhook
            $customFields = $order->getCustomFields() ?? [];
            if (!empty($customFields["flizpay_cashback_applied"])) {
                $this->logger->info(
                    "Duplicate webhook detected, cashback already applied",
                    [
                        "orderId" => $orderId,
                        "previousCashback" =>
                            $customFields["flizpay_cashback_applied"],
                    ],
                );
                return new JsonResponse(["success" => true], Response::HTTP_OK);
            }

            // Apply cashback BEFORE marking as paid, because the paid state
            // transition triggers Shopware's order confirmation email.
            // The email must include the updated totals with cashback applied.
            if (isset($data["originalAmount"]) && isset($data["amount"])) {
                $discount =
                    (float) $data["originalAmount"] - (float) $data["amount"];

                $this->logger->info("Cashback fields present in webhook", [
                    "orderId" => $orderId,
                    "originalAmount" => $data["originalAmount"],
                    "amount" => $data["amount"],
                    "calculatedDiscount" => $discount,
                ]);

                if ($discount > 0) {
                    $this->logger->info("Applying cashback to order", [
                        "orderId" => $orderId,
                        "discount" => $discount,
                    ]);

                    $this->applyCashbackToOrder(
                        $order,
                        $data,
                        $discount,
                        $context,
                    );
                } else {
                    $this->logger->warning("No cashback discount detected", [
                        "orderId" => $orderId,
                        "originalAmount" => $data["originalAmount"],
                        "amount" => $data["amount"],
                        "discount" => $discount,
                    ]);
                }
            } else {
                $this->logger->warning(
                    "Cashback fields missing from webhook payload",
                    [
                        "orderId" => $orderId,
                        "hasOriginalAmount" => isset($data["originalAmount"]),
                        "hasAmount" => isset($data["amount"]),
                        "payload" => $data,
                    ],
                );
            }

            // Mark as paid AFTER cashback is applied so the confirmation
            // email (triggered by this state change) reflects the correct total
            try {
                $this->transactionStateHandler->paid(
                    $transaction->getId(),
                    $context,
                );
                $this->logger->info("Transaction marked as paid", [
                    "orderId" => $orderId,
                    "transactionId" => $transaction->getId(),
                    "orderAmountTotal" => $order->getAmountTotal(),
                ]);
            } catch (IllegalTransitionException $e) {
                $this->logger->info(
                    "Transaction already paid, skipping state transition",
                    [
                        "orderId" => $orderId,
                        "transactionId" => $transaction->getId(),
                    ],
                );
            }

            $this->logger->info("Payment completed successfully", [
                "orderId" => $orderId,
                "transactionId" => $transactionId,
            ]);

            return new JsonResponse(["success" => true], Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error("Failed to process payment webhook", [
                "orderId" => $orderId,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
            ]);

            $this->sentryReporter->report($e, [
                "orderId" => $orderId,
            ]);

            return new JsonResponse(
                ["error" => "Processing failed"],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    /**
     * Apply cashback discount to order (matching WooCommerce behavior)
     *
     * This adds a credit/discount line item to show the FLIZ cashback as a separate row
     * (like WooCommerce's "Rabatt" line), and updates the order total accordingly.
     * The original product prices remain unchanged - only a discount line is added.
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

        // Get the original price object to preserve tax information
        $originalPrice = $order->getPrice();
        $taxStatus = $originalPrice->getTaxStatus();

        // Get tax rate from the order (use the first tax rule, typically the main VAT rate)
        $taxRate = 0.0;
        $taxRules = $originalPrice->getTaxRules();
        if ($taxRules->count() > 0) {
            $taxRate = $taxRules->first()->getTaxRate();
        }

        // Calculate tax portion of the discount
        // If tax status is 'gross', the discount includes tax
        // If tax status is 'net', we need to calculate tax separately
        $discountNet = $discount;
        $discountTax = 0.0;
        if ($taxStatus === "gross" && $taxRate > 0) {
            $discountNet = round($discount / (1 + $taxRate / 100), 2);
            $discountTax = round($discount - $discountNet, 2);
        } elseif ($taxStatus === "net" && $taxRate > 0) {
            $discountTax = round(($discount * $taxRate) / 100, 2);
        }

        $this->logger->info("Applying cashback to order", [
            "orderId" => $order->getId(),
            "originalAmount" => $originalAmount,
            "finalAmount" => $finalAmount,
            "discount" => $discount,
            "discountNet" => $discountNet,
            "discountTax" => $discountTax,
            "cashbackPercent" => $cashbackPercent,
            "taxRate" => $taxRate,
            "taxStatus" => $taxStatus,
        ]);

        // Get the next position number for the line item
        $lineItems = $order->getLineItems();
        $maxPosition = 0;
        if ($lineItems) {
            foreach ($lineItems as $lineItem) {
                if ($lineItem->getPosition() > $maxPosition) {
                    $maxPosition = $lineItem->getPosition();
                }
            }
        }

        // Create a credit line item for the cashback discount (negative amount)
        // This matches WooCommerce's "Rabatt" display
        $creditLineItemId = Uuid::randomHex();
        $creditLineItem = [
            "id" => $creditLineItemId,
            "orderId" => $order->getId(),
            "identifier" => "flizpay-cashback-" . $order->getId(),
            "referencedId" => null,
            "productId" => null,
            "quantity" => 1,
            "label" => "FLIZpay Cashback (" . round($cashbackPercent, 0) . "%)",
            "type" => LineItem::CREDIT_LINE_ITEM_TYPE,
            "good" => false,
            "removable" => false,
            "stackable" => false,
            "position" => $maxPosition + 1,
            "states" => [],
            // Price with negative values for discount
            "price" => [
                "unitPrice" => -$discount,
                "totalPrice" => -$discount,
                "quantity" => 1,
                "calculatedTaxes" => [
                    [
                        "tax" => -$discountTax,
                        "taxRate" => $taxRate,
                        "price" => -$discount,
                    ],
                ],
                "taxRules" => [
                    [
                        "taxRate" => $taxRate,
                        "percentage" => 100.0,
                    ],
                ],
            ],
            "payload" => [
                "flizpay_cashback" => true,
                "cashback_percent" => round($cashbackPercent, 2),
                "original_amount" => $originalAmount,
            ],
        ];

        // Add the credit line item
        $this->orderLineItemRepository->create([$creditLineItem], $context);

        // Update the order's total price
        // The new total is: original total + credit (negative discount)
        $originalNet = $originalPrice->getNetPrice();
        $originalTotal = $originalPrice->getTotalPrice();
        $originalPositionPrice = $originalPrice->getPositionPrice();

        // Calculate new totals (subtract discount)
        $newTotal = round($originalTotal - $discount, 2);
        $newNet = round($originalNet - $discountNet, 2);
        // Position price stays the same (it's the sum of line item prices before shipping)
        // But we need to include the credit line item
        $newPositionPrice = round($originalPositionPrice - $discount, 2);

        // Recalculate taxes
        $calculatedTaxes = $originalPrice->getCalculatedTaxes();
        $newTaxesData = [];
        foreach ($calculatedTaxes as $tax) {
            // Reduce each tax proportionally
            $taxReduction =
                $discountTax * ($tax->getTaxRate() / ($taxRate ?: 1));
            $newTaxesData[] = [
                "tax" => round($tax->getTax() - $taxReduction, 2),
                "taxRate" => $tax->getTaxRate(),
                "price" => round(
                    $tax->getPrice() -
                        ($discount * $tax->getTaxRate()) / ($taxRate ?: 1),
                    2,
                ),
            ];
        }

        // If no taxes existed, create a zero tax entry
        if (empty($newTaxesData)) {
            $newTaxesData[] = [
                "tax" => 0.0,
                "taxRate" => $taxRate,
                "price" => $newTotal,
            ];
        }

        // Build the new price object
        $newPriceData = [
            "netPrice" => $newNet,
            "totalPrice" => $newTotal,
            "positionPrice" => $newPositionPrice,
            "rawTotal" => $newTotal,
            "taxStatus" => $taxStatus,
            "calculatedTaxes" => $newTaxesData,
            "taxRules" => array_map(
                fn($rule) => [
                    "taxRate" => $rule->getTaxRate(),
                    "percentage" => $rule->getPercentage(),
                ],
                $originalPrice->getTaxRules()->getElements(),
            ),
        ];

        // Update the order with new price and custom fields
        $orderUpdateData = [
            [
                "id" => $order->getId(),
                "price" => $newPriceData,
                "customFields" => [
                    "flizpay_cashback_applied" => $discount,
                    "flizpay_cashback_percent" => round($cashbackPercent, 2),
                    "flizpay_cashback_currency" => $currency,
                    "flizpay_original_amount" => $originalAmount,
                    "flizpay_credit_line_item_id" => $creditLineItemId,
                ],
            ],
        ];

        $this->orderRepository->update($orderUpdateData, $context);

        $this->logger->info("FLIZ Cashback Applied", [
            "orderId" => $order->getId(),
            "creditLineItemId" => $creditLineItemId,
            "originalAmount" => $originalAmount,
            "finalAmount" => $finalAmount,
            "newTotal" => $newTotal,
            "newNet" => $newNet,
            "discount" => $discount,
            "cashbackPercent" => round($cashbackPercent, 2),
            "currency" => $currency,
        ]);
    }
}
