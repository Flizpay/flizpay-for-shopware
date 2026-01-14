/**
 * FLIZpay Settings Page Component
 *
 * Vue component for the FLIZpay plugin settings page
 * Automatically tests gateway connection on save (like WordPress plugin)
 */
import template from "./flizpay-settings.html.twig";
import "./flizpay-settings.scss";

import type {
  FlizpayConfig,
  FlizpayConnectionTestResult,
  FlizpayTestConnectionResponse,
  PaymentFlowOption,
  SystemConfigValues,
  FlizpayCashbackData,
} from "FlizpayForShopware/types/flizpay.types";
import type FlizpayApiService from "FlizpayForShopware/service/flizpay-api.service";

const { Component, Mixin } = Shopware;

const FLIZ_CONFIG = {
  API_KEY: "FlizpayForShopware.config.apiKey",
  WEBHOOK_URL: "FlizpayForShopware.config.webhookUrl",
  WEBHOOK_ALIVE: "FlizpayForShopware.config.webhookAlive",
  ENABLE_LOGGING: "FlizpayForShopware.config.enableLogging",
  DISPLAY_CASHBACK_IN_TITLE: "FlizpayForShopware.config.displayCashbackInTitle",
  SHOW_LOGO: "FlizpayForShopware.config.showLogo",
  SHOW_DESCRIPTION_IN_TITLE: "FlizpayForShopware.config.showDescriptionInTitle",
  SHOW_SUBTITLE: "FlizpayForShopware.config.showSubtitle",
  CASHBACK_DATA: "FlizpayForShopware.config.cashbackData",
} as const;

interface SystemConfigApiService {
  getValues(
    domain: string,
    salesChannelId?: string | null,
  ): Promise<SystemConfigValues>;
  saveValues(
    values: SystemConfigValues,
    salesChannelId?: string | null,
  ): Promise<void>;
}

interface ComponentData {
  isLoading: boolean;
  isSaving: boolean;
  config: FlizpayConfig;
  connectionStatus: FlizpayConnectionTestResult | null;
  initialApiKey: string | null;
  isWaitingForWebhook: boolean;
  currentSalesChannelId: string | null;
  pollingInterval: number | null;
  cashbackData: FlizpayCashbackData | null;
}

interface ComponentMethods {
  loadConfig(): Promise<void>;
  saveConfig(): Promise<void>;
  testGatewayConnection(): Promise<void>;
  startWebhookPolling(): void;
  stopWebhookPolling(): void;
  confirmReconfiguration(): Promise<boolean>;
  onSalesChannelChanged(salesChannelId: string | null): void;
  goToPaymentMethods(): void;
  createNotificationSuccess(config: { message: string }): void;
  createNotificationError(config: { message: string }): void;
  createNotificationWarning(config: { message: string }): void;
  $tc(key: string): string;
  $router: { push(location: { name: string }): void };
}

interface ComponentComputed {
  paymentFlowOptions: PaymentFlowOption[];
  isConnectionEstablished: boolean;
  hasCashback: boolean;
  maxCashbackValue: number;
}

interface ComponentInstance
  extends ComponentData, ComponentMethods, ComponentComputed {
  flizpayApiService: FlizpayApiService;
  systemConfigApiService: SystemConfigApiService;
}

Component.register("flizpay-settings", {
  template,

  inject: ["flizpayApiService", "systemConfigApiService"],

  mixins: [Mixin.getByName("notification")],

  data(): ComponentData {
    return {
      isLoading: true,
      isSaving: false,
      config: {
        apiKey: "",
        webhookUrl: "",
        webhookAlive: false,
        webhookKey: "",
        paymentFlow: "redirect",
        enableLogging: false,
        displayCashbackInTitle: true,
        showLogo: true,
        showDescriptionInTitle: true,
        showSubtitle: true,
      },
      connectionStatus: null,
      initialApiKey: null,
      isWaitingForWebhook: false,
      currentSalesChannelId: null,
      pollingInterval: null,
      cashbackData: null,
    };
  },

  computed: {
    // Computed property to use static assets in template
    assetFilter() {
      return Shopware.Filter.getByName("asset");
    },

    isConnectionEstablished(this: ComponentInstance): boolean {
      return this.config.webhookAlive === true;
    },

    hasCashback(this: ComponentInstance): boolean {
      if (!this.cashbackData) return false;
      return (
        (this.cashbackData.first_purchase_amount ?? 0) > 0 ||
        (this.cashbackData.standard_amount ?? 0) > 0
      );
    },

    maxCashbackValue(this: ComponentInstance): number {
      const value = !this.cashbackData
        ? 0
        : Math.max(
            this.cashbackData.first_purchase_amount ?? 0,
            this.cashbackData.standard_amount ?? 0,
          );
      return value;
    },
  },

  created(this: ComponentInstance): void {
    this.loadConfig();
  },

  beforeUnmount(this: ComponentInstance): void {
    // Clean up polling interval on component unmount
    this.stopWebhookPolling();
  },

  methods: {
    async loadConfig(this: ComponentInstance): Promise<void> {
      this.isLoading = true;

      try {
        const values: SystemConfigValues =
          await this.systemConfigApiService.getValues(
            "FlizpayForShopware.config",
            this.currentSalesChannelId,
          );

        this.config.apiKey = (values[FLIZ_CONFIG.API_KEY] as string) || "";
        this.config.webhookUrl =
          (values[FLIZ_CONFIG.WEBHOOK_URL] as string) || "";
        this.config.webhookAlive =
          (values[FLIZ_CONFIG.WEBHOOK_ALIVE] as boolean) || false;
        this.config.enableLogging =
          (values[FLIZ_CONFIG.ENABLE_LOGGING] as boolean) || false;
        this.config.displayCashbackInTitle =
          (values[FLIZ_CONFIG.DISPLAY_CASHBACK_IN_TITLE] as boolean) ?? true;
        this.config.showLogo =
          (values[FLIZ_CONFIG.SHOW_LOGO] as boolean) ?? true;
        this.config.showDescriptionInTitle =
          (values[FLIZ_CONFIG.SHOW_DESCRIPTION_IN_TITLE] as boolean) ?? true;
        this.config.showSubtitle =
          (values[FLIZ_CONFIG.SHOW_SUBTITLE] as boolean) ?? true;

        const cashbackDataJson = values[FLIZ_CONFIG.CASHBACK_DATA] as string;

        if (cashbackDataJson) {
          try {
            this.cashbackData = JSON.parse(cashbackDataJson);
          } catch (e) {
            this.cashbackData = null;
          }
        }

        this.initialApiKey = this.config.apiKey;
      } catch {
        this.createNotificationError({
          message: this.$tc("flizpay-config.errors.loadFailed"),
        });
      } finally {
        this.isLoading = false;
      }
    },

    async saveConfig(this: ComponentInstance): Promise<void> {
      if (!this.config.apiKey) {
        this.createNotificationError({
          message: this.$tc("flizpay-config.errors.apiKeyRequired"),
        });
        return;
      }

      // Check if API key changed and needs reconfiguration
      if (
        this.config.webhookAlive &&
        this.config.apiKey !== this.initialApiKey
      ) {
        const confirmed = await this.confirmReconfiguration();
        if (!confirmed) {
          return;
        }
      }

      this.isSaving = true;
      this.connectionStatus = null;

      try {
        // Step 1: Save basic config first
        await this.systemConfigApiService.saveValues(
          {
            [FLIZ_CONFIG.API_KEY]: this.config.apiKey,
            [FLIZ_CONFIG.ENABLE_LOGGING]: this.config.enableLogging,
            [FLIZ_CONFIG.DISPLAY_CASHBACK_IN_TITLE]:
              this.config.displayCashbackInTitle,
            [FLIZ_CONFIG.SHOW_LOGO]: this.config.showLogo,
            [FLIZ_CONFIG.SHOW_DESCRIPTION_IN_TITLE]:
              this.config.showDescriptionInTitle,
            [FLIZ_CONFIG.SHOW_SUBTITLE]: this.config.showSubtitle,
          },
          this.currentSalesChannelId,
        );

        this.createNotificationSuccess({
          message: this.$tc("flizpay-config.notifications.saveSuccess"),
        });

        // Step 2: Automatically test gateway connection (like WordPress)
        await this.testGatewayConnection();

        this.initialApiKey = this.config.apiKey;
      } catch (error) {
        this.createNotificationError({
          message: this.$tc("flizpay-config.errors.saveFailed"),
        });
      } finally {
        this.isSaving = false;
      }
    },

    async testGatewayConnection(this: ComponentInstance): Promise<void> {
      try {
        const response: FlizpayTestConnectionResponse =
          await this.flizpayApiService.configurePaymentGateway(
            this.config.apiKey,
            this.currentSalesChannelId,
          );

        if (response.success) {
          // Update local state with response data
          if (response.data) {
            this.config.webhookUrl =
              response.data.webhookUrl || this.config.webhookUrl;
            this.config.webhookAlive = response.data.webhookAlive || false;
          }

          // Start polling for webhook verification
          this.isWaitingForWebhook = true;
          this.connectionStatus = {
            type: "info",
            message: this.$tc("flizpay-config.connection.pending"),
          };
          this.startWebhookPolling();
        } else {
          this.connectionStatus = {
            type: "error",
            message:
              response.message || this.$tc("flizpay-config.connection.failed"),
          };
          this.createNotificationError({
            message:
              response.message || this.$tc("flizpay-config.connection.failed"),
          });
        }
      } catch (error: unknown) {
        const errorMessage =
          error instanceof Error
            ? error.message
            : this.$tc("flizpay-config.connection.failed");
        this.connectionStatus = {
          type: "error",
          message: errorMessage,
        };
      }
    },

    startWebhookPolling(this: ComponentInstance): void {
      let attempts = 0;
      const maxAttempts = 15; // 15 attempts * 2 seconds = 30 seconds

      // Clear any existing interval
      this.stopWebhookPolling();

      this.pollingInterval = window.setInterval(async () => {
        attempts++;

        try {
          // Reload config to check webhook status
          const values: SystemConfigValues =
            await this.systemConfigApiService.getValues(
              "FlizpayForShopware.config",
              this.currentSalesChannelId,
            );

          const webhookAlive = values[FLIZ_CONFIG.WEBHOOK_ALIVE] as boolean;

          if (webhookAlive) {
            // Success! Webhook verified
            this.stopWebhookPolling();
            this.config.webhookAlive = true;
            this.isWaitingForWebhook = false;

            this.connectionStatus = {
              type: "success",
              message: this.$tc("flizpay-config.connection.established"),
            };

            this.createNotificationSuccess({
              message: this.$tc("flizpay-config.connection.webhookVerified"),
            });
          } else if (attempts >= maxAttempts) {
            // Timeout - webhook not received
            this.stopWebhookPolling();
            this.isWaitingForWebhook = false;

            this.connectionStatus = {
              type: "warning",
              message:
                "Webhook verification timeout. Please check your server configuration or try again.",
            };

            this.createNotificationWarning({
              message:
                "Webhook not verified within 30 seconds. Connection may still work - please check your server logs.",
            });
          }
        } catch (error) {
          console.error("Error polling webhook status:", error);
        }
      }, 2000); // Poll every 2 seconds
    },

    stopWebhookPolling(this: ComponentInstance): void {
      if (this.pollingInterval !== null) {
        clearInterval(this.pollingInterval);
        this.pollingInterval = null;
      }
    },

    confirmReconfiguration(this: ComponentInstance): Promise<boolean> {
      return new Promise((resolve) => {
        const confirmed = confirm(
          this.$tc("flizpay-config.reconfiguration.warning"),
        );
        resolve(confirmed);
      });
    },

    onSalesChannelChanged(
      this: ComponentInstance,
      salesChannelId: string | null,
    ): void {
      this.currentSalesChannelId = salesChannelId;
      this.stopWebhookPolling();
      this.loadConfig();
    },

    goToPaymentMethods(this: ComponentInstance): void {
      this.$router.push({ name: "sw.settings.payment.overview" });
    },
  },
});
