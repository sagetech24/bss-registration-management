<?php

/**
 * @return array<string, mixed>|null
 */
function rm_fetch_event_promotion(int $event_id, string $slug): ?array
{
    global $wpdb;

    $slug = rm_sanitize_package_slug($slug);
    if ($event_id < 1 || $slug === '') {
        return null;
    }

    $row = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM `event_promotions`
             WHERE `event_id` = %d AND `slug` = %s
             LIMIT 1',
            $event_id,
            $slug
        ),
        ARRAY_A
    );

    return is_array($row) && $row !== [] ? rm_normalize_event_promotion_row($row) : null;
}

/**
 * @return array<string, mixed>|null
 */
function rm_fetch_event_promotion_by_id(int $promotion_id, int $event_id = 0): ?array
{
    global $wpdb;

    if ($promotion_id < 1) {
        return null;
    }

    if ($event_id > 0) {
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM `event_promotions`
                 WHERE `id` = %d AND `event_id` = %d
                 LIMIT 1',
                $promotion_id,
                $event_id
            ),
            ARRAY_A
        );
    } else {
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM `event_promotions` WHERE `id` = %d LIMIT 1',
                $promotion_id
            ),
            ARRAY_A
        );
    }

    return is_array($row) && $row !== [] ? rm_normalize_event_promotion_row($row) : null;
}

/**
 * @return list<array<string, mixed>>
 */
function rm_list_event_promotions(int $event_id, bool $active_only = true): array
{
    global $wpdb;

    if ($event_id < 1) {
        return [];
    }

    $sql = 'SELECT * FROM `event_promotions` WHERE `event_id` = %d';
    $params = [$event_id];

    if ($active_only) {
        $sql .= ' AND `is_active` = 1';
    }

    $sql .= ' ORDER BY `sort_order` ASC, `id` ASC';

    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
    if (!is_array($rows)) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $normalized = rm_normalize_event_promotion_row($row);
        if ($active_only && !rm_validate_event_promotion($normalized)['ok']) {
            continue;
        }

        $out[] = $normalized;
    }

    return $out;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function rm_normalize_event_promotion_row(array $row): array
{
    $pricing_config = $row['pricing_config'] ?? null;
    if (is_string($pricing_config) && $pricing_config !== '') {
        $decoded = json_decode($pricing_config, true);
        $pricing_config = is_array($decoded) ? $decoded : [];
    } elseif (!is_array($pricing_config)) {
        $pricing_config = [];
    }

    $member_min = max(1, (int) ($row['member_min'] ?? 1));
    $member_max = max($member_min, (int) ($row['member_max'] ?? $member_min));
    $mode = (string) ($row['registration_mode'] ?? RM_REGISTRATION_MODE_GROUP_FLAT);
    if (!in_array($mode, rm_registration_modes(), true)) {
        $mode = RM_REGISTRATION_MODE_GROUP_FLAT;
    }

    return [
        'id'                  => isset($row['id']) ? (int) $row['id'] : 0,
        'event_id'            => isset($row['event_id']) ? (int) $row['event_id'] : 0,
        'slug'                => (string) ($row['slug'] ?? ''),
        'title'               => (string) ($row['title'] ?? ''),
        'description'         => (string) ($row['description'] ?? ''),
        'registration_mode'   => $mode,
        'member_min'          => $member_min,
        'member_max'          => $member_max,
        'require_all_members' => !empty($row['require_all_members']),
        'package_price'       => isset($row['package_price']) ? (float) $row['package_price'] : 0.0,
        'pricing_config'      => $pricing_config,
        'valid_from'          => $row['valid_from'] ?? null,
        'valid_until'         => $row['valid_until'] ?? null,
        'is_active'           => !empty($row['is_active']),
        'sort_order'          => isset($row['sort_order']) ? (int) $row['sort_order'] : 0,
    ];
}

/**
 * @param array<string, mixed> $promotion
 * @return array{ok: bool, error: string}
 */
function rm_validate_event_promotion(array $promotion): array
{
    if (empty($promotion['is_active'])) {
        return [
            'ok'    => false,
            'error' => 'This registration package is not available.',
        ];
    }

    $now = current_time('timestamp');

    $valid_from = $promotion['valid_from'] ?? null;
    if (is_string($valid_from) && $valid_from !== '') {
        $from_ts = strtotime($valid_from);
        if ($from_ts !== false && $now < $from_ts) {
            return [
                'ok'    => false,
                'error' => 'This registration package is not available yet.',
            ];
        }
    }

    $valid_until = $promotion['valid_until'] ?? null;
    if (is_string($valid_until) && $valid_until !== '') {
        $until_ts = strtotime($valid_until);
        if ($until_ts !== false && $now > $until_ts) {
            return [
                'ok'    => false,
                'error' => 'This registration package has expired.',
            ];
        }
    }

    return [
        'ok'    => true,
        'error' => '',
    ];
}

/**
 * Merge package promotion over the event's default v2 registration config.
 *
 * @param array<string, mixed> $event
 * @param array<string, mixed>|null $promotion
 * @return array<string, mixed>
 */
function rm_effective_registration_config(array $event, ?array $promotion = null): array
{
    $config = rm_parse_registration_config($event);

    if ($promotion === null) {
        return $config;
    }

    $config['mode'] = $promotion['registration_mode'];
    $config['group']['min'] = (int) $promotion['member_min'];
    $config['group']['max'] = (int) $promotion['member_max'];
    $config['group']['require_all_members'] = !empty($promotion['require_all_members']);

    if ($config['mode'] === RM_REGISTRATION_MODE_INDIVIDUAL) {
        $config['group']['min'] = 1;
        $config['group']['max'] = 1;
        $config['group']['require_all_members'] = true;
    }

    $pricing_config = is_array($promotion['pricing_config'] ?? null)
        ? $promotion['pricing_config']
        : [];

    if ($config['mode'] === RM_REGISTRATION_MODE_GROUP_PER_HEAD) {
        $config['pricing']['model'] = (string) ($pricing_config['model'] ?? 'package_slots');
        $config['pricing']['slots'] = is_array($pricing_config['slots'] ?? null)
            ? $pricing_config['slots']
            : [];
    } else {
        $config['pricing']['model'] = (string) ($pricing_config['model'] ?? 'flat');
        $config['pricing']['slots'] = is_array($pricing_config['slots'] ?? null)
            ? $pricing_config['slots']
            : [];
    }

    $config['pricing']['base_price'] = (float) $promotion['package_price'];
    $config['_event_promotion_id'] = (int) ($promotion['id'] ?? 0);
    $config['_package_slug'] = (string) ($promotion['slug'] ?? '');
    $config['_package_title'] = (string) ($promotion['title'] ?? '');

    return $config;
}

/**
 * @param array<string, mixed> $promotion
 * @return array{min: int, max: int, require_all_members: bool}
 */
function rm_promotion_group_limits(array $promotion): array
{
    $min = max(1, (int) ($promotion['member_min'] ?? 1));
    $max = max($min, (int) ($promotion['member_max'] ?? $min));
    $require_all = !empty($promotion['require_all_members']);

    if ($require_all) {
        $min = $max;
    }

    return [
        'min'                 => $min,
        'max'                 => $max,
        'require_all_members' => $require_all,
    ];
}

/**
 * @param array<string, mixed> $event
 * @param array<string, mixed>|null $promotion
 * @return array{min: int, max: int, require_all_members: bool}
 */
function rm_effective_group_limits(array $event, ?array $promotion = null): array
{
    if ($promotion !== null) {
        return rm_promotion_group_limits($promotion);
    }

    $limits = rm_registration_group_limits($event);

    return [
        'min'                 => (int) $limits['min'],
        'max'                 => (int) $limits['max'],
        'require_all_members' => false,
    ];
}

/**
 * @param array<string, mixed> $event
 * @param array<string, mixed>|null $promotion
 */
function rm_effective_is_group_mode(array $event, ?array $promotion = null): bool
{
    $config = rm_effective_registration_config($event, $promotion);

    return in_array(
        $config['mode'],
        [RM_REGISTRATION_MODE_GROUP_FLAT, RM_REGISTRATION_MODE_GROUP_PER_HEAD],
        true
    );
}

/**
 * @param array<string, mixed> $promotion
 * @return array<string, mixed>
 */
function rm_present_event_promotion(array $promotion): array
{
    $limits = rm_promotion_group_limits($promotion);
    $price = (float) ($promotion['package_price'] ?? 0);
    $promo_currency = (string) ($promotion['_currency'] ?? 'SGD');
    $price_display = rm_format_currency($price, $promo_currency);

    $member_rule = '';
    if ($limits['require_all_members']) {
        $member_rule = $limits['max'] === 1
            ? '1 registrant required'
            : $limits['max'] . ' registrants required';
    } elseif ($limits['min'] === $limits['max']) {
        $member_rule = $limits['max'] . ' registrant' . ($limits['max'] === 1 ? '' : 's');
    } else {
        $member_rule = 'Up to ' . $limits['max'] . ' members — add more later (min ' . $limits['min'] . ')';
    }

    $mode = (string) ($promotion['registration_mode'] ?? '');
    $mode_labels = [
        RM_REGISTRATION_MODE_INDIVIDUAL     => 'Individual',
        RM_REGISTRATION_MODE_GROUP_FLAT     => 'Group flat',
        RM_REGISTRATION_MODE_GROUP_PER_HEAD => 'Per-head',
    ];

    return [
        'id'                  => (int) ($promotion['id'] ?? 0),
        'slug'                => (string) ($promotion['slug'] ?? ''),
        'title'               => (string) ($promotion['title'] ?? ''),
        'description'         => (string) ($promotion['description'] ?? ''),
        'price_display'       => $price_display,
        'package_price'       => $price,
        'member_rule'         => $member_rule,
        'member_min'          => $limits['min'],
        'member_max'          => $limits['max'],
        'require_all_members' => $limits['require_all_members'],
        'registration_mode'   => $mode,
        'registration_mode_label' => $mode_labels[$mode] ?? $mode,
        'is_active'           => !empty($promotion['is_active']),
        'valid_from'          => $promotion['valid_from'] ?? null,
        'valid_until'         => $promotion['valid_until'] ?? null,
        'sort_order'          => (int) ($promotion['sort_order'] ?? 0),
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array{ok: bool, error: string, data: array<string, mixed>}
 */
function rm_normalize_event_promotion_input(array $input, int $event_id): array
{
    $title = isset($input['title']) ? sanitize_text_field((string) $input['title']) : '';
    if ($title === '') {
        return [
            'ok'    => false,
            'error' => 'Package title is required.',
            'data'  => [],
        ];
    }

    $slug = isset($input['slug'])
        ? rm_sanitize_package_slug((string) $input['slug'])
        : '';
    if ($slug === '') {
        $slug = rm_sanitize_package_slug(sanitize_title($title));
    }
    if ($slug === '') {
        return [
            'ok'    => false,
            'error' => 'Package slug is required.',
            'data'  => [],
        ];
    }

    $mode = isset($input['registration_mode'])
        ? sanitize_key((string) $input['registration_mode'])
        : RM_REGISTRATION_MODE_GROUP_FLAT;
    if (!in_array($mode, rm_registration_modes(), true)) {
        return [
            'ok'    => false,
            'error' => 'Invalid package registration mode.',
            'data'  => [],
        ];
    }

    $member_min = isset($input['member_min']) ? max(1, absint($input['member_min'])) : 1;
    $member_max = isset($input['member_max']) ? max($member_min, absint($input['member_max'])) : $member_min;
    if ($mode === RM_REGISTRATION_MODE_INDIVIDUAL) {
        $member_min = 1;
        $member_max = 1;
    }

    $package_price = isset($input['package_price']) ? (float) $input['package_price'] : 0.0;
    if ($package_price < 0) {
        return [
            'ok'    => false,
            'error' => 'Package price cannot be negative.',
            'data'  => [],
        ];
    }

    $valid_from = rm_normalize_promotion_datetime($input['valid_from'] ?? null);
    $valid_until = rm_normalize_promotion_datetime($input['valid_until'] ?? null);
    if ($valid_from === false || $valid_until === false) {
        return [
            'ok'    => false,
            'error' => 'Invalid validity date.',
            'data'  => [],
        ];
    }

    return [
        'ok'    => true,
        'error' => '',
        'data'  => [
            'event_id'            => $event_id,
            'slug'                => $slug,
            'title'               => $title,
            'description'         => isset($input['description'])
                ? sanitize_textarea_field((string) $input['description'])
                : '',
            'registration_mode'   => $mode,
            'member_min'          => $member_min,
            'member_max'          => $member_max,
            'require_all_members' => !empty($input['require_all_members']) ? 1 : 0,
            'package_price'       => $package_price,
            'pricing_config'      => null,
            'valid_from'          => $valid_from,
            'valid_until'         => $valid_until,
        'is_active'           => isset($input['is_active']) && (
            $input['is_active'] === true
            || $input['is_active'] === 1
            || $input['is_active'] === '1'
        ) ? 1 : 0,
            'sort_order'          => isset($input['sort_order']) ? absint($input['sort_order']) : 0,
        ],
    ];
}

/**
 * @param mixed $value
 * @return string|null|false Null when empty, false when invalid, string datetime otherwise.
 */
function rm_normalize_promotion_datetime($value)
{
    if ($value === null || $value === '') {
        return null;
    }

    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    $raw = str_replace('T', ' ', $raw);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        $raw .= ' 00:00:00';
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw)) {
        $raw .= ':00';
    }

    if (strtotime($raw) === false) {
        return false;
    }

    return $raw;
}

/**
 * @param array<string, mixed> $input
 * @return array{ok: bool, error: string, id: int}
 */
function rm_create_event_promotion(int $event_id, array $input): array
{
    global $wpdb;

    $normalized = rm_normalize_event_promotion_input($input, $event_id);
    if (!$normalized['ok']) {
        return [
            'ok'    => false,
            'error' => $normalized['error'],
            'id'    => 0,
        ];
    }

    $data = $normalized['data'];
    $existing = rm_fetch_event_promotion($event_id, $data['slug']);
    if ($existing !== null) {
        return [
            'ok'    => false,
            'error' => 'A package with this slug already exists for the event.',
            'id'    => 0,
        ];
    }

    foreach (['pricing_config', 'valid_from', 'valid_until', 'description'] as $nullable_key) {
        if (!array_key_exists($nullable_key, $data)) {
            continue;
        }
        if ($data[$nullable_key] === null || $data[$nullable_key] === '') {
            $data[$nullable_key] = null;
        }
    }

    $inserted = $wpdb->insert('event_promotions', $data);

    if ($inserted === false) {
        return [
            'ok'    => false,
            'error' => $wpdb->last_error !== '' ? $wpdb->last_error : 'Failed to create package.',
            'id'    => 0,
        ];
    }

    return [
        'ok'    => true,
        'error' => '',
        'id'    => (int) $wpdb->insert_id,
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array{ok: bool, error: string}
 */
function rm_update_event_promotion(int $promotion_id, int $event_id, array $input): array
{
    global $wpdb;

    if ($promotion_id < 1 || $event_id < 1) {
        return [
            'ok'    => false,
            'error' => 'Invalid package id.',
        ];
    }

    $current = rm_fetch_event_promotion_by_id($promotion_id, $event_id);
    if ($current === null) {
        return [
            'ok'    => false,
            'error' => 'Package could not be found.',
        ];
    }

    $normalized = rm_normalize_event_promotion_input($input, $event_id);
    if (!$normalized['ok']) {
        return [
            'ok'    => false,
            'error' => $normalized['error'],
        ];
    }

    $data = $normalized['data'];
    unset($data['event_id']);

    $existing_pricing = $current['pricing_config'] ?? [];
    $data['pricing_config'] = is_array($existing_pricing) && $existing_pricing !== []
        ? wp_json_encode($existing_pricing)
        : null;

    foreach (['pricing_config', 'valid_from', 'valid_until', 'description'] as $nullable_key) {
        if (!array_key_exists($nullable_key, $data)) {
            continue;
        }
        if ($data[$nullable_key] === null || $data[$nullable_key] === '') {
            $data[$nullable_key] = null;
        }
    }

    $slug_owner = rm_fetch_event_promotion($event_id, $data['slug']);
    if ($slug_owner !== null && (int) $slug_owner['id'] !== $promotion_id) {
        return [
            'ok'    => false,
            'error' => 'A package with this slug already exists for the event.',
        ];
    }

    $updated = $wpdb->update(
        'event_promotions',
        $data,
        [
            'id'       => $promotion_id,
            'event_id' => $event_id,
        ]
    );

    if ($updated === false) {
        return [
            'ok'    => false,
            'error' => $wpdb->last_error !== '' ? $wpdb->last_error : 'Failed to update package.',
        ];
    }

    return [
        'ok'    => true,
        'error' => '',
    ];
}

/**
 * @return array{ok: bool, error: string}
 */
function rm_set_event_promotion_active(int $promotion_id, int $event_id, bool $is_active): array
{
    global $wpdb;

    if ($promotion_id < 1 || $event_id < 1) {
        return [
            'ok'    => false,
            'error' => 'Invalid package id.',
        ];
    }

    $current = rm_fetch_event_promotion_by_id($promotion_id, $event_id);
    if ($current === null) {
        return [
            'ok'    => false,
            'error' => 'Package could not be found.',
        ];
    }

    $updated = $wpdb->update(
        'event_promotions',
        ['is_active' => $is_active ? 1 : 0],
        [
            'id'       => $promotion_id,
            'event_id' => $event_id,
        ],
        ['%d'],
        ['%d', '%d']
    );

    if ($updated === false) {
        return [
            'ok'    => false,
            'error' => $wpdb->last_error !== '' ? $wpdb->last_error : 'Failed to update package status.',
        ];
    }

    return [
        'ok'    => true,
        'error' => '',
    ];
}

/**
 * Resolve promotion from URL slug or POST id for an event.
 *
 * @param array<string, mixed> $event
 * @return array{ok: bool, error: string, promotion: array<string, mixed>|null, slug_requested: bool}
 */
function rm_resolve_registration_promotion(array $event): array
{
    $event_id = isset($event['id']) ? absint($event['id']) : 0;
    $slug = rm_get_registration_package_slug();
    $post_id = rm_get_posted_event_promotion_id();

    if ($slug === '' && $post_id < 1) {
        return [
            'ok'             => true,
            'error'          => '',
            'promotion'      => null,
            'slug_requested' => false,
        ];
    }

    $promotion = null;
    if ($post_id > 0) {
        $promotion = rm_fetch_event_promotion_by_id($post_id, $event_id);
    }

    if ($promotion === null && $slug !== '') {
        $promotion = rm_fetch_event_promotion($event_id, $slug);
    }

    if ($promotion === null) {
        return [
            'ok'             => false,
            'error'          => 'This registration package is not available.',
            'promotion'      => null,
            'slug_requested' => $slug !== '' || $post_id > 0,
        ];
    }

    $valid = rm_validate_event_promotion($promotion);
    if (!$valid['ok']) {
        return [
            'ok'             => false,
            'error'          => $valid['error'],
            'promotion'      => null,
            'slug_requested' => true,
        ];
    }

    return [
        'ok'             => true,
        'error'          => '',
        'promotion'      => $promotion,
        'slug_requested' => true,
    ];
}

function rm_sanitize_package_slug(string $slug): string
{
    $slug = strtolower(trim($slug));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug) ?? '';

    return $slug;
}

function rm_get_posted_event_promotion_id(): int
{
    if (!isset($_POST['event_promotion_id'])) {
        return 0;
    }

    return absint(wp_unslash((string) $_POST['event_promotion_id']));
}

/**
 * Package label for dashboard rows.
 *
 * @param array<string, mixed>|null $header
 * @param array<string, mixed>|null $promotion
 */
function rm_package_label_from_header(?array $header, ?array $promotion = null): string
{
    if ($promotion !== null && trim((string) ($promotion['title'] ?? '')) !== '') {
        return (string) $promotion['title'];
    }

    if (!is_array($header)) {
        return 'Individual';
    }

    $promotion_id = isset($header['event_promotion_id']) ? (int) $header['event_promotion_id'] : 0;
    if ($promotion_id < 1) {
        return 'Individual';
    }

    $snapshot = $header['pricing_snapshot'] ?? null;
    if (is_string($snapshot) && $snapshot !== '') {
        $decoded = json_decode($snapshot, true);
        if (is_array($decoded) && !empty($decoded['package_title'])) {
            return (string) $decoded['package_title'];
        }
    } elseif (is_array($snapshot) && !empty($snapshot['package_title'])) {
        return (string) $snapshot['package_title'];
    }

    $fetched = rm_fetch_event_promotion_by_id($promotion_id);
    if ($fetched !== null && $fetched['title'] !== '') {
        return $fetched['title'];
    }

    return 'Package #' . $promotion_id;
}
