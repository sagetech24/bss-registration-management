<?php

/**
 * Build member rows from validated responses for each member.
 *
 * @param array{fields: list<array<string, mixed>>} $schema
 * @param list<array<string, mixed>> $members_responses
 * @return array{ok: bool, error: string, member_rows: list<array{core: array<string, string|null>, custom: array<string, mixed>}>, form_errors: array<string, string>}
 */
function rm_build_member_rows_from_responses(array $schema, array $members_responses): array
{
    $member_rows = [];
    $all_errors = [];

    foreach ($members_responses as $index => $responses) {
        if (!is_array($responses)) {
            $all_errors['members'] = 'Invalid member data.';

            continue;
        }

        $errors = rm_validate_form_responses($schema, $responses);
        if ($errors !== []) {
            foreach ($errors as $key => $message) {
                $all_errors['member_' . $index . '_' . $key] = $message;
            }
            continue;
        }

        $member_rows[] = rm_split_responses($schema, $responses);
    }

    if ($all_errors !== []) {
        return [
            'ok'          => false,
            'error'       => 'Please correct the highlighted fields.',
            'member_rows' => [],
            'form_errors' => $all_errors,
        ];
    }

    return [
        'ok'          => true,
        'error'       => '',
        'member_rows' => $member_rows,
        'form_errors' => [],
    ];
}

/**
 * @param array<string, mixed> $event
 * @return array{ok: bool, error: string, status: string, pending_id: int, order_number: string, form_errors: array<string, string>}
 */
function rm_submit_v2_registration(array $event): array
{
    $schema = rm_parse_form_schema($event);
    $config = rm_parse_registration_config($event);
    $members_responses = rm_parse_members_from_post();

    if ($members_responses === []) {
        if ($config['mode'] === RM_REGISTRATION_MODE_INDIVIDUAL) {
            $members_responses = [rm_form_responses_from_post($schema)];
        } else {
            return [
                'ok'          => false,
                'error'       => 'No member data was submitted.',
                'status'      => '',
                'pending_id'  => 0,
                'order_number'=> '',
                'form_errors' => [],
            ];
        }
    }

    $build = rm_build_member_rows_from_responses($schema, $members_responses);
    if (!$build['ok']) {
        return [
            'ok'          => false,
            'error'       => $build['error'],
            'status'      => '',
            'pending_id'  => 0,
            'order_number'=> '',
            'form_errors' => $build['form_errors'],
        ];
    }

    $pricing = rm_calculate_registration_pricing($event, $build['member_rows']);
    $result = rm_v2_submit_registration($event, $build['member_rows'], $pricing, $schema);

    return [
        'ok'          => $result['ok'],
        'error'       => $result['error'],
        'status'      => $result['status'],
        'pending_id'  => $result['pending_id'],
        'order_number'=> $result['order_number'],
        'form_errors' => [],
    ];
}

/**
 * Normalize v2 registrant row to legacy presenter shape.
 *
 * @param array<string, mixed> $registrant
 * @param array<string, mixed>|null $header
 * @return array<string, mixed>
 */
function rm_normalize_v2_registrant_row(array $registrant, ?array $header = null): array
{
    $payment_status = is_array($header) ? (string) ($header['payment_status'] ?? '') : '';
    $is_paid = in_array($payment_status, ['paid', 'free'], true);

    return [
        'id'                     => isset($registrant['id']) ? (int) $registrant['id'] : 0,
        'christianName'          => (string) ($registrant['christian_name'] ?? ''),
        'givenName'              => (string) ($registrant['given_name'] ?? ''),
        'familyName'             => (string) ($registrant['family_name'] ?? ''),
        'certificateName'        => (string) ($registrant['certificate_name'] ?? ''),
        'email'                  => (string) ($registrant['email'] ?? ''),
        'contact'                => (string) ($registrant['contact'] ?? ''),
        'nric'                   => (string) ($registrant['nric'] ?? ''),
        'title'                  => (string) ($registrant['title'] ?? ''),
        'address1'               => (string) ($registrant['address1'] ?? ''),
        'address2'               => (string) ($registrant['address2'] ?? ''),
        'postcode'               => (string) ($registrant['postcode'] ?? ''),
        'churchName'             => (string) ($registrant['church_name'] ?? ''),
        'orderNumber'            => (string) ($registrant['order_number'] ?? ''),
        'confirmationNumber'     => is_array($header) ? (string) ($header['confirmation_number'] ?? '') : '',
        'amount'                 => isset($registrant['unit_price']) ? (float) $registrant['unit_price'] : 0.0,
        'payment'                => is_array($header) ? ($header['payment_request_id'] ?? null) : null,
        'paymentOption'          => is_array($header) ? (string) ($header['payment_option'] ?? 'N/A') : 'N/A',
        'groupBookings'          => is_array($header) && ($header['member_count'] ?? 1) > 1 ? '1' : '0',
        'isEmailConfirmationSent'=> is_array($header) ? (string) ($header['is_email_confirmation_sent'] ?? '0') : '0',
        'datestamp'              => (string) ($registrant['created_at'] ?? ''),
        'events'                 => isset($registrant['event_id']) ? (int) $registrant['event_id'] : 0,
        'note'                   => isset($registrant['custom_responses']) ? (string) $registrant['custom_responses'] : '{}',
        '_v2'                    => true,
        '_registration_id'       => isset($registrant['registration_id']) ? (int) $registrant['registration_id'] : 0,
        '_member_index'          => isset($registrant['member_index']) ? (int) $registrant['member_index'] : 0,
        '_role'                  => (string) ($registrant['role'] ?? ''),
        '_payment_status'        => $payment_status,
        '_is_paid'               => $is_paid,
        '_header_total'          => is_array($header) ? (float) ($header['total_amount'] ?? 0) : 0.0,
    ];
}

/**
 * @return array{registrants: array<int, array<string, mixed>>, error: string}
 */
function rm_fetch_v2_registrants_from_db(int $event_id): array
{
    global $wpdb;

    if ($event_id < 1) {
        return [
            'registrants' => [],
            'error'       => 'Event id is required.',
        ];
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT r.*, h.confirmation_number, h.payment_status, h.payment_request_id,
                    h.payment_option, h.total_amount AS header_total, h.member_count,
                    h.is_email_confirmation_sent
             FROM `event_registrant` r
             INNER JOIN `event_registration` h ON h.id = r.registration_id
             WHERE r.event_id = %d
             ORDER BY r.created_at ASC',
            $event_id
        ),
        ARRAY_A
    );

    if (!is_array($rows)) {
        return [
            'registrants' => [],
            'error'       => 'Failed to load registrants.',
        ];
    }

    $normalized = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $header = [
            'confirmation_number'      => $row['confirmation_number'] ?? '',
            'payment_status'           => $row['payment_status'] ?? '',
            'payment_request_id'       => $row['payment_request_id'] ?? null,
            'payment_option'           => $row['payment_option'] ?? 'N/A',
            'total_amount'             => $row['header_total'] ?? 0,
            'member_count'             => $row['member_count'] ?? 1,
            'is_email_confirmation_sent' => $row['is_email_confirmation_sent'] ?? 0,
        ];

        unset(
            $row['confirmation_number'],
            $row['payment_status'],
            $row['payment_request_id'],
            $row['payment_option'],
            $row['header_total'],
            $row['member_count'],
            $row['is_email_confirmation_sent']
        );

        $normalized[] = rm_normalize_v2_registrant_row($row, $header);
    }

    return [
        'registrants' => $normalized,
        'error'       => '',
    ];
}

/**
 * @return array{registrant: array<string, mixed>|null, error: string}
 */
function rm_fetch_v2_registrant_by_id(int $registrant_id, int $event_id): array
{
    global $wpdb;

    if ($registrant_id < 1 || $event_id < 1) {
        return [
            'registrant' => null,
            'error'      => 'Registrant id and event id are required.',
        ];
    }

    $row = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT r.*, h.confirmation_number, h.payment_status, h.payment_request_id,
                    h.payment_option, h.total_amount AS header_total, h.member_count,
                    h.is_email_confirmation_sent
             FROM `event_registrant` r
             INNER JOIN `event_registration` h ON h.id = r.registration_id
             WHERE r.id = %d AND r.event_id = %d
             LIMIT 1',
            $registrant_id,
            $event_id
        ),
        ARRAY_A
    );

    if (!is_array($row) || $row === []) {
        return [
            'registrant' => null,
            'error'      => 'Registrant could not be found.',
        ];
    }

    $header = [
        'confirmation_number'        => $row['confirmation_number'] ?? '',
        'payment_status'             => $row['payment_status'] ?? '',
        'payment_request_id'         => $row['payment_request_id'] ?? null,
        'payment_option'             => $row['payment_option'] ?? 'N/A',
        'total_amount'               => $row['header_total'] ?? 0,
        'member_count'               => $row['member_count'] ?? 1,
        'is_email_confirmation_sent' => $row['is_email_confirmation_sent'] ?? 0,
    ];

    unset(
        $row['confirmation_number'],
        $row['payment_status'],
        $row['payment_request_id'],
        $row['payment_option'],
        $row['header_total'],
        $row['member_count'],
        $row['is_email_confirmation_sent']
    );

    return [
        'registrant' => rm_normalize_v2_registrant_row($row, $header),
        'error'      => '',
    ];
}

/**
 * Convert v2 pending primary registrant to legacy shape for HitPay checkout.
 *
 * @param array<string, mixed> $registrant
 * @return array<string, string>
 */
function rm_v2_registrant_for_payment(array $registrant): array
{
    return [
        'christianName' => (string) ($registrant['christian_name'] ?? $registrant['given_name'] ?? ''),
        'familyName'    => (string) ($registrant['family_name'] ?? ''),
        'email'         => (string) ($registrant['email'] ?? ''),
        'contact'       => (string) ($registrant['contact'] ?? ''),
    ];
}
