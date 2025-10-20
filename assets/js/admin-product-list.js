/**
 * WPSubscription Products List Admin JavaScript
 *
 * @package WPSubscription
 * @since 1.6.0
 */

(function ($) {
  "use strict";

  const WPSubscriptionProductList = {
    /**
     * Initialize
     */
    init: function () {
      this.bindEvents();
    },

    /**
     * Bind event handlers
     */
    bindEvents: function () {
      // Edit product in modal
      $(document).on("click", ".edit-product-modal", this.openEditModal);

      // Edit subscription settings (quick edit)
      $(document).on("click", ".edit-subscription-settings", this.openQuickEditModal);

      // Close modal
      $(document).on("click", ".subscrpt-modal-close, .subscrpt-modal-overlay", this.closeModal);

      // Save subscription settings
      $(document).on("click", "#save-subscription-settings", this.saveSettings);

      // Refresh after iframe save
      $(window).on("message", this.handleIframeMessage);

      // Close modal on escape key
      $(document).on("keydown", function (e) {
        if (e.key === "Escape" || e.keyCode === 27) {
          WPSubscriptionProductList.closeModal();
        }
      });
    },

    /**
     * Open edit modal with WooCommerce editor in iframe
     */
    openEditModal: function (e) {
      e.preventDefault();

      const productId = $(this).data("product-id");
      const productName = $(this).data("product-name");
      let editUrl = $(this).data("edit-url");

      // Add parameters to hide admin UI elements
      const separator = editUrl.indexOf("?") !== -1 ? "&" : "?";
      editUrl += separator + "subscrpt_modal=1&TB_iframe=true";

      // Show modal
      WPSubscriptionProductList.showModal(true);

      // Render iframe
      const modalContent = `
				<div class="subscrpt-modal-header">
					<h2>${subscrptProductList.i18n.editProduct}: ${productName}</h2>
					<button type="button" class="subscrpt-modal-close">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="subscrpt-modal-body subscrpt-modal-iframe-container">
					<iframe id="subscrpt-product-iframe" src="${editUrl}" frameborder="0"></iframe>
				</div>
				<div class="subscrpt-modal-footer">
					<button type="button" class="button button-secondary subscrpt-modal-close">${subscrptProductList.i18n.close}</button>
					<button type="button" id="refresh-product-list" class="button button-primary">${subscrptProductList.i18n.saveAndClose}</button>
				</div>
			`;

      $(".subscrpt-modal-content").html(modalContent);

      // Handle save and close
      $("#refresh-product-list").on("click", function () {
        location.reload();
      });

      // Monitor iframe for publish button click
      WPSubscriptionProductList.monitorIframe();
    },

    /**
     * Open quick edit modal for subscription settings only
     */
    openQuickEditModal: function (e) {
      e.preventDefault();

      const productId = $(this).data("product-id");
      const productName = $(this).data("product-name");

      // Show loading
      WPSubscriptionProductList.showModal(false);
      WPSubscriptionProductList.showLoading();

      // Load product data
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "subscrpt_get_product_settings",
          product_id: productId,
          nonce: subscrptProductList.nonce,
        },
        success: function (response) {
          if (response.success) {
            WPSubscriptionProductList.renderQuickEditForm(productId, productName, response.data);
          } else {
            WPSubscriptionProductList.showError(response.data.message || "Failed to load product settings");
          }
        },
        error: function () {
          WPSubscriptionProductList.showError("An error occurred while loading product settings");
        },
      });
    },

    /**
     * Monitor iframe for changes
     */
    monitorIframe: function () {
      const iframe = document.getElementById("subscrpt-product-iframe");
      if (!iframe) return;

      // Listen for iframe load
      $(iframe).on("load", function () {
        try {
          // Try to access iframe content (same-origin only)
          const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

          // Monitor for update/publish button clicks
          $(iframeDoc)
            .find("#publish, #save-post")
            .on("click", function () {
              // Show message that changes will be reflected after closing
              $("#refresh-product-list")
                .text(subscrptProductList.i18n.closeAndRefresh)
                .addClass("button-primary-pulsing");
            });
        } catch (e) {
          // Cross-origin restriction, that's okay
          console.log("Cross-origin iframe access blocked (expected)");
        }
      });
    },

    /**
     * Handle messages from iframe
     */
    handleIframeMessage: function (e) {
      const event = e.originalEvent || e;

      // Check if message is from our iframe
      if (event.data === "woocommerce_product_saved") {
        // Auto-refresh after save
        setTimeout(function () {
          location.reload();
        }, 1000);
      }
    },

    /**
     * Render quick edit form
     */
    renderQuickEditForm: function (productId, productName, settings) {
      const modalContent = `
				<div class="subscrpt-modal-header">
					<h2>${subscrptProductList.i18n.editSettings}: ${productName}</h2>
					<button type="button" class="subscrpt-modal-close">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="subscrpt-modal-body">
					<form id="subscrpt-settings-form">
						<input type="hidden" name="product_id" value="${productId}">
						
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="subscrpt_enabled">${subscrptProductList.i18n.enableSubscription}</label>
								</th>
								<td>
									<label>
										<input type="checkbox" id="subscrpt_enabled" name="subscrpt_enabled" value="1" ${settings.enabled ? "checked" : ""}>
										${subscrptProductList.i18n.enableSubscriptionDesc}
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="subscrpt_payment_type">${subscrptProductList.i18n.paymentType}</label>
								</th>
								<td>
									<select id="subscrpt_payment_type" name="subscrpt_payment_type">
										<option value="recurring" ${settings.payment_type === "recurring" ? "selected" : ""}>${subscrptProductList.i18n.recurring}</option>
										<option value="split" ${settings.payment_type === "split" ? "selected" : ""}>${subscrptProductList.i18n.split}</option>
									</select>
								</td>
							</tr>
							<tr class="recurring-fields" ${settings.payment_type !== "recurring" ? 'style="display:none;"' : ""}>
								<th scope="row">
									<label for="subscription_time">${subscrptProductList.i18n.billingInterval}</label>
								</th>
								<td>
									<input type="number" id="subscription_time" name="subscription_time" value="${settings.subscription_time || 1}" min="1" style="width: 80px;">
									<select id="subscription_type" name="subscription_type" style="width: 150px;">
										<option value="days" ${settings.subscription_type === "days" ? "selected" : ""}>${subscrptProductList.i18n.days}</option>
										<option value="weeks" ${settings.subscription_type === "weeks" ? "selected" : ""}>${subscrptProductList.i18n.weeks}</option>
										<option value="months" ${settings.subscription_type === "months" ? "selected" : ""}>${subscrptProductList.i18n.months}</option>
										<option value="years" ${settings.subscription_type === "years" ? "selected" : ""}>${subscrptProductList.i18n.years}</option>
									</select>
								</td>
							</tr>
							<tr class="split-fields" ${settings.payment_type !== "split" ? 'style="display:none;"' : ""}>
								<th scope="row">
									<label for="subscrpt_installment">${subscrptProductList.i18n.installments}</label>
								</th>
								<td>
									<input type="number" id="subscrpt_installment" name="subscrpt_installment" value="${settings.installments || 3}" min="2" max="12" style="width: 80px;">
									<p class="description">${subscrptProductList.i18n.installmentsDesc}</p>
								</td>
							</tr>
							<tr class="recurring-fields" ${settings.payment_type !== "recurring" ? 'style="display:none;"' : ""}>
								<th scope="row">
									<label for="subscrpt_user_cancel">${subscrptProductList.i18n.userCancel}</label>
								</th>
								<td>
									<label>
										<input type="checkbox" id="subscrpt_user_cancel" name="subscrpt_user_cancel" value="yes" ${settings.user_cancel ? "checked" : ""}>
										${subscrptProductList.i18n.userCancelDesc}
									</label>
								</td>
							</tr>
						</table>
					</form>
				</div>
				<div class="subscrpt-modal-footer">
					<button type="button" class="button button-secondary subscrpt-modal-close">${subscrptProductList.i18n.cancel}</button>
					<button type="button" id="save-subscription-settings" class="button button-primary">${subscrptProductList.i18n.save}</button>
				</div>
			`;

      $(".subscrpt-modal-content").html(modalContent);

      // Handle payment type change
      $("#subscrpt_payment_type").on("change", function () {
        const paymentType = $(this).val();
        if (paymentType === "recurring") {
          $(".recurring-fields").show();
          $(".split-fields").hide();
        } else {
          $(".recurring-fields").hide();
          $(".split-fields").show();
        }
      });
    },

    /**
     * Save settings
     */
    saveSettings: function (e) {
      e.preventDefault();

      const formData = $("#subscrpt-settings-form").serializeArray();
      const data = {
        action: "subscrpt_save_product_settings",
        nonce: subscrptProductList.nonce,
      };

      // Convert form data to object
      $.each(formData, function (i, field) {
        data[field.name] = field.value;
      });

      // Handle checkboxes
      data.subscrpt_enabled = $("#subscrpt_enabled").is(":checked") ? "1" : "0";
      data.subscrpt_user_cancel = $("#subscrpt_user_cancel").is(":checked") ? "yes" : "no";

      // Show saving state
      $("#save-subscription-settings").prop("disabled", true).text(subscrptProductList.i18n.saving);

      // Save settings
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: data,
        success: function (response) {
          if (response.success) {
            WPSubscriptionProductList.showSuccess(subscrptProductList.i18n.settingsSaved);
            setTimeout(function () {
              location.reload();
            }, 1000);
          } else {
            WPSubscriptionProductList.showError(response.data.message || subscrptProductList.i18n.saveFailed);
            $("#save-subscription-settings").prop("disabled", false).text(subscrptProductList.i18n.save);
          }
        },
        error: function () {
          WPSubscriptionProductList.showError(subscrptProductList.i18n.saveFailed);
          $("#save-subscription-settings").prop("disabled", false).text(subscrptProductList.i18n.save);
        },
      });
    },

    /**
     * Show modal
     */
    showModal: function (isLargeModal) {
      let modal = $(".subscrpt-modal");
      if (modal.length === 0) {
        $("body").append(`
					<div class="subscrpt-modal">
						<div class="subscrpt-modal-overlay"></div>
						<div class="subscrpt-modal-content"></div>
					</div>
				`);
        modal = $(".subscrpt-modal");
      }

      // Set modal size
      if (isLargeModal) {
        $(".subscrpt-modal-content").addClass("subscrpt-modal-large");
      } else {
        $(".subscrpt-modal-content").removeClass("subscrpt-modal-large");
      }

      modal.fadeIn(200);
      $("body").addClass("subscrpt-modal-open");
    },

    /**
     * Close modal
     */
    closeModal: function (e) {
      if (e && $(e.target).hasClass("subscrpt-modal-content")) {
        return;
      }
      $(".subscrpt-modal").fadeOut(200);
      $("body").removeClass("subscrpt-modal-open");
    },

    /**
     * Show loading
     */
    showLoading: function () {
      $(".subscrpt-modal-content").html(`
				<div class="subscrpt-modal-loading">
					<span class="spinner is-active"></span>
					<p>${subscrptProductList.i18n.loading}</p>
				</div>
			`);
    },

    /**
     * Show error
     */
    showError: function (message) {
      $(".subscrpt-modal-body").prepend(`
				<div class="notice notice-error is-dismissible">
					<p>${message}</p>
				</div>
			`);
    },

    /**
     * Show success
     */
    showSuccess: function (message) {
      $(".subscrpt-modal-body").prepend(`
				<div class="notice notice-success is-dismissible">
					<p>${message}</p>
				</div>
			`);
    },
  };

  // Initialize on document ready
  $(document).ready(function () {
    WPSubscriptionProductList.init();
  });
})(jQuery);
