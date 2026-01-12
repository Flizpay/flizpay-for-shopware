<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Core\Api;

use FLIZpay\FlizpayForShopware\Service\FlizpayApiService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ["_routeScope" => ["api"]])]
class FlizpayConfigController extends AbstractController
{
    private SystemConfigService $systemConfigService;
    private FlizpayApiService $flizpayApiService;
    private LoggerInterface $logger;

    public function __construct(
        SystemConfigService $systemConfigService,
        FlizpayApiService $flizpayApiService,
        LoggerInterface $logger,
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->flizpayApiService = $flizpayApiService;
        $this->logger = $logger;
    }

    /**
     * Configure payment gateway - called when admin saves settings
     * Follows WooCommerce plugin flow: generate_webhook_url -> get_webhook_key -> fetch_cashback
     */
    #[
        Route(
            path: "/api/_action/flizpay/configure-payment-gateway",
            name: "api.action.flizpay.configure_payment_gateway",
            defaults: ["_routeScope" => ["api"]],
            methods: ["POST"],
        ),
    ]
    public function configurePaymentGateway(
        Request $request,
        Context $context,
    ): JsonResponse {
        $this->logger->info("=== START: configurePaymentGateway ===");

        try {
            $data = json_decode($request->getContent(), true);
            $apiKey = $data["apiKey"] ?? null;
            $salesChannelId = $data["salesChannelId"] ?? null;

            $this->logger->info("Received configuration request", [
                "hasApiKey" => !empty($apiKey),
                "apiKeyLength" => strlen($apiKey ?? ""),
                "salesChannelId" => $salesChannelId,
            ]);

            if (empty($apiKey)) {
                $this->logger->error("API key is missing from request");
                return new JsonResponse(
                    [
                        "success" => false,
                        "message" => "API key is required",
                    ],
                    400,
                );
            }

            // Temporarily save API key for API calls
            $this->logger->info("Saving API key to config");
            $this->systemConfigService->set(
                "FlizpayForShopware.config.apiKey",
                $apiKey,
                $salesChannelId,
            );

            // Reset webhook status - safety measure like WordPress
            $this->logger->info("Resetting webhookAlive to false");
            $this->systemConfigService->set(
                "FlizpayForShopware.config.webhookAlive",
                false,
                $salesChannelId,
            );

            try {
                $this->logger->debug(
                    "Calling flizpayApiService->generate_webhook_url()",
                );
                $webhookUrl = $this->flizpayApiService->generate_webhook_url();
                $this->logger->debug("generate_webhook_url() returned", [
                    "webhookUrl" => $webhookUrl,
                    "isEmpty" => empty($webhookUrl),
                ]);

                if (empty($webhookUrl)) {
                    throw new \RuntimeException(
                        "Failed to register webhook URL with Flizpay",
                    );
                }

                $this->logger->info("Saving webhook URL to config", [
                    "webhookUrl" => $webhookUrl,
                ]);
                $this->systemConfigService->set(
                    "FlizpayForShopware.config.webhookUrl",
                    $webhookUrl,
                    $salesChannelId,
                );
            } catch (\Exception $e) {
                $this->logger->error("Failed to register webhook URL", [
                    "error" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
                ]);

                // Clear API key on failure
                $this->systemConfigService->set(
                    "FlizpayForShopware.config.apiKey",
                    "",
                    $salesChannelId,
                );

                return new JsonResponse(
                    [
                        "success" => false,
                        "message" =>
                            "Failed to register webhook URL with Flizpay. Please check your API key.",
                        "error" => $e->getMessage(),
                    ],
                    401,
                );
            }

            try {
                $this->logger->debug(
                    "Calling flizpayApiService->get_webhook_key()",
                );
                $webhookKey = $this->flizpayApiService->get_webhook_key();
                $this->logger->debug("get_webhook_key() returned", [
                    "hasWebhookKey" => !empty($webhookKey),
                    "webhookKeyLength" => strlen($webhookKey ?? ""),
                ]);

                if (empty($webhookKey)) {
                    throw new \RuntimeException(
                        "Failed to retrieve webhook key from Flizpay",
                    );
                }

                $this->logger->info("Saving webhook key to config");
                $this->systemConfigService->set(
                    "FlizpayForShopware.config.webhookKey",
                    $webhookKey,
                    $salesChannelId,
                );
            } catch (\Exception $e) {
                $this->logger->error("Failed to get webhook key", [
                    "error" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
                ]);

                // Clear API key on failure
                $this->systemConfigService->set(
                    "FlizpayForShopware.config.apiKey",
                    "",
                    $salesChannelId,
                );

                return new JsonResponse(
                    [
                        "success" => false,
                        "message" =>
                            "Failed to authenticate with Flizpay API. Please check your API key.",
                        "error" => $e->getMessage(),
                    ],
                    401,
                );
            }

            // Step 3: Fetch cashback data (non-critical, like WordPress)
            $this->logger->info("STEP 3: Starting cashback data fetch");
            try {
                $this->logger->debug(
                    "Calling flizpayApiService->fetch_cashback_data()",
                );
                $cashbackData = $this->flizpayApiService->fetch_cashback_data();
                $this->logger->debug("fetch_cashback_data() returned", [
                    "hasCashbackData" => !empty($cashbackData),
                    "rawData" => $cashbackData,
                ]);

                if ($cashbackData) {
                    $this->logger->info("Cashback data received", [
                        "cashbackData" => $cashbackData,
                    ]);

                    $this->systemConfigService->set(
                        "FlizpayForShopware.config.cashbackData",
                        json_encode($cashbackData),
                        $salesChannelId,
                    );
                } else {
                    $this->logger->info(
                        "No active cashback data returned from API",
                    );
                }
            } catch (\Exception $e) {
                $this->logger->warning(
                    "Cashback data fetch failed (non-critical)",
                    [
                        "error" => $e->getMessage(),
                        "trace" => $e->getTraceAsString(),
                    ],
                );
            }

            $this->logger->info("=== SUCCESS: All steps completed ===", [
                "webhookUrl" => $webhookUrl ?? null,
                "webhookAlive" => false,
                "requiresWebhookTest" => true,
            ]);

            return new JsonResponse([
                "success" => true,
                "message" =>
                    "Connection successful. Waiting for webhook verification...",
                "data" => [
                    "webhookUrl" => $webhookUrl ?? null,
                    "webhookAlive" => false, // Will be set to true by test webhook
                    "requiresWebhookTest" => true,
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error(
                "=== FATAL ERROR: Unexpected error in configurePaymentGateway ===",
                [
                    "error" => $e->getMessage(),
                    "errorClass" => get_class($e),
                    "file" => $e->getFile(),
                    "line" => $e->getLine(),
                    "trace" => $e->getTraceAsString(),
                ],
            );

            // Clear API key on unexpected failure
            $this->systemConfigService->set(
                "FlizpayForShopware.config.apiKey",
                "",
                $salesChannelId ?? null,
            );

            return new JsonResponse(
                [
                    "success" => false,
                    "message" => "An unexpected error occurred",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
