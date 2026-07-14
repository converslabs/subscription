/**
 * Subscription Plans - admin interactions (free base).
 *
 * Relies on the shared components in assets/js/admin-components/:
 *   - WPSubsModal     - data-wpsubs-modal-open / -close, fires wpsubs:modal:open.
 *   - WPSubsTabs      - the detail page's Selling Plans / Products tabs.
 *   - WPSubsAccordion - the term list + term-modal sections.
 *
 * Everything here is the list/detail chrome plus REST CRUD for plan groups and
 * selling plans (terms). All writes go through wpsubscription/v1. The Products
 * tab is read-only in free, so no product attach/price wiring lives here.
 */
(function () {
  "use strict";

  var cfg = window.subscrptPlans || {};
  var i18n = cfg.i18n || {};

  // day/week/month/year ⇄ billing_interval integer.
  var INTERVAL_TO_INT = { day: 1, week: 2, month: 3, year: 4 };
  var INT_TO_INTERVAL = { 1: "day", 2: "week", 3: "month", 4: "year" };

  /**
   * Call a plan REST endpoint.
   *
   * @param {string} method HTTP verb.
   * @param {string} path   Path under the /plans base, e.g. "/groups".
   * @param {Object} [body] JSON body for write requests.
   * @return {Promise<Object>} Parsed JSON (rejects on non-2xx).
   */
  function api(method, path, body) {
    return fetch(cfg.restUrl + path, {
      method: method,
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": cfg.nonce || "",
      },
      body: body ? JSON.stringify(body) : undefined,
    }).then(function (res) {
      return res.json().then(function (data) {
        if (!res.ok) {
          throw new Error((data && data.message) || i18n.genericError);
        }
        return data;
      });
    });
  }

  /**
   * Disable a button while its request is in flight.
   *
   * @param {HTMLElement} btn     Button.
   * @param {boolean}     loading Loading state.
   */
  function setLoading(btn, loading) {
    if (!btn) {
      return;
    }
    btn.disabled = loading;
    btn.classList.toggle("is-loading", loading);
  }

  /**
   * Open a modal by id via the shared helper.
   *
   * @param {string} id Modal id.
   */
  function openModal(id) {
    if (window.WPSubsModal && typeof window.WPSubsModal.open === "function") {
      window.WPSubsModal.open(id);
    }
  }

  /* ------------------------------------------------------------------ *
   * Row-actions dropdown (kebab) + client-side list filter.
   * ------------------------------------------------------------------ */

  document.addEventListener("click", function (e) {
    var trigger = e.target.closest("[data-subscrpt-dropdown] .wpsubs-row-actions__trigger");
    if (trigger) {
      e.preventDefault();
      var wrap = trigger.closest("[data-subscrpt-dropdown]");
      var menu = wrap.querySelector(".wpsubs-dropdown");
      var opening = menu && !menu.classList.contains("wpsubs-dropdown--open");
      closeDropdowns();
      if (menu && opening) {
        menu.hidden = false;
        menu.classList.add("wpsubs-dropdown--open");
        wrap.classList.add("wpsubs-row-actions--open");
      }
      return;
    }
    // A click on a menu item (or anywhere outside the menu) closes the dropdown.
    if (!e.target.closest(".wpsubs-dropdown") || e.target.closest(".wpsubs-dropdown__item")) {
      closeDropdowns();
    }
  });

  /**
   * Close every open row-actions dropdown.
   */
  function closeDropdowns() {
    document.querySelectorAll(".wpsubs-row-actions--open").forEach(function (wrap) {
      wrap.classList.remove("wpsubs-row-actions--open");
      var menu = wrap.querySelector(".wpsubs-dropdown");
      if (menu) {
        menu.classList.remove("wpsubs-dropdown--open");
        menu.hidden = true;
      }
    });
  }

  document.addEventListener("input", function (e) {
    var input = e.target.closest("[data-subscrpt-filter]");
    if (!input) {
      return;
    }
    var rowSelector = input.getAttribute("data-subscrpt-filter") || "wpsubs-plan-row";
    var term = input.value.toLowerCase().trim();
    document.querySelectorAll("." + rowSelector).forEach(function (row) {
      var name = row.getAttribute("data-subscrpt-name") || "";
      row.style.display = name.indexOf(term) !== -1 ? "" : "none";
    });
  });

  // Whole plan-group row opens its detail page — except clicks on a link,
  // button, or the actions menu, which keep their own behaviour.
  document.addEventListener("click", function (e) {
    var row = e.target.closest(".wpsubs-plan-row[data-href]");
    if (!row || e.target.closest("a, button, .wpsubs-row-actions")) {
      return;
    }
    window.location.href = row.getAttribute("data-href");
  });

  /* ------------------------------------------------------------------ *
   * Plan group: create + delete.
   * ------------------------------------------------------------------ */

  // Plan-type cards: click to select (skips locked/Pro cards). Selection is a
  // border + soft brand background - no radio input.
  document.addEventListener("click", function (e) {
    var card = e.target.closest(".subscrpt-type-card");
    if (!card || card.hasAttribute("data-locked")) {
      return;
    }
    var list = card.closest("[data-subscrpt-type-list]");
    if (!list) {
      return;
    }
    list.querySelectorAll(".subscrpt-type-card").forEach(function (c) {
      var on = c === card;
      c.classList.toggle("is-selected", on);
      c.style.borderColor = on ? "var(--wpsubs-brand)" : "var(--wpsubs-border)";
      c.style.background = on ? "var(--wpsubs-brand-light)" : "";
      var icon = c.querySelector(".dashicons");
      if (icon) {
        icon.style.color = on ? "var(--wpsubs-brand)" : "var(--wpsubs-text-subtle)";
      }
    });
  });

  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-create-plan]");
    if (!btn) {
      return;
    }
    e.preventDefault();

    var modal = btn.closest(".wpsubs-modal");
    var nameInput = modal.querySelector("#subscrpt-create-name");
    var name = nameInput ? nameInput.value.trim() : "";

    if (!name) {
      window.alert(i18n.nameRequired);
      return;
    }

    // Selected type card (defaults to recurring). The REST controller rejects
    // non-recurring types unless Pro is active.
    var selectedCard = modal.querySelector(".subscrpt-type-card.is-selected");
    var planType = selectedCard ? selectedCard.getAttribute("data-subscrpt-type") : "recurring";

    setLoading(btn, true);
    api("POST", "/groups", {
      title: name,
      type: planType || "recurring",
      product_type: 1,
      status: "active",
    })
      .then(function (group) {
        window.location.href = cfg.listUrl + "&view=detail&plan=" + group.id;
      })
      .catch(function (err) {
        setLoading(btn, false);
        window.alert(err.message || i18n.genericError);
      });
  });

  document.addEventListener("click", function (e) {
    var link = e.target.closest("[data-subscrpt-delete-plan]");
    if (!link) {
      return;
    }
    e.preventDefault();

    if (!window.confirm(i18n.confirmPlan)) {
      return;
    }

    var id = link.getAttribute("data-subscrpt-delete-plan");
    api("DELETE", "/groups/" + id)
      .then(function () {
        var row = link.closest(".wpsubs-plan-row");
        if (row) {
          row.parentNode.removeChild(row);
        } else {
          window.location.href = cfg.listUrl;
        }
      })
      .catch(function (err) {
        window.alert(err.message || i18n.genericError);
      });
  });

  /* ------------------------------------------------------------------ *
   * Selling plans (terms): create / edit / delete / toggle.
   * ------------------------------------------------------------------ */

  /**
   * Collect every [data-subscrpt-field] value inside a scope into a flat map.
   *
   * @param {HTMLElement} scope Container.
   * @return {Object} field → value.
   */
  function collectFields(scope) {
    var out = {};
    scope.querySelectorAll("[data-subscrpt-field]").forEach(function (el) {
      var key = el.getAttribute("data-subscrpt-field");
      if (el.classList.contains("wpsubs-adv-select")) {
        out[key] = advValue(el);
      } else if (el.type === "checkbox") {
        out[key] = el.checked;
      } else if (el.type === "radio") {
        if (el.checked) {
          out[key] = el.value;
        }
      } else {
        out[key] = el.value;
      }
    });
    return out;
  }

  /**
   * Read the current value of an advanced-select (its hidden input).
   *
   * @param {HTMLElement} root .wpsubs-adv-select root.
   * @return {string}
   */
  function advValue(root) {
    var hidden = root.querySelector('input[type="hidden"]');
    return hidden ? hidden.value : "";
  }

  /**
   * Programmatically set an advanced-select's value + visible label.
   *
   * @param {HTMLElement} root  .wpsubs-adv-select root.
   * @param {string}      value Option value to select.
   */
  function setAdvSelect(root, value) {
    value = value == null ? "" : String(value);
    var hidden = root.querySelector('input[type="hidden"]');
    if (hidden) {
      hidden.value = value;
    }
    var label = root.querySelector(".wpsubs-adv-select__label");
    var item = root.querySelector('.wpsubs-adv-select__item[data-value="' + value + '"]');
    if (label) {
      label.textContent = item ? item.textContent.trim() : root.getAttribute("data-placeholder") || "";
    }
  }

  /**
   * Map the term modal's flat fields to a /terms payload.
   *
   * @param {Object} f         Flat field map.
   * @param {string} groupId   Plan group id.
   * @param {string} groupType Plan group type key.
   * @return {Object} REST payload.
   */
  function termPayload(f, groupId, groupType) {
    var data = {
      free_trial_interval: f.free_trial_interval || "day",
    };

    // Recurring Delivery: separate delivery schedule + sync toggle.
    if (typeof f.delivery_sync !== "undefined") {
      data.delivery_sync = !!f.delivery_sync;
      if (data.delivery_sync && typeof f.delivery_day !== "undefined") {
        data.delivery_day = f.delivery_day;
      }
    }
    if (typeof f.delivery_frequency !== "undefined") {
      var deliveryFreq = parseInt(f.delivery_frequency, 10);
      if (!String(f.delivery_frequency).trim() || isNaN(deliveryFreq) || deliveryFreq < 1) {
        // Empty delivery schedule -> mirror the billing schedule.
        data.delivery_frequency = parseInt(f.billing_frequency, 10) || 1;
        data.delivery_interval = f.billing_interval || "month";
      } else {
        data.delivery_frequency = deliveryFreq;
        data.delivery_interval = f.delivery_interval || "month";
      }
    }

    // Split Payment: number of payments + access-ends timing.
    if (typeof f.installment_count !== "undefined") {
      data.installment_count = Math.max(2, parseInt(f.installment_count, 10) || 2);
    }
    if (typeof f.access_ends !== "undefined") {
      data.access_ends = f.access_ends || "full_duration";
      if ("custom" === data.access_ends) {
        data.access_custom_value = parseInt(f.access_custom_value, 10) || 1;
        data.access_custom_interval = f.access_custom_interval || "month";
      }
    }

    return {
      plan_group_id: parseInt(groupId, 10),
      type: groupType || "recurring",
      title: f.title || "",
      billing_frequency: parseInt(f.billing_frequency, 10) || 1,
      billing_interval: INTERVAL_TO_INT[f.billing_interval] || 3,
      billing_length: parseInt(f.billing_length, 10) || 0,
      free_trial: f.free_trial || "",
      signup_fee: { amount: f.signup_fee_amount || "" },
      status: "active",
      data: data,
    };
  }

  /**
   * Prefill the term modal from a fetched term, or clear it for "add".
   *
   * @param {HTMLElement} modal Term modal.
   * @param {Object|null} term  Term row, or null to reset.
   */
  function fillTermModal(modal, term) {
    modal.querySelectorAll("[data-subscrpt-field]").forEach(function (el) {
      var key = el.getAttribute("data-subscrpt-field");
      var adv = el.classList.contains("wpsubs-adv-select");

      // Reset to defaults for "add".
      if (!term) {
        if (adv) {
          setAdvSelect(el, el.getAttribute("data-default-value") || "");
        } else if (el.type === "checkbox") {
          el.checked = false;
        } else if (el.type !== "radio") {
          el.value = key === "billing_frequency" || key === "billing_length" ? el.defaultValue : "";
        }
        return;
      }

      var data = term.data || {};
      switch (key) {
        case "title":
          el.value = term.title || "";
          break;
        case "billing_frequency":
          el.value = term.billing_frequency || 1;
          break;
        case "billing_interval":
          setAdvSelect(el, INT_TO_INTERVAL[term.billing_interval] || "month");
          break;
        case "billing_length":
          el.value = term.billing_length || 0;
          break;
        case "free_trial":
          el.value = term.free_trial || "";
          break;
        case "free_trial_interval":
          setAdvSelect(el, data.free_trial_interval || "day");
          break;
        case "signup_fee_amount":
          el.value = term.signup_fee && term.signup_fee.amount ? term.signup_fee.amount : "";
          break;
        case "delivery_sync":
          el.checked = !!data.delivery_sync;
          break;
        case "delivery_day":
          setAdvSelect(el, typeof data.delivery_day !== "undefined" ? data.delivery_day : "1");
          break;
        case "delivery_frequency":
          el.value = data.delivery_frequency || 1;
          break;
        case "delivery_interval":
          setAdvSelect(el, data.delivery_interval || "month");
          break;
        case "installment_count":
          el.value = data.installment_count || 2;
          break;
        case "access_ends":
          setAdvSelect(el, data.access_ends || "full_duration");
          break;
        case "access_custom_value":
          el.value = data.access_custom_value || 1;
          break;
        case "access_custom_interval":
          setAdvSelect(el, data.access_custom_interval || "month");
          break;
        default:
          break;
      }
    });
    modal.setAttribute("data-editing", term ? term.id : "");
    toggleAccessCustom(modal);
    toggleDeliveryDay(modal);
    var title = modal.querySelector("[data-subscrpt-term-title]");
    if (title) {
      title.textContent = term ? i18n.editTerm || "Edit Selling Plan" : i18n.addTerm || "Add Selling Plan";
    }
  }

  /**
   * Show the custom access-length inputs only when "Custom" is selected.
   *
   * @param {HTMLElement} modal Term modal (or any ancestor of the controls).
   */
  function toggleAccessCustom(modal) {
    if (!modal) {
      return;
    }
    var sel = modal.querySelector('[data-subscrpt-field="access_ends"]');
    var custom = modal.querySelector("[data-subscrpt-access-custom]");
    if (!sel || !custom) {
      return;
    }
    custom.style.display = "custom" === advValue(sel) ? "" : "none";
  }

  // Re-evaluate the custom access row when the selection changes.
  document.addEventListener("wpsubs:select", function (e) {
    var root = e.target.closest('[data-subscrpt-field="access_ends"]');
    if (root) {
      toggleAccessCustom(root.closest("[data-subscrpt-term-modal]"));
    }
  });

  /**
   * Enable the delivery-day picker only when Synchronize schedule is on
   * (it stays visible but disabled otherwise).
   *
   * @param {HTMLElement} modal Term modal.
   */
  function toggleDeliveryDay(modal) {
    if (!modal) {
      return;
    }
    var box = modal.querySelector('[data-subscrpt-field="delivery_sync"]');
    var day = modal.querySelector("[data-subscrpt-delivery-day]");
    if (!box || !day) {
      return;
    }
    var on = box.checked;
    day.style.opacity = on ? "" : "0.55";
    day.style.pointerEvents = on ? "" : "none";
  }

  // Toggle the delivery-day picker as the sync checkbox changes.
  document.addEventListener("change", function (e) {
    var box = e.target.closest('[data-subscrpt-field="delivery_sync"]');
    if (box) {
      toggleDeliveryDay(box.closest("[data-subscrpt-term-modal]"));
    }
  });

  // Open the term modal in add or edit mode.
  document.addEventListener("click", function (e) {
    var add = e.target.closest("[data-subscrpt-add-term]");
    var edit = e.target.closest("[data-subscrpt-edit-term]");
    if (!add && !edit) {
      return;
    }
    var modal = document.querySelector("[data-subscrpt-term-modal]");
    if (!modal) {
      return;
    }
    if (add) {
      fillTermModal(modal, null);
      // The trigger already carries data-wpsubs-modal-open, so the shared
      // WPSubsModal opens it.
      return;
    }
    e.preventDefault();
    var termId = edit.getAttribute("data-subscrpt-edit-term");
    api("GET", "/terms/" + termId)
      .then(function (term) {
        fillTermModal(modal, term);
        openModal("subscrpt-term-modal");
      })
      .catch(function (err) {
        window.alert(err.message || i18n.genericError);
      });
  });

  // Save the term (create or update).
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-term-submit]");
    if (!btn) {
      return;
    }
    var modal = btn.closest("[data-subscrpt-term-modal]");
    var groupId = modal.getAttribute("data-group-id");
    var groupType = modal.getAttribute("data-group-type");
    var editing = modal.getAttribute("data-editing");
    var payload = termPayload(collectFields(modal), groupId, groupType);

    if (!payload.title) {
      window.alert(i18n.nameRequired);
      return;
    }

    setLoading(btn, true);
    var req = editing ? api("PUT", "/terms/" + editing, payload) : api("POST", "/terms", payload);
    req
      .then(function () {
        window.location.reload();
      })
      .catch(function (err) {
        setLoading(btn, false);
        window.alert(err.message || i18n.genericError);
      });
  });

  // Delete a term.
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-delete-term]");
    if (!btn) {
      return;
    }
    if (!window.confirm(i18n.confirmTerm)) {
      return;
    }
    var id = btn.getAttribute("data-subscrpt-delete-term");
    api("DELETE", "/terms/" + id)
      .then(function () {
        var item = btn.closest("[data-term-id]");
        if (item) {
          item.parentNode.removeChild(item);
        }
      })
      .catch(function (err) {
        window.alert(err.message || i18n.genericError);
      });
  });

  // Set a term active/draft from its actions menu, then refresh.
  document.addEventListener("click", function (e) {
    var link = e.target.closest("[data-subscrpt-set-term-status]");
    if (!link) {
      return;
    }
    e.preventDefault();
    var id = link.getAttribute("data-term-id");
    var status = link.getAttribute("data-subscrpt-set-term-status");
    api("PUT", "/terms/" + id, { status: status })
      .then(function () {
        window.location.reload();
      })
      .catch(function (err) {
        window.alert(err.message || i18n.genericError);
      });
  });

  /* ------------------------------------------------------------------ *
   * Products tab (Pro): bulk-add products to the plan group.
   * ------------------------------------------------------------------ */

  /**
   * Populate the Add Products picker with a product search.
   *
   * @param {HTMLElement} modal  The add-product modal.
   * @param {string}      search Search term.
   */
  function loadProducts(modal, search) {
    var list = modal.querySelector("[data-subscrpt-product-list]");
    if (!list) {
      return;
    }
    list.innerHTML =
      '<li style="padding:10px 4px;color:var(--wpsubs-text-subtle);font-size:13px;">' +
      (i18n.loading || "Loading…") +
      "</li>";

    api("GET", "/products?search=" + encodeURIComponent(search || ""))
      .then(function (products) {
        if (!products.length) {
          list.innerHTML =
            '<li style="padding:10px 4px;color:var(--wpsubs-text-subtle);font-size:13px;">' +
            (i18n.noProducts || "No products found.") +
            "</li>";
          return;
        }
        list.innerHTML = "";
        products.forEach(function (p) {
          var li = document.createElement("li");
          li.style.cssText =
            "display:flex;align-items:center;gap:10px;padding:8px 4px;border-bottom:1px solid var(--wpsubs-border);";

          var label = document.createElement("label");
          label.style.cssText = "display:flex;align-items:center;gap:10px;flex:1 1 auto;min-width:0;cursor:pointer;";

          var cb = document.createElement("input");
          cb.type = "checkbox";
          cb.className = "wpsubs-checkbox";
          cb.value = p.id;
          cb.setAttribute("data-price", p.price || "");

          var name = document.createElement("span");
          name.textContent = p.name;
          name.style.fontWeight = "500";

          label.appendChild(cb);
          label.appendChild(name);

          var price = document.createElement("span");
          price.style.cssText = "color:var(--wpsubs-text-muted);font-size:13px;flex:0 0 auto;";
          price.textContent = p.price_html || "";

          li.appendChild(label);
          li.appendChild(price);
          list.appendChild(li);
        });
      })
      .catch(function () {
        list.innerHTML =
          '<li style="padding:10px 4px;color:var(--wpsubs-text-subtle);font-size:13px;">' +
          (i18n.genericError || "") +
          "</li>";
      });
  }

  // Load the picker when the modal opens.
  document.addEventListener("wpsubs:modal:open", function (e) {
    var modal = e.target;
    if (modal && modal.id === "subscrpt-add-product") {
      loadProducts(modal, "");
    }
  });

  // Debounced product search.
  var addProductTimer;
  document.addEventListener("input", function (e) {
    var input = e.target.closest("[data-subscrpt-product-search]");
    if (!input) {
      return;
    }
    var modal = input.closest("[data-subscrpt-add-product]");
    window.clearTimeout(addProductTimer);
    addProductTimer = window.setTimeout(function () {
      loadProducts(modal, input.value.trim());
    }, 300);
  });

  // Attach the checked products to every selling plan in the group.
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-add-product-submit]");
    if (!btn) {
      return;
    }
    var modal = btn.closest("[data-subscrpt-add-product]");
    var groupId = modal.getAttribute("data-group-id");
    var checked = modal.querySelectorAll("[data-subscrpt-product-list] input:checked");
    if (!checked.length) {
      return;
    }

    setLoading(btn, true);
    api("GET", "/groups/" + groupId)
      .then(function (group) {
        var terms = (group && group.plans) || [];
        var isInstallments = group && ("installments" === group.type_key || 3 === parseInt(group.type, 10));
        var calls = [];
        Array.prototype.forEach.call(checked, function (box) {
          var price = box.getAttribute("data-price") || "";
          var data = isInstallments
            ? { price_per_installment: price, down_payment: "" }
            : { regular_price: price, sale_price: "" };
          terms.forEach(function (term) {
            calls.push(
              api("POST", "/relations", {
                plan_id: term.id,
                oid: parseInt(box.value, 10),
                vid: 0,
                type: 1,
                status: "active",
                data: data,
              }),
            );
          });
        });
        return Promise.all(calls);
      })
      .then(function () {
        window.location.reload();
      })
      .catch(function (err) {
        setLoading(btn, false);
        window.alert(err.message || i18n.genericError);
      });
  });
})();
