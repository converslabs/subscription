/**
 * Settings > Product page: the live preview.
 *
 * The server renders every layout in both interval styles; this shows the pair
 * the settings name and writes the colour settings onto the buyboxes inside as
 * CSS custom properties, so a change shows before it is saved. It also gives
 * each colour field a native colour picker beside its text box.
 *
 * Nothing here posts anything: the preview is inert and its controls have no
 * names.
 */
(function () {
  "use strict";

  var preview = document.querySelector("[data-subscrpt-preview]");
  if (!preview) {
    return;
  }

  var HEX = /^#([0-9a-f]{3}){1,2}$/i;
  var layoutInput = document.querySelector('input[name="subscrpt_plan_selector_layout"]');
  var intervalsInput = document.querySelector('input[name="subscrpt_plan_intervals_display"]');
  var styleInputs = Array.prototype.slice.call(document.querySelectorAll("[data-subscrpt-style-var]"));

  /**
   * Show the block for the chosen layout and interval style, hide the rest.
   *
   * @return {void}
   */
  function showChosen() {
    var layout = layoutInput ? layoutInput.value : "stacked";
    var intervals = intervalsInput ? intervalsInput.value : "chips";

    Array.prototype.forEach.call(preview.querySelectorAll("[data-subscrpt-preview-layout]"), function (block) {
      block.hidden = !(
        block.dataset.subscrptPreviewLayout === layout && block.dataset.subscrptPreviewIntervals === intervals
      );
    });
  }

  /**
   * Write every style field onto the buyboxes: a valid value sets its custom
   * property, an empty or invalid one removes it, leaving the stylesheet's default.
   *
   * @return {void}
   */
  function applyStyles() {
    var boxes = preview.querySelectorAll(".subscrpt-buybox");

    styleInputs.forEach(function (input) {
      var property = input.dataset.subscrptStyleVar;
      var unit = input.dataset.subscrptStyleUnit || "";
      var value = input.value.trim();
      var valid = unit ? /^\d+$/.test(value) : HEX.test(value);

      Array.prototype.forEach.call(boxes, function (box) {
        if (valid) {
          box.style.setProperty(property, value + unit);
        } else {
          box.style.removeProperty(property);
        }
      });
    });
  }

  /**
   * Give a colour field a picker, kept in step with its text box.
   *
   * @param {HTMLInputElement} input The text box.
   * @return {void}
   */
  function addPicker(input) {
    if (input.dataset.subscrptStyleUnit) {
      return;
    }

    var picker = document.createElement("input");
    picker.type = "color";
    picker.className = "subscrpt-color-picker";
    picker.setAttribute(
      "aria-label",
      input.closest(".wpsubs-settings-field").querySelector(".wpsubs-settings-field__label").textContent.trim(),
    );
    picker.value = HEX.test(input.value) ? long(input.value) : long(input.placeholder);

    picker.addEventListener("input", function () {
      input.value = picker.value;
      applyStyles();
    });
    input.addEventListener("input", function () {
      if (HEX.test(input.value)) {
        picker.value = long(input.value);
      }
    });

    input.parentNode.insertBefore(picker, input);
  }

  /**
   * A three-digit hex colour as six digits, which is all a colour input takes.
   *
   * @param {string} hex A valid hex colour.
   * @return {string}
   */
  function long(hex) {
    return hex.length === 4 ? "#" + hex[1] + hex[1] + hex[2] + hex[2] + hex[3] + hex[3] : hex;
  }

  styleInputs.forEach(function (input) {
    addPicker(input);
    input.addEventListener("input", applyStyles);
  });

  // The adv-select component announces a choice; the hidden input holds the value.
  document.addEventListener("wpsubs:select", showChosen);

  showChosen();
  applyStyles();
})();
