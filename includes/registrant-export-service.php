<?php

/**
 * Google Apps Script / spreadsheet export API for v2 event registrants.
 */

/**
 * @param mixed $snapshot
 * @return array<string, mixed>
 */
function rm_export_decode_pricing_snapshot($snapshot): array
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
 * @param mixed $custom
 * @return array<string, mixed>
 */
function rm_export_decode_custom_responses($custom): array
{
    if (is_array($custom)) {
        return $custom;
    }

    if (!is_string($custom) || $custom === '') {
        return [];
    }

    $decoded = json_decode($custom, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * Spreadsheet-safe flat row with stable key order. Custom response keys are appended.
 *
 * @param array<string, mixed> $registrant Normalized v2 registrant row
 * @param array<string, mixed> $header     Header fields used during normalize (snapshot etc.)
 * @return array<string, mixed>
 */
function rm_present_export_registrant_row(array $registrant, array $header = []): array
{
    $christian_name = trim((string) ($registrant['christianName'] ?? ''));
    $given_name = trim((string) ($registrant['givenName'] ?? ''));
    $first_name = $christian_name !== '' ? $christian_name : $given_name;
    $family_name = trim((string) ($registrant['familyName'] ?? ''));
    $full_name = trim($first_name . ' ' . $family_name);
    if ($full_name === '') {
        $full_name = 'N/A';
    }

    $promotion_id = isset($registrant['_event_promotion_id']) ? (int) $registrant['_event_promotion_id'] : 0;
    $package_label = trim((string) ($registrant['_package_label'] ?? ''));
    if ($package_label === '') {
        $package_label = $promotion_id > 0 ? 'Package #' . $promotion_id : 'Individual';
    }

    $snapshot = rm_export_decode_pricing_snapshot($header['pricing_snapshot'] ?? ($registrant['_pricing_snapshot'] ?? null));
    $package_slug = null;
    if ($promotion_id > 0) {
        $slug = trim((string) ($snapshot['package_slug'] ?? ''));
        $package_slug = $slug !== '' ? $slug : null;
    }

    $payment_status = trim((string) ($registrant['_payment_status'] ?? ''));
    $email_sent = ((string) ($registrant['isEmailConfirmationSent'] ?? '0')) === '1';

    $row = [
        'registrant_id'       => isset($registrant['id']) ? (int) $registrant['id'] : 0,
        'registration_id'     => isset($registrant['_registration_id']) ? (int) $registrant['_registration_id'] : 0,
        'role'                => (string) ($registrant['_role'] ?? ''),
        'member_index'        => isset($registrant['_member_index']) ? (int) $registrant['_member_index'] : 0,
        'full_name'           => $full_name,
        'title'               => trim((string) ($registrant['title'] ?? '')),
        'christian_name'      => $christian_name,
        'given_name'          => $given_name,
        'family_name'         => $family_name,
        'certificate_name'    => trim((string) ($registrant['certificateName'] ?? '')),
        'email'               => trim((string) ($registrant['email'] ?? '')),
        'contact'             => trim((string) ($registrant['contact'] ?? '')),
        'nric'                => trim((string) ($registrant['nric'] ?? '')),
        'address1'            => trim((string) ($registrant['address1'] ?? '')),
        'address2'            => trim((string) ($registrant['address2'] ?? '')),
        'postcode'            => trim((string) ($registrant['postcode'] ?? '')),
        'church_name'         => trim((string) ($registrant['churchName'] ?? '')),
        'order_number'        => trim((string) ($registrant['orderNumber'] ?? '')),
        'confirmation_number' => trim((string) ($registrant['confirmationNumber'] ?? '')),
        'package_label'       => $package_label,
        'package_slug'        => $package_slug,
        'event_promotion_id'  => $promotion_id > 0 ? $promotion_id : null,
        'payment_status'      => $payment_status,
        'payment_option'      => trim((string) ($registrant['paymentOption'] ?? 'N/A')),
        'payment_request_id'  => isset($registrant['payment']) && $registrant['payment'] !== null && $registrant['payment'] !== ''
            ? trim((string) $registrant['payment'])
            : null,
        'amount'              => isset($registrant['amount']) ? (float) $registrant['amount'] : 0.0,
        'total_amount'        => isset($registrant['_header_total']) ? (float) $registrant['_header_total'] : 0.0,
        'registered_at'       => trim((string) ($registrant['datestamp'] ?? '')),
        'email_sent'          => $email_sent,
        'status'              => trim((string) ($registrant['_status'] ?? '')),
    ];

    $custom = rm_export_decode_custom_responses($registrant['note'] ?? null);
    foreach ($custom as $key => $value) {
        $key = trim((string) $key);
        if ($key === '' || array_key_exists($key, $row)) {
            continue;
        }
        if (is_scalar($value) || $value === null) {
            $row[$key] = $value === null ? '' : (string) $value;
        } else {
            $row[$key] = wp_json_encode($value);
        }
    }

    return $row;
}

/**
 * @param list<array<string, mixed>> $export_rows
 * @return list<array<string, mixed>>
 */
function rm_group_export_rows_by_package(array $export_rows): array
{
    $buckets = [];

    foreach ($export_rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $promotion_id = isset($row['event_promotion_id']) ? (int) $row['event_promotion_id'] : 0;
        $key = $promotion_id > 0 ? (string) $promotion_id : 'individual';
        $label = trim((string) ($row['package_label'] ?? ''));
        if ($label === '') {
            $label = $promotion_id > 0 ? 'Package #' . $promotion_id : 'Individual';
        }
        $slug = $row['package_slug'] ?? null;
        if (is_string($slug)) {
            $slug = trim($slug);
            $slug = $slug !== '' ? $slug : null;
        } else {
            $slug = null;
        }

        if (!isset($buckets[$key])) {
            $buckets[$key] = [
                'key'                 => $key,
                'event_promotion_id'  => $promotion_id > 0 ? $promotion_id : null,
                'slug'                => $slug,
                'label'               => $label,
                'people_count'        => 0,
                'registration_ids'    => [],
                'registrants'         => [],
            ];
        }

        $buckets[$key]['people_count']++;
        $buckets[$key]['registrants'][] = $row;

        $registration_id = isset($row['registration_id']) ? (int) $row['registration_id'] : 0;
        if ($registration_id > 0) {
            $buckets[$key]['registration_ids'][$registration_id] = true;
        }
    }

    $out = [];
    foreach ($buckets as $bucket) {
        $out[] = [
            'key'                 => $bucket['key'],
            'event_promotion_id'  => $bucket['event_promotion_id'],
            'slug'                => $bucket['slug'],
            'label'               => $bucket['label'],
            'people_count'        => $bucket['people_count'],
            'registration_count'  => count($bucket['registration_ids']),
            'registrants'         => $bucket['registrants'],
        ];
    }

    usort($out, static function (array $a, array $b): int {
        if ($a['key'] === 'individual') {
            return -1;
        }
        if ($b['key'] === 'individual') {
            return 1;
        }

        return strcmp((string) $a['label'], (string) $b['label']);
    });

    return $out;
}

/**
 * @param list<int> $ids
 */
function rm_mark_registrants_reported(array $ids): void
{
    global $wpdb;

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function (int $id): bool {
        return $id > 0;
    })));

    if ($ids === []) {
        return;
    }

    if (!rm_event_registrant_reported_schema_ready()) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $sql = "UPDATE `event_registrant` SET `reported` = 1 WHERE `reported` = 0 AND `id` IN ({$placeholders})";
    $wpdb->query($wpdb->prepare($sql, $ids));
}

/**
 * @return array{registrants: array<int, array<string, mixed>>, headers_by_id: array<int, array<string, mixed>>, error: string}
 */
function rm_fetch_v2_registrants_for_export(int $event_id, string $mode, ?array $event = null): array
{
    global $wpdb;

    if ($event_id < 1) {
        return [
            'registrants'   => [],
            'headers_by_id' => [],
            'error'         => 'Event id is required.',
        ];
    }

    if (!rm_event_registration_tables_exist()) {
        return [
            'registrants'   => [],
            'headers_by_id' => [],
            'error'         => 'Event registration tables are not installed.',
        ];
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

    $sql = 'SELECT r.*, h.confirmation_number, h.payment_status, h.payment_request_id,
                   h.payment_option, h.total_amount AS header_total, h.member_count,
                   h.is_email_confirmation_sent, h.event_promotion_id, h.pricing_snapshot
            FROM `event_registrant` r
            INNER JOIN `event_registration` h ON h.id = r.registration_id
            WHERE r.event_id = %d
              AND r.status <> %s';
    $params = [$event_id, 'cancelled'];

    if ($mode === 'unreported') {
        if (!rm_event_registrant_reported_schema_ready()) {
            return [
                'registrants'   => [],
                'headers_by_id' => [],
                'error'         => 'Reported column is not installed. Reload the module to run migrations.',
            ];
        }
        $sql .= ' AND r.reported = %d';
        $params[] = 0;
    }

    $sql .= ' ORDER BY r.created_at ASC, r.id ASC';

    $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    if (!is_array($rows)) {
        return [
            'registrants'   => [],
            'headers_by_id' => [],
            'error'         => 'Failed to load registrants.',
        ];
    }

    $normalized = [];
    $headers_by_id = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $header = [
            'confirmation_number'        => $row['confirmation_number'] ?? '',
            'payment_status'             => $row['payment_status'] ?? '',
            'payment_request_id'         => $row['payment_request_id'] ?? null,
            'payment_option'             => $row['payment_option'] ?? 'N/A',
            'total_amount'               => $row['header_total'] ?? 0,
            'member_count'               => $row['member_count'] ?? 1,
            'is_email_confirmation_sent' => $row['is_email_confirmation_sent'] ?? 0,
            'event_promotion_id'         => $row['event_promotion_id'] ?? null,
            'pricing_snapshot'           => $row['pricing_snapshot'] ?? null,
        ];

        unset(
            $row['confirmation_number'],
            $row['payment_status'],
            $row['payment_request_id'],
            $row['payment_option'],
            $row['header_total'],
            $row['member_count'],
            $row['is_email_confirmation_sent'],
            $row['event_promotion_id'],
            $row['pricing_snapshot']
        );

        $registrant = rm_normalize_v2_registrant_row($row, $header);
        $registrant['_status'] = (string) ($row['status'] ?? '');
        $registrant['_pricing_snapshot'] = $header['pricing_snapshot'];
        $registrant['_guest_label'] = $guest_label_singular;
        $registrant['_guest_label_singular'] = $guest_label_singular;
        $registrant['_guest_label_plural'] = $guest_label_plural;

        $registrant_id = (int) ($registrant['id'] ?? 0);
        if ($registrant_id > 0) {
            $headers_by_id[$registrant_id] = $header;
        }

        $normalized[] = $registrant;
    }

    return [
        'registrants'   => $normalized,
        'headers_by_id' => $headers_by_id,
        'error'         => '',
    ];
}

/**
 * @return array<string, mixed>
 */
function rm_build_event_registrants_export(): array
{
    $event_code = rm_get_event_code();
    $mode = rm_get_export_mode();
    $package_filter = rm_get_package_filter();
    $addon_filter = rm_get_export_addon_filter();

    $empty = static function (string $error) use ($mode, $package_filter, $addon_filter): array {
        return [
            'ok'              => false,
            'error'           => $error,
            'event'           => null,
            'mode'            => $mode,
            'package_filter'  => $package_filter,
            'addon_filter'    => $addon_filter,
            'registrant_rows' => [],
            'packages'        => [],
            'summary'         => [
                'total_people'  => 0,
                'paid_count'    => 0,
                'pending_count' => 0,
                'total_revenue' => 0.0,
            ],
        ];
    };

    if ($event_code === '') {
        return $empty('Event code is required.');
    }

    $event_fetch = rm_fetch_event($event_code);
    $event = is_array($event_fetch['event'] ?? null) ? $event_fetch['event'] : null;
    if ($event === null) {
        $error = trim((string) ($event_fetch['error'] ?? ''));
        return $empty($error !== '' ? $error : 'Event could not be found.');
    }

    if (!rm_event_uses_v2_registration($event)) {
        return $empty('This event does not use v2 event_registrant tables.');
    }

    if (!rm_event_registration_tables_exist()) {
        return $empty('Event registration tables are not installed.');
    }

    $event_id = isset($event['id']) ? absint($event['id']) : 0;
    if ($event_id < 1) {
        return $empty('Event id is missing.');
    }

    $fetch = rm_fetch_v2_registrants_for_export($event_id, $mode, $event);
    if ($fetch['error'] !== '') {
        return $empty($fetch['error']);
    }

    $registrants = $fetch['registrants'];
    $registrants = rm_filter_registrants_by_addon_filter($registrants, $addon_filter);

    $registrants = rm_filter_registrants_by_package($registrants, $package_filter);

    $headers_by_id = $fetch['headers_by_id'];
    $export_rows = [];
    $mark_ids = [];

    foreach ($registrants as $registrant) {
        if (!is_array($registrant)) {
            continue;
        }

        $registrant_id = isset($registrant['id']) ? (int) $registrant['id'] : 0;
        $header = $registrant_id > 0 && isset($headers_by_id[$registrant_id])
            ? $headers_by_id[$registrant_id]
            : [];

        $export_rows[] = rm_present_export_registrant_row($registrant, $header);
        if ($registrant_id > 0) {
            $mark_ids[] = $registrant_id;
        }
    }

    $summary_source = rm_registrants_summary($registrants);
    $packages = rm_group_export_rows_by_package($export_rows);

    if ($mode === 'unreported' && $mark_ids !== []) {
        rm_mark_registrants_reported($mark_ids);
    }

    $title = trim((string) ($event['title'] ?? ($event['name'] ?? '')));
    $program_code = trim((string) ($event['programCode'] ?? $event_code));

    return [
        'ok'              => true,
        'error'           => '',
        'event'           => [
            'event_id'   => $event_id,
            'event_code' => $program_code !== '' ? $program_code : $event_code,
            'title'      => $title,
        ],
        'mode'            => $mode,
        'package_filter'  => $package_filter,
        'addon_filter'    => $addon_filter,
        'registrant_rows' => $export_rows,
        'packages'        => $packages,
        'summary'         => [
            'total_people'  => (int) ($summary_source['total'] ?? 0),
            'paid_count'    => (int) ($summary_source['paid_count'] ?? 0),
            'pending_count' => (int) ($summary_source['pending_count'] ?? 0),
            'total_revenue' => (float) ($summary_source['total_revenue'] ?? 0),
        ],
    ];
}
