## ADDED Requirements

### Requirement: Process full refund via PayPal

The gateway SHALL issue a full refund for a PayPal order by calling `POST /v2/payments/captures/{capture_id}/refund` with no amount body, returning `true` on HTTP 201.

#### Scenario: Full refund succeeds

- **WHEN** admin initiates a full refund on a PayPal order with a valid capture ID
- **THEN** gateway calls PayPal refund API and returns `true`

#### Scenario: Full refund fails due to missing capture ID

- **WHEN** admin initiates a full refund on an order with no transaction ID stored
- **THEN** gateway returns a `WP_Error` with message "PayPal capture ID not found on this order."

#### Scenario: Full refund fails due to PayPal API error

- **WHEN** PayPal API returns a non-201 response
- **THEN** gateway returns a `WP_Error` containing the PayPal error message

### Requirement: Process partial refund via PayPal

The gateway SHALL issue a partial refund by including `amount.value` and `amount.currency_code` in the refund request body.

#### Scenario: Partial refund succeeds

- **WHEN** admin initiates a partial refund with a specific amount
- **THEN** gateway sends the correct amount and currency to PayPal and returns `true`

#### Scenario: Partial refund with reason

- **WHEN** admin provides a refund reason
- **THEN** gateway includes `note_to_payer` (truncated to 255 chars) in the request

### Requirement: Order note on successful refund

The gateway SHALL add an order note containing the PayPal refund ID when a refund succeeds.

#### Scenario: Order note added

- **WHEN** PayPal returns HTTP 201 with a refund ID
- **THEN** order note is added: "PayPal refund initiated. Refund ID: {id}"
