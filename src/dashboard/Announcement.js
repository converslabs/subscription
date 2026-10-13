/**
 * What's new in this release, across the top of the dashboard.
 *
 * Changelogs go unread; this is where a store owner actually looks. Closing it
 * is remembered per user until the next announcement, which has a new id.
 */

import { useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import Icon from "./Icon";

/**
 * Record the dismissal. Fire and forget: the banner is already gone, and if
 * the request fails it simply shows again on the next visit.
 *
 * @param {Object} announcement Announcement payload.
 */
function dismiss(announcement) {
  const { url, action, nonce } = announcement.dismiss || {};

  if (!url) {
    return;
  }

  window
    .fetch(url, {
      method: "POST",
      credentials: "same-origin",
      body: new URLSearchParams({ action, nonce, id: announcement.id }),
    })
    .catch(() => {});
}

export default function Announcement({ announcement }) {
  const [open, setOpen] = useState(true);

  if (!open) {
    return null;
  }

  const { label, title, text, features = [], link } = announcement;

  return (
    <section className="subscrpt-announce" aria-labelledby="subscrpt-announce-title">
      <button
        type="button"
        className="subscrpt-announce__close"
        aria-label={__("Dismiss announcement", "subscription")}
        onClick={() => {
          setOpen(false);
          dismiss(announcement);
        }}
      >
        <Icon name="close" size={16} />
      </button>

      <div className="subscrpt-announce__intro">
        {label && (
          <div className="subscrpt-announce__label">
            <Icon name="sparkle" size={14} />
            {label}
          </div>
        )}
        <h2 id="subscrpt-announce-title" className="subscrpt-announce__title">
          {title}
        </h2>
        {text && <div className="subscrpt-announce__text">{text}</div>}
        {link && (
          <a className="subscrpt-announce__more" href={link.url} target="_blank" rel="noreferrer noopener">
            {link.label}
            <Icon name="external" size={13} />
          </a>
        )}
      </div>

      <ul className="subscrpt-announce__features">
        {features.map((feature) => (
          <li key={feature.title} className="subscrpt-announce__feature">
            <span className="subscrpt-announce__icon">
              <Icon name={feature.icon} size={18} />
            </span>
            <div>
              <div className="subscrpt-announce__feature-title">{feature.title}</div>
              <div className="subscrpt-announce__feature-text">{feature.text}</div>
              {feature.link && (
                <a className="subscrpt-announce__feature-link" href={feature.link.url}>
                  {feature.link.label} →
                </a>
              )}
            </div>
          </li>
        ))}
      </ul>
    </section>
  );
}
