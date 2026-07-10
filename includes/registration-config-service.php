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
