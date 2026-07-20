<?php

/**
 * @param array<string, mixed> $row
 */
function rm_legacy_registrant_is_paid(array $row): bool
{
    $payment = trim((string) ($row['payment'] ?? ''));

    if ($payment !== '' && $payment !== 'Group Registration') {
        return true;
    }

    return ($row['groupBookings'] ?? '0') === '1';
}

function rm_legacy_group_confirmation_base(string $confirmation_number): string
{
    $confirmation_number = trim($confirmation_number);
    if ($confirmation_number === '') {
        return '';
    }

    if (preg_match('/^(.+)-(\d+)$/', $confirmation_number, $matches)) {
        return (string) $matches[1];
    }

    return $confirmation_number;
}

/**
 * @return list<array<string, mixed>>
 */
function rm_fetch_legacy_group_members(int $event_id, string $confirmation_base): array
{
    global $wpdb;

    if ($event_id < 1 || $confirmation_base === '') {
        return [];
    }

    $like = $wpdb->esc_like($confirmation_base) . '-%';
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT * FROM `bss_registrant`
             WHERE `events` = %d
               AND (`confirmationNumber` = %s OR `confirmationNumber` LIKE %s)
             ORDER BY `confirmationNumber` ASC',
            $event_id,
            $confirmation_base,
            $like
        ),
        ARRAY_A
    );

    if (!is_array($rows)) {
        return [];
    }

    $members = [];
    foreach ($rows as $row) {
        if (is_array($row) && rm_legacy_registrant_is_paid($row)) {
            $members[] = $row;
        }
    }

    usort(
        $members,
        static function (array $a, array $b): int {
            $suffix_a = rm_legacy_confirmation_suffix((string) ($a['confirmationNumber'] ?? ''));
            $suffix_b = rm_legacy_confirmation_suffix((string) ($b['confirmationNumber'] ?? ''));

            return $suffix_a <=> $suffix_b;
        }
    );

    return $members;
}

function rm_legacy_confirmation_suffix(string $confirmation_number): int
{
    if (preg_match('/-(\d+)$/', $confirmation_number, $matches)) {
        return (int) $matches[1];
    }

    return 0;
}

/**
 * @return array{registrants: array<int, array<string, mixed>>, error: string}
 */
function rm_fetch_legacy_paid_registrants(int $event_id): array
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
            'SELECT * FROM `bss_registrant`
             WHERE `events` = %d
               AND (
                    (`payment` IS NOT NULL AND `payment` != %s AND `payment` != %s)
                    OR `groupBookings` = %s
               )
             ORDER BY `datestamp` ASC',
            $event_id,
            '',
            'Group Registration',
            '1'
        ),
        ARRAY_A
    );

    if (!is_array($rows)) {
        return [
            'registrants' => [],
            'error'       => 'Failed to load legacy registrants.',
        ];
    }

    $paid = [];
    foreach ($rows as $row) {
        if (is_array($row) && rm_legacy_registrant_is_paid($row)) {
            $paid[] = $row;
        }
    }

    return [
        'registrants' => $paid,
        'error'       => '',
    ];
}

/**
 * @return array{registrants: array<int, array<string, mixed>>, error: string}
 */
function rm_fetch_v2_registrants_for_migration(int $event_id): array
{
    global $wpdb;

    if ($event_id < 1) {
        return [
            'registrants' => [],
            'error'       => 'Event id is required.',
        ];
    }

    if (!rm_event_registration_tables_exist()) {
        return [
            'registrants' => [],
            'error'       => 'V2 registration tables are not installed.',
        ];
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT r.*, h.confirmation_number, h.payment_status, h.payment_request_id,
                    h.payment_option, h.total_amount AS header_total, h.member_count,
                    h.is_email_confirmation_sent, h.registration_mode
             FROM `event_registrant` r
             INNER JOIN `event_registration` h ON h.id = r.registration_id
             WHERE r.event_id = %d AND r.role != %s
             ORDER BY r.created_at ASC',
            $event_id,
            'addon'
        ),
        ARRAY_A
    );

    if (!is_array($rows)) {
        return [
            'registrants' => [],
            'error'       => 'Failed to load v2 registrants.',
        ];
    }

    $normalized = [];
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
            'registration_mode'          => $row['registration_mode'] ?? 'individual',
        ];

        unset(
            $row['confirmation_number'],
            $row['payment_status'],
            $row['payment_request_id'],
            $row['payment_option'],
            $row['header_total'],
            $row['member_count'],
            $row['is_email_confirmation_sent'],
            $row['registration_mode']
        );

        $normalized[] = rm_normalize_v2_registrant_row($row, $header);
    }

    return [
        'registrants' => $normalized,
        'error'       => '',
    ];
}

/**
 * Build lookup indexes for migration matching.
 *
 * @param array<int, array<string, mixed>> $v2_rows
 * @return array{
 *   order_numbers: array<string, true>,
 *   confirmation_numbers: array<string, true>,
 *   legacy_ids: array<int, true>
 * }
 */
function rm_build_migration_match_index(array $v2_rows, int $event_id = 0): array
{
    global $wpdb;

    $index = [
        'order_numbers'          => [],
        'confirmation_numbers'   => [],
        'legacy_ids'             => [],
    ];

    foreach ($v2_rows as $row) {
        $order = trim((string) ($row['orderNumber'] ?? ''));
        if ($order !== '') {
            $index['order_numbers'][$order] = true;
        }

        $confirmation = trim((string) ($row['confirmationNumber'] ?? ''));
        if ($confirmation !== '') {
            $index['confirmation_numbers'][$confirmation] = true;
        }
    }

    if (!rm_event_registration_tables_exist()) {
        return $index;
    }

    $legacy_id_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT `id`, `custom_responses` FROM `event_registrant`
             WHERE `event_id` = %d
               AND `custom_responses` IS NOT NULL
               AND `custom_responses` != ''",
            $event_id
        ),
        ARRAY_A
    );

    if (is_array($legacy_id_rows)) {
        foreach ($legacy_id_rows as $legacy_row) {
            if (!is_array($legacy_row)) {
                continue;
            }

            $custom = json_decode((string) ($legacy_row['custom_responses'] ?? ''), true);
            if (!is_array($custom)) {
                continue;
            }

            if (isset($custom['_legacy_registrant_id'])) {
                $index['legacy_ids'][(int) $custom['_legacy_registrant_id']] = true;
            }
        }
    }

    return $index;
}

/**
 * @param array<string, mixed> $legacy
 * @param array<int, array<string, mixed>> $members
 * @param array{
 *   order_numbers: array<string, true>,
 *   confirmation_numbers: array<string, true>,
 *   legacy_ids: array<int, true>
 * } $index
 */
function rm_match_legacy_migration_status(array $legacy, array $members, array $index): string
{
    $confirmation_base = rm_legacy_group_confirmation_base((string) ($legacy['confirmationNumber'] ?? ''));
    if ($confirmation_base !== '' && isset($index['confirmation_numbers'][$confirmation_base])) {
        return 'migrated';
    }

    $full_confirmation = trim((string) ($legacy['confirmationNumber'] ?? ''));
    if ($full_confirmation !== '' && isset($index['confirmation_numbers'][$full_confirmation])) {
        return 'migrated';
    }

    $legacy_matches = 0;
    $order_matches = 0;
    $member_count = count($members);

    foreach ($members as $member) {
        $legacy_id = isset($member['id']) ? (int) $member['id'] : 0;
        if ($legacy_id > 0 && isset($index['legacy_ids'][$legacy_id])) {
            ++$legacy_matches;
        }

        $order = trim((string) ($member['orderNumber'] ?? ''));
        if ($order !== '' && isset($index['order_numbers'][$order])) {
            ++$order_matches;
        }
    }

    if ($member_count > 0 && ($legacy_matches === $member_count || $order_matches === $member_count)) {
        return 'migrated';
    }

    if ($legacy_matches > 0 || $order_matches > 0) {
        return 'conflict';
    }

    return 'ready';
}

/**
 * Collapse legacy paid rows into migration units (individual or group).
 *
 * @param array<int, array<string, mixed>> $legacy_rows
 * @return list<array{type: string, members: list<array<string, mixed>>, primary: array<string, mixed>}>
 */
function rm_build_legacy_migration_units(array $legacy_rows): array
{
    $units = [];
    $seen_groups = [];

    foreach ($legacy_rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        if (($row['groupBookings'] ?? '0') === '1') {
            $base = rm_legacy_group_confirmation_base((string) ($row['confirmationNumber'] ?? ''));
            if ($base === '' || isset($seen_groups[$base])) {
                continue;
            }

            $seen_groups[$base] = true;
            $event_id = isset($row['events']) ? absint($row['events']) : 0;
            $members = rm_fetch_legacy_group_members($event_id, $base);
            if ($members === []) {
                continue;
            }

            $units[] = [
                'type'    => 'group',
                'members' => $members,
                'primary' => $members[0],
            ];
            continue;
        }

        $units[] = [
            'type'    => 'individual',
            'members' => [$row],
            'primary' => $row,
        ];
    }

    return $units;
}

/**
 * @param array{type: string, members: list<array<string, mixed>>, primary: array<string, mixed>} $unit
 * @param array{
 *   order_numbers: array<string, true>,
 *   confirmation_numbers: array<string, true>,
 *   legacy_ids: array<int, true>
 * } $index
 * @return array<string, mixed>
 */
function rm_present_migrate_legacy_unit(array $unit, array $index): array
{
    $primary = $unit['primary'];
    $members = $unit['members'];
    $status = rm_match_legacy_migration_status($primary, $members, $index);

    $christian_name = trim((string) ($primary['christianName'] ?? ''));
    $given_name = trim((string) ($primary['givenName'] ?? ''));
    $first_name = $christian_name !== '' ? $christian_name : $given_name;
    $last_name = trim((string) ($primary['familyName'] ?? ''));
    $full_name = trim($first_name . ' ' . $last_name);
    if ($full_name === '') {
        $full_name = 'N/A';
    }

    $amount = 0.0;
    foreach ($members as $member) {
        $amount += (float) ($member['amount'] ?? 0);
    }

    $legacy_id = isset($primary['id']) ? (int) $primary['id'] : 0;
    $payment = trim((string) ($primary['payment'] ?? ''));
    if ($payment === 'Group Registration') {
        foreach ($members as $member) {
            $member_payment = trim((string) ($member['payment'] ?? ''));
            if ($member_payment !== '' && $member_payment !== 'Group Registration') {
                $payment = $member_payment;
                break;
            }
        }
    }

    return [
        'legacy_id'           => $legacy_id,
        'legacy_ids'          => array_values(array_map(
            static fn (array $member): int => (int) ($member['id'] ?? 0),
            $members
        )),
        'type'                => $unit['type'],
        'member_count'        => count($members),
        'full_name'           => $full_name,
        'email'               => trim((string) ($primary['email'] ?? '')),
        'order_number'        => trim((string) ($primary['orderNumber'] ?? '')),
        'confirmation_number' => rm_legacy_group_confirmation_base((string) ($primary['confirmationNumber'] ?? '')),
        'amount_display'      => 'SGD ' . number_format_i18n($amount, 2),
        'registered_at'       => rm_format_payment_transaction_datetime((string) ($primary['datestamp'] ?? '')),
        'payment_reference'   => $payment !== '' && $payment !== 'Group Registration' ? $payment : '',
        'migration_status'    => $status,
        'can_migrate'         => $status === 'ready',
        'is_group'            => $unit['type'] === 'group',
    ];
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function rm_present_migrate_v2_row(array $row): array
{
    $presented = rm_present_registrant_row($row);
    $legacy_id = 0;

    $note_raw = (string) ($row['note'] ?? '');
    if ($note_raw !== '' && $note_raw !== '{}') {
        $custom = json_decode($note_raw, true);
        if (is_array($custom) && isset($custom['_legacy_registrant_id'])) {
            $legacy_id = (int) $custom['_legacy_registrant_id'];
        }
    }

    $presented['legacy_registrant_id'] = $legacy_id;
    $presented['registration_id'] = (int) ($row['_registration_id'] ?? 0);
    $presented['role'] = (string) ($row['_role'] ?? '');
    $presented['is_migrated'] = $legacy_id > 0;

    return $presented;
}

/**
 * @param array<string, mixed> $legacy
 * @return array{core: array<string, string|null>, custom: array<string, mixed>}
 */
function rm_map_legacy_row_to_v2_member(array $legacy): array
{
    $custom = [];
    $note_raw = trim((string) ($legacy['note'] ?? ''));

    if ($note_raw !== '' && $note_raw !== '{}') {
        $decoded = json_decode($note_raw, true);
        if (is_array($decoded)) {
            $custom = $decoded;
        } else {
            $custom['note'] = $note_raw;
        }
    }

    $legacy_id = isset($legacy['id']) ? (int) $legacy['id'] : 0;
    if ($legacy_id > 0) {
        $custom['_legacy_registrant_id'] = $legacy_id;
    }

    return [
        'core' => [
            'nric'             => (string) ($legacy['nric'] ?? ''),
            'title'            => (string) ($legacy['title'] ?? ''),
            'christian_name'   => (string) ($legacy['christianName'] ?? ''),
            'given_name'       => (string) ($legacy['givenName'] ?? ''),
            'family_name'      => (string) ($legacy['familyName'] ?? ''),
            'certificate_name' => (string) ($legacy['certificateName'] ?? ''),
            'email'            => (string) ($legacy['email'] ?? ''),
            'contact'          => (string) ($legacy['contact'] ?? ''),
            'address1'         => (string) ($legacy['address1'] ?? ''),
            'address2'         => (string) ($legacy['address2'] ?? ''),
            'postcode'         => (string) ($legacy['postcode'] ?? ''),
            'church_name'      => (string) ($legacy['churchName'] ?? ''),
        ],
        'custom' => $custom,
    ];
}

/**
 * @param list<array<string, mixed>> $members
 */
function rm_migration_unit_already_exists(int $event_id, array $members): string
{
    global $wpdb;

    if ($members === []) {
        return 'No registrants to migrate.';
    }

    $confirmation_base = rm_legacy_group_confirmation_base((string) ($members[0]['confirmationNumber'] ?? ''));

    if ($confirmation_base !== '') {
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT `id` FROM `event_registration`
                 WHERE `event_id` = %d AND `confirmation_number` = %s LIMIT 1',
                $event_id,
                $confirmation_base
            )
        );

        if ($existing) {
            return 'This registration has already been migrated (matching confirmation number).';
        }
    }

    foreach ($members as $member) {
        $legacy_id = isset($member['id']) ? (int) $member['id'] : 0;
        if ($legacy_id < 1) {
            continue;
        }

        $existing_by_legacy = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT `id` FROM `event_registrant`
                 WHERE `event_id` = %d
                   AND `custom_responses` LIKE %s
                 LIMIT 1",
                $event_id,
                '%"_legacy_registrant_id":' . $legacy_id . '%'
            )
        );

        if ($existing_by_legacy) {
            return 'This registrant has already been migrated.';
        }

        $order = trim((string) ($member['orderNumber'] ?? ''));
        if ($order === '') {
            continue;
        }

        $existing_order = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT `id` FROM `event_registrant` WHERE `order_number` = %s LIMIT 1',
                $order
            )
        );

        if ($existing_order) {
            return 'Order number ' . $order . ' already exists in v2 registrants.';
        }
    }

    return '';
}

/**
 * @param list<array<string, mixed>> $members
 * @return array{ok: bool, error: string, registration_id: int, order_number: string}
 */
function rm_migrate_legacy_members_to_v2(int $event_id, array $event, array $members): array
{
    global $wpdb;

    if ($members === []) {
        return [
            'ok'              => false,
            'error'           => 'No registrants to migrate.',
            'registration_id' => 0,
            'order_number'    => '',
        ];
    }

    if (!rm_event_registration_tables_exist()) {
        return [
            'ok'              => false,
            'error'           => 'V2 registration tables are not installed.',
            'registration_id' => 0,
            'order_number'    => '',
        ];
    }

    $duplicate_error = rm_migration_unit_already_exists($event_id, $members);
    if ($duplicate_error !== '') {
        return [
            'ok'              => false,
            'error'           => $duplicate_error,
            'registration_id' => 0,
            'order_number'    => '',
        ];
    }

    $is_group = count($members) > 1 || (($members[0]['groupBookings'] ?? '0') === '1');
    $primary = $members[0];

    $payment_request_id = '';
    $payment_option = 'N/A';
    foreach ($members as $member) {
        $payment = trim((string) ($member['payment'] ?? ''));
        if ($payment !== '' && $payment !== 'Group Registration') {
            $payment_request_id = $payment;
            $payment_option = trim((string) ($member['paymentOption'] ?? 'N/A'));
            if ($payment_option === '') {
                $payment_option = 'N/A';
            }
            break;
        }
    }

    $total_amount = 0.0;
    foreach ($members as $member) {
        $total_amount += (float) ($member['amount'] ?? 0);
    }

    $confirmation_number = rm_legacy_group_confirmation_base((string) ($primary['confirmationNumber'] ?? ''));
    if ($confirmation_number === '') {
        $confirmation_number = rm_generate_confirmation_number();
    }

    $primary_email = trim((string) ($primary['email'] ?? ''));
    $primary_order = trim((string) ($primary['orderNumber'] ?? ''));
    $paid_at = trim((string) ($primary['datestamp'] ?? ''));
    if ($paid_at === '') {
        $paid_at = current_time('mysql');
    }

    $email_sent = ($primary['isEmailConfirmationSent'] ?? '0') === '1' ? 1 : 0;

    $config = rm_effective_registration_config($event);
    $form_schema = rm_parse_form_schema($event);
    $schema_snapshot = [
        'registrant' => $form_schema['fields'] ?? [],
    ];

    $header = [
        'event_id'                   => $event_id,
        'registration_mode'          => $is_group ? RM_REGISTRATION_MODE_GROUP_FLAT : RM_REGISTRATION_MODE_INDIVIDUAL,
        'confirmation_number'        => $confirmation_number,
        'member_count'               => count($members),
        'subtotal'                   => $total_amount,
        'discount_total'             => 0.00,
        'total_amount'               => $total_amount,
        'pricing_snapshot'           => null,
        'form_schema_snapshot'       => wp_json_encode($schema_snapshot),
        'promo_id'                   => null,
        'event_promotion_id'         => null,
        'payment_status'             => 'paid',
        'payment_request_id'         => $payment_request_id !== '' ? $payment_request_id : null,
        'payment_option'             => $payment_option,
        'primary_email'              => $primary_email,
        'primary_order_number'       => $primary_order,
        'is_email_confirmation_sent' => $email_sent,
        'paid_at'                    => $paid_at,
        'created_at'                 => $paid_at,
        'updated_at'                 => current_time('mysql'),
    ];

    $wpdb->query('START TRANSACTION');

    $inserted = $wpdb->insert('event_registration', $header);
    if (!$inserted) {
        $wpdb->query('ROLLBACK');

        return [
            'ok'              => false,
            'error'           => 'Migration could not be saved. Please try again.',
            'registration_id' => 0,
            'order_number'    => '',
        ];
    }

    $registration_id = (int) $wpdb->insert_id;

    foreach ($members as $index => $member) {
        $mapped = rm_map_legacy_row_to_v2_member($member);
        $custom_json = $mapped['custom'] !== []
            ? wp_json_encode($mapped['custom'])
            : null;

        $order_number = trim((string) ($member['orderNumber'] ?? ''));
        $created_at = trim((string) ($member['datestamp'] ?? ''));
        if ($created_at === '') {
            $created_at = $paid_at;
        }

        $line = [
            'registration_id'  => $registration_id,
            'event_id'         => $event_id,
            'member_index'     => $index,
            'role'             => $index === 0 ? 'primary' : 'member',
            'order_number'     => $order_number,
            'nric'             => $mapped['core']['nric'],
            'title'            => $mapped['core']['title'],
            'christian_name'   => $mapped['core']['christian_name'],
            'given_name'       => $mapped['core']['given_name'],
            'family_name'      => $mapped['core']['family_name'],
            'certificate_name' => $mapped['core']['certificate_name'],
            'email'            => $mapped['core']['email'],
            'contact'          => $mapped['core']['contact'],
            'address1'         => $mapped['core']['address1'],
            'address2'         => $mapped['core']['address2'],
            'postcode'         => $mapped['core']['postcode'],
            'church_name'      => $mapped['core']['church_name'],
            'custom_responses' => $custom_json,
            'unit_price'       => (float) ($member['amount'] ?? 0),
            'discount_percent' => 0.0,
            'status'           => 'confirmed',
            'created_at'       => $created_at,
            'updated_at'       => current_time('mysql'),
        ];

        $line_inserted = $wpdb->insert('event_registrant', $line);
        if (!$line_inserted) {
            $wpdb->query('ROLLBACK');

            return [
                'ok'              => false,
                'error'           => 'Migration could not be saved. Please try again.',
                'registration_id' => 0,
                'order_number'    => '',
            ];
        }
    }

    $wpdb->query('COMMIT');

    return [
        'ok'              => true,
        'error'           => '',
        'registration_id' => $registration_id,
        'order_number'    => $primary_order,
    ];
}

/**
 * @return array{ok: bool, error: string, registration_id: int, order_number: string}
 */
function rm_migrate_legacy_registrant_to_v2(int $legacy_id, int $event_id): array
{
    global $wpdb;

    if ($legacy_id < 1 || $event_id < 1) {
        return [
            'ok'              => false,
            'error'           => 'Invalid registrant or event.',
            'registration_id' => 0,
            'order_number'    => '',
        ];
    }

    $legacy_row = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM `bss_registrant` WHERE `id` = %d AND `events` = %d LIMIT 1',
            $legacy_id,
            $event_id
        ),
        ARRAY_A
    );

    if (!is_array($legacy_row) || $legacy_row === []) {
        return [
            'ok'              => false,
            'error'           => 'Legacy registrant could not be found for this event.',
            'registration_id' => 0,
            'order_number'    => '',
        ];
    }

    if (!rm_legacy_registrant_is_paid($legacy_row)) {
        return [
            'ok'              => false,
            'error'           => 'Only paid legacy registrants can be migrated.',
            'registration_id' => 0,
            'order_number'    => '',
        ];
    }

    $event = rm_get_event_by_id($event_id);
    if (!is_array($event) || $event === []) {
        return [
            'ok'              => false,
            'error'           => 'Event could not be found.',
            'registration_id' => 0,
            'order_number'    => '',
        ];
    }

    if (($legacy_row['groupBookings'] ?? '0') === '1') {
        $base = rm_legacy_group_confirmation_base((string) ($legacy_row['confirmationNumber'] ?? ''));
        $members = rm_fetch_legacy_group_members($event_id, $base);
    } else {
        $members = [$legacy_row];
    }

    if ($members === []) {
        return [
            'ok'              => false,
            'error'           => 'No registrants found to migrate.',
            'registration_id' => 0,
            'order_number'    => '',
        ];
    }

    return rm_migrate_legacy_members_to_v2($event_id, $event, $members);
}

/**
 * Earliest event date to include on the migrate-registrant picker (start of previous calendar year).
 */
function rm_migration_event_min_timestamp(?int $now = null): int
{
    $now = $now ?? current_time('timestamp');
    $previous_year = (int) wp_date('Y', $now) - 1;
    $min_ts = strtotime((string) $previous_year . '-01-01 00:00:00');

    if ($min_ts !== false) {
        return $min_ts;
    }

    return (int) strtotime('-1 year', $now);
}

/**
 * @param array<string, mixed> $event
 */
function rm_event_within_migration_date_window(array $event, ?int $now = null): bool
{
    $ref_ts = rm_event_reference_timestamp($event, true);
    if ($ref_ts === null) {
        return false;
    }

    return $ref_ts >= rm_migration_event_min_timestamp($now);
}

/**
 * @param array<string, array<int, array<string, mixed>>> $events_by_year
 * @return list<array<string, mixed>>
 */
function rm_fetch_legacy_events_for_migration(array $events_by_year): array
{
    $flat = rm_flatten_events_list($events_by_year);
    $legacy_events = [];
    $now = current_time('timestamp');

    foreach ($flat as $event) {
        if (!is_array($event)) {
            continue;
        }

        if (rm_is_cpt_event($event)) {
            continue;
        }

        if (!rm_event_within_migration_date_window($event, $now)) {
            continue;
        }

        $event_id = isset($event['id']) ? absint($event['id']) : 0;
        if ($event_id < 1) {
            continue;
        }

        $legacy_events[] = [
            'id'           => $event_id,
            'title'        => (string) ($event['title'] ?? ''),
            'program_code' => (string) ($event['programCode'] ?? ''),
            'start_date'   => (string) ($event['startDate'] ?? ''),
            'v2_enabled'   => rm_event_uses_v2_registration($event),
        ];
    }

    usort(
        $legacy_events,
        static function (array $a, array $b): int {
            return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        }
    );

    return $legacy_events;
}

/**
 * @return array<string, mixed>
 */
function rm_build_migrate_registrant_data(int $event_id = 0): array
{
    if ($event_id < 1) {
        $event_id = rm_get_event_id();
    }

    if ($event_id < 1) {
        return [
            'ok'    => false,
            'error' => 'Event id is required.',
        ];
    }

    $event = rm_get_event_by_id($event_id);
    if (!is_array($event) || $event === []) {
        return [
            'ok'    => false,
            'error' => 'Event could not be found.',
        ];
    }

    $legacy_fetch = rm_fetch_legacy_paid_registrants($event_id);
    if ($legacy_fetch['error'] !== '') {
        return [
            'ok'    => false,
            'error' => $legacy_fetch['error'],
        ];
    }

    $v2_fetch = rm_fetch_v2_registrants_for_migration($event_id);
    if ($v2_fetch['error'] !== '') {
        return [
            'ok'    => false,
            'error' => $v2_fetch['error'],
        ];
    }

    $index = rm_build_migration_match_index($v2_fetch['registrants'], $event_id);
    $units = rm_build_legacy_migration_units($legacy_fetch['registrants']);

    $legacy_rows = [];
    $migrated_count = 0;
    $ready_count = 0;
    $conflict_count = 0;

    foreach ($units as $unit) {
        $presented = rm_present_migrate_legacy_unit($unit, $index);
        $legacy_rows[] = $presented;

        if ($presented['migration_status'] === 'migrated') {
            ++$migrated_count;
        } elseif ($presented['migration_status'] === 'conflict') {
            ++$conflict_count;
        } else {
            ++$ready_count;
        }
    }

    $v2_rows = [];
    foreach ($v2_fetch['registrants'] as $row) {
        if (is_array($row)) {
            $v2_rows[] = rm_present_migrate_v2_row($row);
        }
    }

    return [
        'ok'          => true,
        'error'       => '',
        'event'       => [
            'id'           => $event_id,
            'title'        => (string) ($event['title'] ?? ''),
            'program_code' => (string) ($event['programCode'] ?? ''),
            'v2_enabled'   => rm_event_uses_v2_registration($event),
        ],
        'legacy_rows' => $legacy_rows,
        'v2_rows'     => $v2_rows,
        'summary'     => [
            'paid_total'   => count($legacy_rows),
            'migrated'   => $migrated_count,
            'ready'      => $ready_count,
            'conflicts'  => $conflict_count,
            'v2_total'   => count($v2_rows),
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function rm_execute_migrate_registrant(): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return [
            'ok'    => false,
            'error' => 'Invalid request method.',
        ];
    }

    if (
        !isset($_POST['rm_migrate_registrant_nonce'])
        || !wp_verify_nonce(
            sanitize_text_field(wp_unslash((string) $_POST['rm_migrate_registrant_nonce'])),
            'rm_migrate_registrant'
        )
    ) {
        return [
            'ok'    => false,
            'error' => 'Your session has expired. Please refresh and try again.',
        ];
    }

    $event_id = isset($_POST['event_id']) ? absint(wp_unslash((string) $_POST['event_id'])) : 0;
    $legacy_id = isset($_POST['legacy_registrant_id'])
        ? absint(wp_unslash((string) $_POST['legacy_registrant_id']))
        : 0;

    if ($event_id < 1 || $legacy_id < 1) {
        return [
            'ok'    => false,
            'error' => 'Event and registrant are required.',
        ];
    }

    $result = rm_migrate_legacy_registrant_to_v2($legacy_id, $event_id);
    if (!$result['ok']) {
        return [
            'ok'    => false,
            'error' => $result['error'],
        ];
    }

    $refresh = rm_build_migrate_registrant_data($event_id);
    if (!$refresh['ok']) {
        return [
            'ok'              => true,
            'error'           => '',
            'message'         => 'Registrant migrated successfully.',
            'registration_id' => $result['registration_id'],
            'order_number'    => $result['order_number'],
        ];
    }

    $refresh['message'] = 'Registrant migrated successfully.';
    $refresh['registration_id'] = $result['registration_id'];
    $refresh['order_number'] = $result['order_number'];

    return $refresh;
}

/**
 * @param array<string, array<int, array<string, mixed>>> $legacy_events_by_year
 * @return array<string, mixed>
 */
function rm_build_migrate_registrant_shell_context(array $legacy_events_by_year): array
{
    $event_id = rm_get_event_id();
    $legacy_events = rm_fetch_legacy_events_for_migration($legacy_events_by_year);
    $page_url = rm_page_url();

    return [
        'migrate_registrant_api_url'    => esc_url_raw(add_query_arg(['action' => 'migrate-registrant-data'], $page_url)),
        'migrate_registrant_execute_url'=> esc_url_raw(add_query_arg(['action' => 'migrate-registrant-execute'], $page_url)),
        'migrate_registrant_nonce'      => wp_create_nonce('rm_migrate_registrant'),
        'migrate_registrant_event_id'   => $event_id,
        'migrate_registrant_events'     => $legacy_events,
    ];
}
