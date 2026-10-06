/**
 * Storefront plan selector (free).
 *
 * Pick a plan group (radio card), then a term (buttons). The chosen plan-term id
 * is written to a hidden field that posts with add-to-cart. Simple products are
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
      var activeBtn = card.querySelector("[data-subscrpt-term-btn].is-active");
      if (activeBtn) {
        planId = activeBtn.getAttribute("data-term-id");
      } else if (card.hasAttribute("data-subscrpt-single-term")) {
        planId = card.getAttribute("data-subscrpt-single-term");
      }
    }
    hidden.value = planId || "";
  }

  // Selecting a card (radio) marks it and syncs the posted plan id.
  box.addEventListener("change", function (e) {
    if (e.target.name !== "subscrpt_plan_group") {
      return;
    }
    box.querySelectorAll("[data-subscrpt-card]").forEach(function (card) {
      card.classList.remove("is-selected");
    });
    var card = e.target.closest("[data-subscrpt-card]");
    if (card) {
      card.classList.add("is-selected");
    }
    syncPlanId();
  });

  // Choosing a term button updates that card's note and selects the card.
  box.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-term-btn]");
    if (!btn) {
      return;
    }
    e.preventDefault();
    var wrap = btn.closest("[data-subscrpt-card]");
    if (!wrap) {
      return;
    }

    wrap.querySelectorAll("[data-subscrpt-term-btn]").forEach(function (b) {
      b.classList.remove("is-active");
    });
    btn.classList.add("is-active");

    var noteEl = wrap.querySelector("[data-subscrpt-note]");
    if (noteEl) {
      // Note is server-built HTML (may include a struck-through regular price).
      noteEl.innerHTML = btn.getAttribute("data-note") || "";
    }

    // The badge reports what *this* term saves, so it moves with the selection.
    // Terms discount by different amounts; one figure for the whole card was
    // only ever right for one of them. Hidden outright when the chosen term is
    // not discounted, so no empty pill is left behind.
    var badgeEl = wrap.querySelector("[data-subscrpt-badge]");
    if (badgeEl) {
      var badge = btn.getAttribute("data-badge") || "";
      badgeEl.textContent = badge;
      badgeEl.hidden = badge === "";
    }

    var radio = wrap.querySelector('input[name="subscrpt_plan_group"]');
    if (radio && !radio.checked) {
      radio.checked = true;
      radio.dispatchEvent(new Event("change", { bubbles: true }));
    }
    syncPlanId();
  });

  // Initialise the hidden plan id from the pre-selected (first) card.
  syncPlanId();

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
    syncPlanId();
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
