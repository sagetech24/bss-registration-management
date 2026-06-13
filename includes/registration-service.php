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

    return $existing !== null;
}

/**
 * Atomically increment lastId and build an order number from programCode + sequence.
 * Format: {programCode}{###}, e.g. ABCS123_009.
 *
 * @return array{ok: bool, order_number: string, error: string}
 */
function rm_allocate_registration_order_number(int $event_id): array
{
    global $wpdb;

    if ($event_id < 1) {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'This event could not be found.',
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

    $iterate_count = str_pad((string) ((int) $row['lastId']), 3, '0', STR_PAD_LEFT);

    return [
        'ok'           => true,
        'order_number' => $program_code . $iterate_count,
        'error'        => '',
    ];
}

/**
 * Validate the event, then allocate a unique order number.
 *
 * @return array{ok: bool, order_number: string, error: string}
 */
function rm_generate_registration_order_number(int $event_id): array
{
    $event_gate = rm_validate_event_registration($event_id);
    if (!$event_gate['ok']) {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => $event_gate['error'],
        ];
    }

    return rm_allocate_registration_order_number($event_id);
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
    global $wpdb;

    $event_id = isset($event['id']) ? absint($event['id']) : 0;
    $event_gate = rm_validate_event_registration($event_id);
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
        $order_result = rm_generate_registration_order_number($event_id);
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
 * @return string Flash key for PRG redirect
 */
function rm_store_registration_success_flash(string $order_number, string $status = 'confirmed'): string
{
    $flash_key = wp_generate_password(12, false);
    set_transient(
        'rm_reg_success_' . $flash_key,
        [
            'order_number' => $order_number,
            'status'       => $status,
        ],
        5 * MINUTE_IN_SECONDS
    );

    return $flash_key;
}

/**
 * @return array{order_number: string, status: string}|null
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
    if (!empty($event['customDate'])) {
        $date_display = wp_strip_all_tags((string) $event['customDate']);
    } elseif (!empty($event['startDate'])) {
        $sd = strtotime((string) $event['startDate']);
        if ($sd) {
            $date_display = date_i18n(get_option('date_format'), $sd);
            $st = isset($event['startTime']) ? trim((string) $event['startTime']) : '';
            if ($st !== '') {
                $date_display .= ' ' . $st;
            }
        }
    }

    $venue = isset($event['venue']) ? trim(wp_strip_all_tags((string) $event['venue'])) : '';
    $thumb_url = isset($event['thumb']) ? trim((string) $event['thumb']) : '';

    $price_num = rm_event_registration_price($event);
    if ($price_num > 0) {
        $decimals = floor($price_num) === $price_num ? 0 : 2;
        $amount_display = '$' . number_format_i18n($price_num, $decimals);
    } else {
        $amount_display = 'FREE';
    }

    return [
        'title'          => $title,
        'program_code'   => $program_code,
        'date_display'   => $date_display,
        'venue'          => $venue,
        'thumb_url'      => $thumb_url,
        'amount_display' => $amount_display,
    ];
}
