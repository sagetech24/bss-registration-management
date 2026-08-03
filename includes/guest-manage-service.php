<?php

/**
 * Post-registration guest add-on flow (lookup + separate checkout).
 */

const RM_GUEST_MANAGE_SESSION_TTL = 28800; // 8 hours
const RM_GUEST_MANAGE_COOKIE = 'rm_guest_manage';
const RM_GUEST_LOOKUP_RATE_LIMIT = 10;
const RM_GUEST_LOOKUP_RATE_WINDOW = 3600;

function rm_guest_manage_signing_key(): string
{
    return 'rm-guest-manage|' . wp_salt('auth');
}

function rm_guest_manage_token_create(int $registration_id, int $ttl = RM_GUEST_MANAGE_SESSION_TTL): string
{
    $registration_id = max(0, $registration_id);
    $exp = time() + max(60, $ttl);
    $payload = wp_json_encode([
        'id'  => $registration_id,
        'exp' => $exp,
    ]);
    $payload_b64 = rtrim(strtr(base64_encode((string) $payload), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $payload_b64, rm_guest_manage_signing_key());

    return $payload_b64 . '.' . $sig;
}

function rm_guest_manage_token_verify(string $token): ?int
{
    $token = trim($token);
    if ($token === '' || strpos($token, '.') === false) {
        return null;
    }

    [$payload_b64, $sig] = explode('.', $token, 2);
    if ($payload_b64 === '' || $sig === '') {
        return null;
    }

    $expected = hash_hmac('sha256', $payload_b64, rm_guest_manage_signing_key());
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

function rm_guest_manage_set_session(int $registration_id): void
{
    if ($registration_id < 1 || headers_sent()) {
        return;
    }

    $token = rm_guest_manage_token_create($registration_id, RM_GUEST_MANAGE_SESSION_TTL);
    $secure = is_ssl();
    setcookie(
        RM_GUEST_MANAGE_COOKIE,
        $token,
        [
            'expires'  => time() + RM_GUEST_MANAGE_SESSION_TTL,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
    $_COOKIE[RM_GUEST_MANAGE_COOKIE] = $token;
}

function rm_guest_manage_clear_session(): void
{
    if (headers_sent()) {
        return;
    }

    $secure = is_ssl();
    setcookie(
        RM_GUEST_MANAGE_COOKIE,
        '',
        [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
    unset($_COOKIE[RM_GUEST_MANAGE_COOKIE]);
}

function rm_guest_manage_session_registration_id(): ?int
{
    if (!isset($_COOKIE[RM_GUEST_MANAGE_COOKIE])) {
        return null;
    }

    return rm_guest_manage_token_verify(
        sanitize_text_field(wp_unslash((string) $_COOKIE[RM_GUEST_MANAGE_COOKIE]))
    );
}

/**
 * Guest add-ons use sub-order numbers ({primary}-01); package slots with role=addon do not.
 *
 * @param array<string, mixed> $row
 */
function rm_guest_is_guest_addon_row(array $row): bool
{
    if (($row['role'] ?? '') !== 'addon') {
        return false;
    }

    $order_number = trim((string) ($row['order_number'] ?? ''));

    return $order_number !== '' && preg_match('/-\d{2}$/', $order_number) === 1;
}

/**
 * Count confirmed guest add-ons on a registration (excludes group_per_head package slots).
 */
function rm_v2_count_registration_guest_addons(int $registration_id): int
{
    global $wpdb;

    if ($registration_id < 1) {
        return 0;
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT `order_number`, `role`, `status` FROM `event_registrant`
             WHERE `registration_id` = %d AND `role` = %s AND `status` <> %s',
            $registration_id,
            'addon',
            'cancelled'
        ),
        ARRAY_A
    );

    if (!is_array($rows)) {
        return 0;
    }

    $count = 0;
    foreach ($rows as $row) {
        if (is_array($row) && rm_guest_is_guest_addon_row($row)) {
            ++$count;
        }
    }

    return $count;
}

/**
 * Pending guest slots reserved by unpaid addon purchases on a registration.
 */
function rm_v2_count_pending_addon_purchase_guests(int $registration_id): int
{
    global $wpdb;

    if ($registration_id < 1 || !rm_event_addon_purchase_schema_ready()) {
        return 0;
    }

    $sum = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT COALESCE(SUM(`guest_count`), 0) FROM `event_addon_purchase`
             WHERE `registration_id` = %d AND `payment_status` = %s',
            $registration_id,
            'pending'
        )
    );

    return max(0, (int) $sum);
}

/**
 * Pending guest slots across all unpaid addon purchases for an event.
 */
function rm_v2_count_pending_event_addon_purchase_guests(int $event_id): int
{
    global $wpdb;

    if ($event_id < 1 || !rm_event_addon_purchase_schema_ready()) {
        return 0;
    }

    $sum = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT COALESCE(SUM(`guest_count`), 0) FROM `event_addon_purchase`
             WHERE `event_id` = %d AND `payment_status` = %s',
            $event_id,
            'pending'
        )
    );

    return max(0, (int) $sum);
}

/**
 * Next member_index for appending guest lines to a registration.
 */
function rm_v2_next_guest_member_offset(int $registration_id): int
{
    global $wpdb;

    if ($registration_id < 1) {
        return 0;
    }

    $max = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT MAX(`member_index`) FROM `event_registrant` WHERE `registration_id` = %d',
            $registration_id
        )
    );

    if ($max === null) {
        return 0;
    }

    return max(0, (int) $max) + 1;
}

/**
 * @return array<string, mixed>|null
 */
function rm_guest_manage_fetch_header(int $registration_id): ?array
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
function rm_guest_manage_find_by_credentials(string $confirmation_number, string $email, int $event_id = 0): ?array
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
                 WHERE LOWER(`confirmation_number`) = LOWER(%s)
                   AND LOWER(`primary_email`) = LOWER(%s)
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
                 WHERE LOWER(`confirmation_number`) = LOWER(%s)
                   AND LOWER(`primary_email`) = LOWER(%s)
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
 * Whether post-registration add-ons are allowed for an event.
 *
 * @param array<string, mixed> $event
 */
function rm_guest_post_registration_allowed(array $event): bool
{
    $config = rm_parse_registration_config($event);
    $guests = is_array($config['guests'] ?? null) ? $config['guests'] : [];

    if (empty($guests['enabled'])) {
        return false;
    }

    if (array_key_exists('allow_post_registration', $guests)) {
        return !empty($guests['allow_post_registration']);
    }

    return true;
}

/**
 * @param array<string, mixed> $event
 * @param array<string, mixed> $header
 * @return array{
 *   guest_count: int,
 *   guest_max: int,
 *   reg_remaining: int|null,
 *   event_remaining: int|null,
 *   slots_remaining: int|null,
 *   can_add: bool,
 *   guest_price: float,
 *   label_singular: string,
 *   label_plural: string
 * }
 */
function rm_guest_manage_capacity_meta(array $event, array $header): array
{
    $config = rm_parse_registration_config($event);
    $guests = is_array($config['guests'] ?? null) ? $config['guests'] : [];
    $registration_id = isset($header['id']) ? (int) $header['id'] : 0;

    $guest_max = max(0, (int) ($guests['max'] ?? 0));
    $existing = rm_v2_count_registration_guest_addons($registration_id);
    $pending = rm_v2_count_pending_addon_purchase_guests($registration_id);
    $used_on_reg = $existing + $pending;

    $reg_remaining = null;
    if ($guest_max > 0) {
        $reg_remaining = max(0, $guest_max - $used_on_reg);
    }

    $capacity = rm_guest_event_capacity($event);
    $event_remaining = $capacity['remaining'];

    $slots_remaining = null;
    if ($reg_remaining !== null && $event_remaining !== null) {
        $slots_remaining = min($reg_remaining, $event_remaining);
    } elseif ($reg_remaining !== null) {
        $slots_remaining = $reg_remaining;
    } elseif ($event_remaining !== null) {
        $slots_remaining = $event_remaining;
    }

    $can_add = $slots_remaining === null || $slots_remaining > 0;

    return [
        'guest_count'     => $existing,
        'guest_max'       => $guest_max,
        'reg_remaining'   => $reg_remaining,
        'event_remaining' => $event_remaining,
        'slots_remaining' => $slots_remaining,
        'can_add'         => $can_add,
        'guest_price'     => max(0, (float) ($guests['price'] ?? 0)),
        'label_singular'  => (string) ($guests['label_singular'] ?? 'Guest'),
        'label_plural'    => (string) ($guests['label_plural'] ?? 'Guests'),
    ];
}

/**
 * Guest form schema adjusted for post-registration capacity on a specific registration.
 *
 * @param array<string, mixed> $event
 * @param array<string, mixed> $header
 * @return array<string, mixed>
 */
function rm_parse_guest_form_schema_for_registration(array $event, array $header): array
{
    $schema = rm_parse_guest_form_schema($event);
    $meta = rm_guest_manage_capacity_meta($event, $header);

    if (empty($schema['enabled'])) {
        return $schema;
    }

    $slots = $meta['slots_remaining'];
    if ($slots !== null) {
        if ($slots <= 0) {
            $schema['enabled'] = false;
            $schema['min'] = 0;
            $schema['max'] = 0;

            return $schema;
        }

        if ((int) ($schema['max'] ?? 0) > 0) {
            $schema['max'] = min((int) $schema['max'], $slots);
        } else {
            $schema['max'] = $slots;
        }
    }

    $schema['min'] = 1;
    $schema['price'] = $meta['guest_price'];
    $schema['label_singular'] = $meta['label_singular'];
    $schema['label_plural'] = $meta['label_plural'];

    return $schema;
}

function rm_guest_manage_lookup_rate_limit_key(): string
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR'])) : 'unknown';

    return 'rm_guest_lookup_' . md5($ip);
}

function rm_guest_manage_lookup_rate_limited(): bool
{
    $key = rm_guest_manage_lookup_rate_limit_key();
    $count = (int) get_transient($key);

    return $count >= RM_GUEST_LOOKUP_RATE_LIMIT;
}

function rm_guest_manage_record_lookup_attempt(): void
{
    $key = rm_guest_manage_lookup_rate_limit_key();
    $count = (int) get_transient($key);
    set_transient($key, $count + 1, RM_GUEST_LOOKUP_RATE_WINDOW);
}

/**
 * @return array{
 *   ok: bool,
 *   error: string,
 *   header: array<string, mixed>|null,
 *   needs_login: bool,
 *   can_add: bool
 * }
 */
function rm_guest_manage_resolve_access(int $event_id = 0, string $locale = RM_LOCALE_EN): array
{
    $fail = static function (string $error_key, bool $needs_login = true) use ($locale): array {
        return [
            'ok'          => false,
            'error'       => $error_key !== '' ? rm__($error_key, $locale) : '',
            'header'      => null,
            'needs_login' => $needs_login,
            'can_add'     => false,
        ];
    };

    $header = null;
    $session_id = rm_guest_manage_session_registration_id();
    if ($session_id !== null) {
        $header = rm_guest_manage_fetch_header($session_id);
    }

    if ($header === null && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['rm_guest_manage_action'])
            ? sanitize_key(wp_unslash((string) $_POST['rm_guest_manage_action']))
            : '';
        if ($action === 'login') {
            if (rm_guest_manage_lookup_rate_limited()) {
                return $fail('guest_manage.error.rate_limited', false);
            }

            $confirmation = isset($_POST['confirmation_number'])
                ? sanitize_text_field(wp_unslash((string) $_POST['confirmation_number']))
                : '';
            $email = isset($_POST['email'])
                ? sanitize_email(wp_unslash((string) $_POST['email']))
                : '';

            rm_guest_manage_record_lookup_attempt();
            $header = rm_guest_manage_find_by_credentials($confirmation, $email, $event_id);
            if ($header === null) {
                return $fail('guest_manage.error.login_not_found');
            }

            rm_guest_manage_set_session((int) $header['id']);
        }
    }

    if ($header === null) {
        return $fail('', true);
    }

    if ($event_id > 0 && (int) ($header['event_id'] ?? 0) !== $event_id) {
        return $fail('guest_manage.error.wrong_event');
    }

    $payment_status = strtolower(trim((string) ($header['payment_status'] ?? '')));
    if (!in_array($payment_status, ['paid', 'free'], true)) {
        return $fail('guest_manage.error.not_paid', false);
    }

    return [
        'ok'          => true,
        'error'       => '',
        'header'      => $header,
        'needs_login' => false,
        'can_add'     => true,
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function rm_guest_manage_load_guest_lines(int $registration_id): array
{
    global $wpdb;

    if ($registration_id < 1) {
        return [];
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT * FROM `event_registrant`
             WHERE `registration_id` = %d AND `role` = %s AND `status` <> %s
             ORDER BY `member_index` ASC, `id` ASC',
            $registration_id,
            'addon',
            'cancelled'
        ),
        ARRAY_A
    );

    if (!is_array($rows)) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        if (is_array($row) && rm_guest_is_guest_addon_row($row)) {
            $out[] = $row;
        }
    }

    return $out;
}

/**
 * @param list<array<string, mixed>> $rows
 * @param array{fields?: list<array<string, mixed>>, label_singular?: string} $guest_schema
 * @return list<array<string, mixed>>
 */
function rm_guest_manage_present_guests(array $rows, array $guest_schema = [], string $locale = RM_LOCALE_EN): array
{
    $guest_fields = is_array($guest_schema['fields'] ?? null) ? $guest_schema['fields'] : [];
    $label_singular = trim((string) ($guest_schema['label_singular'] ?? 'Guest'));
    if ($label_singular === '') {
        $label_singular = 'Guest';
    }

    $out = [];
    foreach ($rows as $index => $row) {
        if (!is_array($row)) {
            continue;
        }

        if (function_exists('rm_email_present_guest_line')) {
            $presented = rm_email_present_guest_line($row, $guest_fields, $label_singular, (int) $index);
            $presented['order_number'] = (string) ($row['order_number'] ?? '');
            $out[] = $presented;
            continue;
        }

        $given = trim((string) ($row['given_name'] ?? ''));
        $family = trim((string) ($row['family_name'] ?? ''));
        $christian = trim((string) ($row['christian_name'] ?? ''));
        $full = trim($christian . ' ' . $given . ' ' . $family);
        if ($full === '') {
            $full = trim((string) ($row['certificate_name'] ?? ''));
        }

        $out[] = [
            'full_name'    => $full !== '' ? $full : '—',
            'email'        => (string) ($row['email'] ?? ''),
            'order_number' => (string) ($row['order_number'] ?? ''),
            'heading'      => '',
            'fields'       => [],
        ];
    }

    return $out;
}

/**
 * @param array<string, string|int> $args
 */
function rm_add_guests_url(array $args = []): string
{
    $defaults = [
        'action' => 'add-guests',
    ];

    if (!isset($args['lang'])) {
        $request_lang = rm_get_request_lang();
        if ($request_lang !== '') {
            $defaults['lang'] = $request_lang;
        }
    }

    return add_query_arg(array_merge($defaults, $args), rm_page_url());
}

/**
 * @param array<string, string|int> $extra
 */
function rm_add_guests_page_url(string $event_code, string $locale = '', array $extra = []): string
{
    $args = array_merge([
        'event_code' => $event_code,
    ], $extra);

    if ($locale !== '') {
        $args['lang'] = $locale;
    }

    return rm_add_guests_url($args);
}

/**
 * @param array<string, mixed> $event
 */
function rm_add_guests_url_for_event(array $event, string $locale = ''): string
{
    $event_code = trim((string) ($event['programCode'] ?? ''));
    if ($event_code === '') {
        return '';
    }

    return rm_add_guests_page_url($event_code, $locale);
}

/**
 * @return array<string, mixed>|null
 */
function rm_addon_purchase_load(int $purchase_id): ?array
{
    global $wpdb;

    if ($purchase_id < 1 || !rm_event_addon_purchase_schema_ready()) {
        return null;
    }

    $row = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM `event_addon_purchase` WHERE `id` = %d LIMIT 1',
            $purchase_id
        ),
        ARRAY_A
    );

    return is_array($row) && $row !== [] ? $row : null;
}

/**
 * Normalize one guest from a pending addon purchase into a v2 registrant row shape.
 *
 * @param array{core?: array<string, string|null>, custom?: array<string, mixed>} $guest_row
 * @param array<string, mixed> $purchase
 * @return array<string, mixed>
 */
function rm_normalize_addon_purchase_guest_to_registrant_row(
    array $guest_row,
    array $purchase,
    int $suffix_index
): array {
    $core = is_array($guest_row['core'] ?? null) ? $guest_row['core'] : [];
    $custom = is_array($guest_row['custom'] ?? null) ? $guest_row['custom'] : [];

    $pricing = [];
    $pricing_raw = $purchase['pricing_snapshot'] ?? '';
    if (is_string($pricing_raw) && $pricing_raw !== '') {
        $decoded = json_decode($pricing_raw, true);
        if (is_array($decoded)) {
            $pricing = $decoded;
        }
    }

    $guest_price = (float) ($pricing['guest_price'] ?? 0);
    $registration_id = (int) ($purchase['registration_id'] ?? 0);
    $event_id = (int) ($purchase['event_id'] ?? 0);
    $primary_order = trim((string) ($purchase['primary_order_number'] ?? ''));
    $custom_json = $custom !== [] ? wp_json_encode($custom) : null;

    $registrant_row = [
        'id'               => 0,
        'registration_id'  => $registration_id,
        'event_id'         => $event_id,
        'member_index'     => 9000 + $suffix_index,
        'role'             => 'addon',
        'order_number'     => $primary_order !== ''
            ? rm_format_guest_order_number($primary_order, $suffix_index)
            : '',
        'nric'             => $core['nric'] ?? null,
        'title'            => $core['title'] ?? null,
        'christian_name'   => $core['christian_name'] ?? null,
        'given_name'       => $core['given_name'] ?? null,
        'family_name'      => $core['family_name'] ?? null,
        'certificate_name' => $core['certificate_name'] ?? null,
        'email'            => $core['email'] ?? null,
        'contact'          => $core['contact'] ?? null,
        'address1'         => $core['address1'] ?? null,
        'address2'         => $core['address2'] ?? null,
        'postcode'         => $core['postcode'] ?? null,
        'church_name'      => $core['church_name'] ?? null,
        'custom_responses' => $custom_json,
        'unit_price'       => $guest_price,
        'discount_percent' => 0.0,
        'status'           => 'pending',
        'created_at'       => (string) ($purchase['created_at'] ?? current_time('mysql')),
        'updated_at'       => (string) ($purchase['updated_at'] ?? current_time('mysql')),
    ];

    $header = [
        'confirmation_number'        => (string) ($purchase['confirmation_number'] ?? ''),
        'payment_status'             => 'pending',
        'payment_request_id'         => $purchase['payment_request_id'] ?? null,
        'payment_option'             => (string) ($purchase['payment_option'] ?? 'N/A'),
        'total_amount'               => (float) ($purchase['total_amount'] ?? 0),
        'member_count'               => (int) ($purchase['member_count'] ?? 1),
        'is_email_confirmation_sent' => (int) ($purchase['is_email_confirmation_sent'] ?? 0),
        'event_promotion_id'         => $purchase['event_promotion_id'] ?? null,
        'pricing_snapshot'           => $purchase['header_pricing_snapshot'] ?? null,
    ];

    $normalized = rm_normalize_v2_registrant_row($registrant_row, $header);
    $normalized['_addon_purchase_id'] = (int) ($purchase['id'] ?? 0);
    $normalized['_is_pending_addon_purchase'] = true;

    return $normalized;
}

/**
 * Pending post-registration addon purchases as normalized registrant rows.
 *
 * @return list<array<string, mixed>>
 */
function rm_fetch_pending_addon_purchase_registrant_rows(int $event_id, ?array $event = null): array
{
    if ($event_id < 1 || !rm_event_addon_purchase_schema_ready()) {
        return [];
    }

    global $wpdb;

    $purchases = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT p.*, h.confirmation_number, h.payment_status AS header_payment_status,
                    h.payment_request_id AS header_payment_request_id,
                    h.payment_option AS header_payment_option,
                    h.total_amount AS header_total, h.member_count,
                    h.is_email_confirmation_sent, h.event_promotion_id,
                    h.pricing_snapshot AS header_pricing_snapshot,
                    h.primary_order_number, h.primary_email
             FROM `event_addon_purchase` p
             INNER JOIN `event_registration` h ON h.id = p.registration_id
             WHERE p.event_id = %d AND p.payment_status = %s
             ORDER BY p.created_at ASC, p.id ASC',
            $event_id,
            'pending'
        ),
        ARRAY_A
    );

    if (!is_array($purchases) || $purchases === []) {
        return [];
    }

    $guest_label_singular = 'Guest';
    $guest_label_plural = 'Guests';
    if (is_array($event)) {
        $config = rm_parse_registration_config($event);
        if (!empty($config['guests']['enabled'])) {
            $guest_label_singular = (string) ($config['guests']['label_singular'] ?? 'Guest');
            $guest_label_plural = (string) ($config['guests']['label_plural'] ?? 'Guests');
            if ($guest_label_singular === '') {
                $guest_label_singular = 'Guest';
            }
            if ($guest_label_plural === '') {
                $guest_label_plural = 'Guests';
            }
        }
    }

    $suffix_offset_by_registration = [];
    $rows = [];

    foreach ($purchases as $purchase) {
        if (!is_array($purchase)) {
            continue;
        }

        $registration_id = (int) ($purchase['registration_id'] ?? 0);
        if ($registration_id < 1) {
            continue;
        }

        if (!isset($suffix_offset_by_registration[$registration_id])) {
            $suffix_offset_by_registration[$registration_id] = rm_v2_count_registration_guest_addons($registration_id);
        }

        $guest_payload_raw = $purchase['guest_payload'] ?? '';
        if (!is_string($guest_payload_raw) || $guest_payload_raw === '') {
            continue;
        }

        $guest_payload = json_decode($guest_payload_raw, true);
        if (!is_array($guest_payload) || $guest_payload === []) {
            continue;
        }

        $suffix_base = $suffix_offset_by_registration[$registration_id];
        foreach ($guest_payload as $guest_index => $guest_row) {
            if (!is_array($guest_row)) {
                continue;
            }

            $normalized = rm_normalize_addon_purchase_guest_to_registrant_row(
                $guest_row,
                $purchase,
                $suffix_base + (int) $guest_index
            );
            $normalized['_guest_label'] = $guest_label_singular;
            $normalized['_guest_label_singular'] = $guest_label_singular;
            $normalized['_guest_label_plural'] = $guest_label_plural;
            $rows[] = $normalized;
        }

        $suffix_offset_by_registration[$registration_id] += count($guest_payload);
    }

    return $rows;
}

function rm_addon_purchase_reference(int $purchase_id, string $parent_confirmation): string
{
    $parent_confirmation = sanitize_text_field(trim($parent_confirmation));

    return $parent_confirmation . '-addon-' . max(1, $purchase_id);
}

/**
 * @return int Purchase id or 0
 */
function rm_addon_purchase_id_from_reference(string $reference): int
{
    $reference = trim($reference);
    if ($reference === '' || !preg_match('/-addon-(\d+)$/', $reference, $matches)) {
        return 0;
    }

    return max(0, (int) ($matches[1] ?? 0));
}

/**
 * @return int Purchase id or 0
 */
function rm_addon_purchase_find_by_payment_request_id(string $payment_request_id): int
{
    global $wpdb;

    $payment_request_id = sanitize_text_field(trim($payment_request_id));
    if ($payment_request_id === '' || !rm_event_addon_purchase_schema_ready()) {
        return 0;
    }

    $id = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT `id` FROM `event_addon_purchase` WHERE `payment_request_id` = %s LIMIT 1',
            $payment_request_id
        )
    );

    return is_numeric($id) ? (int) $id : 0;
}

/**
 * @param array<string, mixed> $header
 * @param array<string, mixed> $event
 * @param list<array<string, mixed>> $guest_responses
 * @return array{ok: bool, error: string, form_errors: array<string, string>, status: string, purchase_id: int, redirect_url: string}
 */
function rm_guest_manage_submit(
    array $header,
    array $event,
    array $guest_responses,
    string $locale = RM_LOCALE_EN
): array {
    $empty = [
        'ok'          => false,
        'error'       => '',
        'form_errors' => [],
        'status'      => '',
        'purchase_id' => 0,
        'redirect_url'=> '',
    ];

    if (!rm_guest_post_registration_allowed($event)) {
        $empty['error'] = rm__('guest_manage.error.post_reg_disabled', $locale);

        return $empty;
    }

    $registration_id = isset($header['id']) ? (int) $header['id'] : 0;
    $meta = rm_guest_manage_capacity_meta($event, $header);
    if (!$meta['can_add']) {
        $empty['error'] = rm__(
            'guest_manage.error.capacity_full',
            $locale,
            ['guest' => (string) ($meta['label_plural'] ?? 'Guests')]
        );

        return $empty;
    }

    $guest_schema = rm_parse_guest_form_schema_for_registration($event, $header);
    if (empty($guest_schema['enabled']) || $guest_responses === []) {
        $empty['error'] = rm__('guest_manage.error.no_guests', $locale);

        return $empty;
    }

    $guest_build = rm_build_member_rows_from_responses($guest_schema, $guest_responses, $locale);
    if (!$guest_build['ok']) {
        $empty['error'] = $guest_build['error'];
        $empty['form_errors'] = $guest_build['form_errors'];

        return $empty;
    }

    $guest_rows = $guest_build['member_rows'];
    $guest_count = count($guest_rows);
    $max_allowed = (int) ($guest_schema['max'] ?? 0);
    if ($max_allowed > 0 && $guest_count > $max_allowed) {
        $empty['error'] = rm__(
            'guest_manage.error.too_many',
            $locale,
            ['count' => (string) $max_allowed, 'guest' => strtolower($meta['label_plural'])]
        );

        return $empty;
    }

    $guest_price = $meta['guest_price'];
    $subtotal = round($guest_count * $guest_price, 2);
    $total = $subtotal;

    $primary_order = trim((string) ($header['primary_order_number'] ?? ''));
    $confirmation = trim((string) ($header['confirmation_number'] ?? ''));
    $event_id = isset($header['event_id']) ? (int) $header['event_id'] : 0;
    $member_offset = rm_v2_next_guest_member_offset($registration_id);
    $suffix_offset = rm_v2_count_registration_guest_addons($registration_id);

    $pricing = [
        'guest_price'   => $guest_price,
        'guest_count'   => $guest_count,
        'guest_subtotal'=> $subtotal,
    ];

    if ($total <= 0) {
        $inserted = rm_v2_insert_guest_lines(
            'event_registrant',
            $registration_id,
            $event_id,
            $guest_rows,
            $pricing,
            'confirmed',
            $member_offset,
            $primary_order,
            $suffix_offset
        );

        if (!$inserted['ok']) {
            $empty['error'] = $inserted['error'] !== ''
                ? $inserted['error']
                : rm__('guest_manage.error.save_failed', $locale);

            return $empty;
        }

        if (function_exists('rm_email_send_addon_confirmation')) {
            $email_result = rm_email_send_addon_confirmation($registration_id, $guest_count, $suffix_offset);
            if (!$email_result['ok'] && empty($email_result['skipped'])) {
                error_log(
                    '[rm_guest_manage] Add-on confirmation email failed for registration '
                    . $registration_id . ': ' . ($email_result['error'] ?? '')
                );
            }
        }

        return [
            'ok'           => true,
            'error'        => '',
            'form_errors'  => [],
            'status'       => 'confirmed',
            'purchase_id'  => 0,
            'redirect_url' => '',
        ];
    }

    if (!rm_event_addon_purchase_schema_ready()) {
        $empty['error'] = rm__('guest_manage.error.save_failed', $locale);

        return $empty;
    }

    global $wpdb;

    $form_snapshot = [
        'guest'      => $guest_schema['fields'] ?? [],
        'guest_meta' => [
            'label_singular' => $meta['label_singular'],
            'label_plural'   => $meta['label_plural'],
        ],
        'locale'     => $locale,
    ];

    $inserted = $wpdb->insert(
        'event_addon_purchase',
        [
            'registration_id'       => $registration_id,
            'event_id'              => $event_id,
            'confirmation_number'   => $confirmation,
            'guest_count'           => $guest_count,
            'subtotal'              => $subtotal,
            'total_amount'          => $total,
            'payment_status'        => 'pending',
            'payment_option'        => 'N/A',
            'guest_payload'         => wp_json_encode($guest_rows),
            'pricing_snapshot'      => wp_json_encode($pricing),
            'form_schema_snapshot'  => wp_json_encode($form_snapshot),
            'created_at'            => current_time('mysql'),
            'updated_at'            => current_time('mysql'),
        ],
        [
            '%d', '%d', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
        ]
    );

    if (!$inserted) {
        $empty['error'] = rm__('guest_manage.error.save_failed', $locale);

        return $empty;
    }

    $purchase_id = (int) $wpdb->insert_id;

    return [
        'ok'           => true,
        'error'        => '',
        'form_errors'  => [],
        'status'       => 'pending_payment',
        'purchase_id'  => $purchase_id,
        'redirect_url' => '',
    ];
}

/**
 * Finalize a paid addon purchase — idempotent.
 *
 * @return array{ok: bool, error: string, order_numbers: list<string>}
 */
function rm_finalize_addon_purchase(int $purchase_id, string $payment_request_id = '', string $payment_option = 'N/A'): array
{
    $fail = static function (string $error): array {
        return [
            'ok'            => false,
            'error'         => $error,
            'order_numbers' => [],
        ];
    };

    $purchase = rm_addon_purchase_load($purchase_id);
    if ($purchase === null) {
        return $fail('Add-on purchase could not be found.');
    }

    $status = strtolower(trim((string) ($purchase['payment_status'] ?? '')));
    if (in_array($status, ['paid', 'free'], true)) {
        return [
            'ok'            => true,
            'error'         => '',
            'order_numbers' => [],
        ];
    }

    $registration_id = (int) ($purchase['registration_id'] ?? 0);
    $header = rm_guest_manage_fetch_header($registration_id);
    if ($header === null) {
        return $fail('Parent registration could not be found.');
    }

    $guest_payload_raw = $purchase['guest_payload'] ?? '';
    $guest_rows = [];
    if (is_string($guest_payload_raw) && $guest_payload_raw !== '') {
        $decoded = json_decode($guest_payload_raw, true);
        if (is_array($decoded)) {
            $guest_rows = $decoded;
        }
    }

    if ($guest_rows === []) {
        return $fail('Guest data is missing from add-on purchase.');
    }

    global $wpdb;

    $wpdb->query('START TRANSACTION');

    $fresh = rm_addon_purchase_load($purchase_id);
    if ($fresh === null) {
        $wpdb->query('ROLLBACK');

        return $fail('Add-on purchase could not be found.');
    }

    $fresh_status = strtolower(trim((string) ($fresh['payment_status'] ?? '')));
    if (in_array($fresh_status, ['paid', 'free'], true)) {
        $wpdb->query('COMMIT');

        return [
            'ok'            => true,
            'error'         => '',
            'order_numbers' => [],
        ];
    }

    $event_id = (int) ($purchase['event_id'] ?? 0);
    $primary_order = trim((string) ($header['primary_order_number'] ?? ''));
    $member_offset = rm_v2_next_guest_member_offset($registration_id);
    $suffix_offset = rm_v2_count_registration_guest_addons($registration_id);

    $pricing_raw = $purchase['pricing_snapshot'] ?? '';
    $pricing = [];
    if (is_string($pricing_raw) && $pricing_raw !== '') {
        $decoded_pricing = json_decode($pricing_raw, true);
        if (is_array($decoded_pricing)) {
            $pricing = $decoded_pricing;
        }
    }
    if ($pricing === []) {
        $pricing = ['guest_price' => 0];
    }

    $inserted = rm_v2_insert_guest_lines(
        'event_registrant',
        $registration_id,
        $event_id,
        $guest_rows,
        $pricing,
        'confirmed',
        $member_offset,
        $primary_order,
        $suffix_offset
    );

    if (!$inserted['ok']) {
        $wpdb->query('ROLLBACK');

        return $fail($inserted['error'] !== '' ? $inserted['error'] : 'Guest lines could not be saved.');
    }

    $payment_option = function_exists('rm_payment_normalize_option')
        ? rm_payment_normalize_option($payment_option)
        : sanitize_text_field($payment_option);

    $update = $wpdb->update(
        'event_addon_purchase',
        [
            'payment_status'     => 'paid',
            'payment_request_id' => sanitize_text_field($payment_request_id),
            'payment_option'     => $payment_option,
            'paid_at'            => current_time('mysql'),
            'updated_at'         => current_time('mysql'),
        ],
        ['id' => $purchase_id],
        ['%s', '%s', '%s', '%s', '%s'],
        ['%d']
    );

    if ($update === false) {
        $wpdb->query('ROLLBACK');

        return $fail('Add-on purchase could not be updated.');
    }

    $wpdb->query('COMMIT');

    $order_numbers = [];
    for ($i = 0; $i < count($guest_rows); ++$i) {
        $order_numbers[] = rm_format_guest_order_number($primary_order, $suffix_offset + $i);
    }

    if (function_exists('rm_email_send_addon_confirmation')) {
        rm_email_send_addon_confirmation($registration_id, count($guest_rows), $suffix_offset, $purchase_id);
    }

    return [
        'ok'            => true,
        'error'         => '',
        'order_numbers' => $order_numbers,
    ];
}

/**
 * @return array<string, mixed>
 */
function rm_build_add_guests_context(): array
{
    $view_action = rm_get_view_action();
    $is_payment_return = $view_action === 'add-guests-payment-return';

    if ($is_payment_return) {
        rm_handle_addon_payment_return();
    }

    $event_code = rm_get_event_code();

    $context = [
        'view_action'         => 'add-guests',
        'is_public_layout'    => true,
        'page_url'            => rm_add_guests_url($event_code !== '' ? ['event_code' => $event_code] : []),
        'event_code'          => $event_code,
        'event'               => null,
        'event_present'       => null,
        'event_not_found'     => false,
        'error_message'       => '',
        'success_message'     => '',
        'needs_login'         => true,
        'access_ok'           => false,
        'can_add'             => false,
        'header'              => null,
        'guests'              => [],
        'guest_schema'        => ['fields' => [], 'enabled' => false],
        'capacity_meta'       => [],
        'event_addon_full'    => false,
        'confirmation_number' => '',
        'primary_name'        => '',
        'primary_order'       => '',
        'form_errors'         => [],
        'guests_input'        => [],
        'registration_config' => [],
        'event_currency'      => 'SGD',
        'locale'              => RM_LOCALE_EN,
        'html_lang'           => 'en',
        'ui_strings'          => [],
        'add_guests_success'  => null,
    ];

    if ($event_code === '') {
        $context['error_message'] = rm__('guest_manage.error.no_event', RM_LOCALE_EN);
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
            : rm__('guest_manage.error.event_not_found', RM_LOCALE_EN);
        $context['event_not_found'] = true;

        return $context;
    }

    if (!rm_event_uses_v2_registration($event)) {
        $context['error_message'] = rm__('guest_manage.error.v1_not_supported', RM_LOCALE_EN);

        return $context;
    }

    if (!rm_guest_post_registration_allowed($event)) {
        $context['error_message'] = rm__('guest_manage.error.post_reg_disabled', RM_LOCALE_EN);

        return $context;
    }

    $context['event'] = $event;
    $context['event_present'] = rm_present_registration_event($event);
    $context['event_currency'] = rm_registration_currency($event);
    $request_lang = rm_get_request_lang();
    $context['locale'] = rm_resolve_locale($event, $request_lang !== '' ? $request_lang : null);
    $context['html_lang'] = rm_locale_html_lang($context['locale']);
    $context['ui_strings'] = rm_public_ui_strings($context['locale']);
    $context['registration_config'] = rm_parse_registration_config($event);
    $context['page_url'] = rm_add_guests_page_url($event_code, $context['locale']);
    $context['event_landing_href'] = function_exists('rm_event_landing_url')
        ? rm_event_landing_url($event)
        : home_url('/');

    $event_id = isset($event['id']) ? (int) $event['id'] : 0;
    $locale = $context['locale'];
    $event_capacity = rm_guest_event_capacity($event);
    $context['event_addon_full'] = $event_capacity['remaining'] !== null
        && (int) $event_capacity['remaining'] <= 0;

    $flash = rm_consume_addon_success_flash();
    if ($flash !== null) {
        $context['add_guests_success'] = $flash;
        $context['success_message'] = (string) ($flash['message'] ?? '');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_payment_return) {
        $nonce_ok = isset($_POST['rm_guest_manage_nonce'])
            && wp_verify_nonce(
                sanitize_text_field(wp_unslash((string) $_POST['rm_guest_manage_nonce'])),
                'rm_guest_manage'
            );

        if (!$nonce_ok) {
            $context['error_message'] = rm__('guest_manage.error.session_expired', $locale);
            $context['needs_login'] = true;

            return $context;
        }

        $post_action = isset($_POST['rm_guest_manage_action'])
            ? sanitize_key(wp_unslash((string) $_POST['rm_guest_manage_action']))
            : '';

        if ($post_action === 'logout') {
            rm_guest_manage_clear_session();
            wp_safe_redirect(rm_add_guests_page_url($event_code, $locale, ['closed' => '1']));
            exit;
        }

        if ($post_action === 'login' && !empty($context['event_addon_full'])) {
            $guest_plural = trim((string) ($context['registration_config']['guests']['label_plural'] ?? 'Guests'));
            if ($guest_plural === '') {
                $guest_plural = 'Guests';
            }
            $context['error_message'] = rm__('guest_manage.error.event_full', $locale, ['guest' => $guest_plural]);
            $context['needs_login'] = true;

            return $context;
        }
    }

    $access = rm_guest_manage_resolve_access($event_id, $locale);
    $context['needs_login'] = !empty($access['needs_login']);
    $context['access_ok'] = !empty($access['ok']);

    if (!$access['ok']) {
        if ($access['error'] !== '') {
            $context['error_message'] = $access['error'];
        }

        return $context;
    }

    $header = $access['header'];
    if (!is_array($header)) {
        return $context;
    }

    $context['header'] = $header;
    $context['confirmation_number'] = (string) ($header['confirmation_number'] ?? '');
    $context['primary_order'] = (string) ($header['primary_order_number'] ?? '');
    $context['capacity_meta'] = rm_guest_manage_capacity_meta($event, $header);
    $context['can_add'] = !empty($context['capacity_meta']['can_add']) && empty($context['event_addon_full']);
    $context['guest_schema'] = rm_parse_guest_form_schema_for_registration($event, $header);
    $guest_display_schema = function_exists('rm_email_resolve_guest_schema')
        ? rm_email_resolve_guest_schema($header, $event_id)
        : [
            'fields'         => $context['guest_schema']['fields'] ?? [],
            'label_singular' => (string) ($context['guest_schema']['label_singular'] ?? 'Guest'),
        ];
    $context['guests'] = rm_guest_manage_present_guests(
        rm_guest_manage_load_guest_lines((int) $header['id']),
        $guest_display_schema,
        $locale
    );

    $primary_line = rm_guest_manage_load_primary_line((int) $header['id']);
    if ($primary_line !== null) {
        $given = trim((string) ($primary_line['given_name'] ?? ''));
        $family = trim((string) ($primary_line['family_name'] ?? ''));
        $christian = trim((string) ($primary_line['christian_name'] ?? ''));
        $context['primary_name'] = trim($christian . ' ' . $given . ' ' . $family);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $is_payment_return) {
        return $context;
    }

    $post_action = isset($_POST['rm_guest_manage_action'])
        ? sanitize_key(wp_unslash((string) $_POST['rm_guest_manage_action']))
        : '';

    if ($post_action === 'login') {
        $context['success_message'] = rm__('guest_manage.success.loaded', $locale);

        return $context;
    }

    if ($post_action !== 'submit_guests') {
        return $context;
    }

    if (!$context['can_add']) {
        $guest_plural = trim((string) (
            $context['capacity_meta']['label_plural']
            ?? $context['registration_config']['guests']['label_plural']
            ?? 'Guests'
        ));
        if ($guest_plural === '') {
            $guest_plural = 'Guests';
        }
        $context['error_message'] = !empty($context['event_addon_full'])
            ? rm__('guest_manage.error.event_full', $locale, ['guest' => $guest_plural])
            : rm__('guest_manage.error.capacity_full', $locale, ['guest' => $guest_plural]);

        return $context;
    }

    $guest_responses = rm_parse_guests_from_post();
    $context['guests_input'] = $guest_responses;

    $result = rm_guest_manage_submit($header, $event, $guest_responses, $locale);
    if (!$result['ok']) {
        $context['error_message'] = $result['error'];
        $context['form_errors'] = $result['form_errors'];

        return $context;
    }

    if ($result['status'] === 'pending_payment' && $result['purchase_id'] > 0) {
        $checkout = rm_payment_initiate_addon_checkout(
            $result['purchase_id'],
            $event,
            $header,
            $event_code
        );

        if ($checkout['ok'] && $checkout['url'] !== '') {
            if (!rm_payment_redirect_to_checkout($checkout['url'])) {
                $context['error_message'] = rm__('guest_manage.error.payment_failed', $locale);

                return $context;
            }
        }

        $context['error_message'] = $checkout['error'] !== ''
            ? $checkout['error']
            : rm__('guest_manage.error.payment_failed', $locale);

        return $context;
    }

    $flash_key = rm_store_addon_success_flash(
        (int) $header['id'],
        count($guest_responses),
        'confirmed'
    );
    wp_safe_redirect(rm_add_guests_page_url($event_code, $locale, ['added' => $flash_key]));
    exit;
}

/**
 * @return array<string, mixed>|null
 */
function rm_guest_manage_load_primary_line(int $registration_id): ?array
{
    global $wpdb;

    if ($registration_id < 1) {
        return null;
    }

    $row = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM `event_registrant`
             WHERE `registration_id` = %d AND `role` = %s
             ORDER BY `member_index` ASC, `id` ASC
             LIMIT 1',
            $registration_id,
            'primary'
        ),
        ARRAY_A
    );

    return is_array($row) && $row !== [] ? $row : null;
}

function rm_store_addon_success_flash(int $registration_id, int $guest_count, string $status): string
{
    $key = wp_generate_password(12, false, false);
    set_transient(
        'rm_addon_flash_' . $key,
        [
            'registration_id' => $registration_id,
            'guest_count'     => $guest_count,
            'status'          => $status,
            'message'         => '',
        ],
        600
    );

    return $key;
}

/**
 * @return array<string, mixed>|null
 */
function rm_consume_addon_success_flash(): ?array
{
    if (!isset($_GET['added'])) {
        return null;
    }

    $key = sanitize_key(wp_unslash((string) $_GET['added']));
    if ($key === '') {
        return null;
    }

    $flash = get_transient('rm_addon_flash_' . $key);
    delete_transient('rm_addon_flash_' . $key);

    if (!is_array($flash)) {
        return null;
    }

    return $flash;
}

function rm_handle_addon_payment_return(): void
{
    $event_code = rm_get_event_code();
    $purchase_id = rm_get_addon_purchase_id();
    $payment_reference = rm_get_payment_reference();
    $payment_status = rm_get_payment_status();

    if ($event_code === '' || $purchase_id < 1) {
        wp_safe_redirect(rm_add_guests_url(['event_code' => $event_code]));
        exit;
    }

    $purchase = rm_addon_purchase_load($purchase_id);
    $event_id = is_array($purchase) ? (int) ($purchase['event_id'] ?? 0) : 0;

    $payment_request_id = '';
    if (is_array($purchase)) {
        $payment_request_id = trim((string) ($purchase['payment_request_id'] ?? ''));
    }

    if ($payment_request_id === '' && $payment_reference !== '') {
        $payment_request_id = $payment_reference;
    }

    $hitpay_state = 'unknown';
    if ($payment_request_id !== '' && function_exists('rm_payment_probe_return_state')) {
        $hitpay_state = rm_payment_probe_return_state(0, $payment_request_id, $payment_status);
    } elseif ($payment_status === 'completed') {
        $hitpay_state = 'completed';
    }

    $already_paid = is_array($purchase)
        && in_array(strtolower((string) ($purchase['payment_status'] ?? '')), ['paid', 'free'], true);

    if (!$already_paid && ($hitpay_state === 'completed' || $payment_status === 'completed')) {
        for ($attempt = 0; $attempt < 4; ++$attempt) {
            $fresh = rm_addon_purchase_load($purchase_id);
            if (is_array($fresh) && in_array(strtolower((string) ($fresh['payment_status'] ?? '')), ['paid', 'free'], true)) {
                $already_paid = true;
                break;
            }
            if ($attempt > 0) {
                usleep(500000);
            }
        }
    }

    if ($already_paid) {
        $guest_count = is_array($purchase) ? (int) ($purchase['guest_count'] ?? 0) : 0;
        $flash_key = rm_store_addon_success_flash(
            is_array($purchase) ? (int) ($purchase['registration_id'] ?? 0) : 0,
            $guest_count,
            'confirmed'
        );
        $locale = rm_get_request_lang();
        $redirect_args = [
            'event_code' => $event_code,
            'added'      => $flash_key,
        ];
        if ($locale !== '') {
            $redirect_args['lang'] = $locale;
        }
        wp_safe_redirect(rm_add_guests_url($redirect_args));
        exit;
    }

    $locale = rm_get_request_lang();
    $redirect_args = [
        'event_code' => $event_code,
        'payment_failed' => '1',
    ];
    if ($locale !== '') {
        $redirect_args['lang'] = $locale;
    }
    wp_safe_redirect(rm_add_guests_url($redirect_args));
    exit;
}
