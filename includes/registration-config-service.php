<?php

const RM_REGISTRATION_VERSION = 2;

const RM_REGISTRATION_MODE_INDIVIDUAL = 'individual';
const RM_REGISTRATION_MODE_GROUP_FLAT = 'group_flat';
const RM_REGISTRATION_MODE_GROUP_PER_HEAD = 'group_per_head';

const RM_FORM_PRESET_MINIMAL = 'minimal';
const RM_FORM_PRESET_STANDARD = 'standard';
const RM_FORM_PRESET_FULL = 'full';
const RM_FORM_PRESET_CUSTOM = 'custom';

const RM_CURRENCY_SGD = 'SGD';
const RM_CURRENCY_USD = 'USD';
const RM_CURRENCY_RMB = 'RMB';
const RM_CURRENCY_AUD = 'AUD';

const RM_EVENT_COVERAGE_LOCAL = 'local';
const RM_EVENT_COVERAGE_INTERNATIONAL = 'international';

/**
 * @return list<string>
 */
function rm_registration_modes(): array
{
    return [
        RM_REGISTRATION_MODE_INDIVIDUAL,
        RM_REGISTRATION_MODE_GROUP_FLAT,
        RM_REGISTRATION_MODE_GROUP_PER_HEAD,
    ];
}

/**
 * @return list<string>
 */
function rm_event_coverage_options(): array
{
    return [
        RM_EVENT_COVERAGE_LOCAL,
        RM_EVENT_COVERAGE_INTERNATIONAL,
    ];
}

/**
 * @return list<string>
 */
function rm_form_presets(): array
{
    return [
        RM_FORM_PRESET_MINIMAL,
        RM_FORM_PRESET_STANDARD,
        RM_FORM_PRESET_FULL,
        RM_FORM_PRESET_CUSTOM,
    ];
}

/**
 * Supported event payment currencies (staff-facing codes).
 *
 * @return list<string>
 */
function rm_registration_currencies(): array
{
    return [
        RM_CURRENCY_SGD,
        RM_CURRENCY_USD,
        RM_CURRENCY_RMB,
        RM_CURRENCY_AUD,
    ];
}

/**
 * Map a staff-facing currency code to the ISO code HitPay expects.
 */
function rm_registration_currency_for_hitpay(string $currency): string
{
    $currency = strtoupper(sanitize_key($currency));

    // HitPay / ISO 4217 use CNY for Chinese Yuan (RMB).
    if ($currency === RM_CURRENCY_RMB) {
        return 'CNY';
    }

    if (in_array($currency, rm_registration_currencies(), true)) {
        return $currency;
    }

    return RM_CURRENCY_SGD;
}

/**
 * @param array<string, mixed> $event
 */
function rm_registration_currency(array $event): string
{
    $config = rm_parse_registration_config($event);
    $currency = strtoupper(sanitize_key((string) ($config['pricing']['currency'] ?? RM_CURRENCY_SGD)));

    return in_array($currency, rm_registration_currencies(), true)
        ? $currency
        : RM_CURRENCY_SGD;
}

/**
 * Format a monetary amount with the event's configured currency.
 *
 * Returns e.g. "SGD 120.00", "USD 50", or "FREE".
 */
function rm_format_currency(float $amount, string $currency = 'SGD', bool $show_free = true): string
{
    if ($amount <= 0 && $show_free) {
        return 'FREE';
    }

    $decimals = floor($amount) === $amount ? 0 : 2;

    return $currency . ' ' . number_format_i18n($amount, $decimals);
}

/**
 * @param array<string, mixed> $event
 * @return array<string, mixed>
 */
function rm_decode_event_settings(array $event): array
{
    $raw = $event['settings'] ?? null;
    if (is_array($raw)) {
        return $raw;
    }

    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * @param array<string, mixed> $event
 */
function rm_event_uses_v2_registration(array $event): bool
{
    $settings = rm_decode_event_settings($event);
    $registration = isset($settings['registration']) && is_array($settings['registration'])
        ? $settings['registration']
        : [];

    if (isset($registration['version']) && (int) $registration['version'] === RM_REGISTRATION_VERSION) {
        return true;
    }

    if (!empty($registration['mode']) && is_string($registration['mode'])) {
        return in_array($registration['mode'], rm_registration_modes(), true);
    }

    return false;
}

/**
 * @return array<string, mixed>
 */
function rm_registration_config_defaults(): array
{
    return [
        'version'  => RM_REGISTRATION_VERSION,
        'mode'     => RM_REGISTRATION_MODE_INDIVIDUAL,
        'coverage' => RM_EVENT_COVERAGE_LOCAL,
        'form'     => [
            'preset' => RM_FORM_PRESET_FULL,
            'fields' => [],
            'scope'  => 'per_member',
        ],
        'group'    => [
            'min' => 1,
            'max' => 1,
        ],
        'pricing'  => [
            'model'      => 'flat',
            'base_price' => null,
            'currency'   => RM_CURRENCY_SGD,
            'slots'      => [],
        ],
        'guests'   => [
            'enabled'        => false,
            'label_singular' => 'Guest',
            'label_plural'   => 'Guests',
            'min'            => 0,
            'max'            => 0,
            'event_max'      => 0,
            'price'          => 0,
            'form'           => [
                'fields' => [],
            ],
        ],
    ];
}

/**
 * Common international dial codes for contact number prepend.
 *
 * @return list<array{code: string, label: string, dial: string}>
 */
function rm_phone_country_codes(): array
{
    return [
        ['code' => 'SG', 'label' => 'Singapore', 'dial' => '+65'],
        ['code' => 'MY', 'label' => 'Malaysia', 'dial' => '+60'],
        ['code' => 'ID', 'label' => 'Indonesia', 'dial' => '+62'],
        ['code' => 'PH', 'label' => 'Philippines', 'dial' => '+63'],
        ['code' => 'TH', 'label' => 'Thailand', 'dial' => '+66'],
        ['code' => 'VN', 'label' => 'Vietnam', 'dial' => '+84'],
        ['code' => 'BN', 'label' => 'Brunei', 'dial' => '+673'],
        ['code' => 'HK', 'label' => 'Hong Kong', 'dial' => '+852'],
        ['code' => 'MO', 'label' => 'Macau', 'dial' => '+853'],
        ['code' => 'TW', 'label' => 'Taiwan', 'dial' => '+886'],
        ['code' => 'CN', 'label' => 'China', 'dial' => '+86'],
        ['code' => 'JP', 'label' => 'Japan', 'dial' => '+81'],
        ['code' => 'KR', 'label' => 'South Korea', 'dial' => '+82'],
        ['code' => 'IN', 'label' => 'India', 'dial' => '+91'],
        ['code' => 'AU', 'label' => 'Australia', 'dial' => '+61'],
        ['code' => 'NZ', 'label' => 'New Zealand', 'dial' => '+64'],
        ['code' => 'GB', 'label' => 'United Kingdom', 'dial' => '+44'],
        ['code' => 'US', 'label' => 'United States / Canada', 'dial' => '+1'],
        ['code' => 'DE', 'label' => 'Germany', 'dial' => '+49'],
        ['code' => 'FR', 'label' => 'France', 'dial' => '+33'],
        ['code' => 'NL', 'label' => 'Netherlands', 'dial' => '+31'],
        ['code' => 'CH', 'label' => 'Switzerland', 'dial' => '+41'],
        ['code' => 'AE', 'label' => 'United Arab Emirates', 'dial' => '+971'],
        ['code' => 'SA', 'label' => 'Saudi Arabia', 'dial' => '+966'],
        ['code' => 'ZA', 'label' => 'South Africa', 'dial' => '+27'],
    ];
}

/**
 * Compose a full contact number from dial code + local digits.
 */
function rm_compose_phone_number(string $dial_code, string $local_number): string
{
    $dial = trim($dial_code);
    if ($dial === '') {
        $dial = '+65';
    }
    if ($dial[0] !== '+') {
        $dial = '+' . ltrim($dial, '+');
    }

    $local = preg_replace('/\D+/', '', $local_number) ?? '';
    $local = ltrim($local, '0');

    $dial_digits = ltrim($dial, '+');
    if ($local !== '' && str_starts_with($local, $dial_digits)) {
        $local = substr($local, strlen($dial_digits)) ?: '';
    }

    if ($local === '') {
        return '';
    }

    return $dial . $local;
}

/**
 * Split a stored contact number into dial code + local digits for form display.
 *
 * @return array{dial: string, local: string}
 */
function rm_split_phone_number(string $full_number, string $coverage = RM_EVENT_COVERAGE_LOCAL): array
{
    $full = trim($full_number);
    $default_dial = '+65';

    if ($coverage === RM_EVENT_COVERAGE_LOCAL) {
        $digits = preg_replace('/\D+/', '', $full) ?? '';
        if (str_starts_with($digits, '65') && strlen($digits) > 8) {
            $digits = substr($digits, 2);
        }

        return [
            'dial'  => $default_dial,
            'local' => $digits,
        ];
    }

    if ($full === '') {
        return [
            'dial'  => $default_dial,
            'local' => '',
        ];
    }

    $normalized = $full;
    if ($normalized[0] !== '+') {
        $normalized = '+' . ltrim($normalized, '+');
    }

    $codes = rm_phone_country_codes();
    usort($codes, static function (array $a, array $b): int {
        return strlen($b['dial']) <=> strlen($a['dial']);
    });

    foreach ($codes as $country) {
        $dial = (string) $country['dial'];
        if (str_starts_with($normalized, $dial)) {
            $local = substr($normalized, strlen($dial));
            $local = preg_replace('/\D+/', '', (string) $local) ?? '';

            return [
                'dial'  => $dial,
                'local' => $local,
            ];
        }
    }

    $digits = preg_replace('/\D+/', '', $full) ?? '';

    return [
        'dial'  => $default_dial,
        'local' => $digits,
    ];
}

/**
 * @param array<string, mixed> $config
 */
function rm_registration_coverage(array $config): string
{
    $coverage = sanitize_key((string) ($config['coverage'] ?? RM_EVENT_COVERAGE_LOCAL));

    return in_array($coverage, rm_event_coverage_options(), true)
        ? $coverage
        : RM_EVENT_COVERAGE_LOCAL;
}

/**
 * @param array<string, mixed> $event
 * @return array<string, mixed>
 */
function rm_parse_registration_config(array $event): array
{
    $defaults = rm_registration_config_defaults();
    $settings = rm_decode_event_settings($event);
    $registration = isset($settings['registration']) && is_array($settings['registration'])
        ? $settings['registration']
        : [];

    $config = array_replace_recursive($defaults, $registration);

    if (!in_array($config['mode'], rm_registration_modes(), true)) {
        $config['mode'] = RM_REGISTRATION_MODE_INDIVIDUAL;
    }

    $coverage = sanitize_key((string) ($config['coverage'] ?? RM_EVENT_COVERAGE_LOCAL));
    $config['coverage'] = in_array($coverage, rm_event_coverage_options(), true)
        ? $coverage
        : RM_EVENT_COVERAGE_LOCAL;

    if (!in_array($config['form']['preset'], rm_form_presets(), true)) {
        $config['form']['preset'] = RM_FORM_PRESET_FULL;
    }

    if (!is_array($config['form']['fields'])) {
        $config['form']['fields'] = [];
    }

    $config['group']['min'] = max(1, (int) ($config['group']['min'] ?? 1));
    $config['group']['max'] = max($config['group']['min'], (int) ($config['group']['max'] ?? $config['group']['min']));

    if ($config['mode'] === RM_REGISTRATION_MODE_INDIVIDUAL) {
        $config['group']['min'] = 1;
        $config['group']['max'] = 1;
    }

    if (!isset($config['pricing']['model']) || !is_string($config['pricing']['model'])) {
        $config['pricing']['model'] = $config['mode'] === RM_REGISTRATION_MODE_GROUP_PER_HEAD
            ? 'package_slots'
            : 'flat';
    }

    $currency = strtoupper(sanitize_key((string) ($config['pricing']['currency'] ?? RM_CURRENCY_SGD)));
    $config['pricing']['currency'] = in_array($currency, rm_registration_currencies(), true)
        ? $currency
        : RM_CURRENCY_SGD;

    if (!isset($config['guests']) || !is_array($config['guests'])) {
        $config['guests'] = $defaults['guests'];
    }
    $config['guests']['enabled'] = !empty($config['guests']['enabled']);
    $config['guests']['label_singular'] = trim((string) ($config['guests']['label_singular'] ?? 'Guest'));
    $config['guests']['label_plural'] = trim((string) ($config['guests']['label_plural'] ?? 'Guests'));
    if ($config['guests']['label_singular'] === '') {
        $config['guests']['label_singular'] = 'Guest';
    }
    if ($config['guests']['label_plural'] === '') {
        $config['guests']['label_plural'] = 'Guests';
    }
    $config['guests']['min'] = max(0, (int) ($config['guests']['min'] ?? 0));
    $config['guests']['max'] = max($config['guests']['min'], (int) ($config['guests']['max'] ?? 0));
    $config['guests']['event_max'] = max(0, (int) ($config['guests']['event_max'] ?? 0));
    $config['guests']['price'] = max(0, (float) ($config['guests']['price'] ?? 0));
    if (!isset($config['guests']['form']) || !is_array($config['guests']['form'])) {
        $config['guests']['form'] = ['fields' => []];
    }
    if (!isset($config['guests']['form']['fields']) || !is_array($config['guests']['form']['fields'])) {
        $config['guests']['form']['fields'] = [];
    }

    return $config;
}

/**
 * @param array<string, mixed> $event
 */
function rm_registration_is_group_mode(array $event): bool
{
    $config = rm_parse_registration_config($event);

    return in_array($config['mode'], [RM_REGISTRATION_MODE_GROUP_FLAT, RM_REGISTRATION_MODE_GROUP_PER_HEAD], true);
}

/**
 * @param array<string, mixed> $event
 * @return array{min: int, max: int}
 */
function rm_registration_group_limits(array $event): array
{
    $config = rm_parse_registration_config($event);

    return [
        'min' => (int) $config['group']['min'],
        'max' => (int) $config['group']['max'],
    ];
}

/**
 * Human-readable labels for the event profile settings panel.
 *
 * @param array<string, mixed> $config
 * @return array<string, mixed>
 */
function rm_present_registration_config(array $config): array
{
    $mode = (string) ($config['mode'] ?? RM_REGISTRATION_MODE_INDIVIDUAL);
    $mode_labels = [
        RM_REGISTRATION_MODE_INDIVIDUAL     => 'Individual',
        RM_REGISTRATION_MODE_GROUP_FLAT     => 'Group (flat package)',
        RM_REGISTRATION_MODE_GROUP_PER_HEAD => 'Group (per-head tiers)',
    ];

    $preset = (string) ($config['form']['preset'] ?? RM_FORM_PRESET_FULL);
    $preset_labels = [
        RM_FORM_PRESET_MINIMAL  => 'Minimal',
        RM_FORM_PRESET_STANDARD => 'Standard',
        RM_FORM_PRESET_FULL     => 'Full',
        RM_FORM_PRESET_CUSTOM   => 'Custom',
    ];

    $base_price = $config['pricing']['base_price'] ?? null;
    $currency = strtoupper(sanitize_key((string) ($config['pricing']['currency'] ?? RM_CURRENCY_SGD)));
    if (!in_array($currency, rm_registration_currencies(), true)) {
        $currency = RM_CURRENCY_SGD;
    }
    $base_price_display = $base_price !== null && $base_price !== ''
        ? $currency . ' ' . number_format_i18n((float) $base_price, 2)
        : 'Event default price';

    $custom_fields = isset($config['form']['fields']) && is_array($config['form']['fields'])
        ? count($config['form']['fields'])
        : 0;

    $guests = isset($config['guests']) && is_array($config['guests']) ? $config['guests'] : [];
    $guests_enabled = !empty($guests['enabled']);
    $coverage = rm_registration_coverage($config);
    $coverage_labels = [
        RM_EVENT_COVERAGE_LOCAL         => 'Local Event (Singapore Only)',
        RM_EVENT_COVERAGE_INTERNATIONAL => 'International',
    ];

    return [
        'mode'               => $mode,
        'mode_label'         => $mode_labels[$mode] ?? $mode,
        'coverage'           => $coverage,
        'coverage_label'     => $coverage_labels[$coverage] ?? $coverage,
        'preset'             => $preset,
        'preset_label'       => $preset_labels[$preset] ?? $preset,
        'group_min'          => (int) ($config['group']['min'] ?? 1),
        'group_max'          => (int) ($config['group']['max'] ?? 1),
        'pricing_model'      => (string) ($config['pricing']['model'] ?? 'flat'),
        'currency'           => $currency,
        'base_price'         => $base_price !== null && $base_price !== '' ? (float) $base_price : null,
        'base_price_display' => $base_price_display,
        'custom_field_count' => $custom_fields,
        'guests_enabled'     => $guests_enabled,
        'guest_label'        => $guests_enabled ? trim((string) ($guests['label_plural'] ?? 'Guests')) : '',
        'guest_max'          => (int) ($guests['max'] ?? 0),
        'guest_event_max'    => (int) ($guests['event_max'] ?? 0),
        'guest_price'        => (float) ($guests['price'] ?? 0),
    ];
}

/**
 * Build a sanitized registration config block from staff form input.
 *
 * @param array<string, mixed> $input
 * @param array<string, mixed> $existing_registration
 * @return array{ok: bool, error: string, registration: array<string, mixed>}
 */
function rm_normalize_registration_settings_input(array $input, array $existing_registration = []): array
{
    $defaults = rm_registration_config_defaults();
    $existing = array_replace_recursive($defaults, $existing_registration);

    $mode = isset($input['mode']) ? sanitize_key((string) $input['mode']) : (string) $existing['mode'];
    if (!in_array($mode, rm_registration_modes(), true)) {
        return [
            'ok'           => false,
            'error'        => 'Invalid registration mode.',
            'registration' => [],
        ];
    }

    $coverage = isset($input['coverage'])
        ? sanitize_key((string) $input['coverage'])
        : sanitize_key((string) ($existing['coverage'] ?? RM_EVENT_COVERAGE_LOCAL));
    if (!in_array($coverage, rm_event_coverage_options(), true)) {
        return [
            'ok'           => false,
            'error'        => 'Invalid event coverage.',
            'registration' => [],
        ];
    }

    $preset = isset($input['form_preset'])
        ? sanitize_key((string) $input['form_preset'])
        : (string) ($existing['form']['preset'] ?? RM_FORM_PRESET_FULL);
    if (!in_array($preset, rm_form_presets(), true)) {
        return [
            'ok'           => false,
            'error'        => 'Invalid form preset.',
            'registration' => [],
        ];
    }

    $group_min = isset($input['group_min']) ? absint($input['group_min']) : (int) $existing['group']['min'];
    $group_max = isset($input['group_max']) ? absint($input['group_max']) : (int) $existing['group']['max'];
    $group_min = max(1, $group_min);
    $group_max = max($group_min, $group_max);

    if ($mode === RM_REGISTRATION_MODE_INDIVIDUAL) {
        $group_min = 1;
        $group_max = 1;
    }

    $pricing_model = isset($input['pricing_model'])
        ? sanitize_key((string) $input['pricing_model'])
        : (string) ($existing['pricing']['model'] ?? 'flat');
    if (!in_array($pricing_model, ['flat', 'package_slots'], true)) {
        $pricing_model = $mode === RM_REGISTRATION_MODE_GROUP_PER_HEAD ? 'package_slots' : 'flat';
    }

    $currency = isset($input['currency'])
        ? strtoupper(sanitize_key((string) $input['currency']))
        : strtoupper(sanitize_key((string) ($existing['pricing']['currency'] ?? RM_CURRENCY_SGD)));
    if (!in_array($currency, rm_registration_currencies(), true)) {
        return [
            'ok'           => false,
            'error'        => 'Invalid currency.',
            'registration' => [],
        ];
    }

    $base_price = null;
    if (isset($input['base_price']) && trim((string) $input['base_price']) !== '') {
        $base_price = (float) $input['base_price'];
        if ($base_price < 0) {
            return [
                'ok'           => false,
                'error'        => 'Base price cannot be negative.',
                'registration' => [],
            ];
        }
    }

    $existing_fields = isset($existing['form']['fields']) && is_array($existing['form']['fields'])
        ? $existing['form']['fields']
        : [];
    $existing_slots = isset($existing['pricing']['slots']) && is_array($existing['pricing']['slots'])
        ? $existing['pricing']['slots']
        : [];
    $existing_scope = isset($existing['form']['scope']) && is_string($existing['form']['scope'])
        ? $existing['form']['scope']
        : 'per_member';

    if ($preset === RM_FORM_PRESET_CUSTOM) {
        $core_defs = rm_form_core_field_definitions();
        $selected_keys = [];

        if (isset($input['form_fields']) && is_array($input['form_fields'])) {
            foreach ($input['form_fields'] as $field_key) {
                $selected_keys[] = sanitize_key((string) $field_key);
            }
        } else {
            foreach ($existing_fields as $existing_field) {
                if (!is_array($existing_field) || empty($existing_field['key'])) {
                    continue;
                }
                $existing_key = sanitize_key((string) $existing_field['key']);
                if (isset($core_defs[$existing_key])) {
                    $selected_keys[] = $existing_key;
                }
            }
        }

        $core_fields = rm_form_build_fields_from_keys($selected_keys);

        if (!empty($input['custom_fields_submitted'])) {
            $admin_custom_fields = rm_form_normalize_admin_custom_fields_input($input['custom_fields'] ?? []);
        } else {
            $existing_custom_rows = [];
            foreach ($existing_fields as $existing_field) {
                if (!is_array($existing_field) || empty($existing_field['key'])) {
                    continue;
                }
                $existing_key = sanitize_key((string) $existing_field['key']);
                if (isset($core_defs[$existing_key])) {
                    continue;
                }

                $options_text = '';
                if (!empty($existing_field['options']) && is_array($existing_field['options'])) {
                    $labels = [];
                    foreach ($existing_field['options'] as $option) {
                        if (is_array($option)) {
                            $labels[] = (string) ($option['label'] ?? $option['value'] ?? '');
                        } else {
                            $labels[] = (string) $option;
                        }
                    }
                    $options_text = implode("\n", array_values(array_filter($labels)));
                }

                $existing_custom_rows[] = [
                    'key'         => $existing_key,
                    'label'       => $existing_field['label'] ?? $existing_key,
                    'type'        => $existing_field['type'] ?? 'text',
                    'required'    => !empty($existing_field['required']) ? '1' : '',
                    'optionsText' => $options_text,
                    'placeholder' => $existing_field['placeholder'] ?? '',
                ];
            }
            $admin_custom_fields = rm_form_normalize_admin_custom_fields_input($existing_custom_rows);
        }

        $form_fields = array_merge($core_fields, $admin_custom_fields);
    } else {
        $form_fields = [];
    }

    $existing_guests = isset($existing['guests']) && is_array($existing['guests'])
        ? $existing['guests']
        : rm_registration_config_defaults()['guests'];

    $guests_enabled_raw = $input['guests_enabled'] ?? null;
    if ($guests_enabled_raw === null) {
        $guests_enabled = !empty($existing_guests['enabled']);
    } else {
        $guests_enabled = $guests_enabled_raw === true
            || $guests_enabled_raw === 1
            || $guests_enabled_raw === '1'
            || $guests_enabled_raw === 'true';
    }

    $guest_label_singular = isset($input['guest_label_singular'])
        ? sanitize_text_field((string) $input['guest_label_singular'])
        : (string) ($existing_guests['label_singular'] ?? 'Guest');
    $guest_label_plural = isset($input['guest_label_plural'])
        ? sanitize_text_field((string) $input['guest_label_plural'])
        : (string) ($existing_guests['label_plural'] ?? 'Guests');
    if ($guest_label_singular === '') {
        $guest_label_singular = 'Guest';
    }
    if ($guest_label_plural === '') {
        $guest_label_plural = 'Guests';
    }

    $guest_min = isset($input['guest_min']) ? max(0, absint($input['guest_min'])) : (int) ($existing_guests['min'] ?? 0);
    $guest_max = isset($input['guest_max']) ? max($guest_min, absint($input['guest_max'])) : max($guest_min, (int) ($existing_guests['max'] ?? 0));
    $guest_event_max = isset($input['guest_event_max'])
        ? max(0, absint($input['guest_event_max']))
        : max(0, (int) ($existing_guests['event_max'] ?? 0));
    $guest_price = null;
    if (isset($input['guest_price']) && trim((string) $input['guest_price']) !== '') {
        $guest_price = (float) $input['guest_price'];
        if ($guest_price < 0) {
            return [
                'ok'           => false,
                'error'        => 'Guest price cannot be negative.',
                'registration' => [],
            ];
        }
    } else {
        $guest_price = (float) ($existing_guests['price'] ?? 0);
    }

    $existing_guest_fields = isset($existing_guests['form']['fields']) && is_array($existing_guests['form']['fields'])
        ? $existing_guests['form']['fields']
        : [];

    if (!empty($input['guest_fields_submitted'])) {
        $guest_form_fields = rm_form_normalize_admin_custom_fields_input($input['guest_fields'] ?? []);
    } else {
        $guest_custom_rows = [];
        foreach ($existing_guest_fields as $ef) {
            if (!is_array($ef) || empty($ef['key'])) {
                continue;
            }
            $options_text = '';
            if (!empty($ef['options']) && is_array($ef['options'])) {
                $labels = [];
                foreach ($ef['options'] as $option) {
                    if (is_array($option)) {
                        $labels[] = (string) ($option['label'] ?? $option['value'] ?? '');
                    } else {
                        $labels[] = (string) $option;
                    }
                }
                $options_text = implode("\n", array_values(array_filter($labels)));
            }
            $guest_custom_rows[] = [
                'key'         => sanitize_key((string) $ef['key']),
                'label'       => $ef['label'] ?? $ef['key'],
                'type'        => $ef['type'] ?? 'text',
                'required'    => !empty($ef['required']) ? '1' : '',
                'optionsText' => $options_text,
                'placeholder' => $ef['placeholder'] ?? '',
            ];
        }
        $guest_form_fields = rm_form_normalize_admin_custom_fields_input($guest_custom_rows);
    }

    if (!$guests_enabled) {
        $guest_min = 0;
        $guest_max = 0;
        $guest_event_max = 0;
    }

    $registration = $existing;
    $registration['version'] = RM_REGISTRATION_VERSION;
    $registration['mode'] = $mode;
    $registration['coverage'] = $coverage;
    $registration['form']['preset'] = $preset;
    $registration['form']['fields'] = $form_fields;
    $registration['form']['scope'] = $existing_scope;
    $registration['group']['min'] = $group_min;
    $registration['group']['max'] = $group_max;
    $registration['pricing']['model'] = $pricing_model;
    $registration['pricing']['currency'] = $currency;
    $registration['pricing']['base_price'] = $base_price;
    $registration['pricing']['slots'] = $existing_slots;
    $registration['guests'] = [
        'enabled'        => $guests_enabled,
        'label_singular' => $guest_label_singular,
        'label_plural'   => $guest_label_plural,
        'min'            => $guest_min,
        'max'            => $guest_max,
        'event_max'      => $guest_event_max,
        'price'          => $guest_price,
        'form'           => [
            'fields' => $guest_form_fields,
        ],
    ];

    return [
        'ok'           => true,
        'error'        => '',
        'registration' => $registration,
    ];
}

/**
 * Merge registration settings into event settings JSON (bss_events or CPT post meta).
 *
 * @param array<string, mixed> $registration
 * @return array{ok: bool, error: string}
 */
function rm_save_event_registration_settings(int $event_id, array $registration, string $source = ''): array
{
    if ($event_id < 1) {
        return [
            'ok'    => false,
            'error' => 'Invalid event id.',
        ];
    }

    $source = rm_normalize_event_source($source);

    if ($source === 'cpt') {
        $post = get_post($event_id);
        if (!$post instanceof WP_Post || $post->post_type !== 'event') {
            return [
                'ok'    => false,
                'error' => 'Event could not be found.',
            ];
        }

        $settings = [];
        $raw = get_post_meta($event_id, 'settings', true);
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        } elseif (is_array($raw)) {
            $settings = $raw;
        }

        $settings['registration'] = $registration;
        $encoded = wp_json_encode($settings);
        if (!is_string($encoded) || $encoded === '') {
            return [
                'ok'    => false,
                'error' => 'Failed to encode registration settings.',
            ];
        }

        update_post_meta($event_id, 'settings', $encoded);

        return [
            'ok'    => true,
            'error' => '',
        ];
    }

    global $wpdb;

    $row = $wpdb->get_row(
        $wpdb->prepare('SELECT `settings` FROM `bss_events` WHERE `id` = %d LIMIT 1', $event_id),
        ARRAY_A
    );

    if (!is_array($row)) {
        return [
            'ok'    => false,
            'error' => 'Event could not be found.',
        ];
    }

    $settings = [];
    $raw = $row['settings'] ?? '';
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $settings = $decoded;
        }
    } elseif (is_array($raw)) {
        $settings = $raw;
    }

    $settings['registration'] = $registration;
    $encoded = wp_json_encode($settings);
    if (!is_string($encoded) || $encoded === '') {
        return [
            'ok'    => false,
            'error' => 'Failed to encode registration settings.',
        ];
    }

    $updated = $wpdb->update(
        'bss_events',
        ['settings' => $encoded],
        ['id' => $event_id],
        ['%s'],
        ['%d']
    );

    if ($updated === false) {
        return [
            'ok'    => false,
            'error' => $wpdb->last_error !== '' ? $wpdb->last_error : 'Failed to save registration settings.',
        ];
    }

    return [
        'ok'    => true,
        'error' => '',
    ];
}
