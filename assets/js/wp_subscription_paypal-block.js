const settings = window.wc.wcSettings.getSetting("wp_subscription_paypal_data", {});

const createLabel = (title, iconUrl) => {
  return wp.element.createElement(
    "span",
    {
      style: { width: "100%" },
    },
    title,
    wp.element.createElement("img", {
      src: iconUrl,
      alt: title,
      style: { float: "right" },
    }),
  );
};
const Content = () => {
  return window.wp.htmlEntities.decodeEntities(settings.description || "");
};

const Block_Gateway = {
  name: "wp_subscription_paypal",
  label: createLabel(settings.title, settings.icon),
  content: Object(window.wp.element.createElement)(Content, null),
  edit: Object(window.wp.element.createElement)(Content, null),
  canMakePayment: () => true,
  ariaLabel: window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__("PayPal", "wp_subscription"),
  supports: {
    features: settings.features,
  },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);
