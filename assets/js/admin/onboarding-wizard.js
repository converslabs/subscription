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
      this.initLivePreview();
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

      // Page 2: existing product selection → chip
      $(document).on("change", "#subscrpt_existing_product", $.proxy(this.onProductSelected, this));
      $(document).on("click", "#subscrpt-btn-clear-product", $.proxy(this.clearProductSelection, this));

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

      if (pageNum === 1) {
        $("#subscrpt-stepper").hide();
      } else {
        $("#subscrpt-stepper").show();
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
      $(".p2-option-card, .product-toggle-btn").removeClass("active");
      $(e.currentTarget).addClass("active");
      $("#subscrpt-existing-product-fields").hide();
      // Restore name field editability
      $("#subscrpt_product_name").prop("readonly", false).val("");
      $("#subscrpt_product_price").val("");
      this.updatePreview();
    },

    showExistingProduct: function (e) {
      e.preventDefault();
      $(".p2-option-card, .product-toggle-btn").removeClass("active");
      $(e.currentTarget).addClass("active");
      $("#subscrpt-existing-product-fields").show();
      // If already has a selection, re-show chip
      var selectedVal = $("#subscrpt_existing_product").val();
      if (selectedVal) {
        this.showProductChip($("#subscrpt_existing_product option:selected"));
      }
    },

    onProductSelected: function () {
      var selected = $("#subscrpt_existing_product option:selected");
      if (!selected.val()) return;
      this.showProductChip(selected);
      // Pre-fill fields
      var name = selected.text();
      var price = selected.data("price") || "";
      $("#subscrpt_product_name").val(name);
      $("#subscrpt_product_price").val(price);
      this.updatePreview();
    },

    showProductChip: function (option) {
      var name = option.text();
      var price = option.data("price") || "";
      var type = option.data("type") || "";
      var sku = option.data("sku") || "";
      var initials = name
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map(function (w) {
          return w[0].toUpperCase();
        })
        .join("");

      var meta = [sku ? "SKU " + sku : null, type, price ? "$" + parseFloat(price).toFixed(2) : null]
        .filter(Boolean)
        .join(" · ");

      $("#p2-chip-avatar").text(initials);
      $("#p2-chip-name").text(name);
      $("#p2-chip-meta").text(meta);

      $("#subscrpt-product-select-wrap").hide();
      $("#subscrpt-selected-product-chip").show();
    },

    clearProductSelection: function () {
      $("#subscrpt_existing_product").val("");
      $("#subscrpt-selected-product-chip").hide();
      $("#subscrpt-product-select-wrap").show();
      $("#subscrpt_product_name").val("");
      $("#subscrpt_product_price").val("");
      this.updatePreview();
    },

    showRelevantProductSection: function () {
      var activeBtn = $(".product-toggle-btn.active");
      var mode = activeBtn.length ? activeBtn.data("mode") : "new";

      if (mode === "existing") {
        $("#subscrpt-existing-product-fields").show();
        // Show chip if product already selected
        var selectedVal = $("#subscrpt_existing_product").val();
        if (selectedVal) {
          this.showProductChip($("#subscrpt_existing_product option:selected"));
        }
      } else {
        $("#subscrpt-existing-product-fields").hide();
      }
    },

    // ----- Live preview -----

    initLivePreview: function () {
      var self = this;
      $(document).on("input", "#subscrpt_product_name", function () {
        self.updatePreview();
      });
      $(document).on("input", "#subscrpt_product_price", function () {
        self.updatePreview();
      });
      $(document).on("wpsubs:select", "#subscrpt-billing-period-select", function () {
        self.updatePreview();
      });
    },

    updatePreview: function () {
      var name = $("#subscrpt_product_name").val() || "Your product";
      var price = $("#subscrpt_product_price").val() || "0.00";
      var period = $("input[name='subscrpt_billing_period']").val() || "month";

      $("#p2-preview-name").text(name);
      $("#p2-preview-price").text(parseFloat(price).toFixed(2));
      $("#p2-preview-period").text(period);
    },

    // ----- Save & validate -----

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
        billing_per: $("#subscrpt_billing_per").val(),
        billing_period: $("input[name='subscrpt_billing_period']").val(),
        trial_timing_per: $("#subscrpt_trial_timing_per").val(),
        signup_fee: $("#subscrpt_signup_fee").val(),
        // hidden compat fields
        trial_enabled: 0,
        trial_timing_option: "days",
        length_enabled: 0,
        length_per: "",
        length_option: "months",
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
      var existing = $("#subscrpt_existing_product").val();
      var billing = $("input[name='subscrpt_billing_period']").val();

      if (productMode === "new") {
        if (!productName) {
          messages.push("Product name is required.");
          isValid = false;
        }
      } else {
        if (!existing) {
          messages.push("Please select an existing product.");
          isValid = false;
        }
      }

      if (!price || isNaN(parseFloat(price)) || parseFloat(price) < 0) {
        messages.push("Please enter a valid price.");
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
          $("#subscrpt_timing_option").val("never");
          $("#subscrpt_billing_per").val("1");
          $("input[name='subscrpt_billing_period']").val("month");
          $("#subscrpt_trial_timing_per").val("0");
          $("#subscrpt_signup_fee").val("");
          // Reset chip
          $("#subscrpt-selected-product-chip").hide();
          $("#subscrpt-product-select-wrap").show();
          $("#subscrpt_existing_product").val("");
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
