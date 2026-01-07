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
} from "FlizpayForShopware/types/flizpay.types";
import type FlizpayApiService from "FlizpayForShopware/service/flizpay-api.service";

const { Component, Mixin } = Shopware;

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
}

interface ComponentMethods {
  loadConfig(): Promise<void>;
  saveConfig(): Promise<void>;
  testGatewayConnection(): Promise<void>;
  startWebhookPolling(): void;
  stopWebhookPolling(): void;
  confirmReconfiguration(): Promise<boolean>;
  onSalesChannelChanged(salesChannelId: string | null): void;
  createNotificationSuccess(config: { message: string }): void;
  createNotificationError(config: { message: string }): void;
  createNotificationWarning(config: { message: string }): void;
  $tc(key: string): string;
}

interface ComponentComputed {
  paymentFlowOptions: PaymentFlowOption[];
  isConnectionEstablished: boolean;
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
        sandboxMode: true,
        webhookUrl: "",
        webhookAlive: false,
        webhookKey: "",
        paymentFlow: "redirect",
        enableLogging: false,
      },
      connectionStatus: null,
      initialApiKey: null,
      isWaitingForWebhook: false,
      currentSalesChannelId: null,
      pollingInterval: null,
    };
  },

  computed: {
    paymentFlowOptions(this: ComponentInstance): PaymentFlowOption[] {
      return [
        {
          value: "redirect",
          label: this.$tc("flizpay-config.paymentFlow.redirect"),
        },
        {
          value: "embedded",
          label: this.$tc("flizpay-config.paymentFlow.embedded"),
        },
      ];
    },

    isConnectionEstablished(this: ComponentInstance): boolean {
      return this.config.webhookAlive === true;
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
            "FlizpayPayment.config",
            this.currentSalesChannelId,
          );

        this.config.apiKey =
          (values["FlizpayPayment.config.apiKey"] as string) || "";
        this.config.sandboxMode =
          (values["FlizpayPayment.config.sandboxMode"] as boolean) ?? true;
        this.config.webhookUrl =
          (values["FlizpayPayment.config.webhookUrl"] as string) || "";
        this.config.webhookAlive =
          (values["FlizpayPayment.config.webhookAlive"] as boolean) || false;
        this.config.paymentFlow =
          (values["FlizpayPayment.config.paymentFlow"] as
            | "redirect"
            | "embedded") || "redirect";
        this.config.enableLogging =
          (values["FlizpayPayment.config.enableLogging"] as boolean) || false;

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
            "FlizpayPayment.config.apiKey": this.config.apiKey,
            "FlizpayPayment.config.sandboxMode": this.config.sandboxMode,
            "FlizpayPayment.config.paymentFlow": this.config.paymentFlow,
            "FlizpayPayment.config.enableLogging": this.config.enableLogging,
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
        console.error("Save config error:", error);
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
              "FlizpayPayment.config",
              this.currentSalesChannelId,
            );

          const webhookAlive = values[
            "FlizpayPayment.config.webhookAlive"
          ] as boolean;

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
  },
});
