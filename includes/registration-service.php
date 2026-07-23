<?php

/**
 * @return array<string, string>
 */
function rm_registration_form_defaults(): array
{
    return [
        'nric'            => '',
        'title'           => '',
        'christianName'   => '',
        'familyName'      => '',
        'givenName'       => '',
        'certificateName' => '',
        'email'           => '',
        'contact'         => '',
        'address1'        => '',
        'address2'        => '',
        'postcode'        => '',
        'churchName'      => '',
    ];
}

/**
 * @return array<int, string>
 */
function rm_registration_title_options(): array
{
    return ['Mr', 'Ms', 'Mrs', 'Mdm', 'Ps', 'Dr', 'Rev'];
}

/**
 * @return array<string, string>
 */
function rm_get_registration_form_input(): array
{
    $input = rm_registration_form_defaults();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return $input;
    }

    foreach (array_keys($input) as $key) {
        if (!isset($_POST[$key])) {
            continue;
        }

        $raw = wp_unslash((string) $_POST[$key]);
        $input[$key] = $key === 'email' ? sanitize_email($raw) : sanitize_text_field($raw);
    }

    return $input;
}

/**
 * @param array<string, string> $input
 * @return array<string, string>
 */
function rm_validate_registration_input(array $input): array
{
    $errors = [];
    $required_labels = [
        'nric'            => 'NRIC',
        'title'           => 'Title',
        'christianName'   => 'Christian name',
        'familyName'      => 'Family name',
        'givenName'       => 'Given name',
        'certificateName' => 'Certificate name',
        'email'           => 'Email',
        'contact'         => 'Contact number',
        'address1'        => 'Address 1',
        'postcode'        => 'Postal code',
        'churchName'      => 'Church name',
    ];

    foreach ($required_labels as $field => $label) {
        if ($input[$field] === '') {
            $errors[$field] = $label . ' is required.';
        }
    }

    if ($input['email'] !== '' && !is_email($input['email'])) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    return $errors;
}

/**
 * @param array<string, mixed> $event
 */
function rm_event_registration_price(array $event): float
{
    $price_raw = $event['price'] ?? null;

    return is_numeric($price_raw) ? (float) $price_raw : 0.0;
}

/**
 * @param array<string, mixed> $event
 */
function rm_event_is_free(array $event): bool
{
    return rm_event_registration_price($event) <= 0;
}

function rm_registration_order_number_exists(string $order_number, int $event_id): bool
{
    global $wpdb;

    if ($order_number === '' || $event_id < 1) {
        return false;
    }

    $existing = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT 1 FROM `bss_registrant` WHERE `orderNumber` = %s AND `events` = %d LIMIT 1',
            $order_number,
            $event_id
        )
    );

    if ($existing !== null) {
        return true;
    }

    if (rm_event_registration_tables_exist()) {
        $v2_existing = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT 1 FROM `event_registrant` WHERE `order_number` = %s AND `event_id` = %d LIMIT 1',
                $order_number,
                $event_id
            )
        );

        return $v2_existing !== null;
    }

    return false;
}

/**
 * Build a registration order number: {programCode}_{###}
 * Example: CCP012026_001
 */
function rm_format_registration_order_number(string $program_code, int $sequence): string
{
    $program_code = rtrim(trim($program_code), '_');
    $iterate_count = str_pad((string) max(0, $sequence), 3, '0', STR_PAD_LEFT);

    if ($program_code === '') {
        return $iterate_count;
    }

    return $program_code . '_' . $iterate_count;
}

/**
 * Guest/addon order number as a sub of the primary: {primary}-01, {primary}-02, …
 * $guest_index is 0-based.
 */
function rm_format_guest_order_number(string $primary_order_number, int $guest_index): string
{
    $primary_order_number = trim($primary_order_number);
    if ($primary_order_number === '') {
        return '';
    }

    $suffix = str_pad((string) max(1, $guest_index + 1), 2, '0', STR_PAD_LEFT);

    return $primary_order_number . '-' . $suffix;
}

/**
 * Atomically increment lastId and build an order number from programCode + sequence.
 * Format: {programCode}_{###}, e.g. CCP012026_001.
 *
 * @return array{ok: bool, order_number: string, error: string}
 */
function rm_allocate_registration_order_number(int $event_id, string $source = ''): array
{
    global $wpdb;

    if ($event_id < 1) {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'This event could not be found.',
        ];
    }

    $source = rm_normalize_event_source($source);

    if ($source === 'cpt') {
        $event = rm_get_cpt_event_by_id($event_id);
        if ($event === null) {
            return [
                'ok'           => false,
                'order_number' => '',
                'error'        => 'This event could not be found.',
            ];
        }

        $program_code = isset($event['programCode']) ? trim((string) $event['programCode']) : '';
        if ($program_code === '') {
            return [
                'ok'           => false,
                'order_number' => '',
                'error'        => 'This event is not configured for registration.',
            ];
        }

        $wpdb->query('START TRANSACTION');

        $meta_table = $wpdb->postmeta;
        $current = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value FROM {$meta_table} WHERE post_id = %d AND meta_key = 'lastId' LIMIT 1 FOR UPDATE",
                $event_id
            )
        );

        $next = ((int) $current) + 1;
        if ($current === null) {
            $inserted = $wpdb->insert(
                $meta_table,
                [
                    'post_id'    => $event_id,
                    'meta_key'   => 'lastId',
                    'meta_value' => (string) $next,
                ],
                ['%d', '%s', '%s']
            );
            if (!$inserted) {
                $wpdb->query('ROLLBACK');

                return [
                    'ok'           => false,
                    'order_number' => '',
                    'error'        => 'Registration could not be completed. Please try again.',
                ];
            }
        } else {
            $updated = $wpdb->update(
                $meta_table,
                ['meta_value' => (string) $next],
                [
                    'post_id'  => $event_id,
                    'meta_key' => 'lastId',
                ],
                ['%s'],
                ['%d', '%s']
            );
            if ($updated === false) {
                $wpdb->query('ROLLBACK');

                return [
                    'ok'           => false,
                    'order_number' => '',
                    'error'        => 'Registration could not be completed. Please try again.',
                ];
            }
        }

        $wpdb->query('COMMIT');
        clean_post_cache($event_id);

        return [
            'ok'           => true,
            'order_number' => rm_format_registration_order_number($program_code, $next),
            'error'        => '',
        ];
    }

    $wpdb->query('START TRANSACTION');

    $updated = $wpdb->query(
        $wpdb->prepare(
            'UPDATE `bss_events` SET `lastId` = `lastId` + 1 WHERE `id` = %d',
            $event_id
        )
    );

    if ($updated === false) {
        $wpdb->query('ROLLBACK');

        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'Registration could not be completed. Please try again.',
        ];
    }

    $row = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT `programCode`, `lastId` FROM `bss_events` WHERE `id` = %d LIMIT 1',
            $event_id
        ),
        ARRAY_A
    );

    if (!is_array($row)) {
        $wpdb->query('ROLLBACK');

        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'This event could not be found.',
        ];
    }

    $program_code = isset($row['programCode']) ? trim((string) $row['programCode']) : '';
    if ($program_code === '') {
        $wpdb->query('ROLLBACK');

        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'This event is not configured for registration.',
        ];
    }

    $wpdb->query('COMMIT');

    return [
        'ok'           => true,
        'order_number' => rm_format_registration_order_number($program_code, (int) $row['lastId']),
        'error'        => '',
    ];
}

/**
 * Validate the event, then allocate a unique order number.
 *
 * @return array{ok: bool, order_number: string, error: string}
 */
function rm_generate_registration_order_number(int $event_id, string $source = ''): array
{
    $event_gate = rm_validate_event_registration($event_id, $source);
    if (!$event_gate['ok']) {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => $event_gate['error'],
        ];
    }

    return rm_allocate_registration_order_number($event_id, $source);
}

/**
 * @param array<string, string> $input
 * @return array<string, mixed>
 */
function rm_build_registration_row(array $input, int $event_id, string $order_number, float $amount): array
{
    return [
        'nric'               => $input['nric'],
        'title'              => $input['title'],
        'christianName'      => $input['christianName'],
        'givenName'          => $input['givenName'],
        'familyName'         => $input['familyName'],
        'certificateName'    => $input['certificateName'],
        'email'              => $input['email'],
        'contact'            => $input['contact'],
        'address1'           => $input['address1'],
        'address2'           => $input['address2'] !== '' ? $input['address2'] : 'N/A',
        'postcode'           => $input['postcode'],
        'churchName'         => $input['churchName'],
        'note'               => wp_json_encode(new stdClass()),
        'orderNumber'        => $order_number,
        'events'             => $event_id,
        'payment'            => null,
        'paymentOption'      => 'N/A',
        'amount'             => $amount,
        'groupBookings'      => 0,
        'confirmationNumber' => substr(uniqid(), -8),
    ];
}

/**
 * @param array<string, mixed> $event
 * @param array<string, string> $input
 * @return array{ok: bool, order_number: string, error: string, status: string, pending_id: int}
 */
function rm_submit_registration(array $event, array $input): array
{
    if (rm_event_uses_v2_registration($event)) {
        $v2_result = rm_submit_v2_registration($event);

        return [
            'ok'           => $v2_result['ok'],
            'order_number' => $v2_result['order_number'],
            'error'        => $v2_result['error'],
            'status'       => $v2_result['status'],
            'pending_id'   => $v2_result['pending_id'],
            'form_errors'  => $v2_result['form_errors'] ?? [],
        ];
    }

    global $wpdb;

    $event_id = isset($event['id']) ? absint($event['id']) : 0;
    $source = rm_event_source_value($event);
    $event_gate = rm_validate_event_registration($event_id, $source);
    if (!$event_gate['ok']) {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => $event_gate['error'],
            'status'       => '',
            'pending_id'   => 0,
        ];
    }

    if (rm_event_is_free($event)) {
        $order_result = rm_generate_registration_order_number($event_id, $source);
        if (!$order_result['ok']) {
            return [
                'ok'           => false,
                'order_number' => '',
                'error'        => $order_result['error'],
                'status'       => '',
                'pending_id'   => 0,
            ];
        }

        $order_number = $order_result['order_number'];
        if (rm_registration_order_number_exists($order_number, $event_id)) {
            return [
                'ok'           => false,
                'order_number' => '',
                'error'        => 'Registration could not be completed. Please try again.',
                'status'       => '',
                'pending_id'   => 0,
            ];
        }

        $row = rm_build_registration_row($input, $event_id, $order_number, 0.0);
        $inserted = $wpdb->insert('bss_registrant', $row);

        if (!$inserted) {
            return [
                'ok'           => false,
                'order_number' => '',
                'error'        => 'Registration could not be saved. Please try again.',
                'status'       => '',
                'pending_id'   => 0,
            ];
        }

        return [
            'ok'           => true,
            'order_number' => $order_number,
            'error'        => '',
            'status'       => 'confirmed',
            'pending_id'   => 0,
        ];
    }

    $amount = rm_event_registration_price($event);
    $row = rm_build_registration_row($input, $event_id, '', $amount);
    $inserted = $wpdb->insert('bss_registrant_pendings', $row);

    if (!$inserted) {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'Registration could not be saved. Please try again.',
            'status'       => '',
            'pending_id'   => 0,
        ];
    }

    return [
        'ok'           => true,
        'order_number' => '',
        'error'        => '',
        'status'       => 'pending_payment',
        'pending_id'   => (int) $wpdb->insert_id,
    ];
}

/**
 * Finalize a paid pending registration after successful payment.
 * Entry point for future payment webhook integration — not wired yet.
 *
 * @return array{ok: bool, order_number: string, error: string}
 */
function rm_finalize_paid_registration(int $pending_id, string $payment_request_id, string $payment_option = 'N/A'): array
{
    if (rm_event_registration_tables_exist() && rm_v2_load_pending_header($pending_id) !== null) {
        return rm_v2_finalize_paid_registration($pending_id, $payment_request_id, $payment_option);
    }

    global $wpdb;

    if ($pending_id < 1) {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'Pending registration could not be found.',
        ];
    }

    $payment_request_id = sanitize_text_field($payment_request_id);
    if ($payment_request_id === '') {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'Payment reference is required.',
        ];
    }

    $pending = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM `bss_registrant_pendings` WHERE `id` = %d LIMIT 1',
            $pending_id
        ),
        ARRAY_A
    );

    if (!is_array($pending) || $pending === []) {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'Pending registration could not be found.',
        ];
    }

    $event_id = isset($pending['events']) ? absint($pending['events']) : 0;
    $email = isset($pending['email']) ? trim((string) $pending['email']) : '';

    if ($event_id > 0 && $email !== '') {
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT `orderNumber` FROM `bss_registrant` WHERE `email` = %s AND `events` = %d LIMIT 1',
                $email,
                $event_id
            ),
            ARRAY_A
        );

        if (is_array($existing) && !empty($existing['orderNumber'])) {
            return [
                'ok'           => true,
                'order_number' => (string) $existing['orderNumber'],
                'error'        => '',
            ];
        }
    }

    $order_result = rm_allocate_registration_order_number($event_id);
    if (!$order_result['ok']) {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => $order_result['error'],
        ];
    }

    $order_number = $order_result['order_number'];
    if (rm_registration_order_number_exists($order_number, $event_id)) {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'Registration could not be completed. Please try again.',
        ];
    }

    unset($pending['id']);
    $pending['orderNumber'] = $order_number;
    $pending['payment'] = $payment_request_id;
    $pending['paymentOption'] = $payment_option === '' ? 'N/A' : sanitize_text_field($payment_option);
    $pending['isEmailConfirmationSent'] = 0;

    $wpdb->query('START TRANSACTION');

    $inserted = $wpdb->insert('bss_registrant', $pending);
    if (!$inserted) {
        $wpdb->query('ROLLBACK');

        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'Registration could not be saved. Please try again.',
        ];
    }

    $deleted = $wpdb->delete(
        'bss_registrant_pendings',
        ['id' => $pending_id],
        ['%d']
    );

    if ($deleted === false) {
        $wpdb->query('ROLLBACK');

        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'Registration could not be saved. Please try again.',
        ];
    }

    $wpdb->query('COMMIT');

    return [
        'ok'           => true,
        'order_number' => $order_number,
        'error'        => '',
    ];
}

/**
 * @param array<string, mixed> $debug Optional payment-return diagnostics (temporary).
 * @return string Flash key for PRG redirect
 */
function rm_store_registration_success_flash(
    string $order_number,
    string $status = 'confirmed',
    array $debug = []
): string {
    $flash_key = wp_generate_password(12, false);
    set_transient(
        'rm_reg_success_' . $flash_key,
        [
            'order_number' => $order_number,
            'status'       => $status,
            'debug'        => $debug,
        ],
        5 * MINUTE_IN_SECONDS
    );

    return $flash_key;
}

/**
 * @return array{order_number: string, status: string, debug: array<string, mixed>}|null
 */
function rm_consume_registration_success_flash(string $flash_key): ?array
{
    if ($flash_key === '') {
        return null;
    }

    $flash = get_transient('rm_reg_success_' . $flash_key);
    if (!is_array($flash)) {
        return null;
    }

    delete_transient('rm_reg_success_' . $flash_key);

    return [
        'order_number' => isset($flash['order_number']) ? (string) $flash['order_number'] : '',
        'status'       => isset($flash['status']) ? (string) $flash['status'] : 'confirmed',
        'debug'        => isset($flash['debug']) && is_array($flash['debug']) ? $flash['debug'] : [],
    ];
}

/**
 * @return string
 */
function rm_registration_success_message(string $status): string
{
    if ($status === 'pending_payment') {
        return 'Thank you! Your registration has been received. Please complete payment to confirm your spot.';
    }

    if ($status === 'payment_processing') {
        return 'Payment received. Your registration is being confirmed — you will receive an email shortly.';
    }

    if ($status === 'payment_failed') {
        return 'Payment was not completed. Your registration is saved; please try again or contact us.';
    }

    return 'Thank you! Your registration has been received.';
}

/**
 * @param array<string, mixed> $event
 * @return array<string, mixed>
 */
function rm_present_registration_event(array $event): array
{
    $title = isset($event['title']) ? (string) $event['title'] : __('Untitled event', 'act-mini');
    $program_code = isset($event['programCode']) ? trim((string) $event['programCode']) : '';

    $date_display = '';
    $time_display = '';
    $start_time = isset($event['startTime']) ? trim((string) $event['startTime']) : '';
    $end_time = isset($event['endTime']) ? trim((string) $event['endTime']) : '';

    if (!empty($event['customDate'])) {
        $date_display = wp_strip_all_tags((string) $event['customDate']);
    } else {
        $start_ts = !empty($event['startDate']) ? strtotime((string) $event['startDate']) : false;
        $end_ts = !empty($event['endDate']) ? strtotime((string) $event['endDate']) : false;

        if ($start_ts) {
            $date_display = date_i18n(get_option('date_format'), $start_ts);
            if ($end_ts && date('Y-m-d', $end_ts) !== date('Y-m-d', $start_ts)) {
                $date_display .= ' – ' . date_i18n(get_option('date_format'), $end_ts);
            }
        }
    }

    if ($start_time !== '' && $end_time !== '' && $end_time !== $start_time) {
        $time_display = $start_time . ' – ' . $end_time;
    } elseif ($start_time !== '') {
        $time_display = $start_time;
    } elseif ($end_time !== '') {
        $time_display = $end_time;
    }

    $venue = isset($event['venue']) ? trim(wp_strip_all_tags((string) $event['venue'])) : '';
    $thumb_url = isset($event['thumb']) ? trim((string) $event['thumb']) : '';
    $description = isset($event['description']) ? trim((string) $event['description']) : '';

    $price_num = rm_event_registration_price($event);
    $event_currency = rm_registration_currency($event);
    $amount_display = rm_format_currency($price_num, $event_currency);

    return [
        'title'          => $title,
        'program_code'   => $program_code,
        'date_display'   => $date_display,
        'time_display'   => $time_display,
        'venue'          => $venue,
        'thumb_url'      => $thumb_url,
        'description'    => $description,
        'amount_display' => $amount_display,
    ];
}
