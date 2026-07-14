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
   * Map the term modal's flat fields to a /terms payload (Recurring).
   *
   * @param {Object} f       Flat field map.
   * @param {string} groupId Plan group id.
   * @return {Object} REST payload.
   */
  function termPayload(f, groupId) {
    return {
      plan_group_id: parseInt(groupId, 10),
      type: "recurring",
      title: f.title || "",
      billing_frequency: parseInt(f.billing_frequency, 10) || 1,
      billing_interval: INTERVAL_TO_INT[f.billing_interval] || 3,
      billing_length: parseInt(f.billing_length, 10) || 0,
      free_trial: f.free_trial || "",
      signup_fee: { amount: f.signup_fee_amount || "" },
      status: "active",
      data: {
        free_trial_interval: f.free_trial_interval || "day",
      },
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
        default:
          break;
      }
    });
    modal.setAttribute("data-editing", term ? term.id : "");
    var title = modal.querySelector("[data-subscrpt-term-title]");
    if (title) {
      title.textContent = term ? i18n.editTerm || "Edit Selling Plan" : i18n.addTerm || "Add Selling Plan";
    }
  }

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
    var editing = modal.getAttribute("data-editing");
    var payload = termPayload(collectFields(modal), groupId);

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
})();
