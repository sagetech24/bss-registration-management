<?php

function rm_event_reference_timestamp(array $event, bool $prefer_end = true): ?int
{
    if ($prefer_end && !empty($event['endDate'])) {
        $timestamp = strtotime((string) $event['endDate']);
        if ($timestamp !== false) {
            return $timestamp;
        }
    }

    if (!empty($event['startDate'])) {
        $timestamp = strtotime((string) $event['startDate']);
        if ($timestamp !== false) {
            return $timestamp;
        }
    }

    if (!$prefer_end && !empty($event['endDate'])) {
        $timestamp = strtotime((string) $event['endDate']);
        if ($timestamp !== false) {
            return $timestamp;
        }
    }

    return null;
}

/**
 * Load event by id from bss_events, or from CPT when $source === 'cpt'.
 *
 * @return array<string, mixed>|null
 */
function rm_get_event_by_id(int $event_id, string $source = ''): ?array
{
    if ($event_id < 1) {
        return null;
    }

    if ($source === 'cpt') {
        return rm_get_cpt_event_by_id($event_id);
    }

    global $wpdb;

    $event = $wpdb->get_row(
        $wpdb->prepare('SELECT * FROM `bss_events` WHERE `id` = %d LIMIT 1', $event_id),
        ARRAY_A
    );

    return is_array($event) && $event !== [] ? $event : null;
}

/**
 * @param array<string, mixed> $event
 */
function rm_is_cpt_event(array $event): bool
{
    return (($event['source'] ?? '') === 'cpt');
}

/**
 * Infer event_source when not passed (e.g. payment finalize).
 * CPT post IDs are typically outside the legacy bss_events range.
 */
function rm_infer_event_source(int $event_id): string
{
    if ($event_id < 1) {
        return '';
    }

    if (get_post_type($event_id) === 'event') {
        return 'cpt';
    }

    return '';
}

/**
 * Normalize event_source query values.
 */
function rm_normalize_event_source(string $source): string
{
    $source = sanitize_key($source);

    return $source === 'cpt' ? 'cpt' : '';
}

/**
 * @param array<string, mixed> $event
 */
function rm_event_source_value(array $event): string
{
    return rm_is_cpt_event($event) ? 'cpt' : '';
}

/**
 * Append event_source=cpt to URL args when needed.
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function rm_args_with_event_source(array $args, string $source): array
{
    $source = rm_normalize_event_source($source);
    if ($source === 'cpt') {
        $args['event_source'] = 'cpt';
    } else {
        unset($args['event_source']);
    }

    return $args;
}

/**
 * @param array<string, mixed> $event
 */
function rm_format_event_date_display(array $event): string
{
    if (!empty($event['customDate'])) {
        return trim(wp_strip_all_tags((string) $event['customDate']));
    }

    $start_ts = !empty($event['startDate']) ? strtotime((string) $event['startDate']) : false;
    if ($start_ts === false) {
        return '';
    }

    $parts = [wp_date(get_option('date_format'), $start_ts)];
    $start_time = isset($event['startTime']) ? trim((string) $event['startTime']) : '';
    $end_ts = !empty($event['endDate']) ? strtotime((string) $event['endDate']) : false;
    $end_time = isset($event['endTime']) ? trim((string) $event['endTime']) : '';

    if ($start_time !== '') {
        $parts[] = $start_time;
    }

    if ($end_ts !== false && wp_date('Y-m-d', $end_ts) !== wp_date('Y-m-d', $start_ts)) {
        $parts[] = '–';
        $parts[] = wp_date(get_option('date_format'), $end_ts);
        if ($end_time !== '') {
            $parts[] = $end_time;
        }
    } elseif ($end_time !== '' && $end_time !== $start_time) {
        $parts[] = '– ' . $end_time;
    }

    return trim(implode(' ', $parts));
}

/**
 * Load event title and related details for display, verifying the ID exists in bss_events.
 *
 * @return array{
 *     ok: bool,
 *     exists: bool,
 *     error: string,
 *     event_id: int,
 *     title: string,
 *     program_code: string,
 *     venue: string,
 *     date_display: string,
 *     active_until_display: string,
 *     display: string
 * }
 */
function rm_get_event_details(int $event_id): array
{
    static $cache = [];

    if ($event_id < 1) {
        return [
            'ok'                   => false,
            'exists'               => false,
            'error'                => 'Invalid event ID.',
            'event_id'             => $event_id,
            'title'                => '',
            'program_code'         => '',
            'venue'                => '',
            'date_display'         => '',
            'active_until_display' => '',
            'display'              => 'N/A',
        ];
    }

    if (isset($cache[$event_id])) {
        return $cache[$event_id];
    }

    global $wpdb;

    $event = $wpdb->get_row(
        $wpdb->prepare('SELECT * FROM `bss_events` WHERE `id` = %d LIMIT 1', $event_id),
        ARRAY_A
    );

    if ($wpdb->last_error !== '') {
        $result = [
            'ok'                   => false,
            'exists'               => false,
            'error'                => 'Unable to load event details.',
            'event_id'             => $event_id,
            'title'                => '',
            'program_code'         => '',
            'venue'                => '',
            'date_display'         => '',
            'active_until_display' => '',
            'display'              => '#' . $event_id,
        ];
        $cache[$event_id] = $result;

        return $result;
    }

    if (!is_array($event) || $event === []) {
        $result = [
            'ok'                   => false,
            'exists'               => false,
            'error'                => 'Event not found.',
            'event_id'             => $event_id,
            'title'                => '',
            'program_code'         => '',
            'venue'                => '',
            'date_display'         => '',
            'active_until_display' => '',
            'display'              => '#' . $event_id . ' (not found)',
        ];
        $cache[$event_id] = $result;

        return $result;
    }

    $title = trim((string) ($event['title'] ?? ''));
    if ($title === '') {
        $title = 'Untitled event';
    }

    $program_code = trim((string) ($event['programCode'] ?? ''));
    $venue = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) ($event['venue'] ?? ''))));
    $date_display = rm_format_event_date_display($event);

    $active_until_display = '';
    if (!empty($event['activeUntil'])) {
        $active_until_ts = strtotime((string) $event['activeUntil']);
        if ($active_until_ts !== false) {
            $active_until_display = wp_date('M j, Y g:iA', $active_until_ts);
        }
    }

    $display_parts = ['#' . $event_id, $title];
    if ($program_code !== '') {
        $display_parts[] = '(' . $program_code . ')';
    }

    $result = [
        'ok'                   => true,
        'exists'               => true,
        'error'                => '',
        'event_id'             => $event_id,
        'title'                => $title,
        'program_code'         => $program_code,
        'venue'                => $venue,
        'date_display'         => $date_display,
        'active_until_display' => $active_until_display,
        'display'              => implode(' — ', $display_parts),
    ];
    $cache[$event_id] = $result;

    return $result;
}

/**
 * Registration gate: event must exist, activeUntil must be valid, and the event must not have passed.
 *
 * @return array{ok: bool, error: string, event: array<string, mixed>|null}
 */
function rm_validate_event_registration(int $event_id, string $source = ''): array
{
    if ($event_id < 1) {
        return [
            'ok'    => false,
            'error' => 'Event is not available for registration.',
            'event' => null,
        ];
    }

    $event = rm_get_event_by_id($event_id, $source);
    if ($event === null) {
        return [
            'ok'    => false,
            'error' => 'This event could not be found.',
            'event' => null,
        ];
    }

    $now = current_time('timestamp');
    $is_cpt = rm_is_cpt_event($event);

    if (empty($event['activeUntil'])) {
        // CPT events may omit activeUntil until staff configures it — keep open.
        if (!$is_cpt) {
            return [
                'ok'    => false,
                'error' => 'Registration for this event is closed.',
                'event' => null,
            ];
        }
    } else {
        $active_until_ts = strtotime((string) $event['activeUntil']);
        if ($active_until_ts === false || $active_until_ts <= $now) {
            return [
                'ok'    => false,
                'error' => 'Registration for this event has closed.',
                'event' => null,
            ];
        }
    }

    $event_ref_ts = rm_event_reference_timestamp($event, true);
    if ($event_ref_ts === null) {
        if (!$is_cpt) {
            return [
                'ok'    => false,
                'error' => 'This event is not available for registration.',
                'event' => null,
            ];
        }
    } elseif ($event_ref_ts < $now) {
        return [
            'ok'    => false,
            'error' => 'This event has already ended.',
            'event' => null,
        ];
    }

    return [
        'ok'    => true,
        'error' => '',
        'event' => $event,
    ];
}

function rm_event_matches_filter(array $event, string $filter, int $now): bool
{
    // "All events" — include undated records (common for new CPT events).
    if ($filter === '') {
        return true;
    }

    $end_ts = rm_event_reference_timestamp($event, true);
    $start_ts = rm_event_reference_timestamp($event, false);
    $ref_ts = $end_ts ?? $start_ts;

    if ($ref_ts === null) {
        // Dated filters need a reference; treat undated CPT events as upcoming.
        return $filter === 'upcoming' && (($event['source'] ?? '') === 'cpt');
    }

    switch ($filter) {
        case 'upcoming':
            return $ref_ts >= $now;
        case 'past_30':
            return $ref_ts < $now && $ref_ts >= strtotime('-30 days', $now);
        case 'past_90':
            return $ref_ts < $now && $ref_ts >= strtotime('-90 days', $now);
        default:
            return true;
    }
}

function rm_event_matches_search(array $event, string $search): bool
{
    if ($search === '') {
        return true;
    }

    $needle = function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search);
    $haystack = implode(' ', [
        (string) ($event['title'] ?? ''),
        (string) ($event['programCode'] ?? ''),
        (string) ($event['venue'] ?? ''),
        (string) ($event['description'] ?? ''),
    ]);
    $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack) : strtolower($haystack);

    return strpos($haystack, $needle) !== false;
}

/**
 * @param array<string, array<int, array<string, mixed>>> $events_by_year
 * @return array<string, array<int, array<string, mixed>>>
 */
function rm_filter_events(array $events_by_year, string $filter, string $search, string $year = ''): array
{
    if (empty($events_by_year)) {
        return [];
    }

    $now = current_time('timestamp');
    $filtered_events = [];

    foreach ($events_by_year as $year_key => $events_list) {
        if ($year !== '' && (string) $year_key !== $year) {
            continue;
        }

        if (!is_array($events_list)) {
            continue;
        }

        $matched = array_values(array_filter(
            $events_list,
            static function ($event) use ($filter, $search, $now) {
                return is_array($event)
                    && rm_event_matches_filter($event, $filter, $now)
                    && rm_event_matches_search($event, $search);
            }
        ));

        if (empty($matched)) {
            continue;
        }

        $sort_asc = ($filter === 'upcoming');
        usort($matched, static function ($a, $b) use ($sort_asc) {
            $a_ts = rm_event_reference_timestamp($a, false) ?? 0;
            $b_ts = rm_event_reference_timestamp($b, false) ?? 0;
            return $sort_asc ? ($a_ts <=> $b_ts) : ($b_ts <=> $a_ts);
        });

        $filtered_events[$year_key] = $matched;
    }

    if ($filter === 'upcoming') {
        ksort($filtered_events, SORT_NUMERIC);
    } else {
        krsort($filtered_events, SORT_NUMERIC);
    }

    return $filtered_events;
}

/**
 * @param array<string, array<int, array<string, mixed>>> $events_by_year
 */
function rm_count_filtered_events(array $events_by_year): int
{
    $count = 0;

    foreach ($events_by_year as $events_list) {
        if (is_array($events_list)) {
            $count += count($events_list);
        }
    }

    return $count;
}

/**
 * Ensure CPT has a stable programCode (persisted) for registration URLs / order numbers.
 */
function rm_ensure_cpt_program_code(int $post_id, WP_Post $post): string
{
    $code = trim((string) get_post_meta($post_id, 'programCode', true));
    if ($code !== '') {
        return $code;
    }

    $slug = $post->post_name !== '' ? (string) $post->post_name : ('event-' . $post_id);
    $slug = preg_replace('/[^A-Za-z0-9]+/', '', $slug);
    $code = strtoupper((string) $slug);
    if ($code === '') {
        $code = 'CPT' . $post_id;
    }
    if (substr($code, -1) !== '_') {
        $code .= '_';
    }

    update_post_meta($post_id, 'programCode', $code);

    return $code;
}

/**
 * CPT events default to v2 registration settings (persisted on first load).
 */
function rm_ensure_cpt_v2_settings(int $post_id): string
{
    $raw = get_post_meta($post_id, 'settings', true);
    if (is_array($raw) && $raw !== []) {
        $encoded = wp_json_encode($raw);

        return is_string($encoded) ? $encoded : '';
    }

    if (is_string($raw) && trim($raw) !== '') {
        return $raw;
    }

    $encoded = wp_json_encode([
        'registration' => rm_registration_config_defaults(),
    ]);
    if (!is_string($encoded) || $encoded === '') {
        return '';
    }

    update_post_meta($post_id, 'settings', $encoded);

    return $encoded;
}

/**
 * CPT event category term names for display.
 *
 * @return list<string>
 */
function rm_cpt_event_category_names(int $post_id): array
{
    if ($post_id < 1 || !taxonomy_exists('event_category')) {
        return [];
    }

    $terms = get_the_terms($post_id, 'event_category');
    if (!is_array($terms) || $terms === []) {
        return [];
    }

    $names = [];
    foreach ($terms as $term) {
        if ($term instanceof WP_Term) {
            $name = trim((string) $term->name);
            if ($name !== '') {
                $names[] = $name;
            }
        }
    }

    return $names;
}

/**
 * Map a WP `event` CPT post into the bss_events-like shape used by presenters.
 *
 * @return array<string, mixed>
 */
function rm_map_cpt_event_post(WP_Post $post): array
{
    $post_id = (int) $post->ID;
    $start_date = trim((string) get_post_meta($post_id, 'startDate', true));
    $end_date = trim((string) get_post_meta($post_id, 'endDate', true));

    // Fall back to post publish/modified date so undated CPT events still list + filter.
    if ($start_date === '') {
        $post_ts = get_post_time('U', true, $post);
        if (!is_int($post_ts) || $post_ts <= 0) {
            $post_ts = current_time('timestamp');
        }
        $start_date = wp_date('Y-m-d', $post_ts);
    }

    $thumb = get_the_post_thumbnail_url($post_id, 'event-list-thumb');
    if (!is_string($thumb) || $thumb === '') {
        $thumb = get_the_post_thumbnail_url($post_id, 'medium_large');
    }
    if (!is_string($thumb)) {
        $thumb = '';
    }

    $summary = trim((string) get_post_meta($post_id, 'event_summary', true));
    if ($summary === '') {
        $summary = trim((string) $post->post_excerpt);
    }

    return [
        'id'          => $post_id,
        'title'       => get_the_title($post),
        'programCode' => rm_ensure_cpt_program_code($post_id, $post),
        'startDate'   => $start_date,
        'startTime'   => trim((string) get_post_meta($post_id, 'startTime', true)),
        'endDate'     => $end_date !== '' ? $end_date : $start_date,
        'endTime'     => trim((string) get_post_meta($post_id, 'endTime', true)),
        'customDate'  => (string) get_post_meta($post_id, 'customDate', true),
        'venue'       => trim((string) get_post_meta($post_id, 'venue', true)),
        'description' => $summary,
        'price'       => get_post_meta($post_id, 'price', true),
        'activeUntil' => trim((string) get_post_meta($post_id, 'activeUntil', true)),
        'limit'       => get_post_meta($post_id, 'limit', true),
        'lastId'      => (int) get_post_meta($post_id, 'lastId', true),
        'settings'    => rm_ensure_cpt_v2_settings($post_id),
        'categories'  => rm_cpt_event_category_names($post_id),
        'thumb'       => $thumb,
        'source'      => 'cpt',
        'edit_url'    => get_edit_post_link($post_id, 'raw') ?: '',
    ];
}

/**
 * @return array<string, mixed>|null
 */
function rm_get_cpt_event_by_id(int $post_id): ?array
{
    if ($post_id < 1 || !post_type_exists('event')) {
        return null;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== 'event') {
        return null;
    }

    if (!in_array($post->post_status, ['publish', 'private'], true)) {
        return null;
    }

    return rm_map_cpt_event_post($post);
}

/**
 * @return array<string, mixed>|null
 */
function rm_get_cpt_event_by_code(string $event_code): ?array
{
    $event_code = trim($event_code);
    if ($event_code === '' || !post_type_exists('event')) {
        return null;
    }

    $query = new WP_Query([
        'post_type'              => 'event',
        'post_status'            => ['publish', 'private'],
        'posts_per_page'         => 1,
        'no_found_rows'          => true,
        'ignore_sticky_posts'    => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => false,
        'suppress_filters'       => true,
        'meta_key'               => 'programCode',
        'meta_value'             => $event_code,
    ]);

    $post = $query->posts[0] ?? null;
    if (!$post instanceof WP_Post) {
        return null;
    }

    return rm_map_cpt_event_post($post);
}

/**
 * Resolve an event for staff/public flows.
 *
 * @return array<string, mixed>|null
 */
function rm_resolve_event(int $event_id = 0, string $event_code = '', string $source = ''): ?array
{
    $source = rm_normalize_event_source($source);

    if ($source === 'cpt') {
        if ($event_id > 0) {
            $event = rm_get_cpt_event_by_id($event_id);
            if ($event !== null) {
                return $event;
            }
        }
        if ($event_code !== '') {
            return rm_get_cpt_event_by_code($event_code);
        }

        return null;
    }

    if ($event_id > 0) {
        $event = rm_get_event_by_id($event_id);
        if ($event !== null) {
            return $event;
        }
    }

    if ($event_code !== '') {
        $fetch = rm_fetch_event($event_code);
        if (is_array($fetch['event'] ?? null) && $fetch['event'] !== []) {
            return $fetch['event'];
        }

        return rm_get_cpt_event_by_code($event_code);
    }

    return null;
}

/**
 * Load published WP `event` CPT posts, grouped by year (same shape as BSS API events).
 *
 * @return array{events: array<string, array<int, array<string, mixed>>>, error: string}
 */
function rm_fetch_cpt_events(): array
{
    if (!post_type_exists('event')) {
        return [
            'events' => [],
            'error'  => '',
        ];
    }

    $query = new WP_Query([
        'post_type'              => 'event',
        'post_status'            => ['publish', 'private'],
        'posts_per_page'         => -1,
        'orderby'                => 'date',
        'order'                  => 'DESC',
        'no_found_rows'          => true,
        'ignore_sticky_posts'    => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true,
        'suppress_filters'       => true,
    ]);
    $posts = $query->posts;

    $events_by_year = [];

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $event = rm_map_cpt_event_post($post);
        $year = '';

        if (!empty($event['startDate'])) {
            $ts = strtotime((string) $event['startDate']);
            if ($ts !== false) {
                $year = wp_date('Y', $ts);
            }
        }

        if ($year === '') {
            $year = get_the_date('Y', $post);
        }

        if ($year === '') {
            $year = (string) current_time('Y');
        }

        if (!isset($events_by_year[$year])) {
            $events_by_year[$year] = [];
        }

        $events_by_year[$year][] = $event;
    }

    krsort($events_by_year, SORT_NUMERIC);

    return [
        'events' => $events_by_year,
        'error'  => '',
    ];
}

/**
 * Merge year keys from multiple events-by-year collections (unique, newest first).
 *
 * @param array<string, array<int, array<string, mixed>>> ...$collections
 * @return list<string>
 */
function rm_merge_event_years(array ...$collections): array
{
    $years = [];

    foreach ($collections as $events_by_year) {
        foreach (array_keys($events_by_year) as $year) {
            if (is_numeric($year)) {
                $years[(string) $year] = true;
            }
        }
    }

    $list = array_keys($years);
    rsort($list, SORT_NUMERIC);

    return $list;
}
