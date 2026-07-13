<?php

const RM_REGISTRATION_VERSION = 2;

const RM_REGISTRATION_MODE_INDIVIDUAL = 'individual';
const RM_REGISTRATION_MODE_GROUP_FLAT = 'group_flat';
const RM_REGISTRATION_MODE_GROUP_PER_HEAD = 'group_per_head';

const RM_FORM_PRESET_MINIMAL = 'minimal';
const RM_FORM_PRESET_STANDARD = 'standard';
const RM_FORM_PRESET_FULL = 'full';

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
function rm_form_presets(): array
{
    return [
        RM_FORM_PRESET_MINIMAL,
        RM_FORM_PRESET_STANDARD,
        RM_FORM_PRESET_FULL,
    ];
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
        'version' => RM_REGISTRATION_VERSION,
        'mode'    => RM_REGISTRATION_MODE_INDIVIDUAL,
        'form'    => [
            'preset' => RM_FORM_PRESET_FULL,
            'fields' => [],
            'scope'  => 'per_member',
        ],
        'group'   => [
            'min' => 1,
            'max' => 1,
        ],
        'pricing' => [
            'model'      => 'flat',
            'base_price' => null,
            'slots'      => [],
        ],
    ];
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
    ];

    $base_price = $config['pricing']['base_price'] ?? null;
    $base_price_display = $base_price !== null && $base_price !== ''
        ? '$' . number_format_i18n((float) $base_price, 2)
        : 'Event default price';

    $custom_fields = isset($config['form']['fields']) && is_array($config['form']['fields'])
        ? count($config['form']['fields'])
        : 0;

    return [
        'mode'               => $mode,
        'mode_label'         => $mode_labels[$mode] ?? $mode,
        'preset'             => $preset,
        'preset_label'       => $preset_labels[$preset] ?? $preset,
        'group_min'          => (int) ($config['group']['min'] ?? 1),
        'group_max'          => (int) ($config['group']['max'] ?? 1),
        'pricing_model'      => (string) ($config['pricing']['model'] ?? 'flat'),
        'base_price'         => $base_price !== null && $base_price !== '' ? (float) $base_price : null,
        'base_price_display' => $base_price_display,
        'custom_field_count' => $custom_fields,
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

    $registration = $existing;
    $registration['version'] = RM_REGISTRATION_VERSION;
    $registration['mode'] = $mode;
    $registration['form']['preset'] = $preset;
    $registration['form']['fields'] = $existing_fields;
    $registration['form']['scope'] = $existing_scope;
    $registration['group']['min'] = $group_min;
    $registration['group']['max'] = $group_max;
    $registration['pricing']['model'] = $pricing_model;
    $registration['pricing']['base_price'] = $base_price;
    $registration['pricing']['slots'] = $existing_slots;

    return [
        'ok'           => true,
        'error'        => '',
        'registration' => $registration,
    ];
}

/**
 * Merge registration settings into bss_events.settings JSON.
 *
 * @param array<string, mixed> $registration
 * @return array{ok: bool, error: string}
 */
function rm_save_event_registration_settings(int $event_id, array $registration): array
{
    global $wpdb;

    if ($event_id < 1) {
        return [
            'ok'    => false,
            'error' => 'Invalid event id.',
        ];
    }

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
