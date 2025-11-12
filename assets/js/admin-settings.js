// * ----- Disable Enforce Login based on Guest Checkout Setting ----- * //
jQuery(document).ready(($) => {
  const guestCheckout = $("#wp_subscription_allow_guest_checkout");
  const enforceLogin = $("#wp_subscription_enforce_login");

  function toggleEnforceLogin() {
    enforceLogin.prop("disabled", !guestCheckout.is(":checked"));
  }

  // Initial state
  toggleEnforceLogin();

  // On change
  guestCheckout.on("change", toggleEnforceLogin);
});
// * ----- Disable Enforce Login based on Guest Checkout Setting ----- * //
