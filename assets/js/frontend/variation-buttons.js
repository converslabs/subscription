/**
 * Variations as buttons (free).
 *
 * Turns the server-rendered `[data-subscrpt-variation-buttons]` groups into a
 * radio-like control over WooCommerce's own attribute select. The select is
 * kept and only visually hidden: a button sets its value and fires `change`, so
 * WooCommerce's variation script, stock messages, images and price all run as
 * usual, and the plan cards swap on `found_variation`.
 *
 * A select that is already hidden on load belongs to a theme or plugin that
 * replaced it; the group is removed and nothing else changes.
 *
 * Keys: Left/Up and Right/Down move through the buttons and choose, Home and
 * End jump to the ends. A disabled button can be focused, so its reason is read
 * out, but not chosen.
 */
(function ($) {
  "use strict";

  /**
   * Whether the select is hidden by something other than this script.
   *
   * @param {HTMLSelectElement} select The attribute select.
   * @return {boolean}
   */
  function isHiddenByTheme(select) {
    var style = window.getComputedStyle(select);
    return style.display === "none" || style.visibility === "hidden" || select.getClientRects().length === 0;
  }

  /**
   * Wire one button group to its select.
   *
   * @param {HTMLElement} group The `[data-subscrpt-variation-buttons]` element.
   */
  function init(group) {
    var select = document.getElementById(group.getAttribute("data-select"));
    if (!select || isHiddenByTheme(select)) {
      group.remove();
      return;
    }

    var buttons = Array.prototype.slice.call(group.querySelectorAll("[data-value]"));
    // A reason the server gave stays; one this script adds follows the other choices.
    buttons.forEach(function (button) {
      if (button.getAttribute("aria-disabled") === "true") {
        button.setAttribute("data-server-disabled", "1");
      }
    });
    var combinationLabel = group.getAttribute("data-unavailable-label") || "";

    select.classList.add("subscrpt-varbtns__select");
    select.tabIndex = -1;
    select.setAttribute("aria-hidden", "true");
    group.hidden = false;

    // The attribute's row label names the group for assistive technology.
    var rowLabel = document.querySelector('label[for="' + select.id + '"]');
    if (rowLabel) {
      if (!rowLabel.id) {
        rowLabel.id = select.id + "-label";
      }
      group.setAttribute("aria-labelledby", rowLabel.id);
      group.removeAttribute("aria-label");
    }

    /**
     * Whether a button cannot be chosen, for the server's reason or because
     * WooCommerce has dropped its option given the other attributes chosen.
     *
     * @param {HTMLElement} button A button of the group.
     * @return {boolean}
     */
    function isDisabled(button) {
      return button.getAttribute("aria-disabled") === "true";
    }

    /**
     * Mirror the select onto the buttons: which is checked, which can be
     * chosen, and which one Tab reaches.
     */
    function sync() {
      var offered = {};
      Array.prototype.forEach.call(select.options, function (option) {
        offered[option.value] = true;
      });

      var current = select.value;
      var stop = null;

      buttons.forEach(function (button) {
        var value = button.getAttribute("data-value");

        var server = button.hasAttribute("data-server-disabled");

        var missing = !offered[value];
        if (!server) {
          if (missing) {
            button.setAttribute("aria-disabled", "true");
            button.setAttribute("title", combinationLabel);
          } else {
            button.removeAttribute("aria-disabled");
            button.removeAttribute("title");
          }
          var reason = button.querySelector(".subscrpt-varbtn__reason");
          if (missing && !reason) {
            reason = document.createElement("span");
            reason.className = "subscrpt-varbtn__reason";
            reason.setAttribute("data-added", "1");
            reason.textContent = combinationLabel;
            button.appendChild(reason);
          } else if (!missing && reason && reason.hasAttribute("data-added")) {
            reason.remove();
          }
        }

        var checked = value === current && !isDisabled(button);
        button.setAttribute("aria-checked", checked ? "true" : "false");
        button.classList.toggle("is-selected", checked);
        button.tabIndex = -1;
        if (!stop && (checked || (!current && !isDisabled(button)))) {
          stop = button;
        }
      });

      if (stop) {
        stop.tabIndex = 0;
      } else if (buttons.length) {
        buttons[0].tabIndex = 0;
      }
    }

    /**
     * Choose a button: set the select and let WooCommerce react.
     *
     * @param {HTMLElement} button An enabled button.
     */
    function choose(button) {
      if (isDisabled(button)) {
        return;
      }
      select.value = button.getAttribute("data-value");
      select.dispatchEvent(new Event("change", { bubbles: true }));
      sync();
    }

    group.addEventListener("click", function (e) {
      var button = e.target.closest("[data-value]");
      if (button && group.contains(button)) {
        choose(button);
      }
    });

    group.addEventListener("keydown", function (e) {
      var button = e.target.closest("[data-value]");
      if (!button) {
        return;
      }
      var index = buttons.indexOf(button);
      var target = null;

      if (e.key === "ArrowRight" || e.key === "ArrowDown") {
        target = buttons[(index + 1) % buttons.length];
      } else if (e.key === "ArrowLeft" || e.key === "ArrowUp") {
        target = buttons[(index - 1 + buttons.length) % buttons.length];
      } else if (e.key === "Home") {
        target = buttons[0];
      } else if (e.key === "End") {
        target = buttons[buttons.length - 1];
      } else if (e.key === " " || e.key === "Enter") {
        // A real button would click on its own; stopping the page scroll keeps Space from jumping.
        e.preventDefault();
        choose(button);
        return;
      } else {
        return;
      }

      e.preventDefault();
      buttons.forEach(function (b) {
        b.tabIndex = -1;
      });
      target.tabIndex = 0;
      target.focus();
      choose(target);
    });

    // WooCommerce moves the select by script too: Clear, a default, other attributes.
    select.addEventListener("change", sync);
    var $form = $(select).closest("form.variations_form");
    $form.on(
      "change reset_data found_variation hide_variation woocommerce_update_variation_values woocommerce_variation_has_changed",
      function () {
        // WooCommerce rewrites the options after its own handlers; look once they are done.
        window.setTimeout(sync, 0);
      },
    );

    sync();
  }

  /**
   * Wire every group on the page.
   */
  function boot() {
    document.querySelectorAll("[data-subscrpt-variation-buttons]").forEach(init);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})(window.jQuery);
