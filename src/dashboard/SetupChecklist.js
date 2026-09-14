/**
 * Onboarding checklist.
 *
 * The wizard's three steps, each with a button to where it gets done, then a
 * warning when no payment gateway is on — onboarding can be finished without
 * one, but no customer can pay. Once the steps are done the card shrinks to a
 * single line that still opens the wizard, so it can be run again for another
 * plan; the gateway warning stays until a gateway is on.
 */

import { __, sprintf } from "@wordpress/i18n";
// TODO(refactor): replace @wordpress/components with our `wpsubs-*` admin
// components (wpsubs-table-card, wpsubs-btn, …) — see src/dashboard/index.js.
import { Card, CardHeader, CardBody, Button } from "@wordpress/components";
import Icon from "./Icon";

function Count({ setup }) {
  return (
    <span className="subscrpt-setup__count">
      {sprintf(
        /* translators: 1: steps done, 2: steps total. */
        __("%1$d of %2$d done", "subscription"),
        setup.done,
        setup.total,
      )}
    </span>
  );
}

function WizardLink({ wizard }) {
  return (
    <a className="subscrpt-setup__wizard" href={wizard.url}>
      {wizard.label}
      <span aria-hidden="true">→</span>
    </a>
  );
}

function GatewayWarning({ gateway }) {
  return (
    <div className="subscrpt-setup__warning">
      <span className="subscrpt-setup__warning-icon">
        <Icon name="alert" size={16} />
      </span>
      <div className="subscrpt-setup__warning-body">
        <p className="subscrpt-setup__warning-title">{gateway.title}</p>
        <p className="subscrpt-setup__warning-text">{gateway.text}</p>
        <Button variant="secondary" size="small" href={gateway.action.url}>
          {gateway.action.label}
        </Button>
      </div>
    </div>
  );
}

export default function SetupChecklist({ setup }) {
  if (setup.complete) {
    return (
      <Card className="subscrpt-setup is-complete">
        <CardBody>
          <div className="subscrpt-setup__summary">
            <div>
              <h2 className="subscrpt-heading">{__("Onboarding", "subscription")}</h2>
              <Count setup={setup} />
            </div>
            <WizardLink wizard={setup.wizard} />
          </div>
          {setup.gateway && <GatewayWarning gateway={setup.gateway} />}
        </CardBody>
      </Card>
    );
  }

  const percent = Math.round((setup.done / setup.total) * 100);

  return (
    <Card className="subscrpt-setup">
      <CardHeader>
        <div>
          <h2 className="subscrpt-heading">{__("Onboarding", "subscription")}</h2>
          <p className="subscrpt-sub">{__("Your first plan, step by step.", "subscription")}</p>
        </div>
        <Count setup={setup} />
      </CardHeader>

      <CardBody>
        <div
          className="subscrpt-progress"
          role="progressbar"
          aria-valuenow={setup.done}
          aria-valuemin={0}
          aria-valuemax={setup.total}
        >
          <span className="subscrpt-progress__bar" style={{ width: `${percent}%` }} />
        </div>

        <ul className="subscrpt-steps">
          {setup.items.map((item) => (
            <li key={item.id} className={`subscrpt-step${item.done ? " is-done" : ""}`}>
              <span className="subscrpt-step__mark">{item.done ? <Icon name="check" size={13} /> : null}</span>
              <span className="subscrpt-step__label">{item.label}</span>
              {item.done ? (
                <span className="subscrpt-step__state">{__("Done", "subscription")}</span>
              ) : (
                <Button variant="secondary" size="small" href={item.action.url}>
                  {item.action.label}
                </Button>
              )}
            </li>
          ))}
        </ul>

        {setup.gateway && <GatewayWarning gateway={setup.gateway} />}

        <div className="subscrpt-setup__footer">
          <WizardLink wizard={setup.wizard} />
        </div>
      </CardBody>
    </Card>
  );
}
