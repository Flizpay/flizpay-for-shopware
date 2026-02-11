<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Centralized class for communication with all FLIZpay services via API
 * Check our documentation at https://docs.flizpay.de
 *
 * @since 1.0.0
 */
class FlizpayApi
{
    private const API_BASE_URL = "https://api.flizpay.de";

    private ?string $api_key = null;
    private array $routes;
    private Client $httpClient;
    private LoggerInterface $logger;
    private SystemConfigService $systemConfigService;
    private FlizpaySentryReporter $sentryReporter;
    private ?string $salesChannelId;

    /**
     * Sets the API key and initialize the API routes
     *
     * @param SystemConfigService $systemConfigService
     * @param LoggerInterface $logger
     * @param FlizpaySentryReporter $sentryReporter
     * @param string|null $salesChannelId
     *
     * @since 1.0.0
     */
    public function __construct(
        SystemConfigService $systemConfigService,
        LoggerInterface $logger,
        FlizpaySentryReporter $sentryReporter,
        ?string $salesChannelId = null,
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->logger = $logger;
        $this->sentryReporter = $sentryReporter;
        $this->salesChannelId = $salesChannelId;
        $this->init();
    }

    /**
     * Get API key from system config
     *
     * @return string
     * @throws \RuntimeException
     */
    private function getApiKey(): string
    {
        if ($this->api_key === null) {
            $this->api_key = $this->systemConfigService->get(
                "FlizpayForShopware.config.apiKey",
                $this->salesChannelId,
            );
        }

        if (empty($this->api_key)) {
            throw new \RuntimeException(
                "Flizpay API key not configured. Please configure it in the plugin settings.",
            );
        }

        return $this->api_key;
    }

    /**
     * Initialize the API Routes and http client for further usage
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function init(): void
    {
        $this->httpClient = new Client([
            "base_uri" => self::API_BASE_URL,
            "timeout" => 30,
            "headers" => [
                "Content-Type" => "application/json",
                "User-Agent" => "FlizpayShopware6/1.0.0",
            ],
        ]);

        $this->routes = [
            "generate_webhook_key" => function (?array $body): array {
                return [
                    "path" => "/business/generate-webhook-key",
                    "method" => "get",
                    "options" => [
                        "headers" => [
                            "Content-type" => "application/json",
                            "x-api-key" => $this->getApiKey(),
                        ],
                    ],
                ];
            },
            "edit_business" => function (?array $body): array {
                return [
                    "path" => "/business/edit",
                    "method" => "post",
                    "options" => [
                        "headers" => [
                            "Content-type" => "application/json",
                            "x-api-key" => $this->getApiKey(),
                        ],
                        "json" => $body,
                    ],
                ];
            },
            "create_transaction" => function (?array $body): array {
                return [
                    "path" => "/transactions",
                    "method" => "post",
                    "options" => [
                        "headers" => [
                            "Content-type" => "application/json",
                            "x-api-key" => $this->getApiKey(),
                        ],
                        "json" => $body,
                    ],
                ];
            },
            "fetch_cashback_data" => function (?array $body): array {
                return [
                    "path" => "/business/cashback",
                    "method" => "get",
                    "options" => [
                        "headers" => [
                            "Content-type" => "application/json",
                            "x-api-key" => $this->getApiKey(),
                        ],
                    ],
                ];
            },
        ];
    }

    /**
     * Performs an API call to the specified route, with the given body.
     *
     * Available routes:
     * - generate_webhook_key: Generate webhook authentication key
     * - edit_business: Update business webhook URL
     * - create_transaction: Create a new payment transaction
     * - fetch_cashback_data: Retrieve cashback configuration
     *
     * ----
     *
     * @param string $route The route identifier
     * @param array|null $request_body  Request payload (optional)
     * @param bool $api_mode  When true, throws exceptions on API errors. When false, returns raw response.
     * @return void | array
     *
     * @throws \RuntimeException When route handler not found, response is invalid, or API returns error
     * @throws GuzzleException When HTTP request fails
     *
     * @since 1.0.0
     */
    public function dispatch(
        string $route,
        ?array $request_body = null,
        bool $api_mode = true,
    ): ?array {
        $handler = $this->routes[$route];

        if (empty($handler) && $api_mode) {
            throw new \RuntimeException("API Error: No Handler", 400);
        }

        $route_data = $handler($request_body);

        try {
            $response = $this->httpClient->request(
                $route_data["method"],
                $route_data["path"],
                $route_data["options"],
            );
            $body = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException("Invalid JSON response", 400);
            }

            if (empty($body) && $api_mode) {
                throw new \RuntimeException("API Error: Empty Body", 400);
            }

            if (empty($body["data"]) && $api_mode) {
                throw new \RuntimeException(
                    "API Error: No data returned " . $body["message"],
                    400,
                );
            }

            return $body["data"] ?? $body;
        } catch (GuzzleException $error) {
            $this->logger->error("FLIZpay API call failed", [
                "method" => $route_data["method"],
                "endpoint" => $route_data["path"],
                "error" => $error->getMessage(),
            ]);

            $this->sentryReporter->report($error, [
                "method" => $route_data["method"],
                "endpoint" => $route_data["path"],
            ]);

            throw new \RuntimeException(
                "FLIZpay API error: " . $error->getMessage(),
            );
        }
    }
}
