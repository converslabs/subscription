/**
 * Cancellation Flow - keep the Reasons tab's list in step with the editor.
 *
 * The list is rendered from the saved option, so on its own it only caught up
 * on Save Changes: a reason added in "Manage reasons" disappeared from the page
 * when the modal closed, which reads as "it was not added". It now mirrors the
 * editor's `wpsubs:change` event (fired after every add, remove and reorder),
 * and a note beside Save Changes says the change is not saved yet.
 */
(function () {
  "use strict";

  var OPTION = "subscrpt_cancellation_reasons";

  var preview = document.querySelector("[data-subscrpt-reason-preview]");
  if (!preview) {
    return;
  }

  var empty = document.querySelector("[data-subscrpt-reason-empty]");
  var unsaved = document.querySelector("[data-subscrpt-unsaved]");
  var auto = preview.querySelector("[data-subscrpt-reason-auto]");
  var field = document.querySelector('input[name="' + OPTION + '"]');

  /**
   * A comparable fingerprint of a reason list: keys and labels, in order.
   *
   * @param {Array<{key?: string, label?: string}>} items Reason items.
   * @return {string} Fingerprint.
   */
  function signature(items) {
    return items
      .map(function (item) {
        // Tab and newline cannot occur in a saved label (sanitize_text_field).
        return String(item.key || "") + "\t" + String(item.label || "").trim();
      })
      .join("\n");
  }

  // What is saved, read before the editor starts. The editor also fires
  // `wpsubs:change` once while initialising, so "changed" has to mean
  // "differs from this", not "an event arrived" - and undoing an edit hides
  // the note again.
  var saved = "";
  try {
    saved = signature(JSON.parse((field && field.value) || "[]"));
  } catch (err) {
    saved = "";
  }

  /**
   * Whether an item is a typed "Other".
   *
   * The survey adds its own "Other" and the server drops a typed one on save,
   * so the list leaves it out too rather than show a row that will not be kept.
   *
   * @param {{key?: string, label?: string}} item Editor item.
   * @return {boolean} True for an "Other" entry.
   */
  function isOther(item) {
    var key = String(item.key || "").toLowerCase();
    var label = String(item.label || "")
      .trim()
      .toLowerCase();
    return key === "other" || label === "other";
  }

  /**
   * Re-render the list from the editor's items, keeping the automatic "Other" last.
   *
   * @param {Array<{key?: string, label?: string}>} items Editor items, in order.
   * @return {void}
   */
  function render(items) {
    var rows = items.filter(function (item) {
      return item && String(item.label || "").trim() !== "" && !isOther(item);
    });

    Array.prototype.slice.call(preview.querySelectorAll("li:not([data-subscrpt-reason-auto])")).forEach(function (li) {
      li.remove();
    });

    rows.forEach(function (item) {
      var li = document.createElement("li");
      li.textContent = String(item.label).trim();
      preview.insertBefore(li, auto);
    });

    preview.hidden = rows.length === 0;
    if (empty) {
      empty.hidden = rows.length > 0;
    }
    if (unsaved) {
      unsaved.hidden = signature(items) === saved;
    }
  }

  document.addEventListener("wpsubs:change", function (e) {
    var field = e.target && e.target.querySelector ? e.target.querySelector('input[name="' + OPTION + '"]') : null;
    if (!field) {
      return;
    }
    render((e.detail && e.detail.items) || []);
  });
})();
