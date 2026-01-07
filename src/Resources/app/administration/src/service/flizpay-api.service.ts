/**
 * FLIZpay API Service
 *
 * Service for communicating with FLIZpay API endpoints
 */
import type { AxiosInstance, AxiosResponse } from "axios";
import type { LoginService } from "src/core/service/login.service";
import type {
  FlizpayTestConnectionRequest,
  FlizpayTestConnectionResponse,
} from "../types/flizpay.types";

const { ApiService } = Shopware.Classes;

class FlizpayApiService extends ApiService {
  public readonly name: string = "flizpayApiService";

  constructor(
    httpClient: AxiosInstance,
    loginService: LoginService,
    apiEndpoint: string = "flizpay",
  ) {
    super(httpClient, loginService, apiEndpoint);
  }

  /**
   * Configure payment gateway - initializes webhook integration and fetches configuration
   */
  public configurePaymentGateway(
    apiKey: string,
    salesChannelId: string | null = null,
  ): Promise<FlizpayTestConnectionResponse> {
    const headers = this.getBasicHeaders();

    const payload: FlizpayTestConnectionRequest = {
      apiKey,
      salesChannelId,
    };

    return this.httpClient
      .post<FlizpayTestConnectionResponse>(
        `_action/${this.getApiBasePath()}/configure-payment-gateway`,
        payload,
        { headers },
      )
      .then((response: AxiosResponse<FlizpayTestConnectionResponse>) => {
        return ApiService.handleResponse(
          response,
        ) as FlizpayTestConnectionResponse;
      });
  }

  /**
   * Get the API base path for this service
   */
  public getApiBasePath(): string {
    return this.apiEndpoint;
  }
}

export default FlizpayApiService;
