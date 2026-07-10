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
            'required' => true, 'source' => 'core', 'maps_to' => 'title', 'order' => 2,
            'options' => array_map(
                static fn (string $value): array => ['value' => $value, 'label' => $value],
                rm_registration_title_options()
            ),
        ],
        'christian_name' => [
            'key' => 'christian_name', 'type' => 'text', 'label' => 'Christian name',
            'required' => true, 'source' => 'core', 'maps_to' => 'christian_name', 'order' => 3,
        ],
        'given_name' => [
            'key' => 'given_name', 'type' => 'text', 'label' => 'Given name',
            'placeholder' => 'e.g. James', 'required' => true, 'source' => 'core', 'maps_to' => 'given_name', 'order' => 4,
        ],
        'family_name' => [
            'key' => 'family_name', 'type' => 'text', 'label' => 'Family name',
            'required' => true, 'source' => 'core', 'maps_to' => 'family_name', 'order' => 5,
        ],
        'certificate_name' => [
            'key' => 'certificate_name', 'type' => 'text', 'label' => 'Certificate name',
            'required' => true, 'source' => 'core', 'maps_to' => 'certificate_name', 'order' => 6,
        ],
        'email' => [
            'key' => 'email', 'type' => 'email', 'label' => 'Email address',
            'required' => true, 'source' => 'core', 'maps_to' => 'email', 'order' => 7,
        ],
        'contact' => [
            'key' => 'contact', 'type' => 'phone', 'label' => 'Contact number',
            'required' => true, 'source' => 'core', 'maps_to' => 'contact', 'order' => 8,
        ],
        'address1' => [
            'key' => 'address1', 'type' => 'text', 'label' => 'Address 1',
            'required' => true, 'source' => 'core', 'maps_to' => 'address1', 'order' => 9,
        ],
        'address2' => [
            'key' => 'address2', 'type' => 'text', 'label' => 'Address 2',
            'required' => false, 'source' => 'core', 'maps_to' => 'address2', 'order' => 10,
        ],
        'postcode' => [
            'key' => 'postcode', 'type' => 'text', 'label' => 'Postal code',
            'required' => true, 'source' => 'core', 'maps_to' => 'postcode', 'order' => 11,
        ],
        'church_name' => [
            'key' => 'church_name', 'type' => 'text', 'label' => 'Church name',
            'required' => true, 'source' => 'core', 'maps_to' => 'church_name', 'order' => 12,
        ],
    ];
}

/**
 * @return list<string>
 */
function rm_form_preset_field_keys(string $preset): array
{
    return match ($preset) {
        RM_FORM_PRESET_MINIMAL => ['given_name', 'family_name', 'email', 'contact'],
        RM_FORM_PRESET_STANDARD => ['given_name', 'family_name', 'email', 'contact', 'title', 'nric'],
        default => [
            'given_name', 'family_name', 'email', 'contact', 'title', 'nric',
            'christian_name', 'certificate_name', 'address1', 'address2', 'postcode', 'church_name',
        ],
    };
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
    $custom_fields = is_array($form['fields']) ? $form['fields'] : [];
    $core_defs = rm_form_core_field_definitions();

    if ($custom_fields !== []) {
        $fields = rm_form_normalize_fields($custom_fields, $core_defs);
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
 * @return array<string, string>
 */
function rm_form_responses_from_post(array $schema, string $prefix = ''): array
{
    $responses = rm_form_empty_responses($schema);
    $prefix = $prefix !== '' ? $prefix : '';

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
