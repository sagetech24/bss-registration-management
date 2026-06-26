<?php

/**
 * @param array<string, array<int, array<string, mixed>>> $events_by_year
 * @return array<int, array<string, mixed>>
 */
function rm_flatten_events_list(array $events_by_year): array
{
    $flat = [];

    foreach ($events_by_year as $events_list) {
        if (!is_array($events_list)) {
            continue;
        }

        foreach ($events_list as $event) {
            if (is_array($event)) {
                $flat[] = $event;
            }
        }
    }

    return $flat;
}

/**
 * @param array<int, array<string, mixed>> $events
 */
function rm_resolve_event_code(string $requested_code, array $events): string
{
    if ($requested_code !== '') {
        return $requested_code;
    }

    if (empty($events[0]['programCode'])) {
        return '';
    }

    return sanitize_text_field((string) $events[0]['programCode']);
}

/**
 * @param array<int, array<string, mixed>> $events
 * @return array<string, mixed>|null
 */
function rm_find_event_by_code(array $events, string $event_code): ?array
{
    if ($event_code === '') {
        return null;
    }

    foreach ($events as $event) {
        if (($event['programCode'] ?? '') === $event_code) {
            return $event;
        }
    }

    return null;
}

/**
 * @param array<string, mixed> $registrant
 * @return array<string, mixed>
 */
function rm_present_registrant_row(array $registrant, bool $is_pending = false): array
{
    $first_name = trim((string) ($registrant['christianName'] ?? $registrant['givenName'] ?? ''));
    $last_name = trim((string) ($registrant['familyName'] ?? ''));
    $full_name = trim($first_name . ' ' . $last_name);

    if ($full_name === '') {
        $full_name = trim((string) ($registrant['name'] ?? ''));
    }
    if ($full_name === '') {
        $full_name = 'N/A';
    }

    $email = trim((string) ($registrant['email'] ?? ''));
    $phone = trim((string) ($registrant['contact'] ?? ($registrant['phone'] ?? '')));
    $order_number = trim((string) ($registrant['orderNumber'] ?? ''));
    $payment_raw = $registrant['payment'] ?? null;
    $hitpay = isset($registrant['_hitpay']) && is_array($registrant['_hitpay'])
        ? $registrant['_hitpay']
        : [];

    $hitpay_status = strtolower(trim((string) ($hitpay['status'] ?? '')));
    $hitpay_request_status = strtolower(trim((string) ($hitpay['payment_request_status'] ?? '')));

    if ($hitpay_status !== '' || $hitpay_request_status !== '') {
        $is_paid = in_array($hitpay_status, ['succeeded', 'completed'], true)
            || $hitpay_request_status === 'completed';
        $payment_status = $is_paid
            ? 'Paid'
            : ucwords(str_replace('_', ' ', $hitpay_status !== '' ? $hitpay_status : $hitpay_request_status));
    } else {
        $is_paid = $payment_raw !== '' && $payment_raw !== null;
        $payment_status = $is_paid ? 'Paid' : 'Pending';
    }

    $raw_date = trim((string) ($registrant['datestamp'] ?? ''));
    $date_display = rm_format_payment_transaction_datetime($raw_date);

    $amount_raw = $registrant['amount'] ?? null;
    if (!empty($hitpay['amount']) && is_numeric($hitpay['amount'])) {
        $amount_raw = (float) $hitpay['amount'];
    }
    $currency = strtoupper(trim((string) ($hitpay['currency'] ?? '')));
    $amount_display = '—';
    if ($amount_raw !== null && $amount_raw !== '') {
        $amount_display = $currency !== ''
            ? $currency . ' ' . number_format_i18n((float) $amount_raw, 2)
            : '$' . number_format_i18n((float) $amount_raw, 2);
    }

    $email_sent = ($registrant['isEmailConfirmationSent'] ?? '') === '1';

    $payment_request_id = trim((string) ($hitpay['payment_request_id'] ?? ''));
    if ($payment_request_id === '' && $payment_raw !== null && $payment_raw !== '') {
        $payment_request_id = trim((string) $payment_raw);
    }

    $payment_method = trim((string) ($hitpay['payment_method'] ?? ''));
    if ($payment_method === '' || $payment_method === 'N/A') {
        $payment_option = trim((string) ($registrant['paymentOption'] ?? ''));
        $payment_method = $payment_option !== '' && $payment_option !== 'N/A'
            ? rm_payment_normalize_option($payment_option)
            : 'N/A';
    }
    $payment_method_logo = trim((string) ($hitpay['payment_method_logo'] ?? ''));

    $charge_hitpay = isset($registrant['_charge_hitpay']) && is_array($registrant['_charge_hitpay'])
        ? $registrant['_charge_hitpay']
        : [];
    $charge_payment_method = trim((string) ($charge_hitpay['payment_method'] ?? ''));
    $charge_payment_method_logo = trim((string) ($charge_hitpay['payment_method_logo'] ?? ''));

    $charge_amount_raw = !empty($charge_hitpay['amount']) && is_numeric($charge_hitpay['amount'])
        ? (float) $charge_hitpay['amount']
        : null;
    $charge_currency = strtoupper(trim((string) ($charge_hitpay['currency'] ?? '')));
    $charge_amount_display = $amount_display;
    if ($charge_amount_raw !== null) {
        $charge_amount_display = $charge_currency !== ''
            ? $charge_currency . ' ' . number_format_i18n($charge_amount_raw, 2)
            : '$' . number_format_i18n($charge_amount_raw, 2);
    }

    return [
        'registrant_id'      => isset($registrant['id']) ? (int) $registrant['id'] : 0,
        'full_name'          => $full_name,
        'email'              => $email,
        'phone'              => $phone,
        'order_number'       => $order_number,
        'payment_method'     => $payment_method,
        'payment_method_logo' => $payment_method_logo,
        'charge_payment_method' => $charge_payment_method !== '' ? $charge_payment_method : 'N/A',
        'charge_payment_method_logo' => $charge_payment_method_logo,
        'charge_amount_display' => $charge_amount_display,
        'charge_currency'    => $charge_currency !== '' ? $charge_currency : 'N/A',
        'payment_request_id' => $payment_request_id,
        'has_payment'        => $payment_request_id !== '',
        'payment_status'     => $payment_status,
        'is_paid'            => $is_paid,
        'amount_display'     => $amount_display,
        'currency'           => $currency !== '' ? $currency : 'N/A',
        'date_display'       => $date_display,
        'email_sent'         => $email_sent,
        'email_sent_label'   => $email_sent ? 'Yes' : 'No',
        'is_pending'         => $is_pending,
    ];
}

/**
 * @return array<string, string>
 */
function rm_registrant_profile_field_labels(): array
{
    return [
        'id'                     => 'Record ID',
        'orderNumber'            => 'Order number',
        'confirmationNumber'     => 'Confirmation number',
        'datestamp'              => 'Registered at',
        'title'                  => 'Title',
        'nric'                   => 'NRIC (last 4 digits)',
        'christianName'          => 'Christian name',
        'givenName'              => 'Given name',
        'familyName'             => 'Family name',
        'certificateName'        => 'Certificate name',
        'email'                  => 'Email',
        'contact'                => 'Contact number',
        'address1'               => 'Address 1',
        'address2'               => 'Address 2',
        'postcode'               => 'Postal code',
        'churchName'             => 'Church name',
        'note'                   => 'Additional notes',
        'events'                 => 'Event ID',
        'amount'                 => 'Amount',
        'payment'                => 'Payment reference',
        'paymentOption'          => 'Payment option',
        'groupBookings'          => 'Group booking',
        'isEmailConfirmationSent' => 'Confirmation email sent',
    ];
}

/**
 * @return array<int, string>
 */
function rm_registrant_profile_field_order(): array
{
    return [
        'orderNumber',
        'confirmationNumber',
        'datestamp',
        'title',
        'nric',
        'christianName',
        'givenName',
        'familyName',
        'certificateName',
        'email',
        'contact',
        'address1',
        'address2',
        'postcode',
        'churchName',
        'note',
        'events',
        'amount',
        'payment',
        'paymentOption',
        'groupBookings',
        'isEmailConfirmationSent',
        'id',
    ];
}

function rm_format_registrant_profile_value(string $key, mixed $value): string
{
    if ($value === null) {
        return 'N/A';
    }

    if ($key === 'note') {
        $raw = trim((string) $value);
        if ($raw === '' || $raw === '{}' || $raw === '[]') {
            return 'N/A';
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $raw !== '' ? $raw : 'N/A';
        }

        if ($decoded === []) {
            return 'N/A';
        }

        return wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    if ($key === 'datestamp') {
        $formatted = rm_format_payment_transaction_datetime(trim((string) $value));

        return $formatted !== '' ? $formatted : 'N/A';
    }

    if ($key === 'isEmailConfirmationSent') {
        return ((string) $value === '1') ? 'Yes' : 'No';
    }

    if ($key === 'groupBookings') {
        return ((string) $value === '1') ? 'Yes' : 'No';
    }

    if ($key === 'amount') {
        if (!is_numeric($value)) {
            return 'N/A';
        }

        $amount = (float) $value;

        return $amount > 0
            ? '$' . number_format_i18n($amount, floor($amount) === $amount ? 0 : 2)
            : 'FREE';
    }

    $string_value = trim((string) $value);

    return $string_value !== '' ? $string_value : 'N/A';
}

/**
 * @param array<string, mixed> $registrant
 * @return array<string, mixed>
 */
function rm_present_registrant_profile(array $registrant): array
{
    $labels = rm_registrant_profile_field_labels();
    $order = rm_registrant_profile_field_order();
    $fields = [];
    $seen = [];

    foreach ($order as $key) {
        if (!array_key_exists($key, $registrant)) {
            continue;
        }

        $fields[] = [
            'key'   => $key,
            'label' => $labels[$key] ?? ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $key)),
            'value' => rm_format_registrant_profile_value($key, $registrant[$key]),
        ];
        $seen[$key] = true;
    }

    foreach ($registrant as $key => $value) {
        if (!is_string($key) || isset($seen[$key])) {
            continue;
        }

        $fields[] = [
            'key'   => $key,
            'label' => $labels[$key] ?? ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $key)),
            'value' => rm_format_registrant_profile_value($key, $value),
        ];
    }

    $first_name = trim((string) ($registrant['christianName'] ?? $registrant['givenName'] ?? ''));
    $last_name = trim((string) ($registrant['familyName'] ?? ''));
    $full_name = trim($first_name . ' ' . $last_name);
    if ($full_name === '') {
        $full_name = trim((string) ($registrant['name'] ?? 'N/A'));
    }

    return [
        'registrant_id' => isset($registrant['id']) ? (int) $registrant['id'] : 0,
        'full_name'     => $full_name !== '' ? $full_name : 'N/A',
        'order_number'  => trim((string) ($registrant['orderNumber'] ?? '')),
        'fields'        => $fields,
    ];
}

/**
 * @return array{registrant: array<string, mixed>|null, error: string}
 */
function rm_fetch_registrant_by_id(int $registrant_id, int $event_id): array
{
    global $wpdb;

    if ($registrant_id < 1 || $event_id < 1) {
        return [
            'registrant' => null,
            'error'      => 'Registrant id and event id are required.',
        ];
    }

    $registrant = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM `bss_registrant` WHERE `id` = %d AND `events` = %d LIMIT 1',
            $registrant_id,
            $event_id
        ),
        ARRAY_A
    );

    if (!is_array($registrant) || $registrant === []) {
        return [
            'registrant' => null,
            'error'      => 'Registrant could not be found.',
        ];
    }

    return [
        'registrant' => $registrant,
        'error'      => '',
    ];
}

/**
 * @return array{registrants: array<int, array<string, mixed>>, error: string}
 */
function rm_fetch_registrants_from_db(int $event_id): array
{
    if ($event_id < 1) {
        return [
            'registrants' => [],
            'error'       => 'Event id is required.',
        ];
    }

    global $wpdb;

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT * FROM `bss_registrant` WHERE `events` = %d ORDER BY `datestamp` ASC',
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

    return [
        'registrants' => $rows,
        'error'       => '',
    ];
}

/**
 * @param array<string, mixed> $charge
 * @return array<string, mixed>
 */
function rm_present_registrant_charge_details(array $charge): array
{
    $summary = rm_hitpay_summarize_charge($charge);
    $status = strtolower(trim($summary['status']));
    $is_succeeded = in_array($status, ['succeeded', 'completed', 'success', 'succeeded_manually'], true);

    $amount = (float) $summary['amount'];
    $currency = strtoupper(trim($summary['currency']));
    $amount_display = $currency !== ''
        ? $currency . ' ' . number_format_i18n($amount, 2)
        : '$' . number_format_i18n($amount, 2);

    $status_label = $status !== ''
        ? ucwords(str_replace('_', ' ', $status))
        : 'Unknown';

    return [
        'charge_id'              => $summary['charge_id'],
        'payment_request_id'     => $summary['payment_request_id'],
        'reference_number'       => $summary['reference_number'] !== '' ? $summary['reference_number'] : 'N/A',
        'order_reference_number' => $summary['order_reference_number'] !== '' ? $summary['order_reference_number'] : 'N/A',
        'status'                 => $status_label,
        'is_succeeded'           => $is_succeeded,
        'amount_display'         => $amount_display,
        'currency'               => $currency !== '' ? $currency : 'N/A',
        'payment_method'         => $summary['payment_method'],
        'payment_method_logo'    => $summary['payment_method_logo'],
        'customer_name'          => $summary['customer_name'] !== '' ? $summary['customer_name'] : 'N/A',
        'customer_email'         => $summary['customer_email'] !== '' ? $summary['customer_email'] : 'N/A',
        'customer_phone'         => $summary['customer_phone'] !== '' ? $summary['customer_phone'] : 'N/A',
        'paid_display'           => rm_format_payment_transaction_datetime(trim($summary['paid_at'])),
        'created_display'        => rm_format_payment_transaction_datetime((string) ($summary['created_at'] ?? '')),
        'channel'                => trim((string) ($summary['channel'] ?? '')) !== '' ? trim((string) $summary['channel']) : 'N/A',
        'remark'                 => trim($summary['remark']) !== '' ? trim($summary['remark']) : 'N/A',
    ];
}

/**
 * @param array<string, mixed> $payment_request
 * @return array<string, mixed>
 */
function rm_present_registrant_payment_details(array $payment_request): array
{
    $summary = rm_hitpay_summarize_payment_request($payment_request);
    $status = strtolower(trim($summary['status']));
    $is_completed = in_array($status, ['completed', 'succeeded', 'success'], true);

    $amount = (float) $summary['amount'];
    $currency = strtoupper(trim($summary['currency']));
    $amount_display = $currency !== ''
        ? $currency . ' ' . number_format_i18n($amount, 2)
        : '$' . number_format_i18n($amount, 2);

    $status_label = $status !== ''
        ? ucwords(str_replace('_', ' ', $status))
        : 'Unknown';

    $paid_at = trim($summary['paid_at']);
    $created_at = trim((string) ($payment_request['created_at'] ?? ''));

    return [
        'payment_request_id' => $summary['payment_request_id'],
        'reference_number'   => $summary['reference_number'],
        'status'             => $status_label,
        'is_completed'       => $is_completed,
        'amount_display'     => $amount_display,
        'currency'           => $currency !== '' ? $currency : 'N/A',
        'payment_method'     => $summary['payment_method'],
        'payment_method_logo' => $summary['payment_method_logo'],
        'customer_name'      => $summary['customer_name'] !== '' ? $summary['customer_name'] : 'N/A',
        'customer_email'     => $summary['customer_email'] !== '' ? $summary['customer_email'] : 'N/A',
        'paid_display'       => rm_format_payment_transaction_datetime($paid_at),
        'created_display'    => rm_format_payment_transaction_datetime($created_at),
        'purpose'            => trim((string) ($payment_request['purpose'] ?? '')) !== ''
            ? trim((string) $payment_request['purpose'])
            : 'N/A',
    ];
}

/**
 * @param array<int, array<string, mixed>> $registrants
 * @return array{total: int, paid_count: int, pending_count: int, total_revenue: float}
 */
function rm_registrants_summary(array $registrants): array
{
    $paid_count = 0;
    $total_revenue = 0.0;

    foreach ($registrants as $row) {
        if (!is_array($row)) {
            continue;
        }

        $hitpay = isset($row['_hitpay']) && is_array($row['_hitpay']) ? $row['_hitpay'] : [];
        $hitpay_status = strtolower(trim((string) ($hitpay['status'] ?? '')));
        $hitpay_request_status = strtolower(trim((string) ($hitpay['payment_request_status'] ?? '')));
        $payment = $row['payment'] ?? null;

        $is_paid = in_array($hitpay_status, ['succeeded', 'completed'], true)
            || $hitpay_request_status === 'completed'
            || ($payment !== '' && $payment !== null);

        if ($is_paid) {
            $paid_count++;
        }

        if (!empty($hitpay['amount']) && is_numeric($hitpay['amount'])) {
            $total_revenue += (float) $hitpay['amount'];
        } else {
            $total_revenue += (float) ($row['amount'] ?? 0);
        }
    }

    return [
        'total'         => count($registrants),
        'paid_count'    => $paid_count,
        'pending_count' => max(0, count($registrants) - $paid_count),
        'total_revenue' => $total_revenue,
    ];
}

function rm_is_event_not_found(string $event_code, ?array $event, string $error_message): bool
{
    if ($event_code === '' || $event !== null) {
        return false;
    }

    if ($error_message === '') {
        return true;
    }

    return (bool) preg_match('/HTTP 4\d\d/', $error_message);
}

/**
 * @param array<string, array<int, array<string, mixed>>> $events_by_year
 * @return array<string, mixed>
 */
function rm_build_registrants_context(array $events_by_year, string $requested_event_code, int $requested_event_id = 0): array
{
    $event_options = rm_flatten_events_list($events_by_year);
    $selected_event_code = rm_resolve_event_code($requested_event_code, $event_options);
    $selected_event = null;
    $event_id = $requested_event_id > 0 ? $requested_event_id : 0;

    if ($event_id > 0) {
        $selected_event = rm_get_event_by_id($event_id);
        if ($selected_event !== null && $selected_event_code === '') {
            $selected_event_code = trim((string) ($selected_event['programCode'] ?? ''));
        }
    }

    if ($selected_event === null) {
        $selected_event = rm_find_event_by_code($event_options, $selected_event_code);
    }

    $error_message = '';

    if ($selected_event_code === '') {
        return [
            'event_options'         => $event_options,
            'selected_event_code'   => '',
            'selected_event'        => null,
            'registrants'           => [],
            'registrant_rows'       => [],
            'registrants_summary'   => [
                'total'         => 0,
                'paid_count'    => 0,
                'pending_count' => 0,
                'total_revenue' => 0.0,
            ],
            'registrants_error'     => '',
            'hitpay_orphans'        => 0,
        ];
    }

    if ($selected_event === null) {
        $event_fetch = rm_fetch_event($selected_event_code);
        if ($event_fetch['error'] !== '') {
            $error_message = $event_fetch['error'];
        } elseif (is_array($event_fetch['event']) && $event_fetch['event'] !== []) {
            $selected_event = $event_fetch['event'];
        }
    }

    if (rm_is_event_not_found($selected_event_code, $selected_event, $error_message)) {
        return [
            'event_options'         => $event_options,
            'selected_event_code'   => $selected_event_code,
            'selected_event'        => null,
            'registrants'           => [],
            'registrant_rows'       => [],
            'registrants_summary'   => [
                'total'         => 0,
                'paid_count'    => 0,
                'pending_count' => 0,
                'total_revenue' => 0.0,
            ],
            'registrants_error'     => '',
            'event_not_found'       => true,
            'hitpay_orphans'        => 0,
        ];
    }

    if ($selected_event === null) {
        return [
            'event_options'         => $event_options,
            'selected_event_code'   => $selected_event_code,
            'selected_event'        => null,
            'registrants'           => [],
            'registrant_rows'       => [],
            'registrants_summary'   => [
                'total'         => 0,
                'paid_count'    => 0,
                'pending_count' => 0,
                'total_revenue' => 0.0,
            ],
            'registrants_error'     => $error_message,
            'event_not_found'       => false,
            'hitpay_orphans'        => 0,
        ];
    }

    if ($event_id < 1 && is_array($selected_event)) {
        $event_id = isset($selected_event['id']) ? absint($selected_event['id']) : 0;
    }

    $api_args = [
        'action' => 'event-registrants-data',
    ];
    if ($event_id > 0) {
        $api_args['event_id'] = $event_id;
    }
    if ($selected_event_code !== '') {
        $api_args['event_code'] = $selected_event_code;
    }

    $payment_details_args = [
        'action' => 'registrant-payment-details',
    ];
    if ($event_id > 0) {
        $payment_details_args['event_id'] = $event_id;
    }

    $profile_args = [
        'action' => 'registrant-profile',
    ];
    if ($event_id > 0) {
        $profile_args['event_id'] = $event_id;
    }

    return [
        'event_options'            => $event_options,
        'selected_event_code'      => $selected_event_code,
        'selected_event_id'        => $event_id,
        'selected_event'           => $selected_event,
        'registrants_api_url'      => add_query_arg($api_args, rm_page_url()),
        'payment_details_api_url'  => add_query_arg($payment_details_args, rm_page_url()),
        'profile_api_url'          => add_query_arg($profile_args, rm_page_url()),
        'registrants'           => [],
        'registrant_rows'       => [],
        'registrants_summary'   => [
            'total'         => 0,
            'paid_count'    => 0,
            'pending_count' => 0,
            'total_revenue' => 0.0,
        ],
        'registrants_error'     => $error_message,
        'event_not_found'       => false,
        'hitpay_orphans'        => 0,
    ];
}

/**
 * @return array<string, mixed>
 */
function rm_build_event_registrants_data(): array
{
    $event_id = rm_get_event_id();

    if ($event_id < 1) {
        return [
            'ok'                  => false,
            'error'               => 'Event id is required.',
            'registrant_rows'     => [],
            'registrants_summary' => [
                'total'         => 0,
                'paid_count'    => 0,
                'pending_count' => 0,
                'total_revenue' => 0.0,
            ],
        ];
    }

    $db_fetch = rm_fetch_registrants_from_db($event_id);
    $rows = [];

    if ($db_fetch['error'] === '') {
        $payment_request_ids = [];
        foreach ($db_fetch['registrants'] as $registrant) {
            if (!is_array($registrant)) {
                continue;
            }

            $payment_request_id = trim((string) ($registrant['payment'] ?? ''));
            if ($payment_request_id !== '') {
                $payment_request_ids[] = $payment_request_id;
            }
        }

        $charges_by_payment_request = rm_hitpay_map_charges_by_payment_request(
            $event_id,
            $payment_request_ids
        );

        foreach ($db_fetch['registrants'] as $registrant) {
            if (!is_array($registrant)) {
                continue;
            }

            $payment_request_id = trim((string) ($registrant['payment'] ?? ''));
            if ($payment_request_id !== '' && isset($charges_by_payment_request[$payment_request_id])) {
                $registrant['_charge_hitpay'] = $charges_by_payment_request[$payment_request_id];
            }

            $rows[] = rm_present_registrant_row($registrant, false);
        }
    }

    return [
        'ok'                  => $db_fetch['error'] === '',
        'error'               => $db_fetch['error'],
        'registrant_rows'     => $rows,
        'registrants_summary' => rm_registrants_summary($db_fetch['registrants']),
    ];
}

/**
 * @return array<string, mixed>
 */
function rm_build_registrant_payment_details(): array
{
    $event_id = rm_get_event_id();
    $payment_request_id = rm_get_registrant_payment_request_id();

    if ($payment_request_id === '') {
        return [
            'ok'      => false,
            'error'   => 'Payment reference is required.',
            'details' => null,
        ];
    }

    $lookup = rm_payment_get_request($payment_request_id, $event_id);
    if (!$lookup['ok'] || !is_array($lookup['data'])) {
        return [
            'ok'      => false,
            'error'   => $lookup['error'] !== '' ? $lookup['error'] : 'Payment details not found.',
            'details' => null,
        ];
    }

    $payment_request = $lookup['data'];
    $charge_lookup = rm_hitpay_find_charge_for_payment_request(
        $payment_request_id,
        $event_id,
        $payment_request
    );

    $charge = null;
    $charge_error = '';
    if ($charge_lookup['ok'] && is_array($charge_lookup['data'])) {
        $charge = rm_present_registrant_charge_details($charge_lookup['data']);
    } else {
        $charge_error = $charge_lookup['error'] !== ''
            ? $charge_lookup['error']
            : 'Charge not found for this payment request.';
    }

    return [
        'ok'      => true,
        'error'   => '',
        'details' => [
            'charge'       => $charge,
            'charge_error' => $charge_error,
            'payment'      => rm_present_registrant_payment_details($payment_request),
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function rm_build_registrant_profile(): array
{
    $event_id = rm_get_event_id();
    $registrant_id = rm_get_registrant_id();

    if ($event_id < 1 || $registrant_id < 1) {
        return [
            'ok'      => false,
            'error'   => 'Registrant id and event id are required.',
            'profile' => null,
        ];
    }

    $fetch = rm_fetch_registrant_by_id($registrant_id, $event_id);
    if ($fetch['error'] !== '' || !is_array($fetch['registrant'])) {
        return [
            'ok'      => false,
            'error'   => $fetch['error'] !== '' ? $fetch['error'] : 'Registrant could not be found.',
            'profile' => null,
        ];
    }

    return [
        'ok'      => true,
        'error'   => '',
        'profile' => rm_present_registrant_profile($fetch['registrant']),
    ];
}
