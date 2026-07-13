<?php

/**
 * @param array<string, string|int> $extra
 */
function rm_event_profile_url(string $event_code, int $event_id = 0, array $extra = []): string
{
    $args = array_merge(
        [
            'action'     => 'get-event-profile',
            'event_code' => $event_code,
        ],
        $extra
    );

    if ($event_id > 0) {
        $args['event_id'] = $event_id;
    }

    return add_query_arg($args, rm_page_url());
}

/**
 * @return array{type: string, message: string}|null
 */
function rm_consume_event_profile_flash(string $flash_key): ?array
{
    if ($flash_key === '') {
        return null;
    }

    $flash = get_transient('rm_event_profile_' . $flash_key);
    if (!is_array($flash)) {
        return null;
    }

    delete_transient('rm_event_profile_' . $flash_key);

    return [
        'type'    => isset($flash['type']) ? (string) $flash['type'] : 'success',
        'message' => isset($flash['message']) ? (string) $flash['message'] : '',
    ];
}

function rm_store_event_profile_flash(string $type, string $message): string
{
    $flash_key = wp_generate_password(12, false);
    set_transient(
        'rm_event_profile_' . $flash_key,
        [
            'type'    => $type,
            'message' => $message,
        ],
        5 * MINUTE_IN_SECONDS
    );

    return $flash_key;
}

function rm_get_event_profile_flash_key(): string
{
    if (!isset($_GET['profile_flash'])) {
        return '';
    }

    return sanitize_key(wp_unslash((string) $_GET['profile_flash']));
}

/**
 * @param mixed $value
 */
function rm_datetime_local_value($value): string
{
    if (!is_string($value) || trim($value) === '') {
        return '';
    }

    $raw = trim($value);
    if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/', $raw, $matches)) {
        return $matches[1] . 'T' . $matches[2];
    }

    return '';
}

/**
 * Handle staff POSTs for the event profile dashboard.
 */
function rm_handle_event_profile_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $rm_action = isset($_POST['rm_action'])
        ? sanitize_key(wp_unslash((string) $_POST['rm_action']))
        : '';

    if ($rm_action === '') {
        return;
    }

    $event_code = rm_get_event_code();
    $event_id = rm_get_event_id();
    if ($event_code === '' && $event_id < 1) {
        return;
    }

    if (
        !isset($_POST['rm_event_profile_nonce'])
        || !wp_verify_nonce(
            sanitize_text_field(wp_unslash((string) $_POST['rm_event_profile_nonce'])),
            'rm_event_profile'
        )
    ) {
        $flash = rm_store_event_profile_flash('error', 'Your session has expired. Please try again.');
        wp_safe_redirect(rm_event_profile_url($event_code, $event_id, ['profile_flash' => $flash]));
        exit;
    }

    if ($event_id < 1) {
        $event = null;
        if ($event_code !== '') {
            $fetch = rm_fetch_event($event_code);
            $event = is_array($fetch['event'] ?? null) ? $fetch['event'] : null;
        }
        $event_id = is_array($event) ? absint($event['id'] ?? 0) : 0;
    }

    if ($event_id < 1) {
        $flash = rm_store_event_profile_flash('error', 'Event could not be found.');
        wp_safe_redirect(rm_event_profile_url($event_code, 0, ['profile_flash' => $flash]));
        exit;
    }

    $result = [
        'ok'    => false,
        'error' => 'Unknown action.',
    ];

    if ($rm_action === 'save_registration_settings') {
        $result = rm_handle_save_registration_settings_post($event_id);
    } elseif ($rm_action === 'save_promotion') {
        $result = rm_handle_save_promotion_post($event_id);
    } elseif ($rm_action === 'deactivate_promotion') {
        $result = rm_handle_set_promotion_active_post($event_id, false);
    } elseif ($rm_action === 'activate_promotion') {
        $result = rm_handle_set_promotion_active_post($event_id, true);
    }

    $flash = rm_store_event_profile_flash(
        $result['ok'] ? 'success' : 'error',
        $result['ok']
            ? (string) ($result['message'] ?? 'Saved.')
            : (string) ($result['error'] ?? 'Something went wrong.')
    );

    if ($event_code === '') {
        $event = rm_get_event_by_id($event_id);
        $event_code = is_array($event) ? trim((string) ($event['programCode'] ?? '')) : '';
    }

    wp_safe_redirect(rm_event_profile_url($event_code, $event_id, ['profile_flash' => $flash]));
    exit;
}

/**
 * @return array{ok: bool, error: string, message?: string}
 */
function rm_handle_save_registration_settings_post(int $event_id): array
{
    $event = rm_get_event_by_id($event_id);
    if ($event === null) {
        return [
            'ok'    => false,
            'error' => 'Event could not be found.',
        ];
    }

    $already_v2 = rm_event_uses_v2_registration($event);
    $enable_v2 = !empty($_POST['enable_v2']) || $already_v2;

    if (!$enable_v2) {
        return [
            'ok'    => false,
            'error' => 'Enable v2 registration before saving settings.',
        ];
    }

    $settings = rm_decode_event_settings($event);
    $existing_registration = isset($settings['registration']) && is_array($settings['registration'])
        ? $settings['registration']
        : [];

    $normalized = rm_normalize_registration_settings_input(
        [
            'mode'          => $_POST['mode'] ?? null,
            'form_preset'   => $_POST['form_preset'] ?? null,
            'group_min'     => $_POST['group_min'] ?? null,
            'group_max'     => $_POST['group_max'] ?? null,
            'pricing_model' => $_POST['pricing_model'] ?? null,
            'base_price'    => $_POST['base_price'] ?? null,
        ],
        $existing_registration
    );

    if (!$normalized['ok']) {
        return [
            'ok'    => false,
            'error' => $normalized['error'],
        ];
    }

    $saved = rm_save_event_registration_settings($event_id, $normalized['registration']);
    if (!$saved['ok']) {
        return $saved;
    }

    return [
        'ok'      => true,
        'error'   => '',
        'message' => 'Registration settings saved.',
    ];
}

/**
 * @return array{ok: bool, error: string, message?: string}
 */
function rm_handle_save_promotion_post(int $event_id): array
{
    $event = rm_get_event_by_id($event_id);
    if ($event === null) {
        return [
            'ok'    => false,
            'error' => 'Event could not be found.',
        ];
    }

    if (!rm_event_uses_v2_registration($event)) {
        return [
            'ok'    => false,
            'error' => 'Enable v2 registration before managing packages.',
        ];
    }

    $promotion_id = isset($_POST['promotion_id']) ? absint($_POST['promotion_id']) : 0;
    $input = [
        'title'               => $_POST['title'] ?? '',
        'slug'                => $_POST['slug'] ?? '',
        'description'         => $_POST['description'] ?? '',
        'registration_mode'   => $_POST['registration_mode'] ?? '',
        'member_min'          => $_POST['member_min'] ?? 1,
        'member_max'          => $_POST['member_max'] ?? 1,
        'require_all_members' => !empty($_POST['require_all_members']),
        'package_price'       => $_POST['package_price'] ?? 0,
        'valid_from'          => $_POST['valid_from'] ?? '',
        'valid_until'         => $_POST['valid_until'] ?? '',
        'is_active'           => isset($_POST['is_active']) && (string) wp_unslash($_POST['is_active']) === '1',
        'sort_order'          => $_POST['sort_order'] ?? 0,
    ];

    if ($promotion_id > 0) {
        $updated = rm_update_event_promotion($promotion_id, $event_id, $input);
        if (!$updated['ok']) {
            return $updated;
        }

        return [
            'ok'      => true,
            'error'   => '',
            'message' => 'Package updated.',
        ];
    }

    $created = rm_create_event_promotion($event_id, $input);
    if (!$created['ok']) {
        return [
            'ok'    => false,
            'error' => $created['error'],
        ];
    }

    return [
        'ok'      => true,
        'error'   => '',
        'message' => 'Package created.',
    ];
}

/**
 * @return array{ok: bool, error: string, message?: string}
 */
function rm_handle_set_promotion_active_post(int $event_id, bool $is_active): array
{
    $promotion_id = isset($_POST['promotion_id']) ? absint($_POST['promotion_id']) : 0;
    $result = rm_set_event_promotion_active($promotion_id, $event_id, $is_active);
    if (!$result['ok']) {
        return $result;
    }

    return [
        'ok'      => true,
        'error'   => '',
        'message' => $is_active ? 'Package activated.' : 'Package deactivated.',
    ];
}

/**
 * @param array<string, array<int, array<string, mixed>>> $events_by_year
 * @return array<string, mixed>
 */
function rm_build_event_profile_context(array $events_by_year, string $requested_event_code, int $requested_event_id = 0): array
{
    $event_options = rm_flatten_events_list($events_by_year);
    $selected_event_code = rm_resolve_event_code($requested_event_code, $event_options);
    $selected_event = null;
    $event_id = $requested_event_id > 0 ? $requested_event_id : 0;
    $error_message = '';

    if ($event_id > 0) {
        $selected_event = rm_get_event_by_id($event_id);
        if ($selected_event !== null && $selected_event_code === '') {
            $selected_event_code = trim((string) ($selected_event['programCode'] ?? ''));
        }
    }

    if ($selected_event === null) {
        $selected_event = rm_find_event_by_code($event_options, $selected_event_code);
    }

    if ($selected_event_code === '' && $event_id < 1) {
        return [
            'selected_event_code' => '',
            'selected_event'      => null,
            'event_not_found'     => false,
        ];
    }

    if ($selected_event === null && $selected_event_code !== '') {
        $event_fetch = rm_fetch_event($selected_event_code);
        if ($event_fetch['error'] !== '') {
            $error_message = $event_fetch['error'];
        } elseif (is_array($event_fetch['event']) && $event_fetch['event'] !== []) {
            $selected_event = $event_fetch['event'];
        }
    }

    if (rm_is_event_not_found($selected_event_code, $selected_event, $error_message)) {
        return [
            'selected_event_code' => $selected_event_code,
            'selected_event'      => null,
            'event_not_found'     => true,
        ];
    }

    if ($selected_event === null) {
        return [
            'selected_event_code' => $selected_event_code,
            'selected_event'      => null,
            'selected_event_id'   => $event_id,
            'profile_error'       => $error_message !== '' ? $error_message : 'Event could not be loaded.',
            'event_not_found'     => false,
        ];
    }

    if ($event_id < 1) {
        $event_id = isset($selected_event['id']) ? absint($selected_event['id']) : 0;
    }

    // Prefer DB row for settings accuracy after edits.
    $db_event = $event_id > 0 ? rm_get_event_by_id($event_id) : null;
    if (is_array($db_event)) {
        $selected_event = array_merge($selected_event, $db_event);
    }

    $uses_v2 = rm_event_uses_v2_registration($selected_event);
    $registration_config = rm_parse_registration_config($selected_event);
    $page_url = rm_page_url();
    $card = rm_present_event_card($selected_event, $page_url);

    $registrants_args = [
        'action'     => 'get-event-registrants',
        'event_code' => $selected_event_code,
    ];
    if ($event_id > 0) {
        $registrants_args['event_id'] = $event_id;
    }

    $summary = [
        'total'         => 0,
        'paid_count'    => 0,
        'pending_count' => 0,
        'total_revenue' => 0.0,
    ];
    $package_summary = [];
    if ($event_id > 0) {
        $db_fetch = rm_fetch_registrants_from_db($event_id, $selected_event);
        if ($db_fetch['error'] === '') {
            $summary = rm_registrants_summary($db_fetch['registrants']);
            $package_summary = rm_registrants_package_summary($db_fetch['registrants']);
        }
    }

    $promotions_raw = $event_id > 0 ? rm_list_event_promotions($event_id, false) : [];
    $promotions = [];
    $active_package_count = 0;
    foreach ($promotions_raw as $promotion) {
        $present = rm_present_event_promotion($promotion);
        $present['package_href'] = $selected_event_code !== ''
            ? rm_registration_url([
                'event_code' => $selected_event_code,
                'package'    => $present['slug'],
            ])
            : '';
        $present['valid_from_local'] = rm_datetime_local_value($present['valid_from'] ?? null);
        $present['valid_until_local'] = rm_datetime_local_value($present['valid_until'] ?? null);
        $present['valid_from_display'] = !empty($present['valid_from'])
            ? rm_format_payment_transaction_datetime((string) $present['valid_from'])
            : '—';
        $present['valid_until_display'] = !empty($present['valid_until'])
            ? rm_format_payment_transaction_datetime((string) $present['valid_until'])
            : '—';
        if ($present['is_active']) {
            $active_package_count++;
        }
        $promotions[] = $present;
    }

    $flash = rm_consume_event_profile_flash(rm_get_event_profile_flash_key());
    $price_num = function_exists('rm_event_registration_price')
        ? rm_event_registration_price($selected_event)
        : (float) ($selected_event['price'] ?? 0);
    $price_display = $price_num > 0
        ? '$' . number_format_i18n($price_num, 2)
        : 'FREE';

    return [
        'selected_event'              => $selected_event,
        'selected_event_code'         => $selected_event_code,
        'selected_event_id'           => $event_id,
        'event_not_found'             => false,
        'profile_error'               => '',
        'uses_v2'                     => $uses_v2,
        'registration_config'         => $registration_config,
        'registration_config_present' => rm_present_registration_config($registration_config),
        'promotions'                  => $promotions,
        'active_package_count'        => $active_package_count,
        'summary'                     => $summary,
        'package_summary'             => $package_summary,
        'event_card'                  => $card,
        'event_is_free'               => rm_event_is_free($selected_event),
        'event_price_display'         => $price_display,
        'registrants_href'            => add_query_arg($registrants_args, $page_url),
        'registration_href'           => $card['registration_href'],
        'profile_flash'               => $flash,
        'registration_modes'          => rm_registration_modes(),
        'form_presets'                => rm_form_presets(),
    ];
}
