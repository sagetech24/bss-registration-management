<?php

/**
 * @return string
 */
function rm_generate_confirmation_number(): string
{
    return substr(uniqid('', true), -8);
}

/**
 * @param array<string, mixed> $event
 * @param list<array{core: array<string, string|null>, custom: array<string, mixed>}> $member_rows
 * @param array<string, mixed> $pricing
 * @param array{fields: list<array<string, mixed>>} $form_schema
 * @return array{ok: bool, error: string, status: string, pending_id: int, order_number: string, confirmation_number: string}
 */
function rm_v2_submit_registration(
    array $event,
    array $member_rows,
    array $pricing,
    array $form_schema,
    ?array $promotion = null,
    array $guest_rows = []
): array {
    $event_id = isset($event['id']) ? absint($event['id']) : 0;
    $source = rm_event_source_value($event);
    $event_gate = rm_validate_event_registration($event_id, $source);
    if (!$event_gate['ok']) {
        return rm_v2_submit_error($event_gate['error']);
    }

    $config = rm_effective_registration_config($event, $promotion);
    $limits = rm_effective_group_limits($event, $promotion);
    $member_count = count($member_rows);

    if ($member_count < $limits['min']) {
        return rm_v2_submit_error('At least ' . $limits['min'] . ' member(s) are required.');
    }

    if ($member_count > $limits['max']) {
        return rm_v2_submit_error('No more than ' . $limits['max'] . ' member(s) are allowed.');
    }

    if (!empty($limits['require_all_members']) && $member_count !== $limits['max']) {
        return rm_v2_submit_error(
            'This package requires exactly ' . $limits['max'] . ' registrant(s).'
        );
    }

    $guest_count = count($guest_rows);
    $guests_config = $config['guests'] ?? [];
    if (!empty($guests_config['enabled'])) {
        $guest_min = (int) ($guests_config['min'] ?? 0);
        $guest_max = (int) ($guests_config['max'] ?? 0);
        if ($guest_count < $guest_min) {
            $label = (string) ($guests_config['label_plural'] ?? 'guest(s)');
            return rm_v2_submit_error('At least ' . $guest_min . ' ' . strtolower($label) . ' required.');
        }
        if ($guest_max > 0 && $guest_count > $guest_max) {
            $label = (string) ($guests_config['label_plural'] ?? 'guest(s)');
            return rm_v2_submit_error('No more than ' . $guest_max . ' ' . strtolower($label) . ' allowed.');
        }
    } elseif ($guest_count > 0) {
        return rm_v2_submit_error('Guest registration is not enabled for this event.');
    }

    $confirmation_number = rm_generate_confirmation_number();
    $primary_email = '';
    if (isset($member_rows[0]['core']['email'])) {
        $primary_email = trim((string) $member_rows[0]['core']['email']);
    }

    $event_promotion_id = null;
    if ($promotion !== null && !empty($promotion['id'])) {
        $event_promotion_id = (int) $promotion['id'];
    } elseif (!empty($pricing['event_promotion_id'])) {
        $event_promotion_id = (int) $pricing['event_promotion_id'];
    }

    $schema_snapshot = [
        'registrant' => $form_schema['fields'] ?? [],
    ];
    if ($guest_rows !== []) {
        $guest_schema = rm_parse_guest_form_schema($event);
        $schema_snapshot['guest'] = $guest_schema['fields'] ?? [];
    }

    $header = [
        'event_id'                   => $event_id,
        'registration_mode'          => $config['mode'],
        'confirmation_number'        => $confirmation_number,
        'member_count'               => $member_count,
        'subtotal'                   => $pricing['subtotal'],
        'discount_total'             => $pricing['discount_total'],
        'total_amount'               => $pricing['total_amount'],
        'pricing_snapshot'           => wp_json_encode($pricing['pricing_snapshot']),
        'form_schema_snapshot'       => wp_json_encode($schema_snapshot),
        'promo_id'                   => $pricing['promo_id'],
        'event_promotion_id'         => $event_promotion_id,
        'payment_status'             => $pricing['total_amount'] > 0 ? 'pending' : 'free',
        'payment_request_id'         => null,
        'payment_option'             => 'N/A',
        'primary_email'              => $primary_email,
        'primary_order_number'       => '',
        'is_email_confirmation_sent' => 0,
        'created_at'                 => current_time('mysql'),
        'updated_at'                 => current_time('mysql'),
    ];

    if ($pricing['total_amount'] <= 0) {
        return rm_v2_insert_confirmed_registration($header, $member_rows, $pricing, $event_id, $source, $guest_rows);
    }

    return rm_v2_insert_pending_registration($header, $member_rows, $pricing, $event_id, $guest_rows);
}

/**
 * @return array{ok: bool, error: string, status: string, pending_id: int, order_number: string, confirmation_number: string}
 */
function rm_v2_submit_error(string $error): array
{
    return [
        'ok'                  => false,
        'error'               => $error,
        'status'              => '',
        'pending_id'          => 0,
        'order_number'        => '',
        'confirmation_number' => '',
    ];
}

/**
 * @param list<array{core: array<string, string|null>, custom: array<string, mixed>}> $member_rows
 * @return array{ok: bool, error: string, status: string, pending_id: int, order_number: string, confirmation_number: string}
 */
function rm_v2_insert_pending_registration(
    array $header,
    array $member_rows,
    array $pricing,
    int $event_id,
    array $guest_rows = []
): array {
    global $wpdb;

    $wpdb->query('START TRANSACTION');

    $inserted = $wpdb->insert('event_registration_pendings', $header);
    if (!$inserted) {
        $wpdb->query('ROLLBACK');

        return rm_v2_submit_error('Registration could not be saved. Please try again.');
    }

    $pending_id = (int) $wpdb->insert_id;
    $line_result = rm_v2_insert_registrant_lines(
        'event_registrant_pendings',
        $pending_id,
        $event_id,
        $member_rows,
        $pricing,
        'pending'
    );

    if (!$line_result['ok']) {
        $wpdb->query('ROLLBACK');

        return rm_v2_submit_error($line_result['error']);
    }

    if ($guest_rows !== []) {
        $guest_line_result = rm_v2_insert_guest_lines(
            'event_registrant_pendings',
            $pending_id,
            $event_id,
            $guest_rows,
            $pricing,
            'pending',
            count($member_rows)
        );

        if (!$guest_line_result['ok']) {
            $wpdb->query('ROLLBACK');

            return rm_v2_submit_error($guest_line_result['error']);
        }
    }

    $wpdb->query('COMMIT');

    return [
        'ok'                  => true,
        'error'               => '',
        'status'              => 'pending_payment',
        'pending_id'          => $pending_id,
        'order_number'        => '',
        'confirmation_number' => $header['confirmation_number'],
    ];
}

/**
 * @param list<array{core: array<string, string|null>, custom: array<string, mixed>}> $member_rows
 * @return array{ok: bool, error: string, status: string, pending_id: int, order_number: string, confirmation_number: string}
 */
function rm_v2_insert_confirmed_registration(
    array $header,
    array $member_rows,
    array $pricing,
    int $event_id,
    string $source = '',
    array $guest_rows = []
): array {
    global $wpdb;

    $source = rm_normalize_event_source($source);
    if ($source === '') {
        $source = rm_infer_event_source($event_id);
    }

    $header['payment_status'] = 'free';
    $header['paid_at'] = current_time('mysql');

    $wpdb->query('START TRANSACTION');

    $order_numbers = [];
    foreach ($member_rows as $index => $member_row) {
        $order_result = rm_allocate_registration_order_number($event_id, $source);
        if (!$order_result['ok']) {
            $wpdb->query('ROLLBACK');

            return rm_v2_submit_error($order_result['error']);
        }

        $order_numbers[$index] = $order_result['order_number'];
    }

    if (isset($order_numbers[0])) {
        $header['primary_order_number'] = $order_numbers[0];
    }

    $inserted = $wpdb->insert('event_registration', $header);
    if (!$inserted) {
        $wpdb->query('ROLLBACK');

        return rm_v2_submit_error('Registration could not be saved. Please try again.');
    }

    $registration_id = (int) $wpdb->insert_id;
    $line_result = rm_v2_insert_registrant_lines(
        'event_registrant',
        $registration_id,
        $event_id,
        $member_rows,
        $pricing,
        'confirmed',
        $order_numbers
    );

    if (!$line_result['ok']) {
        $wpdb->query('ROLLBACK');

        return rm_v2_submit_error($line_result['error']);
    }

    if ($guest_rows !== []) {
        $guest_line_result = rm_v2_insert_guest_lines(
            'event_registrant',
            $registration_id,
            $event_id,
            $guest_rows,
            $pricing,
            'confirmed',
            count($member_rows)
        );

        if (!$guest_line_result['ok']) {
            $wpdb->query('ROLLBACK');

            return rm_v2_submit_error($guest_line_result['error']);
        }
    }

    $wpdb->query('COMMIT');

    return [
        'ok'                  => true,
        'error'               => '',
        'status'              => 'confirmed',
        'pending_id'          => 0,
        'order_number'        => $order_numbers[0] ?? '',
        'confirmation_number' => $header['confirmation_number'],
    ];
}

/**
 * Promote pending v2 registration to confirmed after payment.
 *
 * @return array{ok: bool, order_number: string, error: string}
 */
function rm_v2_finalize_paid_registration(int $pending_id, string $payment_request_id, string $payment_option = 'N/A'): array
{
    global $wpdb;

    if ($pending_id < 1) {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'Pending registration could not be found.',
        ];
    }

    $header = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM `event_registration_pendings` WHERE `id` = %d LIMIT 1',
            $pending_id
        ),
        ARRAY_A
    );

    if (!is_array($header) || $header === []) {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'Pending registration could not be found.',
        ];
    }

    $event_id = isset($header['event_id']) ? absint($header['event_id']) : 0;
    $source = rm_infer_event_source($event_id);
    $primary_email = trim((string) ($header['primary_email'] ?? ''));

    if ($event_id > 0 && $primary_email !== '') {
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT `primary_order_number` FROM `event_registration`
                 WHERE `primary_email` = %s AND `event_id` = %d AND `payment_status` IN (%s, %s)
                 LIMIT 1',
                $primary_email,
                $event_id,
                'paid',
                'free'
            )
        );

        if (is_string($existing) && $existing !== '') {
            return [
                'ok'           => true,
                'order_number' => $existing,
                'error'        => '',
            ];
        }
    }

    $lines = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT * FROM `event_registrant_pendings` WHERE `registration_id` = %d ORDER BY `member_index` ASC',
            $pending_id
        ),
        ARRAY_A
    );

    if (!is_array($lines) || $lines === []) {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'Pending registrants could not be found.',
        ];
    }

    $wpdb->query('START TRANSACTION');

    $order_numbers = [];
    foreach ($lines as $index => $line) {
        $order_result = rm_allocate_registration_order_number($event_id, $source);
        if (!$order_result['ok']) {
            $wpdb->query('ROLLBACK');

            return [
                'ok'           => false,
                'order_number' => '',
                'error'        => $order_result['error'],
            ];
        }

        $order_numbers[$index] = $order_result['order_number'];
    }

    unset($header['id']);
    $header['payment_status'] = 'paid';
    $header['payment_request_id'] = sanitize_text_field($payment_request_id);
    $header['payment_option'] = $payment_option === '' ? 'N/A' : sanitize_text_field($payment_option);
    $header['primary_order_number'] = $order_numbers[0] ?? '';
    $header['paid_at'] = current_time('mysql');
    $header['updated_at'] = current_time('mysql');

    $inserted = $wpdb->insert('event_registration', $header);
    if (!$inserted) {
        $wpdb->query('ROLLBACK');

        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'Registration could not be saved. Please try again.',
        ];
    }

    $registration_id = (int) $wpdb->insert_id;

    foreach ($lines as $index => $line) {
        unset($line['id']);
        $line['registration_id'] = $registration_id;
        $line['order_number'] = $order_numbers[$index] ?? '';
        $line['status'] = 'confirmed';
        $line['updated_at'] = current_time('mysql');

        $line_inserted = $wpdb->insert('event_registrant', $line);
        if (!$line_inserted) {
            $wpdb->query('ROLLBACK');

            return [
                'ok'           => false,
                'order_number' => '',
                'error'        => 'Registration could not be saved. Please try again.',
            ];
        }
    }

    $wpdb->delete('event_registrant_pendings', ['registration_id' => $pending_id], ['%d']);
    $wpdb->delete('event_registration_pendings', ['id' => $pending_id], ['%d']);

    $wpdb->query('COMMIT');

    return [
        'ok'           => true,
        'order_number' => $order_numbers[0] ?? '',
        'error'        => '',
    ];
}

/**
 * @return array<string, mixed>|null
 */
function rm_v2_load_pending_header(int $pending_id): ?array
{
    global $wpdb;

    if ($pending_id < 1) {
        return null;
    }

    $pending = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM `event_registration_pendings` WHERE `id` = %d LIMIT 1',
            $pending_id
        ),
        ARRAY_A
    );

    return is_array($pending) && $pending !== [] ? $pending : null;
}

/**
 * @return array<string, mixed>|null
 */
function rm_v2_load_pending_primary_registrant(int $pending_id): ?array
{
    global $wpdb;

    if ($pending_id < 1) {
        return null;
    }

    $row = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM `event_registrant_pendings`
             WHERE `registration_id` = %d AND `member_index` = 0
             LIMIT 1',
            $pending_id
        ),
        ARRAY_A
    );

    return is_array($row) && $row !== [] ? $row : null;
}

/**
 * @param list<array{core: array<string, string|null>, custom: array<string, mixed>}> $member_rows
 * @param array<string, string> $order_numbers
 * @return array{ok: bool, error: string}
 */
function rm_v2_insert_registrant_lines(
    string $table,
    int $registration_id,
    int $event_id,
    array $member_rows,
    array $pricing,
    string $status,
    array $order_numbers = []
): array {
    global $wpdb;

    foreach ($member_rows as $index => $member_row) {
        $priced = $pricing['members'][$index] ?? [
            'role'             => $index === 0 ? 'primary' : 'member',
            'unit_price'       => 0.0,
            'discount_percent' => 0.0,
        ];

        $custom_json = $member_row['custom'] !== []
            ? wp_json_encode($member_row['custom'])
            : null;

        $row = [
            'registration_id'  => $registration_id,
            'event_id'         => $event_id,
            'member_index'     => $index,
            'role'             => $priced['role'],
            'order_number'     => $order_numbers[$index] ?? '',
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
            'unit_price'       => $priced['unit_price'],
            'discount_percent' => $priced['discount_percent'],
            'status'           => $status,
            'created_at'       => current_time('mysql'),
            'updated_at'       => current_time('mysql'),
        ];

        $inserted = $wpdb->insert($table, $row);
        if (!$inserted) {
            return [
                'ok'    => false,
                'error' => 'Registration could not be saved. Please try again.',
            ];
        }
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * Insert guest (addon) line items into a registrant table.
 *
 * Guest rows use `role = 'addon'` and are indexed after the member rows.
 * Core-mapped fields (given_name, family_name, etc.) go into columns;
 * everything else is stored in custom_responses.
 *
 * @param list<array{core: array<string, string|null>, custom: array<string, mixed>}> $guest_rows
 * @return array{ok: bool, error: string}
 */
function rm_v2_insert_guest_lines(
    string $table,
    int $registration_id,
    int $event_id,
    array $guest_rows,
    array $pricing,
    string $status,
    int $member_offset = 0
): array {
    global $wpdb;

    $guest_price = (float) ($pricing['guest_price'] ?? 0);

    foreach ($guest_rows as $gi => $guest_row) {
        $core = $guest_row['core'] ?? [];
        $custom = $guest_row['custom'] ?? [];

        $custom_json = $custom !== [] ? wp_json_encode($custom) : null;

        $row = [
            'registration_id'  => $registration_id,
            'event_id'         => $event_id,
            'member_index'     => $member_offset + $gi,
            'role'             => 'addon',
            'order_number'     => '',
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
            'status'           => $status,
            'created_at'       => current_time('mysql'),
            'updated_at'       => current_time('mysql'),
        ];

        $inserted = $wpdb->insert($table, $row);
        if (!$inserted) {
            return [
                'ok'    => false,
                'error' => 'Guest registration could not be saved. Please try again.',
            ];
        }
    }

    return ['ok' => true, 'error' => ''];
}
