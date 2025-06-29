jQuery(document).ready(function ($) {
  function addPasswordToggle(inputId) {
    const $input = $("#" + inputId);
    if (!$input.length) return;

    // Default value.
    let isPassword = $input.attr("type") === "password";

    // Create Dashicon toggle button
    const $toggleBtn = $("<button>")
      .attr("type", "button")
      .addClass("dashicons")
      .addClass(isPassword ? "dashicons-visibility" : "dashicons-hidden")
      .css({
        border: $input.css("border"),
        borderRadius: $input.css("border-radius"),
        background: $input.css("background-color"),
        height: $input.height(),
        minHeight: "30px",
        width: "35px",
        margin: "0px 8px",
        fontSize: "18px",
        cursor: "pointer",
        lineHeight: 1,
      })
      .attr("title", "Show password");

    // Toggle functionality
    $toggleBtn.on("click", function () {
      isPassword = $input.attr("type") === "password";
      $input.attr("type", isPassword ? "text" : "password");
      $toggleBtn
        .removeClass("dashicons-visibility dashicons-hidden")
        .addClass(isPassword ? "dashicons-hidden" : "dashicons-visibility")
        .attr("title", isPassword ? "Hide password" : "Show password");
    });

    // Insert button
    $input.after($toggleBtn);
  }

  // Add copy button
  function addCopyButton(inputId) {
    const $input = $("#" + inputId);
    if (!$input.length) return;

    // Create Dashicon copy button
    const $copyBtn = $("<button>")
      .attr("type", "button")
      .addClass("dashicons dashicons-admin-page")
      .css({
        border: $input.css("border"),
        borderRadius: $input.css("border-radius"),
        background: $input.css("background-color"),
        height: $input.height(),
        minHeight: "30px",
        width: "35px",
        margin: "0px 8px",
        fontSize: "18px",
        cursor: "pointer",
        lineHeight: 1,
      })
      .attr("title", "Copy to clipboard");

    // Copy functionality
    $copyBtn.on("click", function (event) {
      event.preventDefault();
      const text = $input.val();

      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text);
        } else {
          const tempInput = document.createElement("textarea");
          tempInput.value = text;
          document.body.appendChild(tempInput);
          tempInput.select();
          document.execCommand("copy");
          document.body.removeChild(tempInput);
        }
        alert("Data successfully copied to clipboard.");
      } catch (err) {
        alert("Failed to the data. Please try to copy manually.");
        console.error("Failed to the data. ", err);
      }
    });

    // Insert button
    $input.after($copyBtn);
  }

  // Add toggles
  addPasswordToggle("woocommerce_wp_subscription_paypal_client_id");
  addPasswordToggle("woocommerce_wp_subscription_paypal_client_secret");

  // Add copy buttons
  addCopyButton("woocommerce_wp_subscription_paypal_webhook_url");
});
