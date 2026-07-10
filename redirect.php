<?php

/**
 * Standalone redirect entry for legacy group registration URLs.
 * Point legacy URLs to: /registration-manager/redirect.php?e={event_id}
 */
require_once __DIR__ . '/bootstrap.php';

$event_id = isset($_GET['e']) ? absint(wp_unslash((string) $_GET['e'])) : 0;

if ($event_id < 1) {
    wp_safe_redirect(home_url('/registration/'));
    exit;
}

$event = rm_get_event_by_id($event_id);
if ($event === null) {
    wp_safe_redirect(home_url('/registration/'));
    exit;
}

$program_code = isset($event['programCode']) ? trim((string) $event['programCode']) : '';
if ($program_code === '') {
    wp_safe_redirect(home_url('/registration/'));
    exit;
}

wp_safe_redirect(rm_registration_url(['event_code' => $program_code]), 302);
exit;
