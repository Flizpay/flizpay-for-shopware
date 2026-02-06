<?php declare(strict_types=1);

namespace FLIZpay\FlizpayForShopware;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use FLIZpay\FlizpayForShopware\Service\PaymentMethodInstaller;
use FLIZpay\FlizpayForShopware\Service\FlizpayApi;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class FlizpayForShopware extends Plugin
{
    public function configureRoutes(
        RoutingConfigurator $routes,
        string $environment,
    ): void {
        $routes->import(__DIR__ . "/Resources/config/routes.xml");
    }
    public function install(InstallContext $installContext): void
    {
        $this->getPaymentMethodInstaller()->install(
            $installContext->getContext(),
        );
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        $this->getPaymentMethodInstaller()->setIsActive(
            false,
            $uninstallContext->getContext(),
        );

        if ($uninstallContext->keepUserData()) {
            return;
        }

        // Clean up configuration
        $this->cleanupConfiguration();
    }

    public function activate(ActivateContext $activateContext): void
    {
        $this->getPaymentMethodInstaller()->setIsActive(
            true,
            $activateContext->getContext(),
        );
        parent::activate($activateContext);

        // Notify Flizpay backend of activation
        $this->notifyFlizpayStatus(true);
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->getPaymentMethodInstaller()->setIsActive(
            false,
            $deactivateContext->getContext(),
        );
        parent::deactivate($deactivateContext);

        // Notify Flizpay backend of deactivation
        $this->notifyFlizpayStatus(false);
    }

    private function getPaymentMethodInstaller(): PaymentMethodInstaller
    {
        /** @var EntityRepository $paymentMethodRepository */
        $paymentMethodRepository = $this->container->get(
            "payment_method.repository",
        );

        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);

        return new PaymentMethodInstaller(
            $paymentMethodRepository,
            $pluginIdProvider,
        );
    }

    /**
     * Notify Flizpay backend of plugin activation/deactivation status
     *
     * @param bool $isActive
     * @return void
     */
    private function notifyFlizpayStatus(bool $isActive): void
    {
        try {
            // Silently skip if container is not available
            if (!$this->container) {
                return;
            }

            /** @var SystemConfigService $systemConfig */
            $systemConfig = $this->container->get(SystemConfigService::class);
            $apiKey = $systemConfig->getString(
                "FlizpayForShopware.config.apiKey",
            );

            if (!$apiKey) {
                return; // Not configured yet
            }

            /** @var FlizpayApi $flizpayApi */
            $flizpayApi = $this->container->get(FlizpayApi::class);

            $flizpayApi->dispatch(
                "edit_business",
                [
                    "isActive" => $isActive,
                    "pluginVersion" => $this->getVersion(),
                ],
                false,
            );
        } catch (\Exception $e) {
            // Silently fail - don't block activation/deactivation
            // Logger might not be available during lifecycle operations
        }
    }

    /**
     * Clean up all plugin configuration on uninstall
     *
     * @return void
     */
    private function cleanupConfiguration(): void
    {
        $this->notifyFlizpayStatus(false);

        try {
            // Silently skip if container is not available
            if (!$this->container) {
                return;
            }

            /** @var SystemConfigService $systemConfig */
            $systemConfig = $this->container->get(SystemConfigService::class);
            $apiKey = $systemConfig->getString(
                "FlizpayForShopware.config.apiKey",
            );

            if (!$apiKey) {
                return; // Not configured yet
            }

            /** @var FlizpayApi $flizpayApi */
            $flizpayApi = $this->container->get(FlizpayApi::class);
            $flizpayApi->dispatch("edit_business", ["webhookUrl" => ""], false);

            /** @var Connection $connection */
            $connection = $this->container->get(Connection::class);
            $connection->executeStatement(
                "DELETE FROM system_config
                 WHERE configuration_key LIKE 'FlizpayForShopware.config.%'",
            );
        } catch (\Exception $e) {
            // Silently fail - don't block activation/deactivation
            // Logger might not be available during lifecycle operations
        }
    }

    /**
     * Get plugin version from composer.json
     *
     * @return string
     */
    private function getVersion(): string
    {
        $composerFile = $this->getPath() . "/composer.json";
        if (!file_exists($composerFile)) {
            return "1.0.0";
        }

        $composerJson = json_decode(file_get_contents($composerFile), true);
        return $composerJson["version"] ?? "1.0.0";
    }
}
