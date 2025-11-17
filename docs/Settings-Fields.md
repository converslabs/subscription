# Here is how to add subscription settings fields.

We need to use 2 hooks for this.

1. `subscrpt_settings_fields`: This renders the frontend settings field.
2. `subscrpt_register_settings`: This registers the settings fields in the backend in order to save.

## `subscrpt_settings_fields`

This is a filter hook and it is used to add the settings fields. Then it groups the fields and sorts based on priorities.

> [!NOTE]
> The group's priority depends on the **Heading**'s priority.

There are 4 types of fields at the moment. They are,

- `heading`
- `input`
- `select`
- `toggle`
- `join`

To insert a field, you will need to add to arrays to the filter. Every settings field should contain the following data:

```php
[
    'type'       => (string) 'heading' // Field type
    'group'      => (string) 'main'   // Field group
    'priority'   => (int) 1     // Group Priority or Field Priority (relative to group)
    'field_data' => (array) []  // Different field data.
]
```

And here are the specific field data:
**Heading Field**

```php
[
    'title'       => (string) "The tile of the field"
    'description' => (string) "The description of the field"
]
```

**Input Field**

```php
[
    'id'          =>(string) "id_of_the_field" // This will also be used in the backend to get and store data.
    'title'       =>(string) "The tile of the field"
    'description' =>(string) "The description of the field"
    'value'       =>(string) "Default value"
    'placeholder' =>(string) "Placeholder"
    'disabled'    =>(bool) "Disabled status"
    'type'        =>(string) "Input type [text, email, number, date, time, etc.]"
]
```

**Toggle/Switch Field**

```php
[
    'id'          =>(string) "id_of_the_field" // This will also be used in the backend to get and store data.
    'title'       =>(string) "The tile of the field"
    'label'       =>(string) "The lable to show at the right of the field"
    'description' =>(string) "The description of the field"
    'value'       =>(string) "Default value"
    'checked'     =>(bool) "Checked status"
    'disabled'    =>(bool) "Disabled status"
]
```

**Select Field**

```php
[
    'id'          =>(string) "id_of_the_field" // This will also be used in the backend to get and store data.
    'title'       =>(string) "The tile of the field"
    'description' =>(string) "The description of the field"
    'options'     =>(array) "List of options [key => value, ...]"
    'selected'    =>(string) "Value of the selected option"
    'disabled'    =>(string|array) "Value or array of values to disable"
]
```

**Join Field**

> This is a special type of field that combines multiple fields.

```php
[
    'title'       =>(string) "The tile of the field"
    'description' =>(string) "The description of the field"
    'elements'    =>(array) "An array of HTML strings of multiple fields"
    'vertical'    =>(bool) "Should the join represent vertically"
]
```

## `subscrpt_register_settings`

This hook is used to register the settings to DB to save the data. It can also be done using `admin_init` but it is preferred to use `subscrpt_register_settings`.
Here is an example of this,

```php
register_setting(
    'wp_subscription_settings',
    'field_id',
    array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    )
);
```

# A complete Example

```php
add_filter( 'subscrpt_settings_fields', [ $this, 'add_settings_fields' ] );
add_action( 'subscrpt_register_settings', [ $this, 'register_settings_fields' ] );

public function add_settings_fields( $settings_fields ) {
    $new_fields = [
        [
            'type'       => 'heading',
            'group'      => 'test',
            'priority'   => 1,
            'field_data' => [
                'title'       => __( 'Test Heading', 'wp_subscription' ),
                'description' => __( 'Test heading description.', 'wp_subscription' ),
            ],
        ],
        [
            'type'       => 'toggle',
            'group'      => 'test',
            'priority'   => 1,
            'field_data' => [
                'id'          => 'test_field_id',
                'title'       => __( 'Test Switch', 'wp_subscription' ),
                'label'       => __( 'Toggle on test switch', 'wp_subscription' ),
                'description' => __( 'Toggle Description. Click to toggle!', 'wp_subscription' ),
                'value'       => '1',
                'checked'     => '1' === get_option( 'test_field_id', '1' ),
                'disabled'    => false,
            ],
        ],
    ];
}

public function add_settings_fields() {
    register_setting(
        'wp_subscription_settings',
        'test_field_id',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        )
    );
}
```
