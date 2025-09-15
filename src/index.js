import { ExperimentalOrderMeta, registerCheckoutFilters, TotalsItem } from "@woocommerce/blocks-checkout";
import { FormattedMonetaryAmount } from "@woocommerce/blocks-components";
import { __ } from "@wordpress/i18n";
import { registerPlugin } from "@wordpress/plugins";

import { getCurrencyFromPriceResponse } from "@woocommerce/price-format";
// import { useStoreCart } from "@woocommerce/base-context/hooks";

const modifyCartItemPrice = (defaultValue, extensions, args, validation) => {
  const { sdevs_subscription } = extensions;
  const { cartItem } = args;
  const { totals } = cartItem;

  if (totals === undefined) {
    return defaultValue;
  }
  if (totals.line_total === "0") {
    return `<price/> ${__("Due Today", "wp_subscription")}`;
  }
  if (sdevs_subscription && sdevs_subscription.type) {
    // Capitalize the first letter to match product page display
    const capitalizedType = sdevs_subscription.type.charAt(0).toUpperCase() + sdevs_subscription.type.slice(1);

    // Check max_no_payment - handle string, number, null, undefined
    const maxPayments = parseInt(sdevs_subscription.max_no_payment, 10);
    const paymentInfo = !isNaN(maxPayments) && maxPayments > 0 ? ` x ${maxPayments}` : "";
    return `<price/> / ${
      sdevs_subscription.time && sdevs_subscription.time > 1 ? " " + sdevs_subscription.time + "-" : ""
    }${capitalizedType}${paymentInfo}`;
  }
  return defaultValue;
};

const modifySubtotalPriceFormat = (defaultValue, extensions, args, validation) => {
  const { sdevs_subscription } = extensions;
  const { sdevs_subscription: recurrings } = extensions;

  if (sdevs_subscription && sdevs_subscription.type) {
    // Capitalize the first letter to match product page display
    const capitalizedType = sdevs_subscription.type.charAt(0).toUpperCase() + sdevs_subscription.type.slice(1);

    // Check max_no_payment - handle string, number, null, undefined
    const maxPayments = parseInt(sdevs_subscription.max_no_payment, 10);
    const paymentInfo = !isNaN(maxPayments) && maxPayments > 0 ? ` x ${maxPayments}` : "";
    return `<price/> ${__("Every", "wp_subscription")} ${
      sdevs_subscription.time && sdevs_subscription.time > 1 ? " " + sdevs_subscription.time + "-" : ""
    }${capitalizedType}${paymentInfo}`;
  }

  return defaultValue;
};

registerCheckoutFilters("sdevs-subscription", {
  cartItemPrice: modifyCartItemPrice,
  subtotalPriceFormat: modifySubtotalPriceFormat,
});

const RecurringTotals = ({ cart, extensions }) => {
  if (Object.keys(extensions).length === 0) {
    return;
  }
  const { cartTotals } = cart;
  const { sdevs_subscription: recurrings } = extensions;
  const currency = getCurrencyFromPriceResponse(cartTotals);
  if (recurrings.length === 0) {
    return;
  }
  return (
    <TotalsItem
      className="wc-block-components-totals-footer-item"
      label={__("Recurring totals", "wp_subscription")}
      description={
        <div style={{ display: "grid" }}>
          {recurrings.map((recurring) => {
            // Capitalize the first letter to match product page display
            const capitalizedType = recurring.type.charAt(0).toUpperCase() + recurring.type.slice(1);

            return (
              <div style={{ margin: "20px 0", float: "right" }}>
                <div style={{ fontSize: "18px" }}>
                  <FormattedMonetaryAmount currency={currency} value={parseInt(recurring.price, 10)} /> /{" "}
                  {recurring.time && recurring.time > 1
                    ? `${recurring.time + "-" + capitalizedType} `
                    : capitalizedType}
                </div>
                <small>{recurring.description}</small>
                {recurring.can_user_cancel === "yes" && (
                  <>
                    <br />
                    <small>{__("You can cancel subscription at any time!", "wp_subscription")} </small>
                  </>
                )}
                {recurring.max_no_payment > 0 && (
                  <>
                    <br />
                    <small>
                      {__("This subscription will be built for", "wp_subscription")} {recurring.max_no_payment}{" "}
                      {__("times.", "wp_subscription")}
                    </small>
                  </>
                )}
              </div>
            );
          })}
        </div>
      }
    ></TotalsItem>
  );
};

const render = () => {
  return (
    <ExperimentalOrderMeta>
      <RecurringTotals />
    </ExperimentalOrderMeta>
  );
};

registerPlugin("sdevs-subscription", {
  render,
  scope: "woocommerce-checkout",
});
