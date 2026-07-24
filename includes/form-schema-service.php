<?php

/**
 * @return array<string, array<string, mixed>>
 */
function rm_form_core_field_definitions(): array
{
    return [
        'nric' => [
            'key' => 'nric', 'type' => 'text', 'label' => 'NRIC (Last 4 digits)',
            'placeholder' => 'e.g. 1234', 'required' => true, 'source' => 'core', 'maps_to' => 'nric', 'order' => 1,
        ],
        'title' => [
            'key' => 'title', 'type' => 'select', 'label' => 'Title',
            'placeholder' => 'Please select title', 'required' => true, 'source' => 'core', 'maps_to' => 'title', 'order' => 2,
            'options' => array_map(
                static fn (string $value): array => ['value' => $value, 'label' => $value],
                rm_registration_title_options()
            ),
        ],
        'christian_name' => [
            'key' => 'christian_name', 'type' => 'text', 'label' => 'Christian name',
            'placeholder' => 'e.g. John', 'required' => true, 'source' => 'core', 'maps_to' => 'christian_name', 'order' => 3,
        ],
        'given_name' => [
            'key' => 'given_name', 'type' => 'text', 'label' => 'Given name',
            'placeholder' => 'e.g. James', 'required' => true, 'source' => 'core', 'maps_to' => 'given_name', 'order' => 4,
        ],
        'family_name' => [
            'key' => 'family_name', 'type' => 'text', 'label' => 'Family name',
            'placeholder' => 'e.g. Tan', 'required' => true, 'source' => 'core', 'maps_to' => 'family_name', 'order' => 5,
        ],
        'certificate_name' => [
            'key' => 'certificate_name', 'type' => 'text', 'label' => 'Certificate name',
            'placeholder' => 'e.g. James Tan', 'required' => true, 'source' => 'core', 'maps_to' => 'certificate_name', 'order' => 6,
        ],
        'email' => [
            'key' => 'email', 'type' => 'email', 'label' => 'Email address',
            'placeholder' => 'e.g. name@example.com', 'required' => true, 'source' => 'core', 'maps_to' => 'email', 'order' => 7,
        ],
        'contact' => [
            'key' => 'contact', 'type' => 'phone', 'label' => 'Contact number',
            'placeholder' => 'e.g. 91234567', 'required' => true, 'source' => 'core', 'maps_to' => 'contact', 'order' => 8,
        ],
        'address1' => [
            'key' => 'address1', 'type' => 'text', 'label' => 'Address 1',
            'placeholder' => 'e.g. 123 Street Name', 'required' => true, 'source' => 'core', 'maps_to' => 'address1', 'order' => 9,
        ],
        'address2' => [
            'key' => 'address2', 'type' => 'text', 'label' => 'Address 2',
            'placeholder' => 'e.g. Unit #01-01', 'required' => false, 'source' => 'core', 'maps_to' => 'address2', 'order' => 10,
        ],
        'postcode' => [
            'key' => 'postcode', 'type' => 'text', 'label' => 'Postal code',
            'placeholder' => 'e.g. 123456', 'required' => true, 'source' => 'core', 'maps_to' => 'postcode', 'order' => 11,
        ],
        'church_name' => [
            'key' => 'church_name', 'type' => 'text', 'label' => 'Church name',
            'placeholder' => 'e.g. Church of Singapore', 'required' => true, 'source' => 'core', 'maps_to' => 'church_name', 'order' => 12,
        ],
    ];
}

/**
 * Core fields that must always appear on a custom form preset.
 *
 * @return list<string>
 */
function rm_form_custom_required_field_keys(): array
{
    return ['given_name', 'family_name', 'email'];
}

/**
 * @return list<string>
 */
function rm_form_preset_field_keys(string $preset): array
{
    return match ($preset) {
        RM_FORM_PRESET_MINIMAL => ['given_name', 'family_name', 'email', 'contact'],
        RM_FORM_PRESET_STANDARD => ['given_name', 'family_name', 'email', 'contact', 'title', 'nric'],
        RM_FORM_PRESET_CUSTOM => rm_form_custom_required_field_keys(),
        default => [
            'given_name', 'family_name', 'email', 'contact', 'title', 'nric',
            'christian_name', 'certificate_name', 'address1', 'address2', 'postcode', 'church_name',
        ],
    };
}

/**
 * Build normalized core field definitions from selected keys.
 * Always includes required custom-preset keys and marks them required.
 *
 * @param list<string> $keys
 * @return list<array<string, mixed>>
 */
function rm_form_build_fields_from_keys(array $keys): array
{
    $core_defs = rm_form_core_field_definitions();
    $required = rm_form_custom_required_field_keys();
    $selected = [];

    foreach ($keys as $key) {
        $key = sanitize_key((string) $key);
        if ($key !== '' && isset($core_defs[$key])) {
            $selected[$key] = true;
        }
    }

    foreach ($required as $key) {
        $selected[$key] = true;
    }

    $fields = [];
    foreach (array_keys($core_defs) as $key) {
        if (!isset($selected[$key])) {
            continue;
        }

        $field = $core_defs[$key];
        if (in_array($key, $required, true)) {
            $field['required'] = true;
        }
        $fields[] = $field;
    }

    usort($fields, static function (array $a, array $b): int {
        return ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0));
    });

    return $fields;
}

/**
 * Selected core field keys for the settings custom-field picker.
 *
 * @param array<string, mixed> $config
 * @return list<string>
 */
function rm_form_selected_field_keys(array $config): array
{
    $preset = (string) ($config['form']['preset'] ?? RM_FORM_PRESET_FULL);
    $fields = isset($config['form']['fields']) && is_array($config['form']['fields'])
        ? $config['form']['fields']
        : [];
    $core_defs = rm_form_core_field_definitions();

    if ($preset === RM_FORM_PRESET_CUSTOM && $fields !== []) {
        $keys = [];
        foreach ($fields as $field) {
            if (!is_array($field) || empty($field['key'])) {
                continue;
            }
            $key = sanitize_key((string) $field['key']);
            if (!isset($core_defs[$key])) {
                continue;
            }
            $keys[] = $key;
        }

        return array_values(array_unique(array_merge(rm_form_custom_required_field_keys(), $keys)));
    }

    return rm_form_preset_field_keys($preset === RM_FORM_PRESET_CUSTOM ? RM_FORM_PRESET_CUSTOM : $preset);
}

/**
 * Field types admins can create on a custom form preset.
 *
 * @return list<string>
 */
function rm_form_allowed_custom_field_types(): array
{
    return ['text', 'number', 'email', 'phone', 'textarea', 'select', 'radio', 'date', 'checkbox'];
}

/**
 * Parse newline/comma-separated option labels into form options.
 *
 * @return list<array{value: string, label: string}>
 */
function rm_form_parse_options_text(string $text): array
{
    $parts = preg_split('/[\r\n,]+/', $text) ?: [];
    $options = [];

    foreach ($parts as $part) {
        $label = trim((string) $part);
        if ($label === '') {
            continue;
        }
        $options[] = [
            'value' => $label,
            'label' => $label,
        ];
    }

    return $options;
}

/**
 * Extra (non-core) custom fields for the Event Settings editor.
 *
 * @param array<string, mixed> $config
 * @return list<array{key: string, label: string, type: string, required: bool, optionsText: string}>
 */
function rm_form_present_admin_custom_fields(array $config): array
{
    $fields = isset($config['form']['fields']) && is_array($config['form']['fields'])
        ? $config['form']['fields']
        : [];
    $core_defs = rm_form_core_field_definitions();
    $presented = [];

    foreach ($fields as $field) {
        if (!is_array($field) || empty($field['key'])) {
            continue;
        }

        $key = sanitize_key((string) $field['key']);
        if ($key === '' || isset($core_defs[$key])) {
            continue;
        }

        $options_text = '';
        if (!empty($field['options']) && is_array($field['options'])) {
            $labels = [];
            foreach ($field['options'] as $option) {
                if (is_array($option)) {
                    $labels[] = (string) ($option['label'] ?? $option['value'] ?? '');
                } else {
                    $labels[] = (string) $option;
                }
            }
            $options_text = implode("\n", array_values(array_filter($labels, static fn (string $label): bool => $label !== '')));
        }

        $presented[] = [
            'key'         => $key,
            'label'       => sanitize_text_field((string) ($field['label'] ?? $key)),
            'type'        => sanitize_key((string) ($field['type'] ?? 'text')),
            'required'    => !empty($field['required']),
            'optionsText' => $options_text,
        ];
    }

    return $presented;
}

/**
 * Normalize admin-created custom fields from Event Settings POST data.
 *
 * @param mixed $rows
 * @return list<array<string, mixed>>
 */
function rm_form_normalize_admin_custom_fields_input(mixed $rows): array
{
    if (!is_array($rows)) {
        return [];
    }

    $core_defs = rm_form_core_field_definitions();
    $used_keys = array_fill_keys(array_keys($core_defs), true);
    $fields = [];
    $order = 100;

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $label = sanitize_text_field((string) ($row['label'] ?? ''));
        if ($label === '') {
            continue;
        }

        $key = sanitize_key((string) ($row['key'] ?? ''));
        if ($key === '') {
            $key = sanitize_key($label);
        }
        if ($key === '') {
            $key = 'custom_field';
        }

        if (isset($core_defs[$key])) {
            $key = 'custom_' . $key;
        }

        $candidate = $key;
        $suffix = 2;
        while (isset($used_keys[$candidate])) {
            $candidate = $key . '_' . $suffix;
            $suffix++;
        }
        $key = $candidate;
        $used_keys[$key] = true;

        $type = sanitize_key((string) ($row['type'] ?? 'text'));
        if (!in_array($type, rm_form_allowed_custom_field_types(), true)) {
            $type = 'text';
        }

        $field = [
            'key'      => $key,
            'label'    => $label,
            'type'     => $type,
            'required' => !empty($row['required']),
            'source'   => 'custom',
            'order'    => $order,
        ];

        $placeholder = sanitize_text_field((string) ($row['placeholder'] ?? ''));
        $field['placeholder'] = $placeholder !== '' ? $placeholder : $label;

        if (in_array($type, ['select', 'radio', 'checkbox_group'], true)) {
            $options = [];
            if (isset($row['options']) && is_array($row['options'])) {
                $options = rm_form_normalize_options($row['options']);
            } else {
                $options_text = (string) ($row['options'] ?? $row['optionsText'] ?? '');
                $options = rm_form_normalize_options(rm_form_parse_options_text($options_text));
            }

            if ($options === []) {
                continue;
            }
            $field['options'] = $options;
        }

        $fields[] = $field;
        $order++;
    }

    return $fields;
}

/**
 * @param array<string, mixed> $event
 * @return array{fields: list<array<string, mixed>>, preset: string, scope: string}
 */
function rm_parse_form_schema(array $event): array
{
    $config = rm_parse_registration_config($event);
    $form = $config['form'];
    $preset = (string) ($form['preset'] ?? RM_FORM_PRESET_FULL);
    $scope = (string) ($form['scope'] ?? 'per_member');
    $stored_fields = is_array($form['fields']) ? $form['fields'] : [];
    $core_defs = rm_form_core_field_definitions();

    if ($preset === RM_FORM_PRESET_CUSTOM) {
        if ($stored_fields !== []) {
            $fields = rm_form_normalize_fields($stored_fields, $core_defs);
            $fields = rm_form_ensure_custom_required_fields($fields, $core_defs);
        } else {
            $fields = rm_form_build_fields_from_keys(rm_form_custom_required_field_keys());
        }
    } else {
        $keys = rm_form_preset_field_keys($preset);
        $fields = [];
        foreach ($keys as $key) {
            if (isset($core_defs[$key])) {
                $fields[] = $core_defs[$key];
            }
        }
    }

    usort($fields, static function (array $a, array $b): int {
        return ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0));
    });

    return [
        'fields' => $fields,
        'preset' => $preset,
        'scope'  => $scope,
    ];
}

/**
 * Build the guest form schema from the event's guests.form.fields config.
 *
 * @param array<string, mixed> $event
 * @return array{fields: list<array<string, mixed>>, enabled: bool, label_singular: string, label_plural: string, min: int, max: int, price: float}
 */
function rm_parse_guest_form_schema(array $event): array
{
    $config = rm_parse_registration_config($event);
    $guests = $config['guests'];
    $capacity = rm_guest_event_capacity($event);
    $event_max = (int) ($capacity['event_max'] ?? 0);
    $used = (int) ($capacity['used'] ?? 0);
    $remaining = $capacity['remaining'];

    if (empty($guests['enabled'])) {
        return [
            'fields'         => [],
            'enabled'        => false,
            'label_singular' => 'Guest',
            'label_plural'   => 'Guests',
            'min'            => 0,
            'max'            => 0,
            'event_max'      => $event_max,
            'used'           => $used,
            'remaining'      => $remaining,
            'price'          => 0.0,
        ];
    }

    $stored_fields = is_array($guests['form']['fields'] ?? null) ? $guests['form']['fields'] : [];
    $core_defs = rm_form_core_field_definitions();
    $fields = rm_form_normalize_fields($stored_fields, $core_defs);

    usort($fields, static function (array $a, array $b): int {
        return ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0));
    });

    $min = (int) ($guests['min'] ?? 0);
    $max = (int) ($guests['max'] ?? 0);
    $enabled = true;

    if ($remaining !== null) {
        if ($max > 0) {
            $max = min($max, $remaining);
        } else {
            $max = $remaining;
        }
        if ($remaining < $min) {
            $min = 0;
        }
        if ($remaining <= 0) {
            // Hide the guest add-on step entirely when event capacity is full.
            $enabled = false;
            $min = 0;
            $max = 0;
        }
    }

    return [
        'fields'         => $fields,
        'enabled'        => $enabled,
        'label_singular' => (string) ($guests['label_singular'] ?? 'Guest'),
        'label_plural'   => (string) ($guests['label_plural'] ?? 'Guests'),
        'min'            => $min,
        'max'            => $max,
        'event_max'      => $event_max,
        'used'           => $used,
        'remaining'      => $remaining,
        'price'          => (float) ($guests['price'] ?? 0),
    ];
}

/**
 * Present guest custom fields for the admin settings editor.
 *
 * @param array<string, mixed> $config
 * @return list<array{key: string, label: string, type: string, required: bool, optionsText: string}>
 */
function rm_form_present_admin_guest_fields(array $config): array
{
    $fields = isset($config['guests']['form']['fields']) && is_array($config['guests']['form']['fields'])
        ? $config['guests']['form']['fields']
        : [];

    $presented = [];
    foreach ($fields as $field) {
        if (!is_array($field) || empty($field['key'])) {
            continue;
        }

        $options_text = '';
        if (!empty($field['options']) && is_array($field['options'])) {
            $labels = [];
            foreach ($field['options'] as $option) {
                if (is_array($option)) {
                    $labels[] = (string) ($option['label'] ?? $option['value'] ?? '');
                } else {
                    $labels[] = (string) $option;
                }
            }
            $options_text = implode("\n", array_values(array_filter($labels, static fn (string $label): bool => $label !== '')));
        }

        $presented[] = [
            'key'         => sanitize_key((string) $field['key']),
            'label'       => sanitize_text_field((string) ($field['label'] ?? $field['key'])),
            'type'        => sanitize_key((string) ($field['type'] ?? 'text')),
            'required'    => !empty($field['required']),
            'optionsText' => $options_text,
        ];
    }

    return $presented;
}

/**
 * Ensure custom-preset forms always include required core fields.
 *
 * @param list<array<string, mixed>> $fields
 * @param array<string, array<string, mixed>> $core_defs
 * @return list<array<string, mixed>>
 */
function rm_form_ensure_custom_required_fields(array $fields, array $core_defs): array
{
    $by_key = [];
    foreach ($fields as $field) {
        if (!is_array($field) || empty($field['key'])) {
            continue;
        }
        $key = sanitize_key((string) $field['key']);
        $by_key[$key] = $field;
    }

    foreach (rm_form_custom_required_field_keys() as $required_key) {
        if (isset($by_key[$required_key])) {
            $by_key[$required_key]['required'] = true;
            continue;
        }
        if (isset($core_defs[$required_key])) {
            $field = $core_defs[$required_key];
            $field['required'] = true;
            $by_key[$required_key] = $field;
        }
    }

    return array_values($by_key);
}

/**
 * @param list<array<string, mixed>> $fields
 * @param array<string, array<string, mixed>> $core_defs
 * @return list<array<string, mixed>>
 */
function rm_form_normalize_fields(array $fields, array $core_defs): array
{
    $normalized = [];

    foreach ($fields as $field) {
        if (!is_array($field) || empty($field['key'])) {
            continue;
        }

        $key = sanitize_key((string) $field['key']);
        $base = $core_defs[$key] ?? [
            'key'      => $key,
            'type'     => 'text',
            'label'    => ucwords(str_replace('_', ' ', $key)),
            'required' => false,
            'source'   => 'custom',
            'order'    => 99,
        ];

        $merged = array_replace($base, $field);
        $merged['key'] = $key;
        $merged['type'] = sanitize_key((string) ($merged['type'] ?? 'text'));
        $merged['label'] = sanitize_text_field((string) ($merged['label'] ?? $key));
        $merged['required'] = !empty($merged['required']);
        $merged['source'] = ($merged['source'] ?? 'custom') === 'core' ? 'core' : 'custom';

        $placeholder = sanitize_text_field((string) ($merged['placeholder'] ?? ''));
        if ($placeholder === '') {
            $placeholder = $merged['source'] === 'custom'
                ? $merged['label']
                : sanitize_text_field((string) ($base['placeholder'] ?? $merged['label']));
        }
        $merged['placeholder'] = $placeholder;

        if ($merged['source'] === 'core' && !empty($base['maps_to'])) {
            $merged['maps_to'] = $base['maps_to'];
        } elseif (!empty($merged['maps_to'])) {
            $merged['maps_to'] = sanitize_key((string) $merged['maps_to']);
        }

        if (!empty($merged['options']) && is_array($merged['options'])) {
            $merged['options'] = rm_form_normalize_options($merged['options']);
        }

        $normalized[] = $merged;
    }

    return $normalized;
}

/**
 * @param list<mixed> $options
 * @return list<array{value: string, label: string}>
 */
function rm_form_normalize_options(array $options): array
{
    $normalized = [];

    foreach ($options as $option) {
        if (is_string($option)) {
            $normalized[] = ['value' => $option, 'label' => $option];
            continue;
        }

        if (!is_array($option)) {
            continue;
        }

        $value = isset($option['value']) ? (string) $option['value'] : '';
        if ($value === '') {
            continue;
        }

        $normalized[] = [
            'value' => $value,
            'label' => isset($option['label']) ? (string) $option['label'] : $value,
        ];
    }

    return $normalized;
}

/**
 * @param array{fields: list<array<string, mixed>>} $schema
 * @param array<string, mixed> $responses
 * @return array<string, string>
 */
function rm_validate_form_responses(array $schema, array $responses): array
{
    $errors = [];
    $allowed_keys = [];

    foreach ($schema['fields'] as $field) {
        $key = (string) ($field['key'] ?? '');
        if ($key === '') {
            continue;
        }

        $allowed_keys[$key] = true;
        $value = $responses[$key] ?? null;
        $field_errors = rm_validate_form_field_value($field, $value);
        foreach ($field_errors as $field_key => $message) {
            $errors[$field_key] = $message;
        }
    }

    foreach (array_keys($responses) as $response_key) {
        if (!is_string($response_key) || isset($allowed_keys[$response_key])) {
            continue;
        }

        $errors[$response_key] = 'Unknown field.';
    }

    return $errors;
}

/**
 * @return array<string, string>
 */
function rm_validate_form_field_value(array $field, mixed $value): array
{
    $key = (string) ($field['key'] ?? '');
    $type = (string) ($field['type'] ?? 'text');
    $required = !empty($field['required']);
    $errors = [];

    if ($type === 'checkbox') {
        if ($required && empty($value)) {
            $errors[$key] = ($field['label'] ?? $key) . ' is required.';
        }

        return $errors;
    }

    if ($type === 'checkbox_group') {
        if ($required && (!is_array($value) || $value === [])) {
            $errors[$key] = ($field['label'] ?? $key) . ' is required.';
        } elseif (is_array($value)) {
            $allowed = rm_form_option_values($field);
            foreach ($value as $item) {
                if (!in_array((string) $item, $allowed, true)) {
                    $errors[$key] = 'Invalid selection for ' . ($field['label'] ?? $key) . '.';
                    break;
                }
            }
        }

        return $errors;
    }

    $string_value = is_scalar($value) ? trim((string) $value) : '';

    if ($required && $string_value === '') {
        $errors[$key] = ($field['label'] ?? $key) . ' is required.';

        return $errors;
    }

    if ($string_value === '') {
        return $errors;
    }

    switch ($type) {
        case 'email':
            if (!is_email($string_value)) {
                $errors[$key] = 'Please enter a valid email address.';
            }
            break;

        case 'number':
            if (!is_numeric($string_value)) {
                $errors[$key] = ($field['label'] ?? $key) . ' must be a number.';
                break;
            }
            $num = (float) $string_value;
            $validation = is_array($field['validation'] ?? null) ? $field['validation'] : [];
            if (isset($validation['min']) && is_numeric($validation['min']) && $num < (float) $validation['min']) {
                $errors[$key] = ($field['label'] ?? $key) . ' must be at least ' . $validation['min'] . '.';
            }
            if (isset($validation['max']) && is_numeric($validation['max']) && $num > (float) $validation['max']) {
                $errors[$key] = ($field['label'] ?? $key) . ' must be at most ' . $validation['max'] . '.';
            }
            break;

        case 'select':
        case 'radio':
            $allowed = rm_form_option_values($field);
            if (!in_array($string_value, $allowed, true)) {
                $errors[$key] = 'Invalid selection for ' . ($field['label'] ?? $key) . '.';
            }
            break;

        case 'date':
            $parsed = strtotime($string_value);
            if ($parsed === false) {
                $errors[$key] = 'Please enter a valid date.';
            }
            break;

        case 'phone':
        case 'text':
        case 'textarea':
            $validation = is_array($field['validation'] ?? null) ? $field['validation'] : [];
            if (!empty($validation['maxLength']) && strlen($string_value) > (int) $validation['maxLength']) {
                $errors[$key] = ($field['label'] ?? $key) . ' is too long.';
            }
            if (!empty($validation['pattern']) && preg_match('/' . $validation['pattern'] . '/', $string_value) !== 1) {
                $errors[$key] = ($field['label'] ?? $key) . ' format is invalid.';
            }
            break;
    }

    return $errors;
}

/**
 * @return list<string>
 */
function rm_form_option_values(array $field): array
{
    $options = is_array($field['options'] ?? null) ? $field['options'] : [];
    $values = [];

    foreach ($options as $option) {
        if (is_array($option) && isset($option['value'])) {
            $values[] = (string) $option['value'];
        } elseif (is_string($option)) {
            $values[] = $option;
        }
    }

    return $values;
}

/**
 * @param array{fields: list<array<string, mixed>>} $schema
 * @param array<string, mixed> $responses
 * @return array{core: array<string, string|null>, custom: array<string, mixed>}
 */
function rm_split_responses(array $schema, array $responses): array
{
    $core_columns = [
        'nric', 'title', 'christian_name', 'given_name', 'family_name', 'certificate_name',
        'email', 'contact', 'address1', 'address2', 'postcode', 'church_name',
    ];
    $core = array_fill_keys($core_columns, null);
    $custom = [];

    foreach ($schema['fields'] as $field) {
        $key = (string) ($field['key'] ?? '');
        if ($key === '' || !array_key_exists($key, $responses)) {
            continue;
        }

        $value = rm_form_sanitize_response_value($field, $responses[$key]);
        if (rm_form_value_is_empty($value)) {
            continue;
        }

        if (($field['source'] ?? 'custom') === 'core' && !empty($field['maps_to'])) {
            $maps_to = (string) $field['maps_to'];
            if (array_key_exists($maps_to, $core)) {
                $core[$maps_to] = is_scalar($value) ? (string) $value : wp_json_encode($value);
            }
            continue;
        }

        $custom[$key] = $value;
    }

    return [
        'core'   => $core,
        'custom' => $custom,
    ];
}

function rm_form_value_is_empty(mixed $value): bool
{
    if ($value === null) {
        return true;
    }

    if (is_array($value)) {
        return $value === [];
    }

    if (is_bool($value)) {
        return false;
    }

    return trim((string) $value) === '';
}

/**
 * @param array<string, mixed> $field
 */
function rm_form_sanitize_response_value(array $field, mixed $value): mixed
{
    $type = (string) ($field['type'] ?? 'text');

    if ($type === 'checkbox') {
        return !empty($value);
    }

    if ($type === 'checkbox_group') {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn ($item): string => sanitize_text_field((string) $item),
            $value
        ));
    }

    if ($type === 'email') {
        return sanitize_email((string) $value);
    }

    if ($type === 'number') {
        return is_numeric($value) ? (string) $value : '';
    }

    if ($type === 'textarea') {
        return sanitize_textarea_field((string) $value);
    }

    return sanitize_text_field((string) $value);
}

/**
 * @param array{fields: list<array<string, mixed>>} $schema
 * @return array<string, string>
 */
function rm_form_empty_responses(array $schema): array
{
    $responses = [];

    foreach ($schema['fields'] as $field) {
        $key = (string) ($field['key'] ?? '');
        if ($key === '') {
            continue;
        }

        $type = (string) ($field['type'] ?? 'text');
        if ($type === 'checkbox') {
            $responses[$key] = false;
        } elseif ($type === 'checkbox_group') {
            $responses[$key] = [];
        } else {
            $responses[$key] = '';
        }
    }

    return $responses;
}

/**
 * @param array{fields: list<array<string, mixed>>} $schema
 * @param string $prefix
 * @param string $coverage local|international
 * @return array<string, string>
 */
function rm_form_responses_from_post(array $schema, string $prefix = '', string $coverage = RM_EVENT_COVERAGE_LOCAL): array
{
    $responses = rm_form_empty_responses($schema);
    $prefix = $prefix !== '' ? $prefix : '';
    $coverage = in_array($coverage, rm_event_coverage_options(), true)
        ? $coverage
        : RM_EVENT_COVERAGE_LOCAL;
    $allowed_dials = array_values(array_unique(array_map(
        static fn (array $country): string => (string) $country['dial'],
        rm_phone_country_codes()
    )));

    foreach ($schema['fields'] as $field) {
        $key = (string) ($field['key'] ?? '');
        if ($key === '') {
            continue;
        }

        $post_key = $prefix . $key;
        $type = (string) ($field['type'] ?? 'text');

        if ($type === 'checkbox') {
            $responses[$key] = isset($_POST[$post_key]) && $_POST[$post_key] !== '';
            continue;
        }

        if ($type === 'checkbox_group') {
            $raw = $_POST[$post_key] ?? [];
            $responses[$key] = is_array($raw)
                ? array_map(static fn ($v): string => sanitize_text_field(wp_unslash((string) $v)), $raw)
                : [];
            continue;
        }

        if ($type === 'phone') {
            $dial_key = $post_key . '_dial';
            $local_key = $post_key . '_local';

            if (isset($_POST[$local_key]) || isset($_POST[$dial_key])) {
                if ($coverage === RM_EVENT_COVERAGE_LOCAL) {
                    $dial = '+65';
                } else {
                    $dial = isset($_POST[$dial_key])
                        ? sanitize_text_field(wp_unslash((string) $_POST[$dial_key]))
                        : '+65';
                    if (!in_array($dial, $allowed_dials, true)) {
                        $dial = '+65';
                    }
                }
                $local = isset($_POST[$local_key])
                    ? sanitize_text_field(wp_unslash((string) $_POST[$local_key]))
                    : '';
                $responses[$key] = rm_compose_phone_number($dial, $local);
                continue;
            }

            if (isset($_POST[$post_key])) {
                $responses[$key] = sanitize_text_field(wp_unslash((string) $_POST[$post_key]));
            }
            continue;
        }

        if (!isset($_POST[$post_key])) {
            continue;
        }

        $raw = wp_unslash((string) $_POST[$post_key]);
        $responses[$key] = $type === 'email' ? sanitize_email($raw) : sanitize_text_field($raw);
    }

    return $responses;
}

/**
 * Parse members JSON from POST (v2 group / individual submit).
 *
 * @return list<array<string, mixed>>
 */
function rm_parse_members_from_post(): array
{
    if (!isset($_POST['members_json'])) {
        return [];
    }

    $raw = wp_unslash((string) $_POST['members_json']);
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $members = [];
    foreach ($decoded as $member) {
        if (is_array($member)) {
            $members[] = $member;
        }
    }

    return $members;
}

/**
 * Parse guests JSON from POST (v2 registration with guest addons).
 *
 * @return list<array<string, mixed>>
 */
function rm_parse_guests_from_post(): array
{
    if (!isset($_POST['guests_json'])) {
        return [];
    }

    $raw = wp_unslash((string) $_POST['guests_json']);
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $guests = [];
    foreach ($decoded as $guest) {
        if (is_array($guest)) {
            $guests[] = $guest;
        }
    }

    return $guests;
}
