/**
 * This file is for forwarding paddle webhooks to local setup.
 * * Run `yarn listen-webhook` or `npm run listen-webhook` to start.
 *
 * ! You may need to disable webhook signature verification to work properly.
 * ? Comment '$this->lets_verify_paddle_api' in '/includes/Gateways/Paddle/Helpers/Smartpay_Paddle_Webhook_Handler.php'.
 *
 * ? Duplicate this file and rename it to 'smee.js'.
 * ? Go to 'smee.io' and click 'Start a new channel' and use that URL in the sourceUrl below and in Paddle notifications setup.
 * ? Copy the Webhook URL from plugin settings and use that in the targetUrl below.
 */

const sourceUrl = "https://smee.io/wp-subs-webhook-test"; // Your smee provided URL
const targetUrl = "https://wordpress-woo.test/?wc-api=wp_subscription_paypal"; // Your local plugin webhook URL

const SmeeClient = require("smee-client");

const smee = new SmeeClient({
  source: sourceUrl,
  target: targetUrl,
  logger: {
    info: (...args) => {
      const text = args.join(" ");

      const match = text.match(/"event_type":"([^"]+)"/);

      if (match) {
        console.log("Webhook Event:", match[1]);
      }
    },

    error: console.error,
  },
});

smee.start();

console.log("Listening for webhooks...");
