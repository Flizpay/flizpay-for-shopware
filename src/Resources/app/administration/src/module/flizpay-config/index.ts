/**
 * FLIZpay Configuration Module
 *
 * Registers the FLIZpay settings module in Shopware administration
 */
import type { ModuleManifest } from "src/core/factory/module.factory";
import "./page/flizpay-settings";

const { Module } = Shopware;

const moduleConfig: ModuleManifest = {
  type: "plugin",
  name: "flizpay-config",
  title: "flizpay-config.general.mainMenuItemGeneral",
  description: "flizpay-config.general.description",
  color: "#00D094",
  icon: "regular-credit-card",

  routes: {
    settings: {
      component: "flizpay-settings",
      path: "settings",
      meta: {
        parentPath: "sw.settings.index",
      },
    },
  },

  settingsItem: [
    {
      to: "flizpay.config.settings",
      group: "plugins",
      iconComponent: "flizpay-icon",
    },
  ],

  navigation: [
    {
      id: "flizpay-config",
      label: "flizpay-config.general.mainMenuItemGeneral",
      color: "#00D094",
      icon: "regular-credit-card",
      path: "flizpay.config.settings",
      parent: "sw-settings",
      position: 100,
    },
  ],
};

Module.register("flizpay-config", moduleConfig);
