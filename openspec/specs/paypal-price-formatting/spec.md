### Requirement: PayPal plan recurring price is a decimal-formatted string

The system SHALL format the recurring billing cycle price as a string with exactly 2 decimal places and a period as decimal separator before inserting it into the PayPal billing plan payload.

#### Scenario: Whole number price

- **WHEN** a product is priced at `10.00`
- **THEN** the PayPal plan payload contains `"value": "10.00"` (string, not number)

#### Scenario: Single decimal digit price

- **WHEN** a product is priced at `10.9`
- **THEN** the PayPal plan payload contains `"value": "10.90"` (padded to 2 decimal places)

#### Scenario: Two decimal digit price

- **WHEN** a product is priced at `10.99`
- **THEN** the PayPal plan payload contains `"value": "10.99"`

#### Scenario: Price with locale comma separator

- **WHEN** the server locale uses comma as decimal separator and the product price is `10.50`
- **THEN** the PayPal plan payload contains `"value": "10.50"` (period separator, not comma)

### Requirement: PayPal plan setup fee is a decimal-formatted string

The system SHALL format the setup/signup fee as a string with exactly 2 decimal places and a period as decimal separator before inserting it into the PayPal plan `payment_preferences.setup_fee` field.

#### Scenario: Zero setup fee

- **WHEN** no signup fee is configured
- **THEN** the PayPal plan payload contains `setup_fee.value: "0.00"` (not `"0"`)

#### Scenario: Non-zero setup fee with single decimal

- **WHEN** signup fee is `5.5`
- **THEN** the PayPal plan payload contains `setup_fee.value: "5.50"`

#### Scenario: Non-zero setup fee with two decimals

- **WHEN** signup fee is `9.99`
- **THEN** the PayPal plan payload contains `setup_fee.value: "9.99"`
