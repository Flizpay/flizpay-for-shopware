/**
 * FLIZpay Plugin Type Definitions
 */

import type FlizpayApiService from "../service/flizpay-api.service";

declare global {
  interface ServiceContainer {
    flizpayApiService: FlizpayApiService;
  }
}

export interface FlizpayConfig {
  apiKey: string;
  sandboxMode: boolean;
  webhookUrl: string;
  webhookAlive: boolean;
  webhookKey: string;
  paymentFlow: "redirect" | "embedded";
  enableLogging: boolean;
}

export interface FlizpayTestConnectionRequest {
  apiKey: string;
  salesChannelId: string | null;
}

export interface FlizpayTestConnectionResponse {
  success: boolean;
  message: string;
  data?: {
    webhookUrl: string | null;
    webhookAlive: boolean;
    hasCashback: boolean;
  };
  error?: string;
}

export interface FlizpayConnectionTestResult {
  type: "success" | "error" | "info" | "warning";
  message: string;
}

export interface PaymentFlowOption {
  value: "redirect" | "embedded";
  label: string;
}

export type SystemConfigValue = string | boolean | number | null | undefined;

export interface SystemConfigValues {
  [key: string]: SystemConfigValue;
}
