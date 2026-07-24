<?php

/**
 * Post-payment fill-later for group_flat packages (add remaining members).
 */

const RM_GROUP_MANAGE_TOKEN_TTL = 2592000; // 30 days
const RM_GROUP_MANAGE_SESSION_TTL = 28800; // 8 hours
const RM_GROUP_MANAGE_COOKIE = 'rm_group_manage';
const RM_GROUP_ADD_RATE_LIMIT = 30;
const RM_GROUP_ADD_RATE_WINDOW = 3600;

/**
 * @param mixed $snapshot
 * @return array<string, mixed>
 */
function rm_group_decode_pricing_snapshot($snapshot): array
{
    if (is_array($snapshot)) {
        return $snapshot;
    }

    if (!is_string($snapshot) || $snapshot === '') {
        return [];
    }

    $decoded = json_decode($snapshot, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * Group limits frozen at checkout (or fallback to promotion / defaults).
 *
 * @param array<string, mixed> $header
 * @return array{min: int, max: int, require_all_members: bool}
 */
function rm_group_limits_from_header(array $header): array
{
    $snapshot = rm_group_decode_pricing_snapshot($header['pricing_snapshot'] ?? null);
    $group = is_array($snapshot['group'] ?? null) ? $snapshot['group'] : [];

    if ($group !== []) {
        $min = max(1, (int) ($group['min'] ?? 1));
        $max = max($min, (int) ($group['max'] ?? $min));
        $require_all = !empty($group['require_all_members']);

        return [
            'min'                 => $min,
            'max'                 => $max,
            'require_all_members' => $require_all,
        ];
    }

    $promotion_id = isset($header['event_promotion_id']) ? (int) $header['event_promotion_id'] : 0;
    if ($promotion_id > 0 && function_exists('rm_fetch_event_promotion_by_id')) {
        $promotion = rm_fetch_event_promotion_by_id($promotion_id);
        if (is_array($promotion) && $promotion !== []) {
            return rm_promotion_group_limits($promotion);
        }
    }

    $member_count = max(1, (int) ($header['member_count'] ?? 1));

    return [
        'min'                 => $member_count,
        'max'                 => $member_count,
        'require_all_members' => true,
    ];
}

/**
 * Whether a paid group_flat registration still has open member slots.
 *
 * @param array<string, mixed> $header
 */
function rm_group_is_incomplete(array $header): bool
{
    $mode = (string) ($header['registration_mode'] ?? '');
    if ($mode !== RM_REGISTRATION_MODE_GROUP_FLAT) {
        return false;
    }

    $payment_status = strtolower(trim((string) ($header['payment_status'] ?? '')));
    if (!in_array($payment_status, ['paid', 'free'], true)) {
        return false;
    }

    $limits = rm_group_limits_from_header($header);
    if (!empty($limits['require_all_members'])) {
        return false;
    }

    $member_count = (int) ($header['member_count'] ?? 0);

    return $member_count < (int) $limits['max'];
}

/**
 * @param array<string, mixed> $header
 * @return array{incomplete: bool, member_count: int, member_max: int, slots_remaining: int}
 */
function rm_group_incomplete_meta(array $header): array
{
    $limits = rm_group_limits_from_header($header);
    $member_count = max(0, (int) ($header['member_count'] ?? 0));
    $member_max = (int) $limits['max'];
    $incomplete = rm_group_is_incomplete($header);

    return [
        'incomplete'      => $incomplete,
        'member_count'    => $member_count,
        'member_max'      => $member_max,
        'slots_remaining' => $incomplete ? max(0, $member_max - $member_count) : 0,
    ];
}

function rm_group_manage_signing_key(): string
{
    return 'rm-group-manage|' . wp_salt('auth');
}

function rm_group_manage_token_create(int $registration_id, int $ttl = RM_GROUP_MANAGE_TOKEN_TTL): string
{
    $registration_id = max(0, $registration_id);
    $exp = time() + max(60, $ttl);
    $payload = wp_json_encode([
        'id'  => $registration_id,
        'exp' => $exp,
    ]);
    $payload_b64 = rtrim(strtr(base64_encode((string) $payload), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $payload_b64, rm_group_manage_signing_key());

    return $payload_b64 . '.' . $sig;
}

function rm_group_manage_token_verify(string $token): ?int
{
    $token = trim($token);
    if ($token === '' || strpos($token, '.') === false) {
        return null;
    }

    [$payload_b64, $sig] = explode('.', $token, 2);
    if ($payload_b64 === '' || $sig === '') {
        return null;
    }

    $expected = hash_hmac('sha256', $payload_b64, rm_group_manage_signing_key());
    if (!hash_equals($expected, $sig)) {
        return null;
    }

    $json = base64_decode(strtr($payload_b64, '-_', '+/'), true);
    if ($json === false) {
        return null;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }

    $registration_id = isset($data['id']) ? (int) $data['id'] : 0;
    $exp = isset($data['exp']) ? (int) $data['exp'] : 0;
    if ($registration_id < 1 || $exp < time()) {
        return null;
    }

    return $registration_id;
}

/**
 * @param array<string, string|int> $args
 */
function rm_manage_group_url(array $args = []): string
{
    $defaults = [
        'action' => 'manage-group',
    ];

    return add_query_arg(array_merge($defaults, $args), rm_page_url());
}

/**
 * @param array<string, mixed> $header
 */
function rm_group_manage_url_for_header(array $header, string $event_code = ''): string
{
    $registration_id = isset($header['id']) ? (int) $header['id'] : 0;
    if ($registration_id < 1) {
        return '';
    }

    if ($event_code === '') {
        $event_id = isset($header['event_id']) ? (int) $header['event_id'] : 0;
        if ($event_id > 0 && function_exists('rm_get_event_by_id')) {
            $event = rm_get_event_by_id($event_id);
            if (is_array($event)) {
                $event_code = trim((string) ($event['programCode'] ?? ''));
            }
        }
    }

    $args = [
        't' => rm_group_manage_token_create($registration_id),
    ];
    if ($event_code !== '') {
        $args['event_code'] = $event_code;
    }

    return rm_manage_group_url($args);
}

function rm_group_manage_get_token_from_request(): string
{
    if (isset($_GET['t'])) {
        return sanitize_text_field(wp_unslash((string) $_GET['t']));
    }

    if (isset($_POST['t'])) {
        return sanitize_text_field(wp_unslash((string) $_POST['t']));
    }

    return '';
}

function rm_group_manage_set_session(int $registration_id): void
{
    if ($registration_id < 1 || headers_sent()) {
        return;
    }

    $token = rm_group_manage_token_create($registration_id, RM_GROUP_MANAGE_SESSION_TTL);
    $secure = is_ssl();
    setcookie(
        RM_GROUP_MANAGE_COOKIE,
        $token,
        [
            'expires'  => time() + RM_GROUP_MANAGE_SESSION_TTL,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
    $_COOKIE[RM_GROUP_MANAGE_COOKIE] = $token;
}

function rm_group_manage_clear_session(): void
{
    if (headers_sent()) {
        return;
    }

    $secure = is_ssl();
    setcookie(
        RM_GROUP_MANAGE_COOKIE,
        '',
        [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
    unset($_COOKIE[RM_GROUP_MANAGE_COOKIE]);
}

function rm_group_manage_session_registration_id(): ?int
{
    if (!isset($_COOKIE[RM_GROUP_MANAGE_COOKIE])) {
        return null;
    }

    return rm_group_manage_token_verify(
        sanitize_text_field(wp_unslash((string) $_COOKIE[RM_GROUP_MANAGE_COOKIE]))
    );
}

/**
 * @return array<string, mixed>|null
 */
function rm_group_manage_fetch_header(int $registration_id): ?array
{
    global $wpdb;

    if ($registration_id < 1 || !rm_event_registration_tables_exist()) {
        return null;
    }

    $row = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM `event_registration` WHERE `id` = %d LIMIT 1',
            $registration_id
        ),
        ARRAY_A
    );

    return is_array($row) && $row !== [] ? $row : null;
}

/**
 * @return array<string, mixed>|null
 */
function rm_group_manage_find_by_credentials(string $confirmation_number, string $email, int $event_id = 0): ?array
{
    global $wpdb;

    $confirmation_number = sanitize_text_field(trim($confirmation_number));
    $email = sanitize_email(trim($email));
    if ($confirmation_number === '' || $email === '' || !rm_event_registration_tables_exist()) {
        return null;
    }

    if ($event_id > 0) {
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM `event_registration`
                 WHERE `confirmation_number` = %s
                   AND `primary_email` = %s
                   AND `event_id` = %d
                 LIMIT 1',
                $confirmation_number,
                $email,
                $event_id
            ),
            ARRAY_A
        );
    } else {
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM `event_registration`
                 WHERE `confirmation_number` = %s
                   AND `primary_email` = %s
                 LIMIT 1',
                $confirmation_number,
                $email
            ),
            ARRAY_A
        );
    }

    return is_array($row) && $row !== [] ? $row : null;
}

/**
 * @return list<array<string, mixed>>
 */
function rm_group_manage_load_member_lines(int $registration_id): array
{
    global $wpdb;

    if ($registration_id < 1) {
        return [];
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT * FROM `event_registrant`
             WHERE `registration_id` = %d
               AND (`role` IS NULL OR `role` <> %s)
             ORDER BY `member_index` ASC, `id` ASC',
            $registration_id,
            'addon'
        ),
        ARRAY_A
    );

    if (!is_array($rows)) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $out[] = $row;
        }
    }

    return $out;
}

/**
 * Resolve access via magic link token, session cookie, or CN + email POST.
 *
 * @return array{
 *   ok: bool,
 *   error: string,
 *   header: array<string, mixed>|null,
 *   needs_login: bool,
 *   can_add: bool
 * }
 */
function rm_group_manage_resolve_access(int $event_id = 0): array
{
    $fail = static function (string $error, bool $needs_login = true): array {
        return [
            'ok'          => false,
            'error'       => $error,
            'header'      => null,
            'needs_login' => $needs_login,
            'can_add'     => false,
        ];
    };

    $header = null;

    $token = rm_group_manage_get_token_from_request();
    if ($token !== '') {
        $registration_id = rm_group_manage_token_verify($token);
        if ($registration_id !== null) {
            $header = rm_group_manage_fetch_header($registration_id);
        }
    }

    if ($header === null) {
        $session_id = rm_group_manage_session_registration_id();
        if ($session_id !== null) {
            $header = rm_group_manage_fetch_header($session_id);
        }
    }

    if ($header === null && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['rm_group_manage_action'])
            ? sanitize_key(wp_unslash((string) $_POST['rm_group_manage_action']))
            : '';
        if ($action === 'login') {
            $confirmation = isset($_POST['confirmation_number'])
                ? sanitize_text_field(wp_unslash((string) $_POST['confirmation_number']))
                : '';
            $email = isset($_POST['email'])
                ? sanitize_email(wp_unslash((string) $_POST['email']))
                : '';
            $header = rm_group_manage_find_by_credentials($confirmation, $email, $event_id);
            if ($header === null) {
                return $fail('No registration found for that confirmation number and email.');
            }
            rm_group_manage_set_session((int) $header['id']);
        }
    }

    if ($header === null) {
        return $fail('', true);
    }

    if ($event_id > 0 && (int) ($header['event_id'] ?? 0) !== $event_id) {
        return $fail('This registration does not belong to this event.');
    }

    $payment_status = strtolower(trim((string) ($header['payment_status'] ?? '')));
    if (!in_array($payment_status, ['paid', 'free'], true)) {
        return $fail('This registration is not paid yet.', false);
    }

    $mode = (string) ($header['registration_mode'] ?? '');
    if ($mode !== RM_REGISTRATION_MODE_GROUP_FLAT) {
        return $fail('Member management is only available for flat group packages.', false);
    }

    $limits = rm_group_limits_from_header($header);
    if (!empty($limits['require_all_members'])) {
        return $fail('This package required all members at checkout.', false);
    }

    rm_group_manage_set_session((int) $header['id']);

    return [
        'ok'          => true,
        'error'       => '',
        'header'      => $header,
        'needs_login' => false,
        'can_add'     => rm_group_is_incomplete($header),
    ];
}

function rm_group_add_rate_limited(int $registration_id): bool
{
    $key = 'rm_group_add_' . $registration_id;
    $count = (int) get_transient($key);
    if ($count >= RM_GROUP_ADD_RATE_LIMIT) {
        return true;
    }

    set_transient($key, $count + 1, RM_GROUP_ADD_RATE_WINDOW);

    return false;
}

/**
 * @param array<string, mixed> $header
 * @param array<string, mixed> $event
 * @param array<string, mixed> $member_responses
 * @return array{ok: bool, error: string, form_errors: array<string, string>}
 */
function rm_group_add_member(array $header, array $event, array $member_responses): array
{
    global $wpdb;

    $registration_id = isset($header['id']) ? (int) $header['id'] : 0;
    $event_id = isset($header['event_id']) ? (int) $header['event_id'] : 0;

    if ($registration_id < 1 || $event_id < 1) {
        return [
            'ok'          => false,
            'error'       => 'Invalid registration.',
            'form_errors' => [],
        ];
    }

    if (rm_group_add_rate_limited($registration_id)) {
        return [
            'ok'          => false,
            'error'       => 'Too many add-member attempts. Please try again later.',
            'form_errors' => [],
        ];
    }

    if (!rm_group_is_incomplete($header)) {
        return [
            'ok'          => false,
            'error'       => 'This group roster is already complete.',
            'form_errors' => [],
        ];
    }

    $schema = rm_parse_form_schema($event);
    $build = rm_build_member_rows_from_responses($schema, [$member_responses]);
    if (!$build['ok']) {
        $form_errors = [];
        foreach ($build['form_errors'] as $key => $message) {
            $form_errors[preg_replace('/^member_0_/', '', $key) ?? $key] = $message;
        }

        return [
            'ok'          => false,
            'error'       => $build['error'],
            'form_errors' => $form_errors,
        ];
    }

    $member_row = $build['member_rows'][0] ?? null;
    if (!is_array($member_row)) {
        return [
            'ok'          => false,
            'error'       => 'Invalid member data.',
            'form_errors' => [],
        ];
    }

    $source = function_exists('rm_infer_event_source')
        ? rm_infer_event_source($event_id)
        : '';

    $wpdb->query('START TRANSACTION');

    $locked = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM `event_registration` WHERE `id` = %d FOR UPDATE',
            $registration_id
        ),
        ARRAY_A
    );

    if (!is_array($locked) || $locked === []) {
        $wpdb->query('ROLLBACK');

        return [
            'ok'          => false,
            'error'       => 'Registration could not be found.',
            'form_errors' => [],
        ];
    }

    if (!rm_group_is_incomplete($locked)) {
        $wpdb->query('ROLLBACK');

        return [
            'ok'          => false,
            'error'       => 'This group roster is already complete.',
            'form_errors' => [],
        ];
    }

    $max_index = (int) $wpdb->get_var(
        $wpdb->prepare(
            'SELECT COALESCE(MAX(`member_index`), -1) FROM `event_registrant`
             WHERE `registration_id` = %d
               AND (`role` IS NULL OR `role` <> %s)',
            $registration_id,
            'addon'
        )
    );
    $next_index = $max_index + 1;

    $order_result = rm_allocate_registration_order_number($event_id, $source);
    if (!$order_result['ok']) {
        $wpdb->query('ROLLBACK');

        return [
            'ok'          => false,
            'error'       => $order_result['error'] !== ''
                ? $order_result['error']
                : 'Could not allocate a registration number.',
            'form_errors' => [],
        ];
    }

    $custom_json = $member_row['custom'] !== []
        ? wp_json_encode($member_row['custom'])
        : null;

    $line = [
        'registration_id'  => $registration_id,
        'event_id'         => $event_id,
        'member_index'     => $next_index,
        'role'             => 'member',
        'order_number'     => $order_result['order_number'],
        'nric'             => $member_row['core']['nric'],
        'title'            => $member_row['core']['title'],
        'christian_name'   => $member_row['core']['christian_name'],
        'given_name'       => $member_row['core']['given_name'],
        'family_name'      => $member_row['core']['family_name'],
        'certificate_name' => $member_row['core']['certificate_name'],
        'email'            => $member_row['core']['email'],
        'contact'          => $member_row['core']['contact'],
        'address1'         => $member_row['core']['address1'],
        'address2'         => $member_row['core']['address2'],
        'postcode'         => $member_row['core']['postcode'],
        'church_name'      => $member_row['core']['church_name'],
        'custom_responses' => $custom_json,
        'unit_price'       => 0.0,
        'discount_percent' => 0.0,
        'status'           => 'confirmed',
        'created_at'       => current_time('mysql'),
        'updated_at'       => current_time('mysql'),
    ];

    $inserted = $wpdb->insert('event_registrant', $line);
    if (!$inserted) {
        $wpdb->query('ROLLBACK');

        return [
            'ok'          => false,
            'error'       => $wpdb->last_error !== ''
                ? $wpdb->last_error
                : 'Could not save the new member.',
            'form_errors' => [],
        ];
    }

    $updated = $wpdb->query(
        $wpdb->prepare(
            'UPDATE `event_registration`
             SET `member_count` = `member_count` + 1,
                 `updated_at` = %s
             WHERE `id` = %d',
            current_time('mysql'),
            $registration_id
        )
    );

    if ($updated === false) {
        $wpdb->query('ROLLBACK');

        return [
            'ok'          => false,
            'error'       => 'Could not update the group roster.',
            'form_errors' => [],
        ];
    }

    $wpdb->query('COMMIT');

    return [
        'ok'          => true,
        'error'       => '',
        'form_errors' => [],
    ];
}

/**
 * Present member lines for the manage-group UI.
 *
 * @param list<array<string, mixed>> $lines
 * @return list<array<string, mixed>>
 */
function rm_group_manage_present_members(array $lines): array
{
    $out = [];
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }

        if (function_exists('rm_email_present_person_line')) {
            $out[] = rm_email_present_person_line($line);
            continue;
        }

        $given = trim((string) ($line['given_name'] ?? ''));
        $family = trim((string) ($line['family_name'] ?? ''));
        $christian = trim((string) ($line['christian_name'] ?? ''));
        $full = trim($christian !== '' ? $christian . ' ' . $family : $given . ' ' . $family);
        $role = (string) ($line['role'] ?? 'member');

        $out[] = [
            'full_name'    => $full !== '' ? $full : 'Registrant',
            'email'        => (string) ($line['email'] ?? ''),
            'contact'      => (string) ($line['contact'] ?? ''),
            'church_name'  => (string) ($line['church_name'] ?? ''),
            'order_number' => (string) ($line['order_number'] ?? ''),
            'role'         => $role,
            'role_label'   => $role === 'primary' ? 'Leader' : 'Member',
        ];
    }

    return $out;
}

/**
 * @return array<string, mixed>
 */
function rm_build_manage_group_context(): array
{
    $event_code = rm_get_event_code();
    $page_url = rm_manage_group_url($event_code !== '' ? ['event_code' => $event_code] : []);

    $context = [
        'view_action'       => 'manage-group',
        'is_public_layout'  => true,
        'page_url'          => $page_url,
        'event_code'        => $event_code,
        'event'             => null,
        'event_present'     => null,
        'event_not_found'   => false,
        'promotion_present' => null,
        'error_message'     => '',
        'success_message'   => '',
        'needs_login'       => true,
        'access_ok'         => false,
        'can_add'           => false,
        'header'            => null,
        'members'           => [],
        'group_meta'        => [
            'incomplete'      => false,
            'member_count'    => 0,
            'member_max'      => 0,
            'slots_remaining' => 0,
        ],
        'package_label'     => '',
        'confirmation_number' => '',
        'form_schema'       => ['fields' => []],
        'form_errors'       => [],
        'member_input'      => [],
        'registration_config' => [],
        'event_currency'    => 'SGD',
        'manage_token'      => rm_group_manage_get_token_from_request(),
    ];

    if ($event_code === '') {
        $context['error_message'] = 'No event was selected. Please use a valid manage-group link.';
        $context['event_not_found'] = true;

        return $context;
    }

    $event_fetch = rm_fetch_event($event_code);
    $event = is_array($event_fetch['event']) && $event_fetch['event'] !== []
        ? $event_fetch['event']
        : null;

    if ($event === null) {
        $context['error_message'] = $event_fetch['error'] !== ''
            ? $event_fetch['error']
            : 'This event could not be found.';
        $context['event_not_found'] = true;

        return $context;
    }

    $context['event'] = $event;
    $context['event_present'] = rm_present_registration_event($event);
    $context['event_currency'] = rm_registration_currency($event);
    $context['form_schema'] = rm_parse_form_schema($event);
    $context['registration_config'] = rm_parse_registration_config($event);

    $event_id = isset($event['id']) ? (int) $event['id'] : 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nonce_ok = isset($_POST['rm_group_manage_nonce'])
            && wp_verify_nonce(
                sanitize_text_field(wp_unslash((string) $_POST['rm_group_manage_nonce'])),
                'rm_group_manage'
            );

        if (!$nonce_ok) {
            $context['error_message'] = 'Your session has expired. Please try again.';
            $context['needs_login'] = true;

            return $context;
        }
    }

    $access = rm_group_manage_resolve_access($event_id);
    $context['needs_login'] = !empty($access['needs_login']);
    $context['access_ok'] = !empty($access['ok']);
    $context['can_add'] = !empty($access['can_add']);

    if (!$access['ok']) {
        if ($access['error'] !== '') {
            $context['error_message'] = $access['error'];
        }

        return $context;
    }

    $header = $access['header'];
    $context['header'] = $header;
    $context['confirmation_number'] = (string) ($header['confirmation_number'] ?? '');
    $context['package_label'] = function_exists('rm_package_label_from_header')
        ? rm_package_label_from_header($header)
        : '';
    $context['group_meta'] = rm_group_incomplete_meta($header);
    $context['can_add'] = !empty($context['group_meta']['incomplete']);
    $context['members'] = rm_group_manage_present_members(
        rm_group_manage_load_member_lines((int) $header['id'])
    );

    $token = rm_group_manage_get_token_from_request();
    if ($token === '' && is_array($header)) {
        $token = rm_group_manage_token_create((int) $header['id']);
    }
    $context['manage_token'] = $token;
    $context['page_url'] = rm_manage_group_url([
        'event_code' => $event_code,
        't'          => $token,
    ]);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return $context;
    }

    $post_action = isset($_POST['rm_group_manage_action'])
        ? sanitize_key(wp_unslash((string) $_POST['rm_group_manage_action']))
        : '';

    if ($post_action === 'login') {
        $context['success_message'] = 'Group registration loaded.';

        return $context;
    }

    if ($post_action !== 'add_member') {
        return $context;
    }

    if (!$context['can_add']) {
        $context['error_message'] = 'This group roster is already complete.';

        return $context;
    }

    $member_responses = [];
    if (isset($_POST['member_json'])) {
        $raw = wp_unslash((string) $_POST['member_json']);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $member_responses = $decoded;
        }
    }

    if ($member_responses === []) {
        $member_responses = rm_form_responses_from_post(
            $context['form_schema'],
            '',
            rm_registration_coverage($context['registration_config'])
        );
    }

    $context['member_input'] = $member_responses;

    $result = rm_group_add_member($header, $event, $member_responses);
    if (!$result['ok']) {
        $context['error_message'] = $result['error'];
        $context['form_errors'] = $result['form_errors'];

        return $context;
    }

    $fresh = rm_group_manage_fetch_header((int) $header['id']);
    if (is_array($fresh)) {
        $header = $fresh;
        $context['header'] = $header;
        $context['group_meta'] = rm_group_incomplete_meta($header);
        $context['can_add'] = !empty($context['group_meta']['incomplete']);
        $context['members'] = rm_group_manage_present_members(
            rm_group_manage_load_member_lines((int) $header['id'])
        );
    }

    $context['member_input'] = [];
    $context['form_errors'] = [];
    $context['success_message'] = $context['can_add']
        ? 'Member added successfully. You can add more remaining members.'
        : 'Member added successfully. Your group roster is now complete.';

    return $context;
}
