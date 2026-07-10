<?php

/**
 * Redirect legacy theme group registration URLs to registration-manager for v2 events.
 */
function rm_maybe_redirect_legacy_registration(): void
{
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    $event_id = rm_legacy_redirect_event_id_from_request();
    if ($event_id < 1) {
        return;
    }

    $event = rm_get_event_by_id($event_id);
    if ($event === null) {
        return;
    }

    if (!rm_event_uses_v2_registration($event)) {
        return;
    }

    if (!rm_registration_is_group_mode($event)) {
        return;
    }

    $program_code = isset($event['programCode']) ? trim((string) $event['programCode']) : '';
    if ($program_code === '') {
        return;
    }

    $target = rm_registration_url(['event_code' => $program_code]);
    wp_safe_redirect($target, 302);
    exit;
}

function rm_legacy_redirect_event_id_from_request(): int
{
    if (isset($_GET['e'])) {
        return absint(wp_unslash((string) $_GET['e']));
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    if ($request_uri === '') {
        return 0;
    }

    if (preg_match('/[?&]e=(\d+)/', $request_uri, $matches) === 1) {
        return absint($matches[1]);
    }

    return 0;
}

function rm_legacy_redirect_bootstrap(): void
{
    add_action('template_redirect', 'rm_maybe_redirect_legacy_registration', 1);
}
