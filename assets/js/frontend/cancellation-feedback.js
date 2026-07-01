/**
 * Cancellation feedback modal.
 *
 * Intercepts the Cancel action link on the single-subscription page, shows a
 * feedback modal, records the customer's reason + comment via AJAX, then follows
 * the original secure cancel URL. Recording is best-effort: if the request fails,
 * the cancellation still proceeds.
 */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    var modal = document.getElementById("subscrpt-feedback-modal");
    if (!modal) {
      return;
    }

    var cancelLink = document.querySelector(".subscrpt_action_buttons a.cancel");
    if (!cancelLink) {
      return;
    }

    var cancelUrl = cancelLink.getAttribute("href");
    var confirmBtn = document.getElementById("subscrpt-feedback-confirm");

    /**
     * Open the modal.
     *
     * @param {Event} e Click event.
     */
    function openModal(e) {
      e.preventDefault();
      modal.hidden = false;
      document.body.style.overflow = "hidden";
      var firstRadio = modal.querySelector('input[name="subscrpt_feedback_reason"]');
      if (firstRadio) firstRadio.focus();
    }

    /**
     * Close the modal without cancelling.
     */
    function closeModal() {
      modal.hidden = true;
      document.body.style.overflow = "";
    }

    /**
     * Navigate to the original cancel URL.
     */
    function proceed() {
      window.location.href = cancelUrl;
    }

    cancelLink.addEventListener("click", openModal);

    var dismissEls = modal.querySelectorAll("[data-subscrpt-feedback-dismiss]");
    for (var i = 0; i < dismissEls.length; i++) {
      dismissEls[i].addEventListener("click", closeModal);
    }

    // Close on Escape, but only while this modal is open.
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !modal.hidden) {
        closeModal();
      }
    });

    if (confirmBtn) {
      confirmBtn.addEventListener("click", function () {
        confirmBtn.disabled = true;

        var checked = modal.querySelector('input[name="subscrpt_feedback_reason"]:checked');
        var commentEl = document.getElementById("subscrpt-feedback-comment");

        var params = new URLSearchParams();
        params.append("action", "subscrpt_record_cancellation_feedback");
        params.append("nonce", subscrptCancellationFeedback.nonce);
        params.append("subscription_id", modal.getAttribute("data-subscription") || "");
        params.append("reason_key", checked ? checked.value : "");
        params.append("comment", commentEl ? commentEl.value : "");

        fetch(subscrptCancellationFeedback.ajaxUrl, {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: params.toString(),
        })
          .then(proceed)
          .catch(proceed);
      });
    }
  });
})();
