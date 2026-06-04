/**
 * Onboarding Wizard — SPA-style
 * All pages rendered at once; JS controls section visibility
 * PHP values passed via localized script data (see Menu::enqueue_admin_assets)
 */
(function ($) {
  "use strict";

  var Wizard = {
    currentPage: 1,
    ajaxUrl: "",
    subscriptionsUrl: "",

    init: function () {
      this.ajaxUrl = subscrpt_wizard.ajax_url;
      this.subscriptionsUrl = subscrpt_wizard.subscriptions_url;
      this.bindEvents();
      this.showRelevantProductSection();
      this.initPageIndicator();
    },

    initPageIndicator: function () {
      this.currentPage = parseInt($("#subscrpt-current-page").text(), 10) || 1;
    },

    bindEvents: function () {
      // Page 1
      $(document).on("click", "#subscrpt-btn-start", $.proxy(this.goToPage2, this));
      $(document).on("click", "#subscrpt-btn-skip", $.proxy(this.skip, this));

      // Page 2: product toggle
      $(document).on("click", "#subscrpt-btn-create-new", $.proxy(this.showNewProduct, this));
      $(document).on("click", "#subscrpt-btn-use-existing", $.proxy(this.showExistingProduct, this));

      // Page 2: trial/length toggles
      $(document).on("change", "#subscrpt_trial_enabled", $.proxy(this.toggleTrialFields, this));
      $(document).on("change", "#subscrpt_length_enabled", $.proxy(this.toggleLengthFields, this));

      // Page 2: navigation
      $(document).on("click", "#subscrpt-btn-back", $.proxy(this.goToPage1, this));
      $(document).on("click", "#subscrpt-btn-save", $.proxy(this.savePage2, this));

      // Page 3
      $(document).on("click", "#subscrpt-btn-add-another", $.proxy(this.restart, this));
      $(document).on("click", "#subscrpt-btn-go-subscriptions", $.proxy(this.goToSubscriptions, this));
    },

    // ----- Page transitions -----

    goToPage1: function () {
      this.switchSection(1);
    },

    goToPage2: function () {
      this.switchSection(2);
    },

    goToPage3: function (productId) {
      $("#subscrpt-product-id").val(productId || 0);
      this.switchSection(3);
    },

    switchSection: function (pageNum) {
      this.currentPage = pageNum;

      $("#subscrpt-wizard-page").val(pageNum);

      // Show/hide stepper (page 1 has no stepper)
      if (pageNum === 1) {
        $("#subscrpt-stepper").hide();
      } else {
        $("#subscrpt-stepper").show();
        // Update stepper active states
        $(".wpsubs-wizard-stepper__step").removeClass("active done");
        $('.wpsubs-wizard-stepper__step[data-step="1"]').addClass("done");
        $('.wpsubs-wizard-stepper__step[data-step="' + pageNum + '"]').addClass("active");
      }

      $(".wizard-section").removeClass("active");
      $("#subscrpt-section-" + pageNum).addClass("active");
    },

    // ----- Page 1 actions -----

    skip: function (e) {
      e.preventDefault();
      window.location.href = this.subscriptionsUrl;
    },

    // ----- Page 2 actions -----

    showNewProduct: function (e) {
      e.preventDefault();
      $("#subscrpt-new-product-fields").show();
      $("#subscrpt-existing-product-fields").hide();
      $(".product-toggle-btn").removeClass("active");
      $(e.currentTarget).addClass("active");
    },

    showExistingProduct: function (e) {
      e.preventDefault();
      $("#subscrpt-new-product-fields").hide();
      $("#subscrpt-existing-product-fields").show();
      $(".product-toggle-btn").removeClass("active");
      $(e.currentTarget).addClass("active");
    },

    showRelevantProductSection: function () {
      var activeBtn = $(".product-toggle-btn.active");
      var mode = activeBtn.length ? activeBtn.data("mode") : "new";

      if (mode === "existing") {
        $("#subscrpt-new-product-fields").hide();
        $("#subscrpt-existing-product-fields").show();
      } else {
        $("#subscrpt-existing-product-fields").hide();
        $("#subscrpt-new-product-fields").show();
      }

      this.toggleTrialFields();
      this.toggleLengthFields();
    },

    toggleTrialFields: function () {
      if ($("#subscrpt_trial_enabled").is(":checked")) {
        $("#subscrpt-trial-fields").addClass("visible");
      } else {
        $("#subscrpt-trial-fields").removeClass("visible");
      }
    },

    toggleLengthFields: function () {
      if ($("#subscrpt_length_enabled").is(":checked")) {
        $("#subscrpt-length-fields").addClass("visible");
      } else {
        $("#subscrpt-length-fields").removeClass("visible");
      }
    },

    savePage2: function (e) {
      e.preventDefault();

      if (!this.validatePage2()) {
        return;
      }

      var data = {
        action: "subscrpt_save_wizard_page2",
        nonce: $("#subscrpt_wizard_nonce").val(),
        product_mode: $(".product-toggle-btn.active").data("mode") || "new",
        product_name: $("#subscrpt_product_name").val(),
        product_price: $("#subscrpt_product_price").val(),
        existing_product_id: $("#subscrpt_existing_product").val(),
        timing_option: $("#subscrpt_timing_option").val(),
        billing_period: $("#subscrpt_billing_period").val(),
        trial_enabled: $("#subscrpt_trial_enabled").is(":checked") ? 1 : 0,
        trial_timing_per: $("#subscrpt_trial_timing_per").val(),
        trial_timing_option: $("#subscrpt_trial_timing_option").val(),
        length_enabled: $("#subscrpt_length_enabled").is(":checked") ? 1 : 0,
        length_per: $("#subscrpt_length_per").val(),
        length_option: $("#subscrpt_length_option").val(),
      };

      var self = this;

      $.post(this.ajaxUrl, data, function (response) {
        if (response.success) {
          self.goToPage3(response.data.product_id || 0);
        } else {
          alert(response.data.message || "Something went wrong. Please try again.");
        }
      }).fail(function () {
        alert("Server error. Please try again.");
      });
    },

    validatePage2: function () {
      var isValid = true;
      var messages = [];
      var productMode = $(".product-toggle-btn.active").data("mode") || "new";
      var productName = $("#subscrpt_product_name").val().trim();
      var price = $("#subscrpt_product_price").val().trim();
      var existingProduct = $("#subscrpt_existing_product").val();
      var timing = $("#subscrpt_timing_option").val();
      var billing = $("#subscrpt_billing_period").val();

      if (productMode === "new") {
        if (!productName) {
          messages.push("Product name is required.");
          isValid = false;
        }
      } else {
        if (!existingProduct) {
          messages.push("Please select an existing product.");
          isValid = false;
        }
      }

      if (!price) {
        messages.push("Product price is required.");
        isValid = false;
      } else if (isNaN(parseFloat(price)) || parseFloat(price) <= 0) {
        messages.push("Please enter a valid price greater than 0.");
        isValid = false;
      }

      if (!timing) {
        messages.push("Please select a subscription timing.");
        isValid = false;
      }

      if (!billing) {
        messages.push("Please select a billing period.");
        isValid = false;
      }

      if (!isValid) {
        alert(messages.join("\n"));
      }

      return isValid;
    },

    // ----- Page 3 actions -----

    restart: function (e) {
      e.preventDefault();
      var self = this;
      $.post(
        this.ajaxUrl,
        {
          action: "subscrpt_reset_wizard",
          nonce: $("#subscrpt_wizard_nonce").val(),
        },
        function () {
          self.switchSection(1);
          $("#subscrpt-current-page").text("1");
          $("#subscrpt_product_name").val("");
          $("#subscrpt_product_price").val("");
          $("#subscrpt_timing_option").val("");
          $("#subscrpt_billing_period").val("");
          $("#subscrpt_trial_enabled").prop("checked", false);
          $("#subscrpt_length_enabled").prop("checked", false);
          self.toggleTrialFields();
          self.toggleLengthFields();
        },
      );
    },

    goToSubscriptions: function () {
      window.location.href = this.subscriptionsUrl;
    },
  };

  $(document).ready(function () {
    Wizard.init();
  });
})(jQuery);
