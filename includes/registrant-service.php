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
    $is_paid = $payment_raw !== '' && $payment_raw !== null;
    $payment_status = $is_paid ? 'Paid' : 'Pending';

    $raw_date = trim((string) ($registrant['datestamp'] ?? ''));
    $date_display = 'N/A';
    if ($raw_date !== '') {
        $ts = strtotime($raw_date);
        $date_display = $ts
            ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $ts)
            : $raw_date;
    }

    $amount_raw = $registrant['amount'] ?? null;
    $amount_display = '—';
    if ($amount_raw !== null && $amount_raw !== '') {
        $amount_display = '$' . number_format_i18n((float) $amount_raw, 2);
    }

    $email_sent = ($registrant['isEmailConfirmationSent'] ?? '') === '1';

    return [
        'full_name'        => $full_name,
        'email'            => $email,
        'phone'            => $phone,
        'order_number'     => $order_number,
        'payment_status'   => $payment_status,
        'is_paid'          => $is_paid,
        'amount_display'   => $amount_display,
        'date_display'     => $date_display,
        'email_sent'       => $email_sent,
        'email_sent_label' => $email_sent ? 'Yes' : 'No',
        'is_pending'       => $is_pending,
    ];
}

/**
 * @param array<int, array<string, mixed>> $registrants
 * @param array<int, array<string, mixed>> $pending_registrants
 * @return array{total: int, paid_count: int, pending_count: int, total_revenue: float}
 */
function rm_registrants_summary(array $registrants, array $pending_registrants): array
{
    $paid_count = 0;
    $total_revenue = 0.0;

    foreach ($registrants as $row) {
        if (!is_array($row)) {
            continue;
        }

        $payment = $row['payment'] ?? null;
        if ($payment !== '' && $payment !== null) {
            $paid_count++;
        }

        $total_revenue += (float) ($row['amount'] ?? 0);
    }

    return [
        'total'         => count($registrants),
        'paid_count'    => $paid_count,
        'pending_count' => count($pending_registrants),
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
function rm_build_registrants_context(array $events_by_year, string $requested_event_code): array
{
    $event_options = rm_flatten_events_list($events_by_year);
    $selected_event_code = rm_resolve_event_code($requested_event_code, $event_options);
    $selected_event = rm_find_event_by_code($event_options, $selected_event_code);

    $error_message = '';
    $registrants = [];
    $pending_registrants = [];
    $rows = [];

    if ($selected_event_code === '') {
        return [
            'event_options'         => $event_options,
            'selected_event_code'   => '',
            'selected_event'        => null,
            'registrants'           => [],
            'pending_registrants'   => [],
            'registrant_rows'       => [],
            'registrants_summary'   => [
                'total'         => 0,
                'paid_count'    => 0,
                'pending_count' => 0,
                'total_revenue' => 0.0,
            ],
            'registrants_error'     => '',
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
            'pending_registrants'   => [],
            'registrant_rows'       => [],
            'registrants_summary'   => [
                'total'         => 0,
                'paid_count'    => 0,
                'pending_count' => 0,
                'total_revenue' => 0.0,
            ],
            'registrants_error'     => '',
            'event_not_found'       => true,
        ];
    }

    if ($selected_event === null) {
        return [
            'event_options'         => $event_options,
            'selected_event_code'   => $selected_event_code,
            'selected_event'        => null,
            'registrants'           => [],
            'pending_registrants'   => [],
            'registrant_rows'       => [],
            'registrants_summary'   => [
                'total'         => 0,
                'paid_count'    => 0,
                'pending_count' => 0,
                'total_revenue' => 0.0,
            ],
            'registrants_error'     => $error_message,
            'event_not_found'       => false,
        ];
    }

    $registrants_fetch = rm_fetch_registrants($selected_event_code);
    if ($registrants_fetch['error'] !== '') {
        $error_message = $registrants_fetch['error'];
    } else {
        $registrants = $registrants_fetch['registrants'];
    }

    $pending_fetch = rm_fetch_pending_registrants($selected_event_code);
    if ($pending_fetch['error'] !== '' && $error_message === '') {
        $error_message = $pending_fetch['error'];
    } else {
        $pending_registrants = $pending_fetch['registrants'];
    }

    foreach ($registrants as $registrant) {
        if (is_array($registrant)) {
            $rows[] = rm_present_registrant_row($registrant, false);
        }
    }

    foreach ($pending_registrants as $registrant) {
        if (is_array($registrant)) {
            $rows[] = rm_present_registrant_row($registrant, true);
        }
    }

    return [
        'event_options'         => $event_options,
        'selected_event_code'   => $selected_event_code,
        'selected_event'        => $selected_event,
        'registrants'           => $registrants,
        'pending_registrants'   => $pending_registrants,
        'registrant_rows'       => $rows,
        'registrants_summary'   => rm_registrants_summary($registrants, $pending_registrants),
        'registrants_error'     => $error_message,
        'event_not_found'       => false,
    ];
}
