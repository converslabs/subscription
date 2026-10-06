/**
 * Storefront plan selector (free).
 *
 * Pick a plan group (radio card), then a term (a radio group inside the card).
 * The chosen plan-term id is written to a hidden field that posts with
 * add-to-cart. Simple products are
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
   * Write the chosen plan-term id into the hidden field that posts with
   * add-to-cart.
   */
  function syncPlanId() {
    var hidden = box.querySelector("[data-subscrpt-plan-id]");
    if (!hidden) {
      return;
    }
    var checked = box.querySelector('input[name="subscrpt_plan_group"]:checked');
    var card = checked ? checked.closest("[data-subscrpt-card]") : null;
    var planId = "";
    if (card) {
      var term = card.querySelector("input[data-subscrpt-term]:checked");
      // A theme's copy of the older template marks its term buttons instead.
      var legacy = card.querySelector("button[data-subscrpt-term-btn].is-active");
      if (term) {
        planId = term.value;
      } else if (legacy) {
        planId = legacy.getAttribute("data-term-id");
      } else if (card.hasAttribute("data-subscrpt-single-term")) {
        planId = card.getAttribute("data-subscrpt-single-term");
      }
    }
    hidden.value = planId || "";
  }

  /**
   * Mark the card whose radio is checked and enable only its terms, so a term
   * of an unselected card is neither posted nor reached with Tab.
   */
  function syncCards() {
    box.querySelectorAll("[data-subscrpt-card]").forEach(function (card) {
      var radio = card.querySelector('input[name="subscrpt_plan_group"]');
      var selected = !!(radio && radio.checked);
      card.classList.toggle("is-selected", selected);
      card.querySelectorAll("input[data-subscrpt-term]").forEach(function (term) {
        term.disabled = !selected;
      });
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
    }
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
   */
  function swap(html, context) {
    if (html === box.innerHTML && (context || null) === box.getAttribute("data-subscrpt-context")) {
      return;
    }
    box.dispatchEvent(new CustomEvent("subscrpt_cards_before_swap", { bubbles: true }));
    box.innerHTML = html;
    if (context) {
      box.setAttribute("data-subscrpt-context", context);
    } else {
      box.removeAttribute("data-subscrpt-context");
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
    swap(rendered.innerHTML, rendered.getAttribute("data-subscrpt-context") || "");
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
