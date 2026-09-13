/**
 * The Pro marker, for a link whose destination needs WPSubscription Pro.
 *
 * The admin's own pill (assets/css/admin-components/badges.css), with the same
 * text and title as on the product screen and in the Plans modals.
 */

import { __ } from "@wordpress/i18n";

export default function ProBadge() {
  return (
    <span
      className="wpsubs-badge wpsubs-badge--pro subscrpt-pro-badge"
      title={__("WPSubscription Pro required", "subscription")}
    >
      {__("Pro", "subscription")}
    </span>
  );
}
