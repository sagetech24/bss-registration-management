<?php

/**
 * HitPay sync for registration-manager registrant views.
 * Fetches charges via GET /v1/charges and links them to confirmed registrants by payment_request_id.
 */

/**
 * Read reference_number from a HitPay payment request record (top-level only).
 */
function rm_hitpay_get_payment_request_reference(array $payment_request): string
{
    return trim((string) ($payment_request['reference_number'] ?? ''));
}

/**
 * Strict RM- reference that belongs to the given event id (RM-{pending_id}-{event_id}).
 */
function rm_hitpay_is_registration_reference_for_event(string $reference, int $event_id): bool
{
    $reference = trim($reference);
    if ($reference === '' || $event_id < 1) {
        return false;
    }

    if (!str_starts_with($reference, 'RM-')) {
        return false;
    }

    return preg_match(
        '/^RM-\d+-' . preg_quote((string) $event_id, '/') . '$/',
        $reference
    ) === 1;
}

function rm_hitpay_payment_request_matches_event(array $payment_request, int $event_id): bool
{
    if ($event_id < 1) {
        return false;
    }

    $reference = rm_hitpay_get_payment_request_reference($payment_request);

    return rm_hitpay_is_registration_reference_for_event($reference, $event_id);
}

/**
 * Read reference_number from a HitPay charge (order_reference_number or reference_number).
 */
function rm_hitpay_get_charge_reference(array $charge): string
{
    $reference = trim((string) ($charge['reference_number'] ?? ''));
    if ($reference !== '') {
        return $reference;
    }

    return trim((string) ($charge['order_reference_number'] ?? ''));
}

/**
 * Charge belongs to registration-manager when reference starts with RM-.
 */
function rm_hitpay_is_registration_charge(array $charge): bool
{
    $reference = rm_hitpay_get_charge_reference($charge);

    return $reference !== '' && str_starts_with($reference, 'RM-');
}

function rm_hitpay_charge_belongs_to_event(array $charge, int $event_id): bool
{
    if ($event_id < 1) {
        return rm_hitpay_is_registration_charge($charge);
    }

    if (!rm_hitpay_is_registration_charge($charge)) {
        return false;
    }

    return rm_hitpay_is_registration_reference_for_event(rm_hitpay_get_charge_reference($charge), $event_id);
}

/**
 * Fetch all HitPay charges whose reference_number starts with RM-.
 *
 * @return array{ok: bool, data: array<int, array<string, mixed>>, error: string}
 */
function rm_hitpay_fetch_registration_charges(string $environment = ''): array
{
    if ($environment === '') {
        $environment = rm_payment_environment(0);
    }

    $matched = [];
    $seen_ids = [];
    $cursor = null;

    do {
        $result = rm_hitpay_list_charges($environment, $cursor);
        if (!$result['ok']) {
            return [
                'ok'    => false,
                'data'  => [],
                'error' => $result['error'],
            ];
        }

        foreach ($result['data'] as $charge) {
            if (!rm_hitpay_is_registration_charge($charge)) {
                continue;
            }

            $charge_id = trim((string) ($charge['id'] ?? ''));
            if ($charge_id !== '' && isset($seen_ids[$charge_id])) {
                continue;
            }

            if ($charge_id !== '') {
                $seen_ids[$charge_id] = true;
            }

            $matched[] = $charge;
        }

        $cursor = $result['next_cursor'] !== '' ? $result['next_cursor'] : null;
    } while ($cursor !== null);

    usort(
        $matched,
        static function (array $a, array $b): int {
            $a_time = strtotime((string) ($a['closed_at'] ?? $a['created_at'] ?? '')) ?: 0;
            $b_time = strtotime((string) ($b['closed_at'] ?? $b['created_at'] ?? '')) ?: 0;

            return $b_time <=> $a_time;
        }
    );

    return [
        'ok'    => true,
        'data'  => $matched,
        'error' => '',
    ];
}

/**
 * Fetch RM- registration charges, scoped to an event when event_id is provided.
 *
 * @return array{ok: bool, data: array<int, array<string, mixed>>, error: string}
 */
function rm_hitpay_fetch_registration_charges_for_event(int $event_id, string $environment = ''): array
{
    if ($event_id > 0) {
        $result = rm_hitpay_fetch_charges_for_event($event_id);
        if (!$result['ok']) {
            return $result;
        }

        $matched = [];
        foreach ($result['data'] as $charge) {
            if (rm_hitpay_charge_belongs_to_event($charge, $event_id)) {
                $matched[] = $charge;
            }
        }

        usort(
            $matched,
            static function (array $a, array $b): int {
                $a_time = strtotime((string) ($a['closed_at'] ?? $a['created_at'] ?? '')) ?: 0;
                $b_time = strtotime((string) ($b['closed_at'] ?? $b['created_at'] ?? '')) ?: 0;

                return $b_time <=> $a_time;
            }
        );

        return [
            'ok'    => true,
            'data'  => $matched,
            'error' => '',
        ];
    }

    if ($environment === '') {
        $environment = rm_payment_environment(0);
    }

    return rm_hitpay_fetch_registration_charges($environment);
}

/**
 * @return array{ok: bool, data: array<int, array<string, mixed>>, next_cursor: string, error: string}
 */
function rm_hitpay_list_charges(string $environment, ?string $cursor = null): array
{
    $path = '/charges';
    if ($cursor !== null && $cursor !== '') {
        $path .= '?' . http_build_query(['cursor' => $cursor], '', '&', PHP_QUERY_RFC3986);
    }

    $result = rm_payment_api_request('GET', $path, $environment);

    if (!$result['ok'] || !is_array($result['data'])) {
        return [
            'ok'          => false,
            'data'        => [],
            'next_cursor' => '',
            'error'       => $result['error'] !== '' ? $result['error'] : 'Failed to fetch HitPay charges.',
        ];
    }

    $payload = $result['data'];
    $items = [];

    if (isset($payload['data']) && is_array($payload['data'])) {
        $items = $payload['data'];
    } elseif (array_is_list($payload)) {
        $items = $payload;
    }

    $normalized = [];
    foreach ($items as $item) {
        if (is_array($item)) {
            $normalized[] = $item;
        }
    }

    $next_cursor = '';
    if (is_array($payload['meta'] ?? null)) {
        $next_cursor = trim((string) ($payload['meta']['next_cursor'] ?? ''));
    }

    return [
        'ok'          => true,
        'data'        => $normalized,
        'next_cursor' => $next_cursor,
        'error'       => '',
    ];
}

/**
 * @return array{ok: bool, data: array<string, mixed>|null, error: string}
 */
function rm_hitpay_get_charge(string $charge_id, int $event_id = 0): array
{
    $charge_id = sanitize_text_field($charge_id);
    if ($charge_id === '') {
        return [
            'ok'    => false,
            'data'  => null,
            'error' => 'Charge id is required.',
        ];
    }

    $environment = rm_payment_environment($event_id);
    $result = rm_payment_api_request(
        'GET',
        '/charges/' . rawurlencode($charge_id),
        $environment
    );

    if (!$result['ok'] || !is_array($result['data'])) {
        return [
            'ok'    => false,
            'data'  => null,
            'error' => $result['error'] !== '' ? $result['error'] : 'Charge not found.',
        ];
    }

    return [
        'ok'    => true,
        'data'  => $result['data'],
        'error' => '',
    ];
}

function rm_hitpay_extract_charge_id_from_payment_request(array $payment_request): string
{
    if (!empty($payment_request['payments']) && is_array($payment_request['payments'])) {
        foreach ($payment_request['payments'] as $payment) {
            if (!is_array($payment)) {
                continue;
            }

            foreach (['id', 'charge_id', 'payment_id'] as $key) {
                $candidate = trim((string) ($payment[$key] ?? ''));
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }
    }

    return trim((string) ($payment_request['charge_id'] ?? ''));
}

/**
 * @return array{ok: bool, data: array<string, mixed>|null, error: string}
 */
function rm_hitpay_find_charge_for_payment_request(
    string $payment_request_id,
    int $event_id = 0,
    ?array $payment_request = null
): array {
    $payment_request_id = sanitize_text_field($payment_request_id);
    if ($payment_request_id === '') {
        return [
            'ok'    => false,
            'data'  => null,
            'error' => 'Payment request id is required.',
        ];
    }

    if (is_array($payment_request)) {
        $charge_id = rm_hitpay_extract_charge_id_from_payment_request($payment_request);
        if ($charge_id !== '') {
            $direct = rm_hitpay_get_charge($charge_id, $event_id);
            if ($direct['ok'] && is_array($direct['data'])) {
                $resolved_request_id = trim((string) ($direct['data']['payment_request_id'] ?? ''));
                if ($resolved_request_id === '' || $resolved_request_id === $payment_request_id) {
                    return $direct;
                }
            }
        }
    }

    $environment = rm_payment_environment($event_id);
    $cursor = null;
    $max_pages = 25;

    for ($page = 0; $page < $max_pages; $page++) {
        $result = rm_hitpay_list_charges($environment, $cursor);
        if (!$result['ok']) {
            return [
                'ok'    => false,
                'data'  => null,
                'error' => $result['error'],
            ];
        }

        foreach ($result['data'] as $charge) {
            if (!is_array($charge)) {
                continue;
            }

            if (trim((string) ($charge['payment_request_id'] ?? '')) === $payment_request_id) {
                return [
                    'ok'    => true,
                    'data'  => $charge,
                    'error' => '',
                ];
            }
        }

        $cursor = $result['next_cursor'] !== '' ? $result['next_cursor'] : null;
        if ($cursor === null) {
            break;
        }
    }

    return [
        'ok'    => false,
        'data'  => null,
        'error' => 'Charge not found for this payment request.',
    ];
}

/**
 * Fetch HitPay charges (all pages).
 *
 * @return array{ok: bool, data: array<int, array<string, mixed>>, error: string}
 */
function rm_hitpay_fetch_charges_for_event(int $event_id): array
{
    if ($event_id < 1) {
        return [
            'ok'    => false,
            'data'  => [],
            'error' => 'Event id is required.',
        ];
    }

    $environment = rm_payment_environment($event_id);
    $matched = [];
    $seen_ids = [];
    $cursor = null;

    do {
        $result = rm_hitpay_list_charges($environment, $cursor);
        if (!$result['ok']) {
            return [
                'ok'    => false,
                'data'  => [],
                'error' => $result['error'],
            ];
        }

        foreach ($result['data'] as $charge) {
            $charge_id = trim((string) ($charge['id'] ?? ''));
            if ($charge_id !== '' && isset($seen_ids[$charge_id])) {
                continue;
            }

            if ($charge_id !== '') {
                $seen_ids[$charge_id] = true;
            }

            $matched[] = $charge;
        }

        $cursor = $result['next_cursor'] !== '' ? $result['next_cursor'] : null;
    } while ($cursor !== null);

    return [
        'ok'    => true,
        'data'  => $matched,
        'error' => '',
    ];
}

/**
 * @param list<string> $payment_request_ids
 * @return array<string, array<string, mixed>>
 */
function rm_hitpay_map_charges_by_payment_request(int $event_id, array $payment_request_ids): array
{
    $lookup = [];
    foreach ($payment_request_ids as $payment_request_id) {
        $payment_request_id = trim((string) $payment_request_id);
        if ($payment_request_id !== '') {
            $lookup[$payment_request_id] = true;
        }
    }

    if ($event_id < 1 || $lookup === []) {
        return [];
    }

    $charges_result = rm_hitpay_fetch_charges_for_event($event_id);
    if (!$charges_result['ok']) {
        return [];
    }

    $map = [];
    foreach ($charges_result['data'] as $charge) {
        if (!is_array($charge)) {
            continue;
        }

        $payment_request_id = trim((string) ($charge['payment_request_id'] ?? ''));
        if ($payment_request_id === '' || !isset($lookup[$payment_request_id]) || isset($map[$payment_request_id])) {
            continue;
        }

        $map[$payment_request_id] = rm_hitpay_summarize_charge($charge);
    }

    return $map;
}

/**
 * @param array<string, mixed> $payment_method
 */
function rm_hitpay_payment_method_logo_lg(array $payment_method): string
{
    $logo_groups = [
        $payment_method['method_logo'] ?? null,
        $payment_method['display_logo'] ?? null,
    ];

    foreach ($logo_groups as $logos) {
        if (!is_array($logos)) {
            continue;
        }

        foreach (['lg', 'sm'] as $size) {
            $logo_url = trim((string) ($logos[$size] ?? ''));
            if ($logo_url !== '') {
                return esc_url_raw($logo_url);
            }
        }
    }

    if (isset($payment_method['method_logo']) && is_string($payment_method['method_logo'])) {
        $direct_logo = trim($payment_method['method_logo']);
        if ($direct_logo !== '') {
            return esc_url_raw($direct_logo);
        }
    }

    return '';
}

/**
 * @return array<string, mixed>
 */
function rm_hitpay_summarize_charge(array $charge): array
{
    $payment_method = is_array($charge['payment_method'] ?? null) ? $charge['payment_method'] : [];
    $payment_method_name = trim((string) ($payment_method['name'] ?? ''));
    $customer = is_array($charge['customer'] ?? null) ? $charge['customer'] : [];

    return [
        'charge_id'                => sanitize_text_field((string) ($charge['id'] ?? '')),
        'payment_request_id'       => sanitize_text_field((string) ($charge['payment_request_id'] ?? '')),
        'status'                   => sanitize_text_field((string) ($charge['status'] ?? '')),
        'payment_request_status'   => sanitize_text_field((string) ($charge['status'] ?? '')),
        'amount'                   => isset($charge['amount']) ? (float) $charge['amount'] : 0.0,
        'currency'                 => sanitize_text_field((string) ($charge['currency'] ?? '')),
        'payment_method'           => $payment_method_name !== ''
            ? rm_payment_normalize_option($payment_method_name)
            : 'N/A',
        'payment_method_logo'      => rm_hitpay_payment_method_logo_lg($payment_method),
        'customer_name'            => sanitize_text_field((string) ($customer['name'] ?? '')),
        'customer_email'           => sanitize_email((string) ($customer['email'] ?? '')),
        'customer_phone'           => sanitize_text_field((string) ($customer['phone_number'] ?? ($customer['phone'] ?? ''))),
        'paid_at'                  => sanitize_text_field((string) ($charge['closed_at'] ?? $charge['created_at'] ?? '')),
        'remark'                   => sanitize_text_field((string) ($charge['remark'] ?? '')),
        'reference_number'         => sanitize_text_field(rm_hitpay_get_charge_reference($charge)),
        'order_reference_number'   => sanitize_text_field((string) ($charge['order_reference_number'] ?? '')),
        'created_at'               => sanitize_text_field((string) ($charge['created_at'] ?? '')),
        'channel'                  => sanitize_text_field((string) ($charge['channel'] ?? '')),
    ];
}

/**
 * @return array<string, mixed>|null
 */
function rm_hitpay_find_registrant_for_charge(array $charge, int $event_id): ?array
{
    if ($event_id < 1) {
        return null;
    }

    $payment_request_id = trim((string) ($charge['payment_request_id'] ?? ''));
    if ($payment_request_id === '') {
        return null;
    }

    global $wpdb;

    $row = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM `bss_registrant` WHERE `events` = %d AND `payment` = %s LIMIT 1',
            $event_id,
            $payment_request_id
        ),
        ARRAY_A
    );

    if (!is_array($row) || $row === []) {
        return null;
    }

    $row['_hitpay'] = rm_hitpay_summarize_charge($charge);

    return $row;
}

/**
 * @return array{ok: bool, data: array<int, array<string, mixed>>, error: string}
 */
function rm_hitpay_list_payment_requests(string $environment, int $current_page = 1): array
{
    $query = [
        'per_page'     => 99,
        'current_page' => max(1, $current_page),
    ];

    $path = '/payment-requests?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $result = rm_payment_api_request('GET', $path, $environment);

    if (!$result['ok'] || !is_array($result['data'])) {
        return [
            'ok'    => false,
            'data'  => [],
            'error' => $result['error'] !== '' ? $result['error'] : 'Failed to fetch HitPay payment requests.',
        ];
    }

    $payload = $result['data'];
    $items = [];

    if (isset($payload['data']) && is_array($payload['data'])) {
        $items = $payload['data'];
    } elseif (array_is_list($payload)) {
        $items = $payload;
    }

    $normalized = [];
    foreach ($items as $item) {
        if (is_array($item)) {
            $normalized[] = $item;
        }
    }

    return [
        'ok'    => true,
        'data'  => $normalized,
        'error' => '',
    ];
}

/**
 * Fetch HitPay payment requests for a selected event.
 * Returns only records with reference_number matching RM-*-{event_id}.
 *
 * @return array{ok: bool, data: array<int, array<string, mixed>>, error: string}
 */
function rm_hitpay_fetch_payment_requests_for_event(int $event_id): array
{
    if ($event_id < 1) {
        return [
            'ok'    => false,
            'data'  => [],
            'error' => 'Event id is required.',
        ];
    }

    $environment = rm_payment_environment($event_id);
    $result = rm_hitpay_list_payment_requests($environment);

    if (!$result['ok']) {
        return $result;
    }

    $matched = [];
    $seen_ids = [];

    foreach ($result['data'] as $payment_request) {
        if (!rm_hitpay_payment_request_matches_event($payment_request, $event_id)) {
            continue;
        }

        $request_id = trim((string) ($payment_request['id'] ?? ''));
        if ($request_id !== '' && isset($seen_ids[$request_id])) {
            continue;
        }

        if ($request_id !== '') {
            $seen_ids[$request_id] = true;
        }

        $matched[] = $payment_request;
    }

    return [
        'ok'    => true,
        'data'  => $matched,
        'error' => '',
    ];
}

/**
 * @return array<string, mixed>
 */
function rm_hitpay_summarize_payment_request(array $payment_request): array
{
    $first_payment = is_array($payment_request['payments'][0] ?? null)
        ? $payment_request['payments'][0]
        : [];

    $payment_type = trim((string) ($first_payment['payment_type'] ?? ''));
    $payment_method = $payment_type !== ''
        ? rm_payment_normalize_option($payment_type)
        : 'N/A';
    $payment_method_data = is_array($first_payment['payment_method'] ?? null)
        ? $first_payment['payment_method']
        : (is_array($payment_request['payment_method'] ?? null) ? $payment_request['payment_method'] : []);

    return [
        'payment_request_id'     => sanitize_text_field((string) ($payment_request['id'] ?? '')),
        'reference_number'       => sanitize_text_field(rm_hitpay_get_payment_request_reference($payment_request)),
        'status'                 => sanitize_text_field((string) ($payment_request['status'] ?? '')),
        'payment_request_status' => sanitize_text_field((string) ($payment_request['status'] ?? '')),
        'amount'                 => isset($payment_request['amount']) ? (float) $payment_request['amount'] : 0.0,
        'currency'               => sanitize_text_field((string) ($payment_request['currency'] ?? '')),
        'payment_method'         => $payment_method,
        'payment_method_logo'    => rm_hitpay_payment_method_logo_lg($payment_method_data),
        'customer_name'          => sanitize_text_field((string) ($payment_request['name'] ?? '')),
        'customer_email'         => sanitize_email((string) ($payment_request['email'] ?? '')),
        'paid_at'                => sanitize_text_field((string) ($payment_request['updated_at'] ?? $payment_request['created_at'] ?? '')),
    ];
}

/**
 * @return array<string, mixed>|null
 */
function rm_hitpay_find_registrant_for_payment_request(array $payment_request, int $event_id): ?array
{
    if (!rm_hitpay_payment_request_matches_event($payment_request, $event_id)) {
        return null;
    }

    $payment_request_id = trim((string) ($payment_request['id'] ?? ''));
    if ($payment_request_id === '') {
        return null;
    }

    global $wpdb;

    $row = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM `bss_registrant` WHERE `events` = %d AND `payment` = %s LIMIT 1',
            $event_id,
            $payment_request_id
        ),
        ARRAY_A
    );

    if (!is_array($row) || $row === []) {
        return null;
    }

    $row['_hitpay'] = rm_hitpay_summarize_payment_request($payment_request);

    return $row;
}

/**
 * Fetch confirmed registrants for an event using HitPay charges as the source of truth.
 *
 * @return array{
 *     registrants: array<int, array<string, mixed>>,
 *     error: string,
 *     hitpay_matched: int,
 *     hitpay_orphans: int
 * }
 */
function rm_fetch_registrants_via_hitpay(int $event_id): array
{
    if ($event_id < 1) {
        return [
            'registrants'    => [],
            'error'          => 'Event id is required.',
            'hitpay_matched' => 0,
            'hitpay_orphans' => 0,
        ];
    }

    $charges_result = rm_hitpay_fetch_charges_for_event($event_id);
    if (!$charges_result['ok']) {
        return [
            'registrants'    => [],
            'error'          => $charges_result['error'],
            'hitpay_matched' => 0,
            'hitpay_orphans' => 0,
        ];
    }

    $charges = $charges_result['data'];
    if ($charges === []) {
        return [
            'registrants'    => [],
            'error'          => '',
            'hitpay_matched' => 0,
            'hitpay_orphans' => 0,
        ];
    }

    $registrants = [];
    $orphans = 0;
    $seen_registrant_ids = [];

    foreach ($charges as $charge) {
        $status = strtolower(trim((string) ($charge['status'] ?? '')));
        if ($status !== 'succeeded') {
            continue;
        }

        $row = rm_hitpay_find_registrant_for_charge($charge, $event_id);
        if ($row === null) {
            $orphans++;
            continue;
        }

        $registrant_id = isset($row['id']) ? (int) $row['id'] : 0;
        if ($registrant_id > 0 && isset($seen_registrant_ids[$registrant_id])) {
            continue;
        }

        if ($registrant_id > 0) {
            $seen_registrant_ids[$registrant_id] = true;
        }

        $registrants[] = $row;
    }

    usort(
        $registrants,
        static function (array $a, array $b): int {
            $a_time = strtotime((string) ($a['datestamp'] ?? '')) ?: 0;
            $b_time = strtotime((string) ($b['datestamp'] ?? '')) ?: 0;

            return $a_time <=> $b_time;
        }
    );

    return [
        'registrants'    => $registrants,
        'error'          => '',
        'hitpay_matched' => count($registrants),
        'hitpay_orphans' => $orphans,
    ];
}
