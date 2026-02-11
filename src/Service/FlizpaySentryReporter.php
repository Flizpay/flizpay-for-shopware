<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Service;

use GuzzleHttp\Client;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Lightweight Sentry error reporter using the Store Endpoint API directly.
 * No sentry/sdk dependency — just raw HTTP POST via Guzzle.
 *
 * Fire-and-forget: all calls are wrapped in try/catch with silent failure.
 * Never affects payment flow.
 *
 * @since 1.0.0
 */
class FlizpaySentryReporter
{
    private const SENTRY_DSN = "https://2dd3c24f69e7f24d0872dc22641aa683@o4507078336053248.ingest.de.sentry.io/4510863137374288";
    private const TIMEOUT = 3;
    private const RATE_LIMIT_SECONDS = 10;

    private Client $client;
    private SystemConfigService $configService;
    private float $lastReportTime = 0;

    private string $storeUrl;
    private string $publicKey;

    public function __construct(SystemConfigService $configService)
    {
        $this->configService = $configService;
        $this->client = new Client();
        $this->parseDsn();
    }

    /**
     * Report an exception to Sentry. Fire-and-forget — never throws.
     *
     * @param \Throwable $exception The exception to report
     * @param array $context Additional context (orderId, transactionId, etc.)
     * @param string $level Sentry level: fatal, error, warning, info, debug
     */
    public function report(
        \Throwable $exception,
        array $context = [],
        string $level = "error",
    ): void {
        try {
            if (!$this->shouldReport()) {
                return;
            }

            $payload = $this->buildPayload($exception, $context, $level);

            $this->client->post($this->storeUrl, [
                "headers" => [
                    "Content-Type" => "application/json",
                    "X-Sentry-Auth" => $this->buildAuthHeader(),
                ],
                "json" => $payload,
                "timeout" => self::TIMEOUT,
            ]);

            $this->lastReportTime = microtime(true);
        } catch (\Throwable) {
            // Silent — never affect payment flow
        }
    }

    private function shouldReport(): bool
    {
        // Kill switch: only report if merchant has configured the plugin
        $apiKey = $this->configService->get("FlizpayForShopware.config.apiKey");
        if (empty($apiKey)) {
            return false;
        }

        // Rate limiting: prevent flood on cascading errors
        if (
            microtime(true) - $this->lastReportTime <
            self::RATE_LIMIT_SECONDS
        ) {
            return false;
        }

        return true;
    }

    private function buildPayload(
        \Throwable $exception,
        array $context,
        string $level,
    ): array {
        $businessId =
            $this->configService->get("FlizpayForShopware.config.businessId") ??
            "unknown";

        return [
            "event_id" => bin2hex(random_bytes(16)),
            "timestamp" => gmdate("Y-m-d\TH:i:s\Z"),
            "level" => $level,
            "platform" => "php",
            "logger" => "flizpay-shopware-sentry-reporter",
            "release" => "flizpay-shopware@" . $this->getPluginVersion(),
            "exception" => [
                "values" => [
                    [
                        "type" => get_class($exception),
                        "value" => $exception->getMessage(),
                        "stacktrace" => [
                            "frames" => $this->buildFrames($exception),
                        ],
                    ],
                ],
            ],
            "tags" => [
                "plugin_version" => $this->getPluginVersion(),
                "shopware_version" => $this->getShopwareVersion(),
                "php_version" => PHP_VERSION,
                "businessId" => $businessId,
            ],
            "request" => $this->buildRequest(),
            "extra" => $context,
            "server_name" => "shopware-plugin",
        ];
    }

    private function buildRequest(): array
    {
        $request = [];

        if (!empty($_SERVER["REQUEST_METHOD"])) {
            $request["method"] = $_SERVER["REQUEST_METHOD"];
        }

        if (!empty($_SERVER["REQUEST_URI"])) {
            $scheme =
                !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off"
                    ? "https"
                    : "http";
            $host =
                $_SERVER["HTTP_HOST"] ?? ($_SERVER["SERVER_NAME"] ?? "unknown");
            $request["url"] = $scheme . "://" . $host . $_SERVER["REQUEST_URI"];
        }

        if (!empty($_SERVER["QUERY_STRING"])) {
            $queryData = [];
            parse_str($_SERVER["QUERY_STRING"], $queryData);
            $request["query_string"] = $queryData;
        }

        return $request;
    }

    private function buildFrames(\Throwable $exception): array
    {
        $frames = [];
        foreach ($exception->getTrace() as $frame) {
            $frames[] = [
                "filename" => $frame["file"] ?? "unknown",
                "lineno" => $frame["line"] ?? 0,
                "function" => $frame["function"] ?? "unknown",
                "module" => $frame["class"] ?? null,
            ];
        }

        // Sentry expects frames in reverse order (oldest call first)
        return array_reverse($frames);
    }

    private function buildAuthHeader(): string
    {
        return sprintf(
            "Sentry sentry_version=7, sentry_client=flizpay-plugin/1.0, sentry_key=%s",
            $this->publicKey,
        );
    }

    private function parseDsn(): void
    {
        $parsed = parse_url(self::SENTRY_DSN);
        $this->publicKey = $parsed["user"] ?? "";
        $projectId = ltrim($parsed["path"] ?? "", "/");
        $this->storeUrl = sprintf(
            "%s://%s/api/%s/store/",
            $parsed["scheme"] ?? "https",
            $parsed["host"] ?? "",
            $projectId,
        );
    }

    private function getPluginVersion(): string
    {
        $composerFile = dirname(__DIR__, 2) . "/composer.json";
        if (file_exists($composerFile)) {
            $data = json_decode(
                (string) file_get_contents($composerFile),
                true,
            );
            return $data["version"] ?? "unknown";
        }
        return "unknown";
    }

    private function getShopwareVersion(): string
    {
        try {
            return \Composer\InstalledVersions::getVersion("shopware/core") ??
                (\Composer\InstalledVersions::getVersion("shopware/platform") ??
                    "unknown");
        } catch (\Throwable) {
            return "unknown";
        }
    }
}
