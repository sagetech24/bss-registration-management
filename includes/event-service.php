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
 * @return array<string, mixed>|null
 */
function rm_get_event_by_id(int $event_id): ?array
{
    if ($event_id < 1) {
        return null;
    }

    global $wpdb;

    $event = $wpdb->get_row(
        $wpdb->prepare('SELECT * FROM `bss_events` WHERE `id` = %d LIMIT 1', $event_id),
        ARRAY_A
    );

    return is_array($event) && $event !== [] ? $event : null;
}

/**
 * Registration gate: event must exist, activeUntil must be valid, and the event must not have passed.
 *
 * @return array{ok: bool, error: string, event: array<string, mixed>|null}
 */
function rm_validate_event_registration(int $event_id): array
{
    if ($event_id < 1) {
        return [
            'ok'    => false,
            'error' => 'Event is not available for registration.',
            'event' => null,
        ];
    }

    $event = rm_get_event_by_id($event_id);
    if ($event === null) {
        return [
            'ok'    => false,
            'error' => 'This event could not be found.',
            'event' => null,
        ];
    }

    $now = current_time('timestamp');

    if (empty($event['activeUntil'])) {
        return [
            'ok'    => false,
            'error' => 'Registration for this event is closed.',
            'event' => null,
        ];
    }

    $active_until_ts = strtotime((string) $event['activeUntil']);
    if ($active_until_ts === false || $active_until_ts <= $now) {
        return [
            'ok'    => false,
            'error' => 'Registration for this event has closed.',
            'event' => null,
        ];
    }

    $event_ref_ts = rm_event_reference_timestamp($event, true);
    if ($event_ref_ts === null) {
        return [
            'ok'    => false,
            'error' => 'This event is not available for registration.',
            'event' => null,
        ];
    }

    if ($event_ref_ts < $now) {
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
    $end_ts = rm_event_reference_timestamp($event, true);
    $start_ts = rm_event_reference_timestamp($event, false);
    $ref_ts = $end_ts ?? $start_ts;

    if ($ref_ts === null) {
        return false;
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
