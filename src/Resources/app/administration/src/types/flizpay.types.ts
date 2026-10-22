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
  webhookUrl: string;
  webhookAlive: boolean;
  enableLogging: boolean;
  /** Switches the <fliz-checkout> title surface on. */
  showHeadline: boolean;
  showLogo: boolean;
  /** Switches the <fliz-checkout> description surface on. */
  showDescription: boolean;
  widgetProduct: boolean;
  widgetListing: boolean;
  widgetCart: boolean;
  widgetMiniCart: boolean;
}

export interface FlizpayWidgetPreview {
  scriptUrl: string;
  publicId: string;
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
    publicId: string;
  };
  error?: string;
}

export type SystemConfigValue = string | boolean | number | null | undefined;

export interface SystemConfigValues {
  [key: string]: SystemConfigValue;
}
