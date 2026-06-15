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
      this.initStepperState();
    },

    initPageIndicator: function () {
      this.currentPage = parseInt($("#subscrpt-current-page").text(), 10) || 1;
    },

    initStepperState: function () {
      var page = parseInt($("#subscrpt-wizard-page").val(), 10) || 1;
      if (page > 1) {
        $("#subscrpt-stepper").show();
        $(".wpsubs-wizard-stepper__step").removeClass("active done");
        $(".wpsubs-wizard-stepper__step").each(function () {
          var step = parseInt($(this).data("step"), 10);
          if (step < page) {
            $(this).addClass("done");
          } else if (step === page) {
            $(this).addClass("active");
          }
        });
      }
    },

    bindEvents: function () {
      // Page 1
      $(document).on("click", "#subscrpt-btn-start", $.proxy(this.goToPage2, this));
      $(document).on("click", "#subscrpt-btn-skip", $.proxy(this.skip, this));

      // Page 2: product toggle
      $(document).on("click", "#subscrpt-btn-create-new", $.proxy(this.showNewProduct, this));
      $(document).on("click", "#subscrpt-btn-use-existing", $.proxy(this.showExistingProduct, this));

      // Page 2: product search
      $(document).on("focus", "#subscrpt-product-search-input", $.proxy(this.openProductSearch, this));
      $(document).on("input", "#subscrpt-product-search-input", $.proxy(this.filterProducts, this));
      $(document).on("click", ".p2-product-search__item", $.proxy(this.onProductItemClick, this));
      $(document).on("click", function (e) {
        if (!$(e.target).closest(".p2-product-search").length) {
          $("#subscrpt-product-search-dropdown").hide();
        }
      });
      $(document).on("click", "#subscrpt-btn-clear-product", $.proxy(this.clearProductSelection, this));
      $(document).on("click", ".p2-variation-item", $.proxy(this.onVariationSelect, this));

      // Page 2: navigation
      $(document).on("click", "#subscrpt-btn-back", $.proxy(this.goToPage1, this));
      $(document).on("click", "#subscrpt-btn-save", $.proxy(this.savePage2, this));

      // Page 3
      $(document).on("click", "#subscrpt-btn-add-another", $.proxy(this.restart, this));
      $(document).on("click", "#subscrpt-btn-start-over", $.proxy(this.restart, this));
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
        $(".wpsubs-wizard-stepper__step").each(function () {
          var step = parseInt($(this).data("step"), 10);
          if (step < pageNum) {
            $(this).addClass("done");
          }
        });
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
      $("#subscrpt-btn-save").html("Create product &rsaquo;");
      this.updatePreview();
    },

    showExistingProduct: function (e) {
      e.preventDefault();
      $(".p2-option-card, .product-toggle-btn").removeClass("active");
      $(e.currentTarget).addClass("active");
      $("#subscrpt-existing-product-fields").show();
      $("#subscrpt-btn-save").html("Update product &rsaquo;");
      // If already has a selection, re-show chip
      var selectedVal = $("#subscrpt_existing_product").val();
      if (selectedVal) {
        this.showProductChip($("#subscrpt_existing_product option:selected"));
      }
    },

    openProductSearch: function () {
      $(".p2-product-search__item").show();
      $(".p2-product-search__empty").hide();
      $("#subscrpt-product-search-dropdown").show();
    },

    filterProducts: function (e) {
      var q = $(e.target).val().toLowerCase().trim();
      $("#subscrpt-product-search-dropdown").show();
      var visible = 0;
      $(".p2-product-search__item").each(function () {
        var name = $(this).data("name") ? $(this).data("name").toLowerCase() : "";
        var sku = $(this).data("sku") ? String($(this).data("sku")).toLowerCase() : "";
        if (!q || name.indexOf(q) >= 0 || sku.indexOf(q) >= 0) {
          $(this).show();
          visible++;
        } else {
          $(this).hide();
        }
      });
      $(".p2-product-search__empty").toggle(visible === 0);
    },

    onProductItemClick: function (e) {
      var item = $(e.currentTarget);
      if (item.hasClass("p2-product-search__item--locked")) {
        return;
      }
      var id = String(item.data("id"));
      var name = item.data("name") || "";
      var price = item.data("price") != null ? String(item.data("price")) : "";
      var type = item.data("type") || "";
      var sku = item.data("sku") ? String(item.data("sku")) : "";
      var productType = item.data("product-type") || "";

      $("#subscrpt-existing-product-hidden").val(id);
      $("#subscrpt-product-search-dropdown").hide();
      this.showProductChip(name, price, type, sku);
      $("#subscrpt_product_name").val(name);
      $("#subscrpt_product_price").val(price);

      // Reset variation picker state
      $("#subscrpt-variation-id-hidden").val("");

      if (productType === "variable") {
        // Variation picker handles subscription field autofill
        this.fetchAndShowVariations(id);
      } else {
        $("#subscrpt-variation-picker-wrap").hide();

        // Autofill subscription fields from product meta
        var billingPeriod = item.data("billing-period");
        var billingPer = item.data("billing-per");
        var trialPer = item.data("trial-per");
        var signupFee = item.data("signup-fee");

        if (billingPeriod) {
          this.setAdvSelectValue("#subscrpt-billing-period-select", String(billingPeriod));
        }
        if (billingPer) {
          $("#subscrpt_billing_per").val(billingPer);
          $("#subscrpt_billing_per_visible").val(billingPer);
        }
        if (trialPer !== undefined && trialPer !== "") {
          $("#subscrpt_trial_timing_per").val(trialPer);
        }
        if (signupFee !== undefined && signupFee !== "") {
          $("#subscrpt_signup_fee").val(signupFee);
        }
      }

      this.updatePreview();
    },

    fetchAndShowVariations: function (productId) {
      var self = this;
      var wrap = $("#subscrpt-variation-picker-wrap");
      var list = $("#subscrpt-variation-picker-list");

      wrap.show();
      list.html(
        '<p class="p2-variation-picker__loading">' +
          (window.subscrpt_wizard_i18n ? subscrpt_wizard_i18n.loading : "Loading variations…") +
          "</p>",
      );

      $.post(
        this.ajaxUrl,
        {
          action: "subscrpt_get_product_variations",
          nonce: $("#subscrpt_wizard_nonce").val(),
          product_id: productId,
        },
        function (response) {
          if (response.success && response.data && response.data.length) {
            self.renderVariationPicker(response.data);
          } else {
            wrap.hide();
          }
        },
      ).fail(function () {
        wrap.hide();
      });
    },

    renderVariationPicker: function (variations) {
      var self = this;
      var symbol = (window.subscrpt_wizard && subscrpt_wizard.currency_symbol) || "$";
      var html = '<p class="p2-variation-picker__label">Select a variation</p>';
      html += '<div class="p2-variation-picker__list">';

      variations.forEach(function (v) {
        var meta = v.sku ? "SKU " + v.sku : "";
        var priceDisplay =
          v.price !== "" && v.price !== null && v.price !== undefined ? symbol + parseFloat(v.price).toFixed(2) : "";

        html +=
          '<div class="p2-variation-item"' +
          ' data-id="' +
          self.escAttr(String(v.id)) +
          '"' +
          ' data-label="' +
          self.escAttr(v.label) +
          '"' +
          ' data-price="' +
          self.escAttr(String(v.price !== null && v.price !== undefined ? v.price : "")) +
          '"' +
          ' data-sku="' +
          self.escAttr(v.sku || "") +
          '"' +
          ' data-billing-period="' +
          self.escAttr(v.billing_period || "") +
          '"' +
          ' data-billing-per="' +
          self.escAttr(String(v.billing_per || "1")) +
          '"' +
          ' data-trial-per="' +
          self.escAttr(String(v.trial_per !== null && v.trial_per !== undefined ? v.trial_per : "")) +
          '"' +
          ' data-signup-fee="' +
          self.escAttr(String(v.signup_fee || "")) +
          '">' +
          '<div class="p2-variation-item__check">&#10003;</div>' +
          '<div class="p2-variation-item__info">' +
          '<p class="p2-variation-item__name">' +
          self.escHtml(v.label) +
          "</p>" +
          (meta ? '<p class="p2-variation-item__meta">' + self.escHtml(meta) + "</p>" : "") +
          "</div>" +
          (priceDisplay ? '<span class="p2-variation-item__price">' + self.escHtml(priceDisplay) + "</span>" : "") +
          "</div>";
      });

      html += "</div>";
      $("#subscrpt-variation-picker-list").html(html);
      $("#subscrpt-variation-picker-wrap").show();
    },

    onVariationSelect: function (e) {
      var item = $(e.currentTarget);
      $(".p2-variation-item").removeClass("selected");
      item.addClass("selected");

      $("#subscrpt-variation-id-hidden").val(String(item.data("id")));

      // Autofill subscription fields and price from variation meta
      var price = item.data("price");
      var billingPeriod = item.data("billing-period");
      var billingPer = item.data("billing-per");
      var trialPer = item.data("trial-per");
      var signupFee = item.data("signup-fee");

      if (price !== undefined && price !== "") {
        $("#subscrpt_product_price").val(price);
      }
      if (billingPeriod) {
        this.setAdvSelectValue("#subscrpt-billing-period-select", String(billingPeriod));
      }
      if (billingPer) {
        $("#subscrpt_billing_per").val(billingPer);
        $("#subscrpt_billing_per_visible").val(billingPer);
      }
      if (trialPer !== undefined && trialPer !== "") {
        $("#subscrpt_trial_timing_per").val(trialPer);
      }
      if (signupFee !== undefined && signupFee !== "") {
        $("#subscrpt_signup_fee").val(signupFee);
      }

      this.updatePreview();
    },

    escHtml: function (str) {
      return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    },

    escAttr: function (str) {
      return String(str)
        .replace(/&/g, "&amp;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");
    },

    setAdvSelectValue: function (selector, value) {
      var el = $(selector);
      if (!el.length) return;
      var menuItem = el.find('.wpsubs-adv-select__item[data-value="' + value + '"]');
      var label = menuItem.length ? menuItem.find(".wpsubs-adv-select__item-label").text().trim() : value;
      el.find('input[type="hidden"]').val(value);
      el.find(".wpsubs-adv-select__label").text(label);
    },

    showProductChip: function (name, price, type, sku) {
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
      $("#subscrpt-existing-product-hidden").val("");
      $("#subscrpt-product-search-input").val("");
      $(".p2-product-search__item").show();
      $(".p2-product-search__empty").hide();
      $("#subscrpt-product-search-dropdown").hide();
      $("#subscrpt-selected-product-chip").hide();
      $("#subscrpt-product-select-wrap").show();
      $("#subscrpt_product_name").val("");
      $("#subscrpt_product_price").val("");
      // Reset variation picker
      $("#subscrpt-variation-picker-wrap").hide();
      $("#subscrpt-variation-picker-list").empty();
      $("#subscrpt-variation-id-hidden").val("");
      this.updatePreview();
    },

    showRelevantProductSection: function () {
      var activeBtn = $(".product-toggle-btn.active");
      var mode = activeBtn.length ? activeBtn.data("mode") : "new";

      if (mode === "existing") {
        $("#subscrpt-existing-product-fields").show();
        var selectedId = $("#subscrpt-existing-product-hidden").val();
        if (selectedId) {
          var item = $(".p2-product-search__item[data-id='" + selectedId + "']");
          if (item.length) {
            var name = item.data("name") || "";
            var price = item.data("price") != null ? String(item.data("price")) : "";
            var type = item.data("type") || "";
            var sku = item.data("sku") ? String(item.data("sku")) : "";
            this.showProductChip(name, price, type, sku);
          }
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
      var period = $("input[name='subscrpt_billing_period']").val() || "months";

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

      var productMode = $(".product-toggle-btn.active").data("mode") || "new";
      var confirmMsg =
        productMode === "new"
          ? "Create this subscription product?"
          : "Apply these subscription settings to the selected product?";

      if (!confirm(confirmMsg)) {
        return;
      }

      var data = {
        action: "subscrpt_save_wizard_page2",
        nonce: $("#subscrpt_wizard_nonce").val(),
        product_mode: $(".product-toggle-btn.active").data("mode") || "new",
        product_name: $("#subscrpt_product_name").val(),
        product_price: $("#subscrpt_product_price").val(),
        existing_product_id: $("#subscrpt-existing-product-hidden").val(),
        variation_id: $("#subscrpt-variation-id-hidden").val(),
        timing_option: $("#subscrpt_timing_option").val(),
        billing_per: $("#subscrpt_billing_per").val(),
        billing_period: $("input[name='subscrpt_billing_period']").val(),
        trial_timing_per: $("#subscrpt_trial_timing_per").val(),
        signup_fee: $("#subscrpt_signup_fee").val(),
        // hidden compat fields
        trial_enabled: 0,
        trial_timing_option: $("input[name='subscrpt_trial_timing_option']").val() || "days",
        length_enabled: 0,
        length_per: "",
        length_option: "months",
      };

      var self = this;

      $.post(this.ajaxUrl, data, function (response) {
        if (response.success) {
          window.location.reload();
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
      var existing = $("#subscrpt-existing-product-hidden").val();
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
        if (
          existing &&
          $("#subscrpt-variation-picker-wrap").is(":visible") &&
          !$("#subscrpt-variation-id-hidden").val()
        ) {
          messages.push("Please select a variation.");
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
          $("input[name='subscrpt_billing_period']").val("months");
          $("#subscrpt_trial_timing_per").val("0");
          $("#subscrpt_signup_fee").val("");
          // Reset chip
          $("#subscrpt-selected-product-chip").hide();
          $("#subscrpt-product-select-wrap").show();
          $("#subscrpt_existing_product").val("");
        },
      );
    },
  };

  $(document).ready(function () {
    Wizard.init();
  });
})(jQuery);
