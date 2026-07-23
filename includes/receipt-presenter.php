<?php

/**
 * Resolve the public event landing page URL from event data.
 *
 * @param array<string, mixed> $event
 */
function rm_event_landing_url(array $event): string
{
    $event_id = isset($event['id']) ? absint($event['id']) : 0;
    $settings = rm_event_decode_settings($event);

    if (!empty($settings['customURL'])) {
        $url = rm_normalize_public_event_url(trim((string) $settings['customURL']));
        if ($url !== '') {
            return $url;
        }
    }

    if (!empty($settings['chinese_ministry']['infoPage'])) {
        $url = rm_normalize_public_event_url(trim((string) $settings['chinese_ministry']['infoPage']));
        if ($url !== '') {
            return $url;
        }
    }

    if (function_exists('rm_is_cpt_event') && rm_is_cpt_event($event) && $event_id > 0) {
        $permalink = get_permalink($event_id);
        if (is_string($permalink) && $permalink !== '') {
            return $permalink;
        }
    }

    if ($event_id > 0) {
        return home_url('/?e=' . $event_id);
    }

    return home_url('/');
}

/**
 * @param array<string, mixed> $event
 * @return array<string, mixed>
 */
function rm_event_decode_settings(array $event): array
{
    $raw = $event['settings'] ?? null;
    if (is_array($raw)) {
        return $raw;
    }

    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

function rm_normalize_public_event_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    if (str_starts_with($url, './')) {
        $url = substr($url, 2);
    }

    if (str_starts_with($url, '/')) {
        return home_url($url);
    }

    if (!preg_match('#^https?://#i', $url)) {
        return home_url($url);
    }

    return $url;
}

/**
 * @return array<string, string>
 */
function rm_receipt_payment_meta(string $order_number): array
{
    global $wpdb;

    $order_number = trim($order_number);
    $empty = [
        'paid_at_display'     => '',
        'payment_reference'   => '',
        'payment_status'      => '',
    ];

    if ($order_number === '') {
        return $empty;
    }

    if (function_exists('rm_event_registration_tables_exist') && rm_event_registration_tables_exist()) {
        $header = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT `paid_at`, `payment_request_id`, `confirmation_number`, `payment_status`
                 FROM `event_registration`
                 WHERE `primary_order_number` = %s
                 LIMIT 1',
                $order_number
            ),
            ARRAY_A
        );

        if (!is_array($header) || $header === []) {
            $header = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT h.`paid_at`, h.`payment_request_id`, h.`confirmation_number`, h.`payment_status`
                     FROM `event_registration` h
                     INNER JOIN `event_registrant` r ON r.`registration_id` = h.`id`
                     WHERE r.`order_number` = %s
                     LIMIT 1',
                    $order_number
                ),
                ARRAY_A
            );
        }

        if (is_array($header) && $header !== []) {
            $paid_at = trim((string) ($header['paid_at'] ?? ''));
            $confirmation = trim((string) ($header['confirmation_number'] ?? ''));
            $payment_request_id = trim((string) ($header['payment_request_id'] ?? ''));

            return [
                'paid_at_display'   => $paid_at !== '' ? rm_format_payment_transaction_datetime($paid_at) : '',
                'payment_reference' => $confirmation !== '' ? $confirmation : $payment_request_id,
                'payment_status'    => trim((string) ($header['payment_status'] ?? '')),
            ];
        }
    }

    $legacy = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT `payment`, `paymentOption`, `dateRegistered` FROM `bss_registrant` WHERE `orderNumber` = %s LIMIT 1',
            $order_number
        ),
        ARRAY_A
    );

    if (!is_array($legacy) || $legacy === []) {
        return $empty;
    }

    $registered_at = trim((string) ($legacy['dateRegistered'] ?? ''));

    return [
        'paid_at_display'   => $registered_at !== '' ? rm_format_payment_transaction_datetime($registered_at) : '',
        'payment_reference' => trim((string) ($legacy['payment'] ?? '')),
        'payment_status'    => 'paid',
    ];
}

/**
 * @param array<string, mixed> $person
 * @return list<array{label: string, value: string}>
 */
function rm_receipt_person_rows(array $person): array
{
    $rows = [];
    $map = [
        'full_name'    => 'Name',
        'email'        => 'Email',
        'contact'      => 'Contact',
        'church_name'  => 'Church',
        'order_number' => 'Registration number',
    ];

    foreach ($map as $key => $label) {
        $value = trim((string) ($person[$key] ?? ''));
        if ($value !== '') {
            $rows[] = [
                'label' => $label,
                'value' => $value,
            ];
        }
    }

    return $rows;
}

/**
 * @param array<string, mixed> $event
 * @param array<string, mixed>|null $event_present
 * @param array<string, mixed> $debug Temporary payment-return diagnostics.
 * @return array<string, mixed>|null
 */
function rm_present_registration_receipt(
    string $order_number,
    string $status,
    array $event,
    ?array $event_present = null,
    array $debug = []
): ?array {
    $event_present = is_array($event_present) ? $event_present : [];
    $program_code = trim((string) ($event_present['program_code'] ?? ($event['programCode'] ?? '')));
    $register_another_href = $program_code !== ''
        ? rm_registration_url(['event_code' => $program_code])
        : rm_registration_url();
    $event_landing_href = rm_event_landing_url($event);
    $show_event_landing = $event_landing_href !== '' && $event_landing_href !== home_url('/');

    if ($status === 'payment_failed') {
        return [
            'status'               => 'payment_failed',
            'title'                => 'Payment not completed',
            'message'              => rm_registration_success_message('payment_failed'),
            'register_another_href'=> $register_another_href,
            'event_landing_href'   => $event_landing_href,
            'show_event_landing'   => $show_event_landing,
            'debug'                => $debug,
        ];
    }

    if ($status === 'payment_processing' || ($status === 'pending_payment' && $order_number === '')) {
        return [
            'status'                => $status === 'payment_processing' ? 'payment_processing' : 'pending_payment',
            'title'                 => $status === 'payment_processing'
                ? 'Confirming your payment'
                : 'Registration received',
            'message'               => rm_registration_success_message(
                $status === 'payment_processing' ? 'payment_processing' : 'pending_payment'
            ),
            'confirmation_email'    => '',
            'register_another_href' => $register_another_href,
            'event_landing_href'    => $event_landing_href,
            'show_event_landing'    => $show_event_landing,
            'debug'                 => $debug,
        ];
    }

    $confirmation = $order_number !== '' ? rm_email_load_confirmation_context($order_number) : null;
    $payment_meta = rm_receipt_payment_meta($order_number);

    if ($confirmation === null) {
        if ($order_number === '') {
            return null;
        }

        return [
            'status'                => $status,
            'title'                 => $status === 'confirmed' ? 'Registration confirmed' : 'Registration received',
            'message'               => rm_registration_success_message($status),
            'confirmation_email'    => '',
            'order_number'          => $order_number,
            'confirmation_number'   => '',
            'amount_display'        => '',
            'payment_method'        => '',
            'payment_status_label'  => '',
            'paid_at_display'       => $payment_meta['paid_at_display'],
            'payment_reference'     => $payment_meta['payment_reference'],
            'package_label'         => '',
            'show_package'          => false,
            'event_title'           => (string) ($event_present['title'] ?? 'Event'),
            'event_date_display'    => (string) ($event_present['date_display'] ?? ''),
            'event_time_display'    => (string) ($event_present['time_display'] ?? ''),
            'event_venue'           => (string) ($event_present['venue'] ?? ''),
            'primary'               => null,
            'members'               => [],
            'guests'                => [],
            'show_members'          => false,
            'show_guests'           => false,
            'register_another_href' => $register_another_href,
            'event_landing_href'    => $event_landing_href,
            'show_event_landing'    => $show_event_landing,
            'debug'                 => $debug,
        ];
    }

    $payment_status = trim((string) ($confirmation['payment_status'] ?? $payment_meta['payment_status']));
    $payment_status_label = 'Confirmed';
    if ($payment_status === 'free') {
        $payment_status_label = 'Free registration';
    } elseif ($payment_status === 'paid') {
        $payment_status_label = 'Paid';
    } elseif ($payment_status === 'pending') {
        $payment_status_label = 'Pending payment';
    }

    $title = $status === 'confirmed' || $payment_status === 'paid' || $payment_status === 'free'
        ? 'Registration confirmed'
        : 'Registration received';

    return [
        'status'                => $status,
        'title'                 => $title,
        'message'               => '',
        'confirmation_email'    => trim((string) ($confirmation['to_email'] ?? '')),
        'order_number'          => (string) ($confirmation['order_number'] ?? $order_number),
        'confirmation_number'   => (string) ($confirmation['confirmation_number'] ?? ''),
        'amount_display'        => (string) ($confirmation['amount_display'] ?? ''),
        'payment_method'        => (string) ($confirmation['payment_method'] ?? ''),
        'payment_status_label'  => $payment_status_label,
        'paid_at_display'       => $payment_meta['paid_at_display'],
        'payment_reference'     => $payment_meta['payment_reference'] !== ''
            ? $payment_meta['payment_reference']
            : (string) ($confirmation['confirmation_number'] ?? ''),
        'package_label'         => (string) ($confirmation['package_label'] ?? ''),
        'show_package'          => !empty($confirmation['show_package']),
        'event_title'           => (string) ($event_present['title'] ?? ($confirmation['event']['title'] ?? 'Event')),
        'event_date_display'    => (string) ($event_present['date_display'] ?? ($confirmation['event']['date_display'] ?? '')),
        'event_time_display'    => (string) ($event_present['time_display'] ?? ''),
        'event_venue'           => (string) ($event_present['venue'] ?? ($confirmation['event']['venue'] ?? '')),
        'primary'               => is_array($confirmation['primary'] ?? null) ? $confirmation['primary'] : null,
        'members'               => is_array($confirmation['members'] ?? null) ? $confirmation['members'] : [],
        'guests'                => is_array($confirmation['guests'] ?? null) ? $confirmation['guests'] : [],
        'show_members'          => !empty($confirmation['show_members']),
        'show_guests'           => !empty($confirmation['show_guests']),
        'register_another_href' => $register_another_href,
        'event_landing_href'    => $event_landing_href,
        'show_event_landing'    => $show_event_landing,
        'debug'                 => $debug,
    ];
}
