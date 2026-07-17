<?php

function rm_module_dir_name(): string
{
    // Folder this module is deployed under (e.g. registration-manager locally,
    // registration-v2 in production). Derived from the code location so the
    // same repo works regardless of the directory name.
    return basename(dirname(__DIR__));
}

function rm_page_url(): string
{
    return home_url('/' . rm_module_dir_name() . '/');
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
 * @return array<string, string>
 */
function rm_event_option_options(): array
{
    return [
        'new'    => 'New Version Events',
        'legacy' => 'Legacy Events',
        'both'   => 'Both New Version & Legacy',
    ];
}

function rm_get_event_option(): string
{
    $options = rm_event_option_options();
    $default = 'new';

    if (!isset($_GET['event_option'])) {
        return $default;
    }

    $option = sanitize_key(wp_unslash((string) $_GET['event_option']));

    return array_key_exists($option, $options) ? $option : $default;
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

function rm_has_active_event_filters(string $filter, string $year, string $search, string $event_option = 'new'): bool
{
    return $filter !== '' || $year !== '' || $search !== '' || $event_option !== 'new';
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

function rm_get_registration_package_slug(): string
{
    if (!isset($_GET['package'])) {
        return '';
    }

    return rm_sanitize_package_slug(
        sanitize_text_field(wp_unslash((string) $_GET['package']))
    );
}

function rm_get_package_filter(): string
{
    if (!isset($_GET['package_filter'])) {
        return 'all';
    }

    $filter = sanitize_text_field(wp_unslash((string) $_GET['package_filter']));
    if ($filter === '' || $filter === 'all') {
        return 'all';
    }

    if ($filter === 'individual') {
        return 'individual';
    }

    if (ctype_digit($filter)) {
        return $filter;
    }

    return 'all';
}

function rm_get_event_id(): int
{
    if (!isset($_GET['event_id'])) {
        return 0;
    }

    return absint(wp_unslash((string) $_GET['event_id']));
}

/**
 * Disambiguate bss_events vs CPT event ids (event_source=cpt).
 */
function rm_get_event_source(): string
{
    if (!isset($_GET['event_source'])) {
        return '';
    }

    return rm_normalize_event_source(
        sanitize_key(wp_unslash((string) $_GET['event_source']))
    );
}

function rm_active_nav(string $view_action): string
{
    if ($view_action === 'get-event-registrants') {
        return 'registrants';
    }

    if ($view_action === 'payment-transactions') {
        return 'payment-transactions';
    }

    if ($view_action === 'get-event-profile') {
        return 'events';
    }

    return 'events';
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

function rm_payment_transactions_per_page(): int
{
    return 10;
}

function rm_get_payment_transactions_page(): int
{
    if (!isset($_GET['tx_page'])) {
        return 1;
    }

    return max(1, absint(wp_unslash((string) $_GET['tx_page'])));
}

function rm_get_registrant_payment_request_id(): string
{
    if (!isset($_GET['payment_request_id'])) {
        return '';
    }

    return sanitize_text_field(wp_unslash((string) $_GET['payment_request_id']));
}

function rm_get_registrant_id(): int
{
    if (!isset($_GET['registrant_id'])) {
        return 0;
    }

    return absint(wp_unslash((string) $_GET['registrant_id']));
}

/**
 * @return array<string, string>
 */
function rm_event_profile_tabs(): array
{
    return [
        'packages'     => 'Promotion Packages',
        'promo-codes'  => 'Promo Codes',
        'registrants'  => 'Registrants',
        'custom-form'  => 'Custom Form Options',
        'settings'     => 'Event Settings',
    ];
}

function rm_get_event_profile_tab(): string
{
    $tabs = rm_event_profile_tabs();
    $default = 'packages';

    if (!isset($_GET['tab'])) {
        return $default;
    }

    $tab = sanitize_key(wp_unslash((string) $_GET['tab']));

    return array_key_exists($tab, $tabs) ? $tab : $default;
}
