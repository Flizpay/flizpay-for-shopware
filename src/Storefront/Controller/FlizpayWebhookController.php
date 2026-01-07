<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Storefront\Controller;

use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use FLIZpay\FlizpayForShopware\Service\FlizpayWebhookService;
use Psr\Log\LoggerInterface;

#[Route(defaults: ["_routeScope" => ["storefront"]])]
class FlizpayWebhookController extends StorefrontController
{
    private FlizpayWebhookService $webhookService;
    private LoggerInterface $logger;

    public function __construct(
        FlizpayWebhookService $webhookService,
        LoggerInterface $logger,
    ) {
        $this->webhookService = $webhookService;
        $this->logger = $logger;
    }

    #[
        Route(
            path: "/flizpay/webhook",
            name: "frontend.flizpay.webhook",
            methods: ["POST"],
        ),
    ]
    public function webhook(Request $request): JsonResponse
    {
        return $this->webhookService->handleWebhook($request);
    }
}
