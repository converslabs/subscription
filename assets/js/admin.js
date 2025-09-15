jQuery(document).ready(() => {
  let sdevs_enable_subscription = jQuery("input#subscrpt_enable");
  sdevs_enable_subscription.change(() => {
    if (sdevs_enable_subscription.is(":checked")) {
      jQuery(".show_if_subscription").show();
    } else {
      jQuery(".show_if_subscription").hide();
    }
  });
  if (sdevs_enable_subscription.is(":checked")) {
    jQuery(".show_if_subscription").show();
  } else {
    jQuery(".show_if_subscription").hide();
  }

  jQuery(document).on("woocommerce_variations_loaded", () => {
    let total_variations = JSON.parse(jQuery(".woocommerce_variations").attr("data-total"));
    for (let index = 0; index < total_variations; index++) {
      let element = document.getElementById("subscrpt_enable[" + index + "]");
      if (element && element.checked) {
        jQuery(".show_if_subscription_" + index).show();
      } else {
        jQuery(".show_if_subscription_" + index).hide();
      }
    }
  });

  let subscrpt_renewal_process = jQuery("#subscrpt_renewal_process");
  subscrpt_renewal_process.change(() => {
    if (subscrpt_renewal_process.val() === "manual") {
      jQuery("#sdevs_renewal_cart_tr").show();
      jQuery("#subscrpt_stripe_auto_renew_tr").hide();
      jQuery("#subscrpt_auto_renewal_toggle_tr").hide();
    } else {
      jQuery("#sdevs_renewal_cart_tr").hide();
      jQuery("#subscrpt_stripe_auto_renew_tr").show();
      jQuery("#subscrpt_auto_renewal_toggle_tr").show();
    }
  });
  if (subscrpt_renewal_process.val() === "manual") {
    jQuery("#sdevs_renewal_cart_tr").show();
    jQuery("#subscrpt_stripe_auto_renew_tr").hide();
    jQuery("#subscrpt_auto_renewal_toggle_tr").hide();
  } else {
    jQuery("#sdevs_renewal_cart_tr").hide();
    jQuery("#subscrpt_stripe_auto_renew_tr").show();
    jQuery("#subscrpt_auto_renewal_toggle_tr").show();
  }

  // Handle "select all" checkbox for subscription list
  jQuery("#cb-select-all-1").on("change", function () {
    jQuery('input[name="subscription_ids[]"]').prop("checked", this.checked);
  });

  // Update "select all" checkbox when individual checkboxes change
  jQuery('input[name="subscription_ids[]"]').on("change", function () {
    var totalCheckboxes = jQuery('input[name="subscription_ids[]"]').length;
    var checkedCheckboxes = jQuery('input[name="subscription_ids[]"]:checked').length;

    if (checkedCheckboxes === 0) {
      jQuery("#cb-select-all-1").prop("indeterminate", false).prop("checked", false);
    } else if (checkedCheckboxes === totalCheckboxes) {
      jQuery("#cb-select-all-1").prop("indeterminate", false).prop("checked", true);
    } else {
      jQuery("#cb-select-all-1").prop("indeterminate", true);
    }
  });

  // Handle bulk action form submission
  jQuery("#subscriptions-form").on("submit", function (e) {
    // Check if this is a bulk action submission by checking the submitter
    var submitter = e.originalEvent ? e.originalEvent.submitter : null;
    var isBulkAction = submitter && (submitter.name === "bulk_action" || submitter.name === "bulk_action2");

    if (!isBulkAction) {
      // This is a filter submission, allow it to proceed
      return true;
    }

    // Prevent default form submission for bulk actions
    e.preventDefault();

    var action = jQuery('select[name="action"]').val();
    var action2 = jQuery('select[name="action2"]').val();
    var selectedAction = action !== "-1" ? action : action2;

    if (selectedAction === "-1") {
      alert("Please select a bulk action.");
      return false;
    }

    var checkedBoxes = jQuery('input[name="subscription_ids[]"]:checked');
    if (checkedBoxes.length === 0) {
      alert("Please select at least one subscription.");
      return false;
    }

    // Confirm destructive actions
    if (selectedAction === "delete") {
      if (
        !confirm(
          "Are you sure you want to permanently delete the selected subscriptions? This action cannot be undone.",
        )
      ) {
        return false;
      }
    } else if (selectedAction === "trash") {
      if (!confirm("Are you sure you want to move the selected subscriptions to trash?")) {
        return false;
      }
    }

    // Handle bulk action via AJAX
    handleBulkAction(selectedAction, checkedBoxes);
  });

  // AJAX function to handle bulk actions
  function handleBulkAction(action, checkedBoxes) {
    var subscriptionIds = [];
    checkedBoxes.each(function () {
      subscriptionIds.push(jQuery(this).val());
    });

    // Show loading state
    var submitButton = jQuery('input[name="bulk_action"]:focus, input[name="bulk_action2"]:focus');
    var originalText = submitButton.val();
    submitButton.val("Processing...").prop("disabled", true);

    // Make AJAX request
    jQuery.ajax({
      url: wp_subscription_ajax.ajaxurl,
      type: "POST",
      data: {
        action: "wp_subscription_bulk_action",
        bulk_action: action,
        subscription_ids: subscriptionIds,
        nonce: wp_subscription_ajax.nonce,
      },
      success: function (response) {
        if (response.success) {
          // Show success message
          showAdminNotice(response.data.message, "success");
          // Reload the page to reflect changes
          setTimeout(function () {
            window.location.reload();
          }, 1000);
        } else {
          // Show error message
          showAdminNotice(response.data.message || "An error occurred while processing the bulk action.", "error");
        }
      },
      error: function () {
        showAdminNotice("An error occurred while processing the bulk action.", "error");
      },
      complete: function () {
        // Reset button state
        submitButton.val(originalText).prop("disabled", false);
      },
    });
  }

  // Function to show admin notices
  function showAdminNotice(message, type) {
    var noticeClass = type === "success" ? "notice-success" : "notice-error";
    var notice = jQuery('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + "</p></div>");

    // Insert notice at the top of the page
    jQuery(".wp-subscription-admin-content").prepend(notice);

    // Auto-dismiss after 5 seconds
    setTimeout(function () {
      notice.fadeOut(function () {
        jQuery(this).remove();
      });
    }, 5000);
  }
});

function hellochange(index) {
  if (document.getElementById("subscrpt_enable[" + index + "]").checked) {
    jQuery(".show_if_subscription_" + index).show();
  } else {
    jQuery(".show_if_subscription_" + index).hide();
  }
}

let subscrpt_product_type = jQuery("#product-type");
let latest_value_of_subscrpt_product_type = subscrpt_product_type.val();

subscrpt_product_type.change(() => {
  if (
    "simple" === latest_value_of_subscrpt_product_type &&
    "simple" !== subscrpt_product_type.val() &&
    "variable" !== subscrpt_product_type.val()
  ) {
    const confirmTypeChange = confirm(
      "Are you sure to change the product type ? If product type changed then You'll lose related subscriptions beacuse of they can't be renewed !",
    );
    if (confirmTypeChange) {
      latest_value_of_subscrpt_product_type = subscrpt_product_type.val();
    } else {
      subscrpt_product_type.val("simple");
    }
  }
});
