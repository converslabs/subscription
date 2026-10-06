/**
 * Storefront plan selector (free).
 *
 * Pick a plan group, then a term. One script drives every layout through data
 * attributes: a group is chosen by a `subscrpt_plan_group` radio or select, each
 * option is a `[data-subscrpt-card]`, its terms are radios or a select, and a
 * layout with no room in its options renders each group's body below them as
 * `[data-subscrpt-body-for]`. The chosen plan-term id is written to a hidden
 * field that posts with add-to-cart. Simple products are
 * rendered on the server; a variable product starts with a placeholder and swaps
 * in the chosen variation's server-rendered cards (`subscrpt_plans_html`).
 * Pure DOM apart from WooCommerce's jQuery variation events; no API.
 */
(function ($) {
  "use strict";

  var box = document.querySelector("[data-subscrpt-buybox]");
  if (!box) {
    return;
  }

  /**
   * The group a purchase option belongs to: its `data-subscrpt-group`, or the
   * value of the group radio inside it (stacked cards, and a theme's copy of
   * the older template).
   *
   * @param {HTMLElement} card A `[data-subscrpt-card]` option.
   * @return {string} The group id, or "".
   */
  function groupOf(card) {
    if (card.hasAttribute("data-subscrpt-group")) {
      return card.getAttribute("data-subscrpt-group");
    }
    var radio = card.querySelector('input[name="subscrpt_plan_group"]');
    return radio ? radio.value : "";
  }

  /**
   * The selected group: the purchase-option select's value in the dropdown
   * layout, the checked group radio in the others.
   *
   * @return {string} The group id, or "".
   */
  function selectedGroup() {
    var select = box.querySelector('select[name="subscrpt_plan_group"]');
    if (select) {
      return select.value;
    }
    var checked = box.querySelector('input[name="subscrpt_plan_group"]:checked');
    return checked ? checked.value : "";
  }

  /**
   * The option element of the selected group.
   *
   * @return {?HTMLElement} The `[data-subscrpt-card]`, or null.
   */
  function selectedCard() {
    var group = selectedGroup();
    var found = null;
    box.querySelectorAll("[data-subscrpt-card]").forEach(function (card) {
      if (!found && groupOf(card) === group) {
        found = card;
      }
    });
    return found;
  }

  /**
   * Write the chosen plan-term id into the hidden field that posts with
   * add-to-cart.
   */
  function syncPlanId() {
    var hidden = box.querySelector("[data-subscrpt-plan-id]");
    if (!hidden) {
      return;
    }
    var card = selectedCard();
    var planId = "";
    if (card) {
      var term = card.querySelector("input[data-subscrpt-term]:checked");
      var select = card.querySelector("select[data-subscrpt-term-select]");
      // A theme's copy of the older template marks its term buttons instead.
      var legacy = card.querySelector("button[data-subscrpt-term-btn].is-active");
      if (term) {
        planId = term.value;
      } else if (select) {
        planId = select.value;
      } else if (legacy) {
        planId = legacy.getAttribute("data-term-id");
      } else if (card.hasAttribute("data-subscrpt-single-term")) {
        planId = card.getAttribute("data-subscrpt-single-term");
      }
    }
    hidden.value = planId || "";
  }

  /**
   * Enable or disable the form controls in an option's body, so only the chosen
   * option's body posts. A control that was already disabled by its own logic
   * is left alone and stays disabled; the details toggle never posts and stays
   * usable.
   *
   * @param {HTMLElement} body    A `[data-subscrpt-card-body]` element.
   * @param {boolean}     enabled Whether its option is the selected one.
   */
  function syncBodyControls(body, enabled) {
    body.querySelectorAll("input, select, textarea, button").forEach(function (el) {
      if (el.hasAttribute("data-subscrpt-details-toggle")) {
        return;
      }
      if (enabled) {
        if (el.hasAttribute("data-subscrpt-body-disabled")) {
          el.removeAttribute("data-subscrpt-body-disabled");
          el.disabled = false;
        }
      } else if (!el.disabled) {
        el.disabled = true;
        el.setAttribute("data-subscrpt-body-disabled", "");
      }
    });
  }

  /**
   * Mark the selected group's option and enable only its terms and its body's
   * controls, so nothing of another group is posted or reached with Tab. An option marked
   * `data-subscrpt-only-selected` shows only while selected, as does an option's
   * `data-subscrpt-panel` (the accordion), and the body
   * rendered below the control for each group shows only for the selected one.
   */
  function syncCards() {
    var group = selectedGroup();
    box.querySelectorAll("[data-subscrpt-card]").forEach(function (card) {
      var selected = groupOf(card) === group;
      card.classList.toggle("is-selected", selected);
      if (card.hasAttribute("data-subscrpt-only-selected")) {
        card.hidden = !selected;
      }
      // An accordion card opens its panel only while selected.
      card.querySelectorAll("[data-subscrpt-panel]").forEach(function (panel) {
        panel.hidden = !selected;
      });
      card.querySelectorAll("input[data-subscrpt-term], select[data-subscrpt-term-select]").forEach(function (term) {
        term.disabled = !selected;
      });
      card.querySelectorAll("[data-subscrpt-card-body]").forEach(function (body) {
        syncBodyControls(body, selected);
      });
    });
    // The dropdown layout's option select is described by the chosen option's badge.
    var groupSelect = box.querySelector("select[data-subscrpt-group-select]");
    var chosen = selectedCard();
    var badge = chosen ? chosen.querySelector("[data-subscrpt-badge][id]") : null;
    if (groupSelect && badge) {
      groupSelect.setAttribute("aria-describedby", badge.id);
    } else if (groupSelect) {
      groupSelect.removeAttribute("aria-describedby");
    }
    box.querySelectorAll("[data-subscrpt-body-for]").forEach(function (body) {
      var selected = body.getAttribute("data-subscrpt-body-for") === group;
      body.hidden = !selected;
      syncBodyControls(body, selected);
    });
    syncPlanId();
  }

  /**
   * Show what the chosen term of a card costs and saves.
   *
   * @param {HTMLElement} term The checked term radio, or the clicked term
   *                           button of an older template.
   */
  function showTerm(term) {
    var card = term.closest("[data-subscrpt-card]");
    if (!card) {
      return;
    }

    card.querySelectorAll("[data-subscrpt-term-btn]").forEach(function (chip) {
      chip.classList.toggle("is-active", chip === term || chip.getAttribute("for") === term.id);
    });

    var noteEl = card.querySelector("[data-subscrpt-note]");
    if (noteEl) {
      // Note is server-built HTML (may include a struck-through regular price).
      noteEl.innerHTML = term.getAttribute("data-note") || "";
    }

    var priceEl = card.querySelector("[data-subscrpt-card-price]");
    if (priceEl) {
      priceEl.textContent = term.getAttribute("data-price") || "";
    }

    // The struck regular price, hidden when the chosen term has no saving.
    var regularEl = card.querySelector("[data-subscrpt-card-regular]");
    if (regularEl) {
      var regular = term.getAttribute("data-regular") || "";
      regularEl.textContent = regular;
      regularEl.hidden = regular === "";
    }

    // The badge reports what *this* term saves, so it moves with the selection.
    // Hidden outright when the chosen term is not discounted, so no empty pill
    // is left behind.
    var badgeEl = card.querySelector("[data-subscrpt-badge]");
    if (badgeEl) {
      var badge = term.getAttribute("data-badge") || "";
      badgeEl.textContent = badge;
      badgeEl.hidden = badge === "";
    }
  }

  box.addEventListener("change", function (e) {
    if (e.target.name === "subscrpt_plan_group") {
      syncCards();
    } else if (e.target.hasAttribute && e.target.hasAttribute("data-subscrpt-term")) {
      showTerm(e.target);
      syncPlanId();
    } else if (e.target.hasAttribute && e.target.hasAttribute("data-subscrpt-term-select")) {
      var option = e.target.options[e.target.selectedIndex];
      if (option) {
        showTerm(option);
      }
      syncPlanId();
    }
  });

  // A card's details panel opens and closes from its button.
  box.addEventListener("click", function (e) {
    var toggle = e.target.closest("[data-subscrpt-details-toggle]");
    if (!toggle) {
      return;
    }
    var panel = document.getElementById(toggle.getAttribute("aria-controls"));
    var open = toggle.getAttribute("aria-expanded") !== "true";
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    if (panel) {
      panel.hidden = !open;
    }
  });

  // Escape closes an open details panel and hands focus back to its button.
  box.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") {
      return;
    }
    var panel = e.target.closest(".subscrpt-buybox__details");
    var toggle = e.target.closest("[data-subscrpt-details-toggle]");
    if (panel) {
      toggle = box.querySelector('[aria-controls="' + panel.id + '"]');
    } else if (toggle && toggle.getAttribute("aria-expanded") !== "true") {
      return;
    }
    if (!toggle) {
      return;
    }
    toggle.setAttribute("aria-expanded", "false");
    var target = document.getElementById(toggle.getAttribute("aria-controls"));
    if (target) {
      target.hidden = true;
    }
    toggle.focus();
  });

  // A click anywhere on a card selects it, except inside its body, which holds
  // controls of its own. A click on a term of an unselected card selects the
  // card first, which enables the term before the label checks it.
  box.addEventListener("click", function (e) {
    if (e.target.closest("[data-subscrpt-card-body]")) {
      return;
    }
    var card = e.target.closest("[data-subscrpt-card]");
    var radio = card ? card.querySelector('input[name="subscrpt_plan_group"]') : null;
    if (radio && !radio.checked) {
      radio.checked = true;
      radio.dispatchEvent(new Event("change", { bubbles: true }));
    }

    var legacy = e.target.closest("button[data-subscrpt-term-btn]");
    if (legacy) {
      e.preventDefault();
      showTerm(legacy);
      syncPlanId();
    }
  });

  // Initialise from the pre-selected (first) card.
  syncCards();

  if (box.getAttribute("data-subscrpt-variable") !== "1" || typeof $ !== "function") {
    return;
  }

  var placeholder = box.innerHTML;

  /**
   * Replace the cards, announcing it on the box so a node parked inside one
   * (the box builder) can be moved out first and put back after. Nothing
   * happens, and nothing is announced, when the cards would not change.
   *
   * @param {string} html    Inner markup to show.
   * @param {string} context The `data-subscrpt-context` of that markup, or "".
   * @param {string} layout  The `data-subscrpt-layout` of that markup, or "" to keep the box's.
   */
  function swap(html, context, layout) {
    if (
      html === box.innerHTML &&
      (context || null) === box.getAttribute("data-subscrpt-context") &&
      (!layout || layout === box.getAttribute("data-subscrpt-layout"))
    ) {
      return;
    }
    box.dispatchEvent(new CustomEvent("subscrpt_cards_before_swap", { bubbles: true }));
    box.innerHTML = html;
    if (context) {
      box.setAttribute("data-subscrpt-context", context);
    } else {
      box.removeAttribute("data-subscrpt-context");
    }
    if (layout) {
      box.setAttribute("data-subscrpt-layout", layout);
    }
    syncCards();
    box.dispatchEvent(new CustomEvent("subscrpt_cards_after_swap", { bubbles: true }));
  }

  /**
   * Show a variation's server-rendered selector inside the existing box, so
   * the listeners bound to it keep working.
   *
   * @param {string} html The template's markup, its own buybox wrapper included.
   */
  function showVariation(html) {
    var holder = document.createElement("div");
    holder.innerHTML = html;
    var rendered = holder.querySelector("[data-subscrpt-buybox]");
    if (!rendered) {
      swap(placeholder, "");
      return;
    }
    swap(
      rendered.innerHTML,
      rendered.getAttribute("data-subscrpt-context") || "",
      rendered.getAttribute("data-subscrpt-layout") || "",
    );
  }

  var $form = $(box).closest("form.variations_form");
  if (!$form.length) {
    $form = $("form.variations_form").first();
  }

  $form.on("found_variation", function (event, variation) {
    if (variation && variation.subscrpt_plans_html) {
      showVariation(variation.subscrpt_plans_html);
    } else {
      swap(placeholder, "");
    }
  });

  $form.on("reset_data hide_variation", function () {
    swap(placeholder, "");
  });
})(window.jQuery);
