/**
 * wpsubs-notice — close button.
 *
 * Closing removes the notice from this page view only. A notice that still
 * applies comes back on the next load, the same as WordPress's own
 * dismissible notices.
 */
(function () {
  "use strict";

  document.addEventListener("click", function (event) {
    var button = event.target.closest("[data-wpsubs-notice-close]");
    if (!button) {
      return;
    }

    var notice = button.closest(".wpsubs-notice");
    if (!notice) {
      return;
    }

    var list = notice.parentNode;
    notice.remove();

    // Drop the empty container so it does not leave a gap under the header.
    if (list && list.classList.contains("wpsubs-notices") && !list.children.length) {
      list.remove();
    }
  });
})();
