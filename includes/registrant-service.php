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
    $christian_name = trim((string) ($registrant['christianName'] ?? ''));
    $given_name = trim((string) ($registrant['givenName'] ?? ''));
    $first_name = $christian_name !== '' ? $christian_name : $given_name;
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
    } elseif (!empty($registrant['_is_paid'])) {
        $is_paid = true;
        $payment_status = 'Paid';
    } else {
        $is_paid = $payment_raw !== '' && $payment_raw !== null;
        $payment_status = $is_paid ? 'Paid' : 'Pending';
    }

    $raw_date = trim((string) ($registrant['datestamp'] ?? ''));
    $registered_timestamp = $raw_date !== '' ? (strtotime($raw_date) ?: 0) : 0;
    $date_display = $registered_timestamp > 0
        ? wp_date('M j, Y g:iA', $registered_timestamp)
        : 'N/A';
    $date_display_date = $registered_timestamp > 0
        ? wp_date('M j, Y', $registered_timestamp)
        : 'N/A';
    $date_display_time = $registered_timestamp > 0
        ? wp_date('g:iA', $registered_timestamp)
        : '';

    $amount_raw = $registrant['amount'] ?? null;
    if (!empty($hitpay['amount']) && is_numeric($hitpay['amount'])) {
        $amount_raw = (float) $hitpay['amount'];
    }
    $currency = strtoupper(trim((string) ($hitpay['currency'] ?? '')));
    $fallback_currency = $currency !== '' ? $currency : 'SGD';
    $amount_display = '—';
    if ($amount_raw !== null && $amount_raw !== '') {
        $amount_display = $fallback_currency . ' ' . number_format_i18n((float) $amount_raw, 2);
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
    $charge_fallback_currency = $charge_currency !== '' ? $charge_currency : $fallback_currency;
    $charge_amount_display = $amount_display;
    if ($charge_amount_raw !== null) {
        $charge_amount_display = $charge_fallback_currency . ' ' . number_format_i18n($charge_amount_raw, 2);
    }

    if ($charge_payment_method === '' || $charge_payment_method === 'N/A') {
        $charge_payment_method = $payment_method;
        $charge_payment_method_logo = $payment_method_logo;
    }

    $role = (string) ($registrant['_role'] ?? '');
    $is_guest = $role === 'addon';
    $role_label = '';
    if ($is_guest) {
        $role_label = (string) ($registrant['_guest_label'] ?? 'Guest');
    }

    $guests = [];
    if (!empty($registrant['_guests']) && is_array($registrant['_guests'])) {
        foreach ($registrant['_guests'] as $guest) {
            if (is_array($guest)) {
                $guests[] = $guest;
            }
        }
    }
    $guest_count = count($guests);
    $guest_button_label = (string) ($registrant['_guest_button_label'] ?? '');

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
        'date_display_date'  => $date_display_date,
        'date_display_time'  => $date_display_time,
        'email_sent'         => $email_sent,
        'email_sent_label'   => $email_sent ? 'Yes' : 'No',
        'is_pending'         => $is_pending,
        'is_guest'           => $is_guest,
        'role'               => $role,
        'role_label'         => $role_label,
        'guest_count'        => $guest_count,
        'guest_button_label' => $guest_button_label,
        'guests'             => $guests,
        'package_label'      => trim((string) ($registrant['_package_label'] ?? 'Individual')) !== ''
            ? (string) ($registrant['_package_label'] ?? 'Individual')
            : 'Individual',
        'event_promotion_id' => isset($registrant['_event_promotion_id']) && $registrant['_event_promotion_id']
            ? (int) $registrant['_event_promotion_id']
            : null,
        'registration_id'    => isset($registrant['_registration_id']) ? (int) $registrant['_registration_id'] : 0,
        'member_count'       => isset($registrant['_member_count'])
            ? max(1, (int) $registrant['_member_count'])
            : 1,
        'is_group'           => isset($registrant['_member_count'])
            ? (int) $registrant['_member_count'] > 1
            : false,
    ];
}

/**
 * Present a guest/addon row for the registrants guests modal.
 *
 * @param array<string, mixed> $registrant
 * @return array<string, mixed>
 */
function rm_present_guest_row(array $registrant): array
{
    $christian_name = trim((string) ($registrant['christianName'] ?? ''));
    $given_name = trim((string) ($registrant['givenName'] ?? ''));
    $first_name = $christian_name !== '' ? $christian_name : $given_name;
    $last_name = trim((string) ($registrant['familyName'] ?? ''));
    $full_name = trim($first_name . ' ' . $last_name);
    if ($full_name === '') {
        $full_name = 'N/A';
    }

    $fields = [];
    $core_map = [
        'email'       => 'Email',
        'contact'     => 'Contact',
        'title'       => 'Title',
        'nric'        => 'NRIC',
        'churchName'  => 'Church',
    ];
    foreach ($core_map as $key => $label) {
        $value = trim((string) ($registrant[$key] ?? ''));
        if ($value !== '') {
            $fields[] = [
                'key'   => $key,
                'label' => $label,
                'value' => $value,
            ];
        }
    }

    $note = $registrant['note'] ?? '';
    if (is_string($note) && $note !== '' && $note !== '{}') {
        $decoded = json_decode($note, true);
        if (is_array($decoded)) {
            foreach ($decoded as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }
                $display = $value;
                if (is_bool($display)) {
                    $display = $display ? 'Yes' : 'No';
                } elseif (is_array($display)) {
                    $display = implode(', ', array_map('strval', $display));
                } else {
                    $display = trim((string) $display);
                }
                if ($display === '') {
                    continue;
                }
                $fields[] = [
                    'key'   => $key,
                    'label' => ucwords(str_replace(['_', '-'], ' ', $key)),
                    'value' => $display,
                ];
            }
        }
    }

    $amount_raw = $registrant['amount'] ?? null;
    $amount_display = '—';
    if ($amount_raw !== null && $amount_raw !== '' && is_numeric($amount_raw) && (float) $amount_raw > 0) {
        $amount_display = number_format_i18n((float) $amount_raw, 2);
    }

    return [
        'registrant_id'  => isset($registrant['id']) ? (int) $registrant['id'] : 0,
        'order_number'   => trim((string) ($registrant['orderNumber'] ?? '')),
        'full_name'      => $full_name,
        'role_label'     => (string) ($registrant['_guest_label'] ?? 'Guest'),
        'amount_display' => $amount_display,
        'fields'         => $fields,
    ];
}

/**
 * Map a guest form field key to the normalized registrant property / custom key.
 */
function rm_addon_field_value_key(string $field_key): string
{
    $map = [
        'christian_name'   => 'christianName',
        'given_name'       => 'givenName',
        'family_name'      => 'familyName',
        'certificate_name' => 'certificateName',
        'church_name'      => 'churchName',
        'email'            => 'email',
        'contact'          => 'contact',
        'title'            => 'title',
        'nric'             => 'nric',
        'address1'         => 'address1',
        'address2'         => 'address2',
        'postcode'         => 'postcode',
    ];

    return $map[$field_key] ?? $field_key;
}

/**
 * Read a single field value from a normalized addon registrant row.
 *
 * @param array<string, mixed> $registrant
 */
function rm_addon_field_display_value(array $registrant, string $field_key): string
{
    $name_keys = ['christian_name', 'given_name', 'family_name', 'christianName', 'givenName', 'familyName'];
    if (in_array($field_key, $name_keys, true)) {
        return '';
    }

    $prop = rm_addon_field_value_key($field_key);
    $core_keys = [
        'christianName', 'givenName', 'familyName', 'certificateName', 'churchName',
        'email', 'contact', 'title', 'nric', 'address1', 'address2', 'postcode',
    ];

    if (in_array($prop, $core_keys, true)) {
        return trim((string) ($registrant[$prop] ?? ''));
    }

    $custom = [];
    $note = $registrant['note'] ?? '';
    if (is_string($note) && $note !== '' && $note !== '{}') {
        $decoded = json_decode($note, true);
        if (is_array($decoded)) {
            $custom = $decoded;
        }
    }

    if (!array_key_exists($field_key, $custom) && !array_key_exists($prop, $custom)) {
        return '';
    }

    $value = $custom[$field_key] ?? $custom[$prop] ?? '';
    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }
    if (is_array($value)) {
        return implode(', ', array_map('strval', $value));
    }

    return trim((string) $value);
}

/**
 * Build table columns from the event guest form schema (excludes name fields shown as Name).
 *
 * @param array<string, mixed>|null $event
 * @return list<array{key: string, label: string}>
 */
function rm_event_addon_field_columns(?array $event): array
{
    if (!is_array($event)) {
        return [];
    }

    $config = rm_parse_registration_config($event);
    $stored_fields = isset($config['guests']['form']['fields']) && is_array($config['guests']['form']['fields'])
        ? $config['guests']['form']['fields']
        : [];
    $core_defs = rm_form_core_field_definitions();
    $fields = rm_form_normalize_fields($stored_fields, $core_defs);

    usort($fields, static function (array $a, array $b): int {
        return ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0));
    });

    $skip = ['christian_name', 'given_name', 'family_name'];
    $columns = [];

    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $key = sanitize_key((string) ($field['key'] ?? ''));
        if ($key === '' || in_array($key, $skip, true)) {
            continue;
        }
        $label = trim((string) ($field['label'] ?? ''));
        if ($label === '') {
            $label = ucwords(str_replace(['_', '-'], ' ', $key));
        }
        $columns[] = [
            'key'   => $key,
            'label' => $label,
        ];
    }

    return $columns;
}

/**
 * Present all addon (guest) rows for the event admin addons tab.
 *
 * @param array<int, array<string, mixed>> $registrants
 * @param array<string, mixed>|null $event
 * @return array{
 *   rows: list<array<string, mixed>>,
 *   columns: list<array{key: string, label: string}>,
 *   label_singular: string,
 *   label_plural: string,
 *   total: int
 * }
 */
function rm_present_event_addon_rows(array $registrants, ?array $event = null): array
{
    $label_singular = 'Guest';
    $label_plural = 'Guests';
    if (is_array($event)) {
        $config = rm_parse_registration_config($event);
        $guests = is_array($config['guests'] ?? null) ? $config['guests'] : [];
        $label_singular = trim((string) ($guests['label_singular'] ?? 'Guest'));
        $label_plural = trim((string) ($guests['label_plural'] ?? 'Guests'));
        if ($label_singular === '') {
            $label_singular = 'Guest';
        }
        if ($label_plural === '') {
            $label_plural = 'Guests';
        }
    }

    $columns = rm_event_addon_field_columns($event);

    $primaries_by_registration = [];
    foreach ($registrants as $row) {
        if (!is_array($row) || ($row['_role'] ?? '') !== 'primary') {
            continue;
        }
        $registration_id = isset($row['_registration_id']) ? (int) $row['_registration_id'] : 0;
        if ($registration_id < 1) {
            continue;
        }
        $christian_name = trim((string) ($row['christianName'] ?? ''));
        $given_name = trim((string) ($row['givenName'] ?? ''));
        $first_name = $christian_name !== '' ? $christian_name : $given_name;
        $last_name = trim((string) ($row['familyName'] ?? ''));
        $full_name = trim($first_name . ' ' . $last_name);
        $primaries_by_registration[$registration_id] = [
            'full_name'    => $full_name !== '' ? $full_name : 'N/A',
            'order_number' => trim((string) ($row['orderNumber'] ?? '')),
        ];
    }

    $rows = [];
    foreach ($registrants as $row) {
        if (!is_array($row) || ($row['_role'] ?? '') !== 'addon') {
            continue;
        }

        $guest = rm_present_guest_row($row);
        $registration_id = isset($row['_registration_id']) ? (int) $row['_registration_id'] : 0;
        $primary = $primaries_by_registration[$registration_id] ?? [
            'full_name'    => 'N/A',
            'order_number' => '',
        ];

        $field_values = [];
        foreach ($columns as $column) {
            $field_values[$column['key']] = rm_addon_field_display_value($row, $column['key']);
        }

        $payment_status = trim((string) ($row['_payment_status'] ?? ''));
        $is_paid = !empty($row['_is_paid']);
        $date_raw = (string) ($row['datestamp'] ?? '');
        $date_display = $date_raw !== '' ? rm_format_payment_transaction_datetime($date_raw) : '—';

        $rows[] = [
            'registrant_id'          => $guest['registrant_id'],
            'order_number'           => $guest['order_number'] !== '' ? $guest['order_number'] : 'N/A',
            'full_name'              => $guest['full_name'],
            'role_label'             => $label_singular,
            'amount_display'         => $guest['amount_display'],
            'primary_name'           => $primary['full_name'],
            'primary_order_number'   => $primary['order_number'],
            'payment_status'         => $payment_status,
            'payment_status_label'   => $payment_status !== ''
                ? ucwords(str_replace('_', ' ', $payment_status))
                : '—',
            'is_paid'                => $is_paid,
            'date_display'           => $date_display,
            'package_label'          => trim((string) ($row['_package_label'] ?? 'Individual')) !== ''
                ? (string) ($row['_package_label'] ?? 'Individual')
                : 'Individual',
            'field_values'           => $field_values,
            'fields'                 => $guest['fields'],
        ];
    }

    return [
        'rows'           => $rows,
        'columns'        => $columns,
        'label_singular' => $label_singular,
        'label_plural'   => $label_plural,
        'total'          => count($rows),
    ];
}

/**
 * Exclude addon (guest) rows — table shows primary and member only.
 *
 * @param array<int, array<string, mixed>> $registrants
 * @return array<int, array<string, mixed>>
 */
function rm_registrants_exclude_addons(array $registrants): array
{
    $out = [];
    foreach ($registrants as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (($row['_role'] ?? '') === 'addon') {
            continue;
        }
        $out[] = $row;
    }

    return $out;
}

/**
 * Exclude non-addon rows — keep guest/addon only.
 *
 * @param array<int, array<string, mixed>> $registrants
 * @return array<int, array<string, mixed>>
 */
function rm_registrants_addons_only(array $registrants): array
{
    $out = [];
    foreach ($registrants as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (($row['_role'] ?? '') === 'addon') {
            $out[] = $row;
        }
    }

    return $out;
}

/**
 * Apply export addon_filter: no-addon | include | addon-only.
 *
 * @param array<int, array<string, mixed>> $registrants
 * @return array<int, array<string, mixed>>
 */
function rm_filter_registrants_by_addon_filter(array $registrants, string $addon_filter): array
{
    if ($addon_filter === 'include') {
        return $registrants;
    }

    if ($addon_filter === 'addon-only') {
        return rm_registrants_addons_only($registrants);
    }

    return rm_registrants_exclude_addons($registrants);
}

/**
 * Attach guest addons to each primary registrant in a registration group.
 *
 * @param array<int, array<string, mixed>> $registrants
 * @return array<int, array<string, mixed>>
 */
function rm_attach_guests_to_primary_registrants(array $registrants): array
{
    $guests_by_registration = [];
    foreach ($registrants as $row) {
        if (!is_array($row) || ($row['_role'] ?? '') !== 'addon') {
            continue;
        }
        $registration_id = isset($row['_registration_id']) ? (int) $row['_registration_id'] : 0;
        if ($registration_id < 1) {
            continue;
        }
        if (!isset($guests_by_registration[$registration_id])) {
            $guests_by_registration[$registration_id] = [];
        }
        $guests_by_registration[$registration_id][] = rm_present_guest_row($row);
    }

    foreach ($registrants as &$row) {
        if (!is_array($row)) {
            continue;
        }
        if (($row['_role'] ?? '') !== 'primary') {
            $row['_guests'] = [];
            $row['_guest_button_label'] = '';
            continue;
        }

        $registration_id = isset($row['_registration_id']) ? (int) $row['_registration_id'] : 0;
        $guests = $guests_by_registration[$registration_id] ?? [];
        $count = count($guests);
        $row['_guests'] = $guests;

        if ($count < 1) {
            $row['_guest_button_label'] = '';
            continue;
        }

        $singular = (string) ($row['_guest_label_singular'] ?? $row['_guest_label'] ?? 'Guest');
        $plural = (string) ($row['_guest_label_plural'] ?? 'Guests');
        $label = $count === 1 ? $singular : $plural;
        $row['_guest_button_label'] = $count . ' ' . $label;
    }
    unset($row);

    return $registrants;
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

        return rm_format_currency($amount);
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

    $christian_name = trim((string) ($registrant['christianName'] ?? ''));
    $given_name = trim((string) ($registrant['givenName'] ?? ''));
    $first_name = $christian_name !== '' ? $christian_name : $given_name;
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
    if ($registrant_id < 1 || $event_id < 1) {
        return [
            'registrant' => null,
            'error'      => 'Registrant id and event id are required.',
        ];
    }

    $event = rm_get_event_by_id($event_id);
    if ($event === null) {
        $source = rm_get_event_source() !== '' ? rm_get_event_source() : rm_infer_event_source($event_id);
        $event = rm_get_event_by_id($event_id, $source);
    }
    if (is_array($event) && rm_event_uses_v2_registration($event) && rm_event_registration_tables_exist()) {
        return rm_fetch_v2_registrant_by_id($registrant_id, $event_id);
    }

    global $wpdb;

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
function rm_fetch_registrants_from_db(int $event_id, ?array $event = null): array
{
    if ($event_id < 1) {
        return [
            'registrants' => [],
            'error'       => 'Event id is required.',
        ];
    }

    if ($event === null) {
        $source = rm_get_event_source() !== '' ? rm_get_event_source() : rm_infer_event_source($event_id);
        $event = rm_get_event_by_id($event_id, $source);
    }

    if (is_array($event) && rm_event_uses_v2_registration($event) && rm_event_registration_tables_exist()) {
        return rm_fetch_v2_registrants_from_db($event_id, $event);
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
    $display_currency = $currency !== '' ? $currency : 'SGD';
    $amount_display = $display_currency . ' ' . number_format_i18n($amount, 2);

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
    $display_currency = $currency !== '' ? $currency : 'SGD';
    $amount_display = $display_currency . ' ' . number_format_i18n($amount, 2);

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
            || !empty($row['_is_paid'])
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

/**
 * Summarize registrants by package (Individual vs named promotions).
 *
 * @param array<int, array<string, mixed>> $registrants
 * @return list<array{key: string, label: string, people: int, registrations: int}>
 */
function rm_registrants_package_summary(array $registrants): array
{
    $buckets = [];

    foreach ($registrants as $row) {
        if (!is_array($row)) {
            continue;
        }

        $promotion_id = isset($row['_event_promotion_id']) ? (int) $row['_event_promotion_id'] : 0;
        $key = $promotion_id > 0 ? (string) $promotion_id : 'individual';
        $label = trim((string) ($row['_package_label'] ?? ''));
        if ($label === '') {
            $label = $promotion_id > 0 ? 'Package #' . $promotion_id : 'Individual';
        }

        if (!isset($buckets[$key])) {
            $buckets[$key] = [
                'key'           => $key,
                'label'         => $label,
                'people'        => 0,
                'registrations' => [],
            ];
        }

        $buckets[$key]['people']++;
        $registration_id = isset($row['_registration_id']) ? (int) $row['_registration_id'] : 0;
        if ($registration_id > 0) {
            $buckets[$key]['registrations'][$registration_id] = true;
        } else {
            $buckets[$key]['registrations']['p_' . $buckets[$key]['people']] = true;
        }
    }

    $out = [];
    foreach ($buckets as $bucket) {
        $out[] = [
            'key'           => $bucket['key'],
            'label'         => $bucket['label'],
            'people'        => $bucket['people'],
            'registrations' => count($bucket['registrations']),
        ];
    }

    usort($out, static function (array $a, array $b): int {
        if ($a['key'] === 'individual') {
            return -1;
        }
        if ($b['key'] === 'individual') {
            return 1;
        }

        return strcmp($a['label'], $b['label']);
    });

    return $out;
}

/**
 * @param array<int, array<string, mixed>> $registrants
 * @return array<int, array<string, mixed>>
 */
function rm_filter_registrants_by_package(array $registrants, string $package_filter): array
{
    if ($package_filter === '' || $package_filter === 'all') {
        return $registrants;
    }

    $filtered = [];
    foreach ($registrants as $row) {
        if (!is_array($row)) {
            continue;
        }

        $promotion_id = isset($row['_event_promotion_id']) ? (int) $row['_event_promotion_id'] : 0;

        if ($package_filter === 'individual') {
            if ($promotion_id < 1) {
                $filtered[] = $row;
            }
            continue;
        }

        if (ctype_digit($package_filter) && $promotion_id === (int) $package_filter) {
            $filtered[] = $row;
        }
    }

    return $filtered;
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
    $event_source = rm_get_event_source();

    if ($event_id > 0 || $selected_event_code !== '') {
        $selected_event = rm_resolve_event($event_id, $selected_event_code, $event_source);
        if ($selected_event !== null) {
            $event_source = rm_event_source_value($selected_event) !== ''
                ? rm_event_source_value($selected_event)
                : $event_source;
            if ($selected_event_code === '') {
                $selected_event_code = trim((string) ($selected_event['programCode'] ?? ''));
            }
            $event_id = isset($selected_event['id']) ? absint($selected_event['id']) : $event_id;
        }
    }

    if ($selected_event === null) {
        $selected_event = rm_find_event_by_code($event_options, $selected_event_code);
    }

    $error_message = '';

    if ($selected_event_code === '' && $event_id < 1) {
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
            $event_source = rm_event_source_value($selected_event);
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

    $api_args = rm_args_with_event_source([
        'action' => 'event-registrants-data',
    ], $event_source);
    if ($event_id > 0) {
        $api_args['event_id'] = $event_id;
    }
    if ($selected_event_code !== '') {
        $api_args['event_code'] = $selected_event_code;
    }

    $payment_details_args = rm_args_with_event_source([
        'action' => 'registrant-payment-details',
    ], $event_source);
    if ($event_id > 0) {
        $payment_details_args['event_id'] = $event_id;
    }

    $profile_args = rm_args_with_event_source([
        'action' => 'registrant-profile',
    ], $event_source);
    if ($event_id > 0) {
        $profile_args['event_id'] = $event_id;
    }

    return [
        'event_options'            => $event_options,
        'selected_event_code'      => $selected_event_code,
        'selected_event_id'        => $event_id,
        'selected_event_source'    => $event_source,
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
    $package_filter = rm_get_package_filter();

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
            'package_summary'     => [],
            'package_filter'      => $package_filter,
            'package_options'     => [
                ['value' => 'all', 'label' => 'All packages'],
                ['value' => 'individual', 'label' => 'Individual'],
            ],
            'pagination'          => [
                'current_page' => 1,
                'total_pages'  => 1,
                'per_page'     => rm_registrants_per_page(),
                'total'        => 0,
                'has_prev'     => false,
                'has_next'     => false,
                'from'         => 0,
                'to'           => 0,
            ],
        ];
    }

    $source = rm_get_event_source();
    if ($source === '') {
        $source = rm_infer_event_source($event_id);
    }
    $event = rm_get_event_by_id($event_id, $source);
    $db_fetch = rm_fetch_registrants_from_db($event_id, $event);
    $all_registrants = $db_fetch['error'] === '' ? $db_fetch['registrants'] : [];
    $all_registrants = rm_attach_guests_to_primary_registrants($all_registrants);
    $table_registrants = rm_registrants_exclude_addons($all_registrants);
    $package_summary = rm_registrants_package_summary($table_registrants);
    $filtered_registrants = rm_filter_registrants_by_package($table_registrants, $package_filter);

    $package_options = [
        ['value' => 'all', 'label' => 'All packages'],
        ['value' => 'individual', 'label' => 'Individual'],
    ];
    foreach ($package_summary as $bucket) {
        if ($bucket['key'] === 'individual') {
            continue;
        }
        $package_options[] = [
            'value' => $bucket['key'],
            'label' => $bucket['label'],
        ];
    }

    $rows = [];
    $per_page = rm_registrants_per_page();
    $current_page = rm_get_registrants_page();
    $total = count($filtered_registrants);
    $total_pages = max(1, (int) ceil($total / $per_page));

    if ($current_page > $total_pages) {
        $current_page = $total_pages;
    }

    $offset = ($current_page - 1) * $per_page;
    $page_registrants = array_slice($filtered_registrants, $offset, $per_page);

    if ($db_fetch['error'] === '') {
        foreach ($page_registrants as $registrant) {
            if (!is_array($registrant)) {
                continue;
            }

            $rows[] = rm_present_registrant_row($registrant, false);
        }
    }

    return [
        'ok'                  => $db_fetch['error'] === '',
        'error'               => $db_fetch['error'],
        'registrant_rows'     => $rows,
        'registrants_summary' => rm_registrants_summary($filtered_registrants),
        'package_summary'     => $package_summary,
        'package_filter'      => $package_filter,
        'package_options'     => $package_options,
        'pagination'          => [
            'current_page' => $current_page,
            'total_pages'  => $total_pages,
            'per_page'     => $per_page,
            'total'        => $total,
            'has_prev'     => $current_page > 1,
            'has_next'     => $current_page < $total_pages,
            'from'         => $total > 0 ? $offset + 1 : 0,
            'to'           => $total > 0 ? min($offset + $per_page, $total) : 0,
        ],
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
