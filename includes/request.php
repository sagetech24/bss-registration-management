<?php

function rm_page_url(): string
{
    return home_url('/registration-manager/');
}

function rm_event_filter_options(): array
{
    return [
        'upcoming' => 'Upcoming Events',
        'past_30'  => 'Past 30-day',
        'past_90'  => 'Past 90-day',
    ];
}

/**
 * @param array<string, array<int, array<string, mixed>>> $events_by_year
 * @return list<string>
 */
function rm_get_available_event_years(array $events_by_year): array
{
    $years = [];

    foreach (array_keys($events_by_year) as $year) {
        if (is_numeric($year)) {
            $years[] = (string) $year;
        }
    }

    rsort($years, SORT_NUMERIC);

    return $years;
}

/**
 * @param list<string> $available_years
 */
function rm_get_event_year(array $available_years): string
{
    if (!isset($_GET['event_year'])) {
        return '';
    }

    $year = sanitize_text_field(wp_unslash((string) $_GET['event_year']));

    return in_array($year, $available_years, true) ? $year : '';
}

function rm_get_view_action(): string
{
    return isset($_GET['action']) ? sanitize_key(wp_unslash((string) $_GET['action'])) : '';
}

function rm_is_past_event_year(string $year): bool
{
    if ($year === '') {
        return false;
    }

    return (int) $year < (int) current_time('Y');
}

function rm_get_event_filter(string $event_year = ''): string
{
    if (rm_is_past_event_year($event_year)) {
        return '';
    }

    $options = rm_event_filter_options();

    if (!isset($_GET['event_filter'])) {
        return '';
    }

    $filter = sanitize_key(wp_unslash((string) $_GET['event_filter']));

    if ($filter === '') {
        return '';
    }

    return array_key_exists($filter, $options) ? $filter : '';
}

function rm_has_active_event_filters(string $filter, string $year, string $search): bool
{
    return $filter !== '' || $year !== '' || $search !== '';
}

function rm_get_event_search(): string
{
    return isset($_GET['event_search'])
        ? sanitize_text_field(wp_unslash((string) $_GET['event_search']))
        : '';
}

function rm_get_event_code(): string
{
    return isset($_GET['event_code'])
        ? sanitize_text_field(wp_unslash((string) $_GET['event_code']))
        : '';
}

function rm_active_nav(string $view_action): string
{
    return $view_action === 'get-event' ? 'registrants' : 'events';
}

function rm_is_public_view(string $view_action): bool
{
    return in_array($view_action, ['register', 'payment-return'], true);
}

function rm_get_pending_id(): int
{
    if (!isset($_GET['pending_id'])) {
        return 0;
    }

    return absint(wp_unslash((string) $_GET['pending_id']));
}

function rm_get_payment_reference(): string
{
    if (!isset($_GET['reference'])) {
        return '';
    }

    return sanitize_text_field(wp_unslash((string) $_GET['reference']));
}

function rm_get_payment_status(): string
{
    if (!isset($_GET['status'])) {
        return '';
    }

    return sanitize_key(wp_unslash((string) $_GET['status']));
}

/**
 * @param array<string, string|int> $args
 */
function rm_registration_url(array $args = []): string
{
    $defaults = [
        'action' => 'register',
    ];

    return add_query_arg(array_merge($defaults, $args), rm_page_url());
}

function rm_get_registration_flash_key(): string
{
    if (!isset($_GET['registered'])) {
        return '';
    }

    return sanitize_key(wp_unslash((string) $_GET['registered']));
}
