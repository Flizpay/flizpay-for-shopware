/**
 * FLIZpay API Service Initialization
 */
import FlizpayApiService from "FlizpayForShopware/service/flizpay-api.service";

const { Application } = Shopware;

const initContainer = Application.getContainer("init");

Application.addServiceProvider(
  "flizpayApiService",
  (container) =>
    new FlizpayApiService(initContainer.httpClient, container.loginService),
);
