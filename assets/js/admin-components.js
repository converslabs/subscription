/**
 * WPSubscription admin UI components.
 *
 * WPSubsAdvSelect — styled dropdown replacing native <select>.
 *
 * Usage:
 *   PHP: wpsubs_render_adv_select( $args )  — renders the HTML
 *   JS:  WPSubsAdvSelect.init()             — auto-inits all .wpsubs-adv-select elements
 *
 * Events fired on the root element (bubbles):
 *   wpsubs:select  — { value, label }  when user picks an item
 */
(function () {
  "use strict";

  var instances = [];

  /**
   * @param {HTMLElement} el  Root .wpsubs-adv-select element.
   */
  function WPSubsAdvSelect(el) {
    this.el = el;
    this.trigger = el.querySelector(".wpsubs-adv-select__trigger");
    this.label = el.querySelector(".wpsubs-adv-select__label");
    this.menu = el.querySelector(".wpsubs-adv-select__menu");
    this.input = el.querySelector('input[type="hidden"]');
    this._bind();
    instances.push(this);
  }

  WPSubsAdvSelect.prototype.open = function () {
    closeAll(this);
    this.el.classList.add("wpsubs-adv-select--open");
    if (this.trigger) this.trigger.setAttribute("aria-expanded", "true");
  };

  WPSubsAdvSelect.prototype.close = function () {
    this.el.classList.remove("wpsubs-adv-select--open");
    if (this.trigger) this.trigger.setAttribute("aria-expanded", "false");
  };

  WPSubsAdvSelect.prototype.isOpen = function () {
    return this.el.classList.contains("wpsubs-adv-select--open");
  };

  /**
   * Programmatically select a value.
   *
   * @param {string} value
   * @param {string} label  Display text shown in trigger. Defaults to value.
   */
  WPSubsAdvSelect.prototype.select = function (value, label) {
    if (this.input) this.input.value = value;
    if (this.label) this.label.textContent = label || value;
    this.el.dispatchEvent(
      new CustomEvent("wpsubs:select", {
        bubbles: true,
        detail: { value: value, label: label || value },
      }),
    );
  };

  /** Reset trigger label back to placeholder. */
  WPSubsAdvSelect.prototype.reset = function () {
    var placeholder = this.el.dataset.placeholder || "";
    if (this.input) this.input.value = this.el.dataset.defaultValue || "";
    if (this.label && placeholder) this.label.textContent = placeholder;
  };

  WPSubsAdvSelect.prototype._bind = function () {
    var self = this;

    if (self.trigger) {
      self.trigger.addEventListener("click", function (e) {
        e.stopPropagation();
        self.isOpen() ? self.close() : self.open();
      });
    }

    if (self.menu) {
      self.menu.addEventListener("click", function (e) {
        var item = e.target.closest(".wpsubs-adv-select__item");
        if (!item || item.hasAttribute("data-disabled")) return;

        var value = item.dataset.value !== undefined ? item.dataset.value : "";
        var labelEl = item.querySelector(".wpsubs-adv-select__item-label");
        var label = labelEl ? labelEl.textContent.trim() : item.textContent.trim();
        var confirmMsg = item.dataset.confirm || "";

        if (confirmMsg && !window.confirm(confirmMsg)) return;

        self.close();
        self.select(value, label);
      });
    }
  };

  function closeAll(except) {
    instances.forEach(function (inst) {
      if (inst !== except) inst.close();
    });
  }

  /**
   * Initialise all un-initialised .wpsubs-adv-select elements under root.
   *
   * @param {Document|HTMLElement} [root]
   */
  function init(root) {
    (root || document).querySelectorAll(".wpsubs-adv-select:not([data-adv-init])").forEach(function (el) {
      el.setAttribute("data-adv-init", "1");
      new WPSubsAdvSelect(el);
    });
  }

  // Global: outside click and Escape close all
  document.addEventListener("click", function () {
    closeAll();
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeAll();
  });

  // Auto-init on DOM ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      init();
    });
  } else {
    init();
  }

  // Public API
  window.WPSubsAdvSelect = { init: init };
})();

/**
 * WPSubsTagSelect — pill/tag input with inline filter and filterable dropdown.
 *
 * Usage:
 *   PHP: wpsubs_render_tag_select( $args )  — renders the HTML
 *   JS:  WPSubsTagSelect.init()             — auto-inits all .wpsubs-tag-select elements
 *
 * Events fired on the root element (bubbles):
 *   wpsubs:select  — { value, label, selected }  when a pill is added or removed
 */
(function () {
  "use strict";

  var instances = [];

  /**
   * @param {HTMLElement} el  Root .wpsubs-tag-select element.
   */
  function WPSubsTagSelect(el) {
    this.el = el;
    this.multiple = !!el.dataset.multiple;
    this.fieldName = el.dataset.name || "";
    this.field = el.querySelector(".wpsubs-tag-select__field");
    this.input = el.querySelector(".wpsubs-tag-select__input");
    this.dropdown = el.querySelector(".wpsubs-tag-select__dropdown");
    this.list = el.querySelector(".wpsubs-tag-select__list");
    this.emptyEl = el.querySelector(".wpsubs-tag-select__empty");
    this._bind();
    instances.push(this);
  }

  WPSubsTagSelect.prototype.open = function () {
    closeAll(this);
    this.el.classList.add("wpsubs-tag-select--open");
    this._filterItems("");
    if (this.input) this.input.focus();
  };

  WPSubsTagSelect.prototype.close = function () {
    this.el.classList.remove("wpsubs-tag-select--open");
    if (this.input) {
      this.input.value = "";
      this._filterItems("");
    }
  };

  WPSubsTagSelect.prototype.isOpen = function () {
    return this.el.classList.contains("wpsubs-tag-select--open");
  };

  /** Show/hide dropdown items based on query, always hiding selected ones. */
  WPSubsTagSelect.prototype._filterItems = function (query) {
    var q = query.trim().toLowerCase();
    var items = this.list ? this.list.querySelectorAll(".wpsubs-tag-select__item") : [];
    var visible = 0;
    items.forEach(function (item) {
      if (item.hasAttribute("data-selected")) {
        item.style.display = "none";
        return;
      }
      var text = item.textContent.toLowerCase();
      var match = !q || text.indexOf(q) !== -1;
      item.style.display = match ? "" : "none";
      if (match) visible++;
    });
    if (this.emptyEl) {
      this.emptyEl.style.display = visible === 0 ? "" : "none";
    }
  };

  /** Add a pill for the given dropdown item. */
  WPSubsTagSelect.prototype._addPill = function (item) {
    var value = item.dataset.value !== undefined ? item.dataset.value : "";
    var label = item.textContent.trim();

    // For single-select, remove the existing pill first.
    if (!this.multiple) {
      var existing = this.el.querySelectorAll(".wpsubs-tag-select__pill");
      var self = this;
      existing.forEach(function (p) {
        self._removePillEl(p, false);
      });
    }

    // Build the pill.
    var pill = document.createElement("span");
    pill.className = "wpsubs-tag-select__pill";
    pill.dataset.value = value;

    var pillLabel = document.createElement("span");
    pillLabel.className = "wpsubs-tag-select__pill-label";
    pillLabel.textContent = label;

    var removeBtn = document.createElement("button");
    removeBtn.type = "button";
    removeBtn.className = "wpsubs-tag-select__pill-remove";
    removeBtn.setAttribute("aria-label", "Remove " + label);
    removeBtn.innerHTML = "&#x2715;"; // ×

    pill.appendChild(pillLabel);
    pill.appendChild(removeBtn);

    // Insert the pill before the text input.
    if (this.input) {
      this.field.insertBefore(pill, this.input);
    } else {
      this.field.appendChild(pill);
    }

    // Mark dropdown item as selected so it stays hidden.
    item.setAttribute("data-selected", "");
    item.style.display = "none";

    this._syncHiddenInputs();
    this._updateInputPlaceholder();

    if (this.input) this.input.value = "";
    this._filterItems("");

    this.el.dispatchEvent(
      new CustomEvent("wpsubs:select", {
        bubbles: true,
        detail: { value: value, label: label, selected: true },
      }),
    );
  };

  /** Remove a pill element. Pass sync=true to update hidden inputs (default). */
  WPSubsTagSelect.prototype._removePillEl = function (pill, sync) {
    var value = pill.dataset.value !== undefined ? pill.dataset.value : "";
    var label = pill.querySelector(".wpsubs-tag-select__pill-label");
    var labelText = label ? label.textContent.trim() : value;

    pill.parentNode.removeChild(pill);

    // Un-mark the corresponding dropdown item.
    var item = this.list
      ? this.list.querySelector('.wpsubs-tag-select__item[data-value="' + CSS.escape(value) + '"]')
      : null;
    if (item) {
      item.removeAttribute("data-selected");
    }

    if (sync !== false) {
      this._syncHiddenInputs();
      this._updateInputPlaceholder();
      this._filterItems(this.input ? this.input.value : "");

      this.el.dispatchEvent(
        new CustomEvent("wpsubs:select", {
          bubbles: true,
          detail: { value: value, label: labelText, selected: false },
        }),
      );
    }
  };

  /** Rebuild hidden inputs to match current pills. */
  WPSubsTagSelect.prototype._syncHiddenInputs = function () {
    var existing = this.el.querySelectorAll("input[data-ts-val]");
    var fieldName = existing.length > 0 ? existing[0].name : this.fieldName + (this.multiple ? "[]" : "");

    existing.forEach(function (inp) {
      inp.parentNode.removeChild(inp);
    });

    var pills = this.el.querySelectorAll(".wpsubs-tag-select__pill");
    var self = this;

    if (this.multiple) {
      if (pills.length === 0) {
        // Empty sentinel so the form field is always present on submit.
        var sentinel = document.createElement("input");
        sentinel.type = "hidden";
        sentinel.name = fieldName;
        sentinel.value = "";
        sentinel.setAttribute("data-ts-val", "");
        self.el.appendChild(sentinel);
      } else {
        pills.forEach(function (pill) {
          var inp = document.createElement("input");
          inp.type = "hidden";
          inp.name = fieldName;
          inp.value = pill.dataset.value !== undefined ? pill.dataset.value : "";
          inp.setAttribute("data-ts-val", "");
          self.el.appendChild(inp);
        });
      }
    } else {
      var inp = document.createElement("input");
      inp.type = "hidden";
      inp.name = fieldName;
      inp.value = pills.length > 0 && pills[0].dataset.value !== undefined ? pills[0].dataset.value : "";
      inp.setAttribute("data-ts-val", "");
      self.el.appendChild(inp);
    }
  };

  /** Show placeholder only when there are no pills. */
  WPSubsTagSelect.prototype._updateInputPlaceholder = function () {
    if (!this.input) return;
    var pills = this.el.querySelectorAll(".wpsubs-tag-select__pill");
    this.input.placeholder = pills.length === 0 ? this.el.dataset.placeholder || "" : "";
  };

  WPSubsTagSelect.prototype._bind = function () {
    var self = this;

    if (self.field) {
      self.field.addEventListener("click", function (e) {
        e.stopPropagation();
        var removeBtn = e.target.closest(".wpsubs-tag-select__pill-remove");
        if (removeBtn) {
          var pill = removeBtn.closest(".wpsubs-tag-select__pill");
          if (pill) self._removePillEl(pill);
          return;
        }
        self.open();
      });
    }

    if (self.input) {
      self.input.addEventListener("input", function () {
        if (!self.isOpen()) self.open();
        self._filterItems(self.input.value);
      });
    }

    if (self.dropdown) {
      self.dropdown.addEventListener("click", function (e) {
        e.stopPropagation();
        var item = e.target.closest(".wpsubs-tag-select__item");
        if (!item || item.hasAttribute("data-disabled")) return;
        self._addPill(item);
        if (!self.multiple) self.close();
      });
    }
  };

  function closeAll(except) {
    instances.forEach(function (inst) {
      if (inst !== except) inst.close();
    });
  }

  /**
   * Initialise all un-initialised .wpsubs-tag-select elements under root.
   *
   * @param {Document|HTMLElement} [root]
   */
  function init(root) {
    (root || document).querySelectorAll(".wpsubs-tag-select:not([data-ts-init])").forEach(function (el) {
      el.setAttribute("data-ts-init", "1");
      new WPSubsTagSelect(el);
    });
  }

  document.addEventListener("click", function () {
    closeAll();
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeAll();
  });

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      init();
    });
  } else {
    init();
  }

  // Public API
  window.WPSubsTagSelect = { init: init };
})();

/**
 * WPSubsEditList — editable ordered list of text items.
 *
 * A reusable admin component: renders items in a reorderable list with per-row
 * remove/move controls, plus an inline input + add button to append new items.
 * The ordered list is serialized as JSON ([{ key, label }]) into a hidden input
 * so it submits with the surrounding form. `key` is a slug derived from the label.
 *
 * Usage:
 *   PHP: SettingsHelper::render_editlist() / any markup with the classes below
 *   JS:  WPSubsEditList.init()  — auto-inits all .wpsubs-editlist elements
 *
 * Expected structure inside .wpsubs-editlist:
 *   input[type=hidden]                      — JSON store
 *   .wpsubs-editlist__items > .wpsubs-editlist__item[data-key]
 *       .wpsubs-editlist__label
 *       [data-editlist-up] [data-editlist-down] [data-editlist-remove]
 *   .wpsubs-editlist__empty                 — shown only when empty
 *   .wpsubs-editlist__input                 — inline text input
 *   [data-editlist-add]                     — add/confirm button
 *
 * Events fired on the root element (bubbles):
 *   wpsubs:change — { items }  after any add/remove/reorder
 */
(function () {
  "use strict";

  /**
   * Derive a slug key from a label.
   *
   * @param {string} label
   * @return {string}
   */
  function slugify(label) {
    return String(label)
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "_")
      .replace(/^_+|_+$/g, "");
  }

  var SVG_UP =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"/></svg>';
  var SVG_DOWN =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>';
  var SVG_TRASH =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';

  /**
   * @param {HTMLElement} el  Root .wpsubs-editlist element.
   */
  function WPSubsEditList(el) {
    this.el = el;
    this.hidden = el.querySelector('input[type="hidden"]');
    this.list = el.querySelector(".wpsubs-editlist__items");
    this.emptyEl = el.querySelector(".wpsubs-editlist__empty");
    this.input = el.querySelector(".wpsubs-editlist__input");
    this._bind();
    this._serialize();
  }

  WPSubsEditList.prototype._rows = function () {
    return this.list ? this.list.querySelectorAll(".wpsubs-editlist__item") : [];
  };

  /** Serialize the current rows into the hidden input as JSON. */
  WPSubsEditList.prototype._serialize = function () {
    var items = [];
    this._rows().forEach(function (row) {
      var labelEl = row.querySelector(".wpsubs-editlist__label");
      items.push({
        key: row.getAttribute("data-key") || "",
        label: labelEl ? labelEl.textContent : "",
      });
    });
    if (this.hidden) this.hidden.value = JSON.stringify(items);
    if (this.emptyEl) this.emptyEl.hidden = items.length > 0;
    this.el.dispatchEvent(new CustomEvent("wpsubs:change", { bubbles: true, detail: { items: items } }));
  };

  /**
   * Append a new item row.
   *
   * @param {string} label
   */
  WPSubsEditList.prototype._addItem = function (label) {
    var text = String(label).trim();
    if (!text || !this.list) return;

    var row = document.createElement("li");
    row.className = "wpsubs-editlist__item";
    row.setAttribute("data-key", slugify(text));

    var handle = document.createElement("span");
    handle.className = "wpsubs-editlist__handle";
    handle.setAttribute("aria-hidden", "true");
    handle.innerHTML = "&#8942;&#8942;";

    var lab = document.createElement("span");
    lab.className = "wpsubs-editlist__label";
    lab.textContent = text;

    var actions = document.createElement("span");
    actions.className = "wpsubs-editlist__actions";
    actions.innerHTML =
      '<button type="button" class="wpsubs-editlist__btn" data-editlist-up>' +
      SVG_UP +
      "</button>" +
      '<button type="button" class="wpsubs-editlist__btn" data-editlist-down>' +
      SVG_DOWN +
      "</button>" +
      '<button type="button" class="wpsubs-editlist__btn wpsubs-editlist__btn--danger" data-editlist-remove>' +
      SVG_TRASH +
      "</button>";

    row.appendChild(handle);
    row.appendChild(lab);
    row.appendChild(actions);
    this.list.appendChild(row);
    this._serialize();
  };

  /** Add the item currently typed in the inline input, then reset it. */
  WPSubsEditList.prototype._commitInput = function () {
    if (!this.input) return;
    var val = this.input.value.trim();
    if (!val) return;
    this._addItem(val);
    this.input.value = "";
    this.input.focus();
  };

  WPSubsEditList.prototype._bind = function () {
    var self = this;

    this.el.addEventListener("click", function (e) {
      if (e.target.closest("[data-editlist-add]")) {
        self._commitInput();
        return;
      }
      var row = e.target.closest(".wpsubs-editlist__item");
      if (!row) return;

      if (e.target.closest("[data-editlist-remove]")) {
        row.parentNode.removeChild(row);
        self._serialize();
      } else if (e.target.closest("[data-editlist-up]")) {
        if (row.previousElementSibling) {
          row.parentNode.insertBefore(row, row.previousElementSibling);
          self._serialize();
        }
      } else if (e.target.closest("[data-editlist-down]")) {
        if (row.nextElementSibling) {
          row.parentNode.insertBefore(row.nextElementSibling, row);
          self._serialize();
        }
      }
    });

    if (this.input) {
      this.input.addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
          e.preventDefault();
          self._commitInput();
        }
      });
    }

    // Drag to reorder — only starts from the handle.
    this._dragRow = null;

    this.el.addEventListener("mousedown", function (e) {
      var handle = e.target.closest(".wpsubs-editlist__handle");
      if (!handle) return;
      var row = handle.closest(".wpsubs-editlist__item");
      if (row) row.setAttribute("draggable", "true");
    });

    this.el.addEventListener("dragstart", function (e) {
      var row = e.target.closest(".wpsubs-editlist__item");
      if (!row || row.getAttribute("draggable") !== "true") return;
      self._dragRow = row;
      row.classList.add("wpsubs-editlist__item--dragging");
      if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = "move";
        try {
          e.dataTransfer.setData("text/plain", "");
        } catch (err) {
          /* IE guard */
        }
      }
    });

    this.el.addEventListener("dragover", function (e) {
      if (!self._dragRow || !self.list) return;
      e.preventDefault();
      var over = e.target.closest(".wpsubs-editlist__item");
      if (!over || over === self._dragRow) return;
      var rect = over.getBoundingClientRect();
      var after = (e.clientY - rect.top) / rect.height > 0.5;
      self.list.insertBefore(self._dragRow, after ? over.nextSibling : over);
    });

    this.el.addEventListener("drop", function (e) {
      if (self._dragRow) e.preventDefault();
    });

    this.el.addEventListener("dragend", function () {
      if (!self._dragRow) return;
      self._dragRow.classList.remove("wpsubs-editlist__item--dragging");
      self._dragRow.removeAttribute("draggable");
      self._dragRow = null;
      self._serialize();
    });
  };

  /**
   * Initialise all un-initialised .wpsubs-editlist elements under root.
   *
   * @param {Document|HTMLElement} [root]
   */
  function init(root) {
    (root || document).querySelectorAll(".wpsubs-editlist:not([data-editlist-init])").forEach(function (el) {
      el.setAttribute("data-editlist-init", "1");
      new WPSubsEditList(el);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      init();
    });
  } else {
    init();
  }

  // Public API
  window.WPSubsEditList = { init: init };
})();
