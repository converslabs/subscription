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
    // Optional live count badge (e.g. when the list lives inside a modal trigger).
    this.countEl = el.querySelector(".wpsubs-editlist__count");
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
    if (this.countEl) this.countEl.textContent = String(items.length);
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

/**
 * WPSubsModal — reusable centered dialog over a dimmed backdrop.
 *
 * Behaviour only; the visual comes from the .wpsubs-modal CSS. A modal is a
 * `.wpsubs-modal[hidden]` element with an id. Any control opens it via
 * `data-wpsubs-modal-open="<modal-id>"`; any control inside closes it via
 * `data-wpsubs-modal-close` (the backdrop and the header/footer buttons use it).
 * Escape also closes. No per-page wiring needed.
 *
 * A modal with `data-wpsubs-modal-autoopen` opens automatically on load (e.g. the
 * Pro-upgrade preview modals). Opening locks body scroll; it's restored when the
 * last open modal closes.
 *
 * Usage:
 *   PHP: wpsubs_render_modal( $args )  — renders the markup
 *   JS:  auto-inits; WPSubsModal.open(id) / .close(id) available programmatically
 *
 * Events fired on the modal element (bubbles):
 *   wpsubs:modal:open / wpsubs:modal:close
 */
(function () {
  "use strict";

  /**
   * @param {string|HTMLElement} target Modal id or element.
   * @return {HTMLElement|null}
   */
  function resolve(target) {
    if (target instanceof HTMLElement) return target;
    return document.getElementById(String(target));
  }

  function open(target) {
    var modal = resolve(target);
    if (!modal) return;
    modal.hidden = false;
    document.body.style.overflow = "hidden";
    modal.dispatchEvent(new CustomEvent("wpsubs:modal:open", { bubbles: true }));
    var focusable = modal.querySelector("input, textarea, select, button:not([data-wpsubs-modal-close])");
    if (focusable) focusable.focus();
  }

  function close(target) {
    var modal = resolve(target);
    if (!modal) return;
    modal.hidden = true;
    // Restore body scroll only when no modal remains open.
    if (!document.querySelector(".wpsubs-modal:not([hidden])")) {
      document.body.style.overflow = "";
    }
    modal.dispatchEvent(new CustomEvent("wpsubs:modal:close", { bubbles: true }));
  }

  function closeAll() {
    document.querySelectorAll(".wpsubs-modal:not([hidden])").forEach(function (m) {
      close(m);
    });
  }

  // Delegated open/close — works for markup added after load too.
  document.addEventListener("click", function (e) {
    var opener = e.target.closest("[data-wpsubs-modal-open]");
    if (opener) {
      e.preventDefault();
      open(opener.getAttribute("data-wpsubs-modal-open"));
      return;
    }
    var closer = e.target.closest("[data-wpsubs-modal-close]");
    if (closer) {
      e.preventDefault();
      var modal = closer.closest(".wpsubs-modal");
      if (modal) close(modal);
    }
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeAll();
  });

  /**
   * Open any modal flagged to auto-open on load.
   *
   * @param {Document|HTMLElement} [root]
   */
  function init(root) {
    (root || document).querySelectorAll(".wpsubs-modal[data-wpsubs-modal-autoopen]").forEach(function (modal) {
      open(modal);
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
  window.WPSubsModal = { open: open, close: close, init: init };
})();

/**
 * WPSubsPager — paginator footer (prev / next / numbers / ellipsis) that
 * controls a slice of rows inside a card. Single source of truth so the
 * server-rendered markup from `wpsubs_render_pager()` and the client
 * re-renderings stay byte-identical.
 *
 * Usage:
 *   PHP:  wpsubs_render_pager( $args )  // emits a .wpsubs-pager[data-wpsubs-pager]
 *   JS:   WPSubsPager.init()            // auto-inits those elements
 *
 * Markup contract (data-* on .wpsubs-pager):
 *   data-current       — current page (1-indexed, int)
 *   data-total         — total pages (int)
 *   data-per-page      — items per page (int)
 *   data-link-mode     — 'url' (default) | 'cb' (callback / data-page buttons)
 *   data-info-format   — optional sprintf string for the "Showing X–Y of Z" text
 *
 * Row scope:
 *   data-wpsubs-pager-scope (on the pager OR an ancestor) — a CSS selector for
 *   the container holding the rows to paginate. Defaults to the closest <table>
 *   inside the pager's card. Rows are direct children matched by `row_selector`
 *   on the scope element (default 'tbody tr'). Pro's non-table layouts can
 *   override both with data attributes.
 *
 * Events fired on the pager root (bubbles):
 *   wpsubs:pager:change — { page, total, pages, perPage }
 */
(function () {
  "use strict";

  var MONTHS = [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December",
  ];

  // Same algorithm as PHP wpsubs_pager_page_range(): first, last, current ± 1,
  // gaps collapse to a single ellipsis (gap == 2 surfaces the missing page).
  function pageRange(current, total) {
    var first = 1;
    var last = total;
    current = Math.max(1, Math.min(total, current));
    var nearStart = Math.max(2, current - 1);
    var nearEnd = Math.min(last - 1, current + 1);
    var nearby = [];
    for (var i = nearStart; i <= nearEnd; i++) nearby.push(i);
    var seen = {};
    var parts = [];
    function push(n) {
      if (!seen[n]) {
        seen[n] = true;
        parts.push(n);
      }
    }
    push(first);
    nearby.forEach(push);
    if (last > first) push(last);
    var range = [];
    for (var j = 0; j < parts.length; j++) {
      var p = parts[j];
      if (j > 0) {
        var gap = p - parts[j - 1];
        if (gap === 2) range.push(parts[j - 1] + 1);
        else if (gap > 2) range.push(null);
      }
      range.push(p);
    }
    return range;
  }

  function el(tag, className, attrs) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (attrs) {
      for (var k in attrs)
        if (Object.prototype.hasOwnProperty.call(attrs, k)) {
          if (k === "html") node.innerHTML = attrs[k];
          else if (k === "text") node.textContent = attrs[k];
          else if (k === "data") {
            for (var dk in attrs.data) node.setAttribute("data-" + dk, attrs.data[dk]);
          } else node.setAttribute(k, attrs[k]);
        }
    }
    return node;
  }

  function readInt(el, name, fallback) {
    var v = el.getAttribute(name);
    if (v === null || v === "") return fallback;
    var n = parseInt(v, 10);
    return isNaN(n) ? fallback : n;
  }

  function i18nInfo(format, start, end, total) {
    if (!format) return "";
    if (typeof wp !== "undefined" && wp.i18n && typeof wp.i18n.__ === "function") {
      return wp.i18n
        .__(format, "subscription")
        .replace("%1$s", String(start))
        .replace("%2$s", String(end))
        .replace("%3$s", String(total));
    }
    return format.replace("%1$s", String(start)).replace("%2$s", String(end)).replace("%3$s", String(total));
  }

  function formatInfo(format, start, end, total) {
    if (typeof wp !== "undefined" && wp.i18n && typeof wp.i18n.sprintf === "function") {
      return wp.i18n.sprintf(format, String(start), String(end), String(total));
    }
    return format.replace("%1$s", String(start)).replace("%2$s", String(end)).replace("%3$s", String(total));
  }

  /**
   * @param {HTMLElement} root  .wpsubs-pager[data-wpsubs-pager]
   */
  function WPSubsPager(root) {
    this.root = root;
    this.scope = root.getAttribute("data-wpsubs-pager-scope")
      ? document.querySelector(root.getAttribute("data-wpsubs-pager-scope"))
      : root.closest("[data-wpsubs-pager-scope]") || this.findScope();
    this.rowSelector = root.getAttribute("data-pager-row-selector") || "tbody tr";
    this.dateCol = readInt(root, "data-date-col", -1);
    this.dateAdv = null;
    this.perPageAdv = null;
    this.linkMode = root.getAttribute("data-link-mode") || "url";
    this.infoFormat = root.getAttribute("data-info-format") || "";
    this.card = root.closest(".subscrpt-card, .wpsubs-table-card, [data-wpsubs-pager-card]") || root.parentElement;
    this.rows = this.collectRows();
    this.page = readInt(root, "data-current", 1);
    this.total = readInt(root, "data-total", 1);
    this.perPage = readInt(root, "data-per-page", 10);
    this.emptyRow = null;
    this.colSpan = 1;
    this._bind();
  }

  WPSubsPager.prototype.findScope = function () {
    // Default: closest <table> within the same card.
    var table = this.root.closest(".subscrpt-card, .wpsubs-table-card, [data-wpsubs-pager-card]");
    if (table) {
      var t = table.querySelector("table");
      if (t) return t;
    }
    return this.root.parentElement;
  };

  WPSubsPager.prototype.collectRows = function () {
    if (!this.scope) return [];
    return Array.prototype.slice.call(this.scope.querySelectorAll(this.rowSelector));
  };

  WPSubsPager.prototype._bind = function () {
    var self = this;
    if (this.card) {
      this.dateAdv = this.card.querySelector(".subscrpt-filter-date");
      this.perPageAdv = this.card.querySelector(".subscrpt-filter-perpage");
      var table = this.card.querySelector("table");
      if (table) {
        this.colSpan = table.querySelectorAll("thead th").length || 1;
      }
    }

    this.root.addEventListener("click", function (e) {
      var btn = e.target.closest(".wpsubs-pagination__btn");
      if (!btn || !self.root.contains(btn)) return;
      if (btn.classList.contains("wpsubs-pagination__btn--ellipsis")) return;
      if (btn.classList.contains("wpsubs-pagination__btn--disabled")) return;
      if (btn.classList.contains("wpsubs-pagination__btn--active")) return;
      var page = parseInt(btn.getAttribute("data-page"), 10);
      if (!isNaN(page) && page !== self.page) {
        self.page = page;
        self.render();
      }
    });

    if (this.dateAdv) {
      this.dateAdv.addEventListener("wpsubs:select", function () {
        self.page = 1;
        self.render();
      });
    }
    if (this.perPageAdv) {
      this.perPageAdv.addEventListener("wpsubs:select", function () {
        var input = self.perPageAdv.querySelector('input[type="hidden"]');
        self.perPage = parseInt(input ? input.value : self.perPage, 10) || self.perPage;
        self.page = 1;
        self.render();
      });
    }
  };

  WPSubsPager.prototype._monthKey = function (tr) {
    if (!tr || this.dateCol < 0) return "";
    var cell = tr.children[this.dateCol];
    if (!cell) return "";
    var d = new Date(cell.textContent.replace(" - ", " ").trim());
    if (isNaN(d.getTime())) return "";
    return d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2);
  };

  WPSubsPager.prototype._injectMonthOptions = function (rows) {
    if (!this.dateAdv || this.dateCol < 0) return;
    var menu = this.dateAdv.querySelector(".wpsubs-adv-select__menu");
    if (!menu) return;
    // Remove previously injected month options (any non-original item).
    var injected = menu.querySelectorAll("[data-pager-month]");
    injected.forEach(function (n) {
      n.parentNode.removeChild(n);
    });
    var seen = {};
    var months = [];
    rows.forEach(function (tr) {
      var key = this._monthKey(tr);
      if (key && !seen[key]) {
        seen[key] = true;
        months.push(key);
      }
    }, this);
    months
      .sort()
      .reverse()
      .forEach(function (key) {
        var parts = key.split("-");
        var btn = document.createElement("button");
        btn.type = "button";
        btn.setAttribute("data-pager-month", "1");
        btn.className = "wpsubs-adv-select__item";
        btn.setAttribute("data-value", key);
        btn.setAttribute("role", "option");
        var span = document.createElement("span");
        span.className = "wpsubs-adv-select__item-label";
        span.textContent = MONTHS[parseInt(parts[1], 10) - 1] + " " + parts[0];
        btn.appendChild(span);
        menu.appendChild(btn);
      }, this);
  };

  WPSubsPager.prototype._advValue = function (adv) {
    if (!adv) return "";
    var input = adv.querySelector('input[type="hidden"]');
    return input ? input.value : "";
  };

  WPSubsPager.prototype._filtered = function () {
    var dm = this._advValue(this.dateAdv);
    if (!dm || this.dateCol < 0) return this.rows;
    return this.rows.filter(function (tr) {
      return this._monthKey(tr) === dm;
    }, this);
  };

  WPSubsPager.prototype._makeBtn = function (label, target, isDisabled, isActive) {
    if (isDisabled) {
      return el("span", "wpsubs-pagination__btn wpsubs-pagination__btn--disabled", {
        "aria-hidden": "true",
        html: label,
      });
    }
    if (isActive) {
      return el("span", "wpsubs-pagination__btn wpsubs-pagination__btn--active", {
        "aria-current": "page",
        text: label,
      });
    }
    if (this.linkMode === "cb") {
      return el("button", "wpsubs-pagination__btn", {
        type: "button",
        "data-page": String(target),
        text: label,
      });
    }
    return el("a", "wpsubs-pagination__btn", { "data-page": String(target), text: label });
  };

  WPSubsPager.prototype._makeEllipsis = function () {
    return el("span", "wpsubs-pagination__btn wpsubs-pagination__btn--ellipsis", {
      "aria-hidden": "true",
      text: "…",
    });
  };

  WPSubsPager.prototype._buildPager = function (pages) {
    var frag = document.createDocumentFragment();
    frag.appendChild(this._makeBtn("‹", this.page - 1, this.page <= 1, false));
    var range = pageRange(this.page, pages);
    for (var i = 0; i < range.length; i++) {
      var p = range[i];
      frag.appendChild(p === null ? this._makeEllipsis() : this._makeBtn(String(p), p, false, p === this.page));
    }
    frag.appendChild(this._makeBtn("›", this.page + 1, this.page >= pages, false));
    return frag;
  };

  WPSubsPager.prototype._updateInfo = function (start, end, total) {
    if (!this.infoFormat) return;
    var span = this.root.querySelector(".wpsubs-pagination__info");
    if (!span) return;
    span.textContent = formatInfo(this.infoFormat, total ? start + 1 : 0, Math.min(end, total), total);
  };

  WPSubsPager.prototype._showEmpty = function (show) {
    if (!this.scope) return;
    if (show) {
      if (!this.emptyRow) {
        var tr = document.createElement("tr");
        tr.className = "subscrpt-empty-row";
        var td = document.createElement("td");
        td.colSpan = this.colSpan;
        td.style.textAlign = "center";
        td.style.padding = "18px";
        td.textContent =
          typeof wp !== "undefined" && wp.i18n && typeof wp.i18n.__ === "function"
            ? wp.i18n.__("No matching records.", "subscription")
            : "No matching records.";
        tr.appendChild(td);
        if (this.scope.tagName === "TBODY" || this.scope.tagName === "TABLE") {
          this.scope.appendChild(tr);
        } else {
          this.scope.appendChild(tr);
        }
        this.emptyRow = tr;
      }
      this.emptyRow.style.display = "";
    } else if (this.emptyRow) {
      this.emptyRow.style.display = "none";
    }
  };

  WPSubsPager.prototype.render = function () {
    var list = this._filtered();
    var pages = Math.max(1, Math.ceil(list.length / this.perPage));
    if (this.page > pages) this.page = pages;
    var start = (this.page - 1) * this.perPage;
    var end = start + this.perPage;
    this.total = pages;

    this.rows.forEach(function (tr) {
      tr.style.display = "none";
    });
    list.slice(start, end).forEach(function (tr) {
      tr.style.display = "";
    });

    this._showEmpty(list.length === 0);

    // Rebuild just the nav portion, leave the root attributes alone.
    var existingNav = this.root.querySelector(".wpsubs-pagination__nav");
    if (existingNav) existingNav.parentNode.removeChild(existingNav);
    var nav = el("div", "wpsubs-pagination__nav");
    nav.appendChild(this._buildPager(pages));
    this.root.appendChild(nav);

    // Keep current/total data-* in sync so subsequent re-inits read the right state.
    this.root.setAttribute("data-current", String(this.page));
    this.root.setAttribute("data-total", String(pages));

    this._updateInfo(start, end, list.length);

    this.root.dispatchEvent(
      new CustomEvent("wpsubs:pager:change", {
        bubbles: true,
        detail: { page: this.page, total: list.length, pages: pages, perPage: this.perPage },
      }),
    );
  };

  WPSubsPager.prototype.refresh = function () {
    // External hook: re-collect rows (Pro renders new content) and re-paginate.
    this.rows = this.collectRows();
    this._injectMonthOptions(this.rows);
    this.render();
  };

  WPSubsPager.prototype.goTo = function (page) {
    page = parseInt(page, 10);
    if (isNaN(page)) return;
    var pages = Math.max(1, Math.ceil(this.rows.length / this.perPage));
    this.page = Math.max(1, Math.min(pages, page));
    this.render();
  };

  /**
   * Initialise all un-initialised .wpsubs-pager elements under root.
   * Skips pagers in `link-mode="url"` — those are server-rendered (e.g. the
   * subscriptions list page) and own all the data they need; touching them
   * from JS would re-derive totals from the current page's rows and clobber
   * the correct pagination (e.g. show only "1" of 3 pages).
   *
   * @param {Document|HTMLElement} [root]
   */
  function init(root) {
    (root || document)
      .querySelectorAll(".wpsubs-pager[data-wpsubs-pager][data-link-mode='cb']:not([data-pager-init])")
      .forEach(function (el) {
        el.setAttribute("data-pager-init", "1");
        var pager = new WPSubsPager(el);
        pager._injectMonthOptions(pager.rows);
        pager.render();
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
  window.WPSubsPager = {
    init: init,
    /**
     * Re-collect rows for every initialised cb-mode pager under root and
     * re-render. Useful when a callback injects new rows (e.g. Pro activities).
     */
    refresh: function (root) {
      (root || document).querySelectorAll(".wpsubs-pager[data-link-mode='cb'][data-pager-init]").forEach(function (el) {
        el.removeAttribute("data-pager-init");
        init(el.parentNode || document);
      });
    },
    /**
     * Force a single pager to re-collect rows (Pro hook) and re-render.
     * Skips url-mode pagers.
     */
    refreshOne: function (el) {
      if (!el) return;
      if (el.getAttribute("data-link-mode") !== "cb") return;
      el.removeAttribute("data-pager-init");
      init(el.parentNode || document);
    },
  };
})();
