/* global subscrptIntegrations */

var SUBSCRPT_SPINNER_SVG =
  '<svg xmlns="http://www.w3.org/2000/svg"' +
  ' style="animation:subscrptSpin 0.75s linear infinite;vertical-align:-3px;margin-right:5px;display:inline-block;"' +
  ' width="13" height="13" viewBox="0 0 13 13" fill="none">' +
  '<circle cx="6.5" cy="6.5" r="5" stroke="currentColor" stroke-opacity="0.25" stroke-width="2"/>' +
  '<path d="M6.5 1.5A5 5 0 0 1 11.5 6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' +
  "</svg>";

(function () {
  var style = document.createElement("style");
  style.textContent = "@keyframes subscrptSpin{to{transform:rotate(360deg)}}";
  document.head.appendChild(style);
})();

/**
 * One-click install and activate a payment gateway plugin from the integrations page.
 *
 * @param {HTMLButtonElement} btn  The clicked button element.
 * @param {string}            slug The wp.org plugin slug.
 */
function subscrptInstallPlugin(btn, slug) {
  var originalHTML = btn.innerHTML;
  var originalCursor = btn.style.cursor;

  btn.disabled = true;
  btn.style.cursor = "wait";
  btn.innerHTML = SUBSCRPT_SPINNER_SVG + "Installing…";

  var data = new FormData();
  data.append("action", "subscrpt_install_integration_plugin");
  data.append("plugin_slug", slug);
  data.append("nonce", subscrptIntegrations.nonce);

  fetch(subscrptIntegrations.ajaxUrl, { method: "POST", body: data })
    .then(function (response) {
      return response.json();
    })
    .then(function (result) {
      if (result.success) {
        var url = new URL(window.location.href);
        url.searchParams.set("subscrpt_installed", "1");
        window.location.href = url.toString();
      } else {
        btn.disabled = false;
        btn.style.cursor = originalCursor;
        btn.innerHTML = originalHTML;
        // eslint-disable-next-line no-alert
        window.alert((result.data && result.data.message) || "Installation failed. Please try again.");
      }
    })
    .catch(function () {
      btn.disabled = false;
      btn.style.cursor = originalCursor;
      btn.innerHTML = originalHTML;
      // eslint-disable-next-line no-alert
      window.alert("Installation failed. Please try again.");
    });
}

function wpSubsInstallPaypalIntegration() {
  jQuery
    .post(wpSubsIntegrations.ajax_url, {
      action_callback: "wp_subs_install_paypal_integration",
      nonce: wpSubsIntegrations.nonce,
    })
    .done(function (response) {
      console.log(response);

      if (response.success) {
        console.log("Option updated:", response.data);
      } else {
        console.error("Failed to update:", response.data);
      }
    })
    .fail(function () {
      console.error("AJAX request failed");
    });
}
