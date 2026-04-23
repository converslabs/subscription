## ADDED Requirements

### Requirement: PayPal plan is created when critical billing fields change

The system SHALL create a new PayPal billing plan when any critical billing field (price, currency, billing interval, billing interval count, trial interval, trial interval count, signup fee, or max payment cycles) differs from all previously stored plans for that product.

#### Scenario: Price change triggers new plan

- **WHEN** a product's price is changed in WooCommerce and a customer checks out
- **THEN** a new PayPal plan is created with the updated price
- **THEN** the new plan ID is used for the checkout session

#### Scenario: Billing interval change triggers new plan

- **WHEN** a product's billing interval is changed (e.g., monthly → yearly) and a customer checks out
- **THEN** a new PayPal plan is created with the updated interval
- **THEN** the old plan ID is not used

#### Scenario: Unchanged product reuses existing plan

- **WHEN** a product's billing fields have not changed since the last PayPal plan was created
- **THEN** the existing plan ID is returned without any PayPal API call

#### Scenario: Signup fee change triggers new plan

- **WHEN** a product's signup fee is changed and a customer checks out
- **THEN** a new PayPal plan is created with the updated signup fee

### Requirement: Duplicate plan creation is skipped when an identical plan already exists

The system SHALL NOT create a new PayPal plan if a stored plan with an identical fingerprint (same price, currency, interval, trial, signup fee, and cycle count) already exists for that product.

#### Scenario: Same configuration after change reversal

- **WHEN** an admin changes a product's price and then reverts it to the original value
- **THEN** the original plan is matched by fingerprint and reused
- **THEN** no new PayPal API plan creation request is made

#### Scenario: Multiple stored plans, fingerprint match found

- **WHEN** a product has accumulated multiple historical plans (different configurations)
- **THEN** the system searches all stored plans for a fingerprint match
- **THEN** the matching plan ID is returned without creating a new plan

### Requirement: Plans array persists multiple historical plan entries

The system SHALL store PayPal plan entries as an array (one entry per distinct billing configuration), not as a single overwritten plan ID.

#### Scenario: New configuration appended

- **WHEN** a new plan is created due to a billing field change
- **THEN** the new `{plan_id, fingerprint}` entry is appended to the stored plans array
- **THEN** previously stored plan entries are preserved

#### Scenario: Plans array survives subsequent lookups

- **WHEN** two successive checkouts use different product configurations
- **THEN** both plan IDs are present in the stored array after both checkouts
