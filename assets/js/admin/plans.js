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
   * Set a variable parent's checkbox from its children's state.
   *
   * @param {HTMLElement} parentCb   Parent control checkbox.
   * @param {HTMLElement[]} childBoxes Variation checkboxes.
   */
  function syncParent(parentCb, childBoxes) {
    var all = childBoxes.every(function (c) {
      return c.checked;
    });
    var none = childBoxes.every(function (c) {
      return !c.checked;
    });
    parentCb.checked = all;
    parentCb.indeterminate = !all && !none;
  }

  /**
   * Fetch the group's product relations as a Set of "oid:vid" keys, so the
   * picker can pre-check what's already attached.
   *
   * @param {number|string} groupId Plan group id.
   * @return {Promise<Set>}
   */
  function fetchAttached(groupId) {
    return api("GET", "/groups/" + groupId)
      .then(function (group) {
        var set = new Set();
        ((group && group.plans) || []).forEach(function (term) {
          (term.relations || []).forEach(function (rel) {
            if (1 === parseInt(rel.type, 10)) {
              set.add(parseInt(rel.oid, 10) + ":" + (parseInt(rel.vid, 10) || 0));
            }
          });
        });
        return set;
      })
      .catch(function () {
        return new Set();
      });
  }

  /**
   * Build one picker row: checkbox, thumbnail, name, price.
   *
   * Attachable rows (simple products, variations) carry data-oid / data-vid /
   * data-price on the checkbox. A "control" row (variable parent) has none —
   * it only toggles its children and is skipped when attaching.
   *
   * @param {Object}  p    Product/variation row from the API.
   * @param {Object}  opts { indent, control, parentId }.
   * @return {{ li: HTMLElement, cb: HTMLElement }}
   */
  function productRow(p, opts) {
    opts = opts || {};

    var li = document.createElement("li");
    li.style.cssText =
      "margin:0;display:flex;align-items:center;gap:10px;padding:8px 4px;padding-left:" +
      (opts.indent ? "36px" : "4px") +
      ";border-bottom:1px solid rgba(0,0,0,0.06);";

    var label = document.createElement("label");
    label.style.cssText = "display:flex;align-items:center;gap:10px;flex:1 1 auto;min-width:0;cursor:pointer;";

    var cb = document.createElement("input");
    cb.type = "checkbox";
    cb.className = "wpsubs-checkbox";
    if (!opts.control) {
      var oid = opts.parentId ? opts.parentId : p.id;
      var vid = opts.parentId ? p.id : 0;
      cb.setAttribute("data-oid", oid);
      cb.setAttribute("data-vid", vid);
      cb.setAttribute("data-price", p.price || "");
      // Pre-check rows already attached to the group.
      if (opts.attached && opts.attached.has(oid + ":" + vid)) {
        cb.checked = true;
      }
    }
    label.appendChild(cb);

    var thumb = document.createElement("span");
    thumb.style.cssText =
      "flex:0 0 auto;width:32px;height:32px;border-radius:4px;background:var(--wpsubs-bg-subtle,#f1f2f4);border:1px solid var(--wpsubs-border);overflow:hidden;display:flex;align-items:center;justify-content:center;";
    if (p.image) {
      var img = document.createElement("img");
      img.src = p.image;
      img.alt = "";
      img.style.cssText = "width:100%;height:100%;object-fit:cover;display:block;";
      thumb.appendChild(img);
    } else {
      thumb.innerHTML =
        '<span class="dashicons dashicons-format-image" style="font-size:16px;width:16px;height:16px;color:var(--wpsubs-text-subtle);"></span>';
    }
    label.appendChild(thumb);

    var name = document.createElement("span");
    name.textContent = p.name;
    name.style.cssText = "font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;";
    label.appendChild(name);

    var price = document.createElement("span");
    price.style.cssText = "color:var(--wpsubs-text-muted);font-size:13px;flex:0 0 auto;";
    price.textContent = p.price_html || "";

    li.appendChild(label);
    li.appendChild(price);

    return { li: li, cb: cb };
  }

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
    var attached = modal._attached || new Set();
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
          var variations = p.variations || [];

          if (variations.length) {
            // Variable product: parent row is a select-all control (no own
            // relation), variations are the attachable rows offset beneath it.
            var parent = productRow(p, { indent: false, control: true });
            list.appendChild(parent.li);

            var childBoxes = [];
            variations.forEach(function (v) {
              var child = productRow(v, { indent: true, parentId: p.id, attached: attached });
              childBoxes.push(child.cb);
              list.appendChild(child.li);
            });

            // Reflect the pre-checked children in the parent control.
            syncParent(parent.cb, childBoxes);

            // Parent toggles every child; children keep the parent in sync.
            parent.cb.addEventListener("change", function () {
              childBoxes.forEach(function (cb) {
                cb.checked = parent.cb.checked;
              });
              parent.cb.indeterminate = false;
            });
            childBoxes.forEach(function (cb) {
              cb.addEventListener("change", function () {
                syncParent(parent.cb, childBoxes);
              });
            });
          } else {
            list.appendChild(productRow(p, { indent: false, attached: attached }).li);
          }
        });

        // No divider under the last row.
        if (list.lastElementChild) {
          list.lastElementChild.style.borderBottom = "none";
        }
      })
      .catch(function () {
        list.innerHTML =
          '<li style="padding:10px 4px;color:var(--wpsubs-text-subtle);font-size:13px;">' +
          (i18n.genericError || "") +
          "</li>";
      });
  }

  // Load the picker when the modal opens (pre-checking attached products).
  document.addEventListener("wpsubs:modal:open", function (e) {
    var modal = e.target;
    if (modal && modal.id === "subscrpt-add-product") {
      fetchAttached(modal.getAttribute("data-group-id")).then(function (set) {
        modal._attached = set;
        loadProducts(modal, "");
      });
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
    // Only attachable rows carry data-oid (variable parents are skipped).
    var boxes = modal.querySelectorAll("[data-subscrpt-product-list] input[data-oid]");

    // Sync the group to the picker: attach checked rows, detach any previously
    // attached row that is now unchecked.
    var checkedKeys = new Set();
    var checked = [];
    Array.prototype.forEach.call(boxes, function (box) {
      if (box.checked) {
        checked.push(box);
        checkedKeys.add(box.getAttribute("data-oid") + ":" + (box.getAttribute("data-vid") || "0"));
      }
    });
    var attached = modal._attached || new Set();
    var toRemove = new Set();
    attached.forEach(function (key) {
      if (!checkedKeys.has(key)) {
        toRemove.add(key);
      }
    });

    if (!checked.length && !toRemove.size) {
      return;
    }

    setLoading(btn, true);
    api("GET", "/groups/" + groupId)
      .then(function (group) {
        var terms = (group && group.plans) || [];
        var isInstallments = group && ("installments" === group.type_key || 3 === parseInt(group.type, 10));
        var calls = [];

        // Attach checked rows to every term.
        checked.forEach(function (box) {
          var price = box.getAttribute("data-price") || "";
          var data = isInstallments
            ? { price_per_installment: price, down_payment: "" }
            : { regular_price: price, sale_price: "" };
          terms.forEach(function (term) {
            calls.push(
              api("POST", "/relations", {
                plan_id: term.id,
                oid: parseInt(box.getAttribute("data-oid"), 10),
                vid: parseInt(box.getAttribute("data-vid"), 10) || 0,
                type: 1,
                status: "active",
                data: data,
              }),
            );
          });
        });

        // Detach rows that were unchecked.
        if (toRemove.size) {
          terms.forEach(function (term) {
            (term.relations || []).forEach(function (rel) {
              if (1 !== parseInt(rel.type, 10)) {
                return;
              }
              var key = parseInt(rel.oid, 10) + ":" + (parseInt(rel.vid, 10) || 0);
              if (toRemove.has(key)) {
                calls.push(api("DELETE", "/relations/" + rel.id));
              }
            });
          });
        }

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

  // Remove a product from the group: delete every relation for that product
  // (all selling plans, all variations).
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-remove-product]");
    if (!btn) {
      return;
    }
    var oid = parseInt(btn.getAttribute("data-subscrpt-remove-product"), 10);
    var wrap = document.querySelector("[data-plan-id]");
    var groupId = wrap && wrap.getAttribute("data-plan-id");
    if (!groupId || !window.confirm(i18n.confirmRemoveProduct || "Remove this product from the plan?")) {
      return;
    }

    setLoading(btn, true);
    api("GET", "/groups/" + groupId)
      .then(function (group) {
        var ids = [];
        ((group && group.plans) || []).forEach(function (term) {
          (term.relations || []).forEach(function (rel) {
            if (1 === parseInt(rel.type, 10) && parseInt(rel.oid, 10) === oid) {
              ids.push(rel.id);
            }
          });
        });
        return Promise.all(
          ids.map(function (id) {
            return api("DELETE", "/relations/" + id);
          }),
        );
      })
      .then(function () {
        window.location.reload();
      })
      .catch(function (err) {
        setLoading(btn, false);
        window.alert(err.message || i18n.genericError);
      });
  });

  // Remove a single variation from the group: delete every relation for that
  // (product, variation) pair across all selling plans.
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-remove-variation]");
    if (!btn) {
      return;
    }
    var oid = parseInt(btn.getAttribute("data-oid"), 10);
    var vid = parseInt(btn.getAttribute("data-vid"), 10);
    var wrap = document.querySelector("[data-plan-id]");
    var groupId = wrap && wrap.getAttribute("data-plan-id");
    if (!groupId || !window.confirm(i18n.confirmRemoveVariation || "Remove this variation from the plan?")) {
      return;
    }

    setLoading(btn, true);
    api("GET", "/groups/" + groupId)
      .then(function (group) {
        var ids = [];
        ((group && group.plans) || []).forEach(function (term) {
          (term.relations || []).forEach(function (rel) {
            if (1 === parseInt(rel.type, 10) && parseInt(rel.oid, 10) === oid && parseInt(rel.vid, 10) === vid) {
              ids.push(rel.id);
            }
          });
        });
        return Promise.all(
          ids.map(function (id) {
            return api("DELETE", "/relations/" + id);
          }),
        );
      })
      .then(function () {
        window.location.reload();
      })
      .catch(function (err) {
        setLoading(btn, false);
        window.alert(err.message || i18n.genericError);
      });
  });

  /* ------------------------------------------------------------------ *
   * Products tab (Pro): inline edit of a price card's rows.
   * ------------------------------------------------------------------ */

  /**
   * Toggle a price card between read and edit mode.
   *
   * @param {HTMLElement} card    The [data-subscrpt-price-card] element.
   * @param {boolean}     editing Desired mode.
   */
  /**
   * Show or hide a price card's one-time column.
   *
   * @param {HTMLElement} card The [data-subscrpt-price-card] element.
   * @param {boolean}     show Whether the column is visible.
   */
  function setOneTimeColumn(card, show) {
    card.querySelectorAll(".subscrpt-onetime-col").forEach(function (cell) {
      cell.style.display = show ? "" : "none";
    });
  }

  function priceCardMode(card, editing) {
    card.querySelectorAll(".subscrpt-pe-view").forEach(function (el) {
      el.style.display = editing ? "none" : "";
    });
    card.querySelectorAll(".subscrpt-pe-edit").forEach(function (el) {
      el.style.display = editing ? "" : "none";
    });
    var toggle = function (sel, show) {
      var btn = card.querySelector(sel);
      if (btn) {
        btn.style.display = show ? "" : "none";
      }
    };
    toggle("[data-subscrpt-edit-prices]", !editing);
    toggle("[data-subscrpt-cancel-prices]", editing);
    toggle("[data-subscrpt-save-prices]", editing);

    // The one-time toggle is only interactive while editing.
    var one = card.querySelector("[data-subscrpt-onetime-toggle]");
    if (one) {
      one.disabled = !editing;
    }
  }

  // Enter edit mode (snapshot current values for cancel).
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-edit-prices]");
    if (!btn) {
      return;
    }
    var card = btn.closest("[data-subscrpt-price-card]");
    card.querySelectorAll("[data-field]").forEach(function (field) {
      field.dataset.orig = "checkbox" === field.type ? (field.checked ? "1" : "") : field.value;
    });
    var one = card.querySelector("[data-subscrpt-onetime-toggle]");
    if (one) {
      one.dataset.orig = one.checked ? "1" : "";
    }
    priceCardMode(card, true);
  });

  // Toggling one-time purchase reveals / hides the one-time price column.
  document.addEventListener("change", function (e) {
    var toggle = e.target.closest("[data-subscrpt-onetime-toggle]");
    if (!toggle) {
      return;
    }
    var card = toggle.closest("[data-subscrpt-price-card]");
    if (card) {
      setOneTimeColumn(card, toggle.checked);
    }
  });

  // Cancel: restore snapshot, back to read mode.
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-cancel-prices]");
    if (!btn) {
      return;
    }
    var card = btn.closest("[data-subscrpt-price-card]");
    card.querySelectorAll("[data-field]").forEach(function (field) {
      if ("checkbox" === field.type) {
        field.checked = "1" === field.dataset.orig;
      } else {
        field.value = field.dataset.orig || "";
      }
    });
    var one = card.querySelector("[data-subscrpt-onetime-toggle]");
    if (one) {
      one.checked = "1" === one.dataset.orig;
      setOneTimeColumn(card, one.checked);
    }
    priceCardMode(card, false);
  });

  // Save: PUT each row's regular / offer / one-time to its relation.
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-save-prices]");
    if (!btn) {
      return;
    }
    var card = btn.closest("[data-subscrpt-price-card]");
    var rows = card.querySelectorAll("[data-subscrpt-relation]");
    // One-time purchase is a card-level flag applied to every plan row.
    var cardToggle = card.querySelector("[data-subscrpt-onetime-toggle]");
    var oneTimeOn = cardToggle ? cardToggle.checked : false;

    setLoading(btn, true);
    var calls = Array.prototype.map.call(rows, function (row) {
      var id = row.getAttribute("data-subscrpt-relation");
      var reg = row.querySelector('[data-field="regular_price"]');
      var sale = row.querySelector('[data-field="sale_price"]');
      var onePrice = row.querySelector('[data-field="one_time_price"]');
      var oneOffer = row.querySelector('[data-field="one_time_offer"]');
      var enabled = row.querySelector('[data-field="enabled"]');
      return api("PUT", "/relations/" + id, {
        exclude: enabled ? !enabled.checked : false,
        data: {
          regular_price: reg ? reg.value : "",
          sale_price: sale ? sale.value : "",
          discount_value: 0,
          one_time: oneTimeOn,
          one_time_price: onePrice ? onePrice.value : "",
          one_time_offer: oneOffer ? oneOffer.value : "",
        },
      });
    });

    Promise.all(calls)
      .then(function () {
        window.location.reload();
      })
      .catch(function (err) {
        setLoading(btn, false);
        window.alert(err.message || i18n.genericError);
      });
  });

  /* ------------------------------------------------------------------ *
   * Products tab: client-side search + pagination over the product list.
   * ------------------------------------------------------------------ */

  /**
   * Windowed page numbers with ellipses (first, last, current ±1).
   *
   * @param {number} current Current page.
   * @param {number} total   Total pages.
   * @return {Array<number|string>}
   */
  function pageRange(current, total) {
    var out = [];
    for (var i = 1; i <= total; i++) {
      if (i === 1 || i === total || (i >= current - 1 && i <= current + 1)) {
        out.push(i);
      } else if (out[out.length - 1] !== "…") {
        out.push("…");
      }
    }
    return out;
  }

  function initBrowser(root) {
    var perPage = parseInt(root.getAttribute("data-per-page"), 10) || 10;
    var input = root.querySelector("[data-subscrpt-browse-search]");
    var items = Array.prototype.slice.call(root.querySelectorAll("[data-subscrpt-browse-item]"));
    var emptyMsg = root.querySelector("[data-subscrpt-browse-empty]");
    var pager = root.querySelector("[data-subscrpt-browse-pager]");
    var term = "";
    var page = 1;

    // Remember each item's own display so showing it restores that (e.g. cards
    // set display:flex inline) instead of clearing it to the block default.
    items.forEach(function (it) {
      it._subscrptDisplay = it.style.display || "";
    });

    function match(item) {
      if (!term) {
        return true;
      }
      var name = (item.getAttribute("data-name") || "").indexOf(term) !== -1;
      var pid = String(item.getAttribute("data-pid") || "").indexOf(term) !== -1;
      return name || pid;
    }

    function render() {
      var visible = items.filter(match);
      var total = Math.max(1, Math.ceil(visible.length / perPage));
      if (page > total) {
        page = total;
      }
      var start = (page - 1) * perPage;
      var pageItems = visible.slice(start, start + perPage);

      items.forEach(function (it) {
        it.style.display = "none";
      });
      pageItems.forEach(function (it) {
        it.style.display = it._subscrptDisplay;
      });

      if (emptyMsg) {
        emptyMsg.style.display = visible.length ? "none" : "";
      }
      renderPager(visible.length, total);
    }

    function renderPager(count, total) {
      if (total <= 1) {
        pager.innerHTML = "";
        return;
      }
      var start = count ? (page - 1) * perPage + 1 : 0;
      var end = Math.min(page * perPage, count);
      var info = (i18n.showingRange || "Showing %1-%2 of %3")
        .replace("%1", start)
        .replace("%2", end)
        .replace("%3", count);

      var nav = "";
      var chip = function (label, target, disabled, active) {
        var cls = "wpsubs-pagination__btn";
        if (disabled) {
          cls += " wpsubs-pagination__btn--disabled";
        }
        if (active) {
          cls += " wpsubs-pagination__btn--active";
        }
        if ("…" === label) {
          return '<span class="wpsubs-pagination__btn wpsubs-pagination__btn--ellipsis" aria-hidden="true">…</span>';
        }
        return '<button type="button" class="' + cls + '" data-subscrpt-page="' + target + '">' + label + "</button>";
      };

      nav += chip("‹", page - 1, page <= 1, false);
      pageRange(page, total).forEach(function (p) {
        nav += "…" === p ? chip("…") : chip(p, p, false, p === page);
      });
      nav += chip("›", page + 1, page >= total, false);

      pager.innerHTML =
        '<div class="wpsubs-pagination" role="navigation">' +
        '<span class="wpsubs-pagination__info">' +
        info +
        "</span>" +
        '<span class="wpsubs-pagination__nav">' +
        nav +
        "</span></div>";
    }

    if (input) {
      var timer;
      input.addEventListener("input", function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(function () {
          term = input.value.trim().toLowerCase();
          page = 1;
          render();
        }, 200);
      });
    }

    // Per-page adv-select (fires wpsubs:select).
    document.addEventListener("wpsubs:select", function (e) {
      var sel = e.target.closest ? e.target.closest("[data-subscrpt-browse-perpage]") : null;
      if (!sel || !root.contains(sel)) {
        return;
      }
      perPage = parseInt(e.detail && e.detail.value, 10) || perPage;
      page = 1;
      render();
    });

    pager.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-subscrpt-page]");
      if (!btn) {
        return;
      }
      page = parseInt(btn.getAttribute("data-subscrpt-page"), 10) || 1;
      render();
    });

    render();
  }

  function initBrowsers() {
    document.querySelectorAll("[data-subscrpt-browse]").forEach(initBrowser);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initBrowsers);
  } else {
    initBrowsers();
  }
})();
