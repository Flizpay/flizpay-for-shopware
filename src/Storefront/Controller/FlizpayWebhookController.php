<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware\Storefront\Controller;

use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use FLIZpay\FlizpayForShopware\Service\FlizpayWebhookService;

#[Route(defaults: ["_routeScope" => ["storefront"]])]
class FlizpayWebhookController extends StorefrontController
{
    private FlizpayWebhookService $webhookService;

    public function __construct(FlizpayWebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    #[
        Route(
            path: "/flizpay/webhook",
            name: "frontend.flizpay.webhook",
            methods: ["POST"],
        ),
    ]
    public function webhook(
        Request $request,
        SalesChannelContext $salesChannelContext,
    ): JsonResponse
    {
        return $this->webhookService->handleWebhook(
            $request,
            $salesChannelContext->getContext(),
        );
    }
}
