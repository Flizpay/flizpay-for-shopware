<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Service;

use Shopware\Core\Checkout\Order\OrderEntity;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class FlizpayApiService
{
    private FlizpayApi $client;
    private RouterInterface $router;

    public function __construct(FlizpayApi $client, RouterInterface $router)
    {
        $this->client = $client;
        $this->router = $router;
    }

    public function get_webhook_key(): ?string
    {
        $response = $this->client->dispatch(
            "generate_webhook_key",
            null,
            false,
        );

        return $response["webhookKey"] ?? null;
    }

    public function generate_webhook_url(): ?string
    {
        // Generate absolute URL with HTTPS
        $webhookUrl = $this->router->generate(
            "frontend.flizpay.webhook", // route name
            [],
            UrlGeneratorInterface::ABSOLUTE_URL, // generates full URL with domain
        );

        // Ensure HTTPS
        $webhookUrl = str_replace("http://", "https://", $webhookUrl);

        // Check if there's a custom webhook base URL configured (for Tailscale/ngrok/etc)
        // This allows overriding localhost URLs that aren't accessible externally
        $customBaseUrl = getenv("FLIZPAY_WEBHOOK_BASE_URL") ?: null;
        if ($customBaseUrl) {
            // Replace the base URL but keep the path
            $path = parse_url($webhookUrl, PHP_URL_PATH);
            $webhookUrl = rtrim($customBaseUrl, "/") . $path;
        }

        // Register webhook URL with Flizpay backend
        // IMPORTANT: Use camelCase "webhookUrl" parameter (not snake_case "webhook_url")
        $response = $this->client->dispatch(
            "edit_business",
            ["webhookUrl" => $webhookUrl], // ✓ Fixed: camelCase to match API expectation
            false,
        );

        // Validate response like WordPress does
        $webhookUrlResponse = $response["webhookUrl"] ?? null;

        if ($webhookUrlResponse !== $webhookUrl) {
            // API didn't save the webhook URL correctly
            return null;
        }

        return $webhookUrlResponse;
    }

    public function fetch_cashback_data(): ?array
    {
        $response = $this->client->dispatch("fetch_cashback_data", null, false);

        if (
            isset($response["cashbacks"]) &&
            count($response["cashbacks"]) > 0
        ) {
            foreach ($response["cashbacks"] as $cashback) {
                $firstPurchaseAmount = floatval(
                    $cashback["firstPurchaseAmount"],
                );
                $amount = floatval($cashback["amount"]);

                if (
                    $cashback["active"] &&
                    ($firstPurchaseAmount > 0 || $amount > 0)
                ) {
                    return [
                        "first_purchase_amount" => $firstPurchaseAmount,
                        "standard_amount" => $amount,
                    ];
                }
            }
        }

        return null;
    }

    public function create_transaction(
        OrderEntity $order,
        string $source,
    ): ?string {
        $orderCustomer = $order->getOrderCustomer();

        if (!$orderCustomer) {
            throw new \RuntimeException("Order customer not found", 401);
        }

        $customer = [
            "email" => $orderCustomer->getEmail(),
            "firstName" => $orderCustomer->getFirstName(),
            "lastName" => $orderCustomer->getLastName(),
        ];

        $currency = $order->getCurrency();
        if (!$currency) {
            throw new \RuntimeException("Order currency not found");
        }

        $body = [
            "amount" => $order->getAmountTotal(),
            "currency" => $currency->getIsoCode(),
            "externalId" => $order->getId(),
            "successUrl" => $this->get_success_url($order),
            "failureUrl" => $this->get_failure_url($order),
            "customer" => $customer,
            "source" => $source,
            "needsShipping" => $this->needs_shipping($order),
        ];

        $response = $this->client->dispatch("create_transaction", $body, false);

        return $response["redirectUrl"] ?? null;
    }

    private function needs_shipping(OrderEntity $order): bool
    {
        $deliveries = $order->getDeliveries();
        return $deliveries !== null && $deliveries->count() > 0;
    }

    private function get_success_url(OrderEntity $order): string
    {
        // Generate URL to order confirmation page
        return $this->router->generate(
            "frontend.checkout.finish.page",
            [
                "orderId" => $order->getId(),
                "deepLinkCode" => $order->getDeepLinkCode(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    private function get_failure_url(OrderEntity $order): string
    {
        // Generate URL to checkout page or error page
        return $this->router->generate(
            "frontend.checkout.cart.page",
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
