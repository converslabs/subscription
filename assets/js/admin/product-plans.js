/**
 * Product editor — plan view (free, simple products).
 *
 * Toggles between the plan view and the classic settings, and connects /
 * detaches the product to a Recurring plan group via the wpsubscription/v1
 * REST API. Relies on the shared admin components (adv-select) being enqueued.
 */
(function () {
  "use strict";

  var cfg = window.subscrptProductPlans || {};
  var i18n = cfg.i18n || {};

  /**
   * Call a plan REST endpoint.
   *
   * @param {string} method HTTP verb.
   * @param {string} path   Path under the /plans base.
   * @param {Object} [body] JSON body.
   * @return {Promise<Object>}
   */
  function api(method, path, body) {
    return fetch(cfg.restUrl + path, {
      method: method,
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": cfg.nonce,
      },
      credentials: "same-origin",
      body: body ? JSON.stringify(body) : undefined,
    }).then(function (res) {
      if (!res.ok) {
        return res.json().then(function (data) {
          throw new Error((data && data.message) || "");
        });
      }
      return res.status === 204 ? {} : res.json();
    });
  }

  function root() {
    return document.querySelector("[data-subscrpt-product-plans]");
  }

  /**
   * The classic-settings pane for a plan-view wrapper. Free renders its own
   * ([data-subscrpt-classic-view]); with Pro active the classic pane is Pro's
   * `.subscrpt-classic-fields` sibling inside the panel.
   *
   * @param {HTMLElement} el The [data-subscrpt-product-plans] wrapper.
   * @return {HTMLElement|null}
   */
  function classicPane(el) {
    var panel = el.closest("#sdevs_subscription_options") || el;
    return panel.querySelector("[data-subscrpt-classic-view]") || panel.querySelector(".subscrpt-classic-fields");
  }

  /**
   * Show either the plan view or the classic settings.
   *
   * @param {HTMLElement} el          The wrapper.
   * @param {boolean}     showClassic Whether classic settings are shown.
   */
  function setView(el, showClassic) {
    var planView = el.querySelector("[data-subscrpt-plan-view]");
    var classic = classicPane(el);
    if (planView) {
      planView.style.display = showClassic ? "none" : "";
    }
    if (classic) {
      classic.style.display = showClassic ? "" : "none";
    }
    var showClassicBtn = el.querySelector("[data-subscrpt-show-classic]");
    var showPlanBtn = el.querySelector("[data-subscrpt-show-plan]");
    if (showClassicBtn) {
      showClassicBtn.style.display = showClassic ? "none" : "";
    }
    if (showPlanBtn) {
      showPlanBtn.style.display = showClassic ? "" : "none";
    }
  }

  // View toggle.
  document.addEventListener("click", function (e) {
    var toClassic = e.target.closest("[data-subscrpt-show-classic]");
    var toPlan = e.target.closest("[data-subscrpt-show-plan]");
    if (!toClassic && !toPlan) {
      return;
    }
    var el = root();
    if (el) {
      setView(el, !!toClassic);
    }
  });

  // On load, default to the plan view (hide the classic pane).
  function init() {
    var el = root();
    if (el) {
      setView(el, false);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  // Picking a plan group reveals its price table (one block per group).
  // The block's "Connect" button reuses the shared save handler below.
  document.addEventListener("wpsubs:select", function (e) {
    var sel = e.target.closest ? e.target.closest("[data-subscrpt-connect-group]") : null;
    if (!sel) {
      return;
    }
    var card = sel.closest("[data-subscrpt-connect-card]");
    var groupId = String(parseInt(e.detail && e.detail.value, 10) || 0);
    card.querySelectorAll("[data-connect-block]").forEach(function (block) {
      block.style.display = block.getAttribute("data-group-id") === groupId ? "block" : "none";
    });
    var divider = card.querySelector("[data-subscrpt-connect-divider]");
    if (divider) {
      divider.style.display = "0" === groupId ? "none" : "";
    }
  });

  // Detach the product from a plan group (delete all its relations).
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-detach]");
    if (!btn) {
      return;
    }
    if (!window.confirm(i18n.confirmDetach || "Detach this product from the plan?")) {
      return;
    }
    var ids = (btn.getAttribute("data-relation-ids") || "")
      .split(",")
      .map(function (s) {
        return s.trim();
      })
      .filter(Boolean);

    btn.disabled = true;
    Promise.all(
      ids.map(function (id) {
        return api("DELETE", "/relations/" + id);
      }),
    )
      .then(function () {
        window.location.reload();
      })
      .catch(function (err) {
        btn.disabled = false;
        window.alert(err.message || i18n.connectError);
      });
  });

  /* ------------------------------------------------------------------ *
   * Edit the connected plans of a group: add / remove individual plans
   * (terms) and set each one's per-product price, inline in the card.
   * ------------------------------------------------------------------ */

  /** Show/hide a card's one-time columns. */
  function setOneTimeColumn(card, show) {
    card.querySelectorAll(".subscrpt-onetime-col").forEach(function (cell) {
      cell.style.display = show ? "" : "none";
    });
  }

  /**
   * Toggle a plan card between read and edit mode.
   *
   * @param {HTMLElement} card    The [data-subscrpt-plan-card] element.
   * @param {boolean}     editing Desired mode.
   */
  function cardMode(card, editing) {
    var view = card.querySelector(".subscrpt-pe-view");
    var edit = card.querySelector(".subscrpt-pe-edit");
    if (view) {
      view.style.display = editing ? "none" : "flex";
    }
    if (edit) {
      edit.style.display = editing ? "block" : "none";
    }
    card.querySelectorAll(".subscrpt-edit-only").forEach(function (el) {
      el.style.display = editing ? "inline-flex" : "none";
    });
    var toggle = function (sel, show) {
      var el = card.querySelector(sel);
      if (el) {
        el.style.display = show ? "" : "none";
      }
    };
    toggle("[data-subscrpt-edit-prices]", !editing);
    toggle("[data-subscrpt-detach]", !editing);
    toggle("[data-subscrpt-cancel-prices]", editing);
    toggle("[data-subscrpt-save-prices]", editing);

    var one = card.querySelector("[data-subscrpt-onetime-toggle]");
    if (one) {
      one.disabled = !editing;
    }
  }

  /** Enabled/dim a term row's price inputs from its offer toggle. */
  function syncTermRow(row) {
    var on = row.querySelector("[data-subscrpt-term-toggle]");
    var enabled = on ? on.checked : true;
    row.querySelectorAll("[data-field]").forEach(function (input) {
      input.disabled = !enabled;
      input.style.opacity = enabled ? "" : "0.5";
    });
  }

  // Enter edit mode (snapshot for cancel).
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-edit-prices]");
    if (!btn) {
      return;
    }
    var card = btn.closest("[data-subscrpt-plan-card]");
    card.querySelectorAll("[data-subscrpt-term-row]").forEach(function (row) {
      row.querySelectorAll("[data-field]").forEach(function (input) {
        input.dataset.orig = input.value;
      });
      var on = row.querySelector("[data-subscrpt-term-toggle]");
      if (on) {
        on.dataset.orig = on.checked ? "1" : "";
      }
      syncTermRow(row);
    });
    var one = card.querySelector("[data-subscrpt-onetime-toggle]");
    if (one) {
      one.dataset.orig = one.checked ? "1" : "";
    }
    cardMode(card, true);
  });

  // A term's offer toggle enables its price inputs.
  document.addEventListener("change", function (e) {
    var toggle = e.target.closest("[data-subscrpt-term-toggle]");
    if (!toggle) {
      return;
    }
    var row = toggle.closest("[data-subscrpt-term-row]");
    if (row) {
      syncTermRow(row);
    }
  });

  // The card one-time toggle reveals / hides the one-time columns.
  document.addEventListener("change", function (e) {
    var toggle = e.target.closest("[data-subscrpt-onetime-toggle]");
    if (!toggle) {
      return;
    }
    var card = toggle.closest("[data-subscrpt-plan-card]");
    if (card) {
      setOneTimeColumn(card, toggle.checked);
    }
  });

  // Cancel: restore snapshot and leave edit mode.
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-cancel-prices]");
    if (!btn) {
      return;
    }
    var card = btn.closest("[data-subscrpt-plan-card]");
    card.querySelectorAll("[data-subscrpt-term-row]").forEach(function (row) {
      row.querySelectorAll("[data-field]").forEach(function (input) {
        input.value = input.dataset.orig || "";
      });
      var on = row.querySelector("[data-subscrpt-term-toggle]");
      if (on) {
        on.checked = "1" === on.dataset.orig;
      }
    });
    var one = card.querySelector("[data-subscrpt-onetime-toggle]");
    if (one) {
      one.checked = "1" === one.dataset.orig;
      setOneTimeColumn(card, one.checked);
    }
    cardMode(card, false);
  });

  // Save: add / update / remove each term's relation with all its prices.
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-save-prices]");
    if (!btn) {
      return;
    }
    var card = btn.closest("[data-subscrpt-plan-card]");
    var wrap = root();
    var productId = wrap ? parseInt(wrap.getAttribute("data-product-id"), 10) : 0;
    var oneToggle = card.querySelector("[data-subscrpt-onetime-toggle]");
    var oneTimeOn = oneToggle ? oneToggle.checked : false;

    var fieldVal = function (row, field) {
      var el = row.querySelector('[data-field="' + field + '"]');
      return el ? el.value : "";
    };

    var calls = [];
    card.querySelectorAll("[data-subscrpt-term-row]").forEach(function (row) {
      var planId = parseInt(row.getAttribute("data-plan-id"), 10);
      var relId = row.getAttribute("data-relation-id");
      var on = row.querySelector("[data-subscrpt-term-toggle]").checked;

      var data = {
        regular_price: fieldVal(row, "regular_price"),
        sale_price: fieldVal(row, "sale_price"),
        discount_value: 0,
        one_time: oneTimeOn,
        one_time_price: fieldVal(row, "one_time_price"),
        one_time_offer: fieldVal(row, "one_time_offer"),
      };

      if (relId) {
        // Existing relation: enable/disable via exclude (never delete here, so
        // it stays consistent with the Plans page). Detach removes everything.
        calls.push(api("PUT", "/relations/" + relId, { exclude: !on, data: data }));
      } else if (on) {
        // Not connected yet and enabled: create the relation.
        calls.push(
          api("POST", "/relations", {
            plan_id: planId,
            oid: productId,
            vid: 0,
            type: 1,
            status: "active",
            exclude: false,
            data: data,
          }),
        );
      }
    });

    btn.disabled = true;
    Promise.all(calls)
      .then(function () {
        window.location.reload();
      })
      .catch(function (err) {
        btn.disabled = false;
        window.alert(err.message || i18n.connectError);
      });
  });
})();
