<?php

/**
 * HitPay payment integration for registration-manager.
 * Default: live on production (biblesociety.sg), sandbox elsewhere.
 * Override: apply_filters('rm_payment_environment', $default, $event_id).
 */

/**
 * @return 'test'|'live'
 */
function rm_payment_environment(int $event_id = 0): string
{
    $default = rm_payment_is_production_site() ? 'live' : 'test';
    $environment = apply_filters('rm_payment_environment', $default, $event_id);

    return $environment === 'live' ? 'live' : 'test';
}

/**
 * @param array<string, mixed>|null $data
 */
function rm_payment_response_is_model_not_found(?array $data, int $status_code): bool
{
    if ($status_code !== 404 || !is_array($data)) {
        return false;
    }

    return isset($data['error_code']) && $data['error_code'] === 'model_not_found';
}

function rm_payment_api_base(string $environment): string
{
    return $environment === 'live'
        ? 'https://api.hit-pay.com/v1'
        : 'https://api.sandbox.hit-pay.com/v1';
}

function rm_payment_resolve_api_key(string $environment): string
{
    $option_key = $environment === 'live' ? 'hitpay_live_key' : 'hitpay_test_key';
    $env_key = $environment === 'live' ? 'HITPAY_LIVE_KEY' : 'HITPAY_TEST_KEY';

    $key = get_option($option_key);
    if (!is_string($key) || trim($key) === '') {
        $key = getenv($env_key);
    }
    if (!is_string($key) || trim($key) === '') {
        if (defined($env_key)) {
            $key = constant($env_key);
        }
    }

    if (!is_string($key) || trim($key) === '') {
        throw new RuntimeException(
            'HitPay API key is not configured for environment: ' . $environment
        );
    }

    return trim($key);
}

/**
 * API-key salt (Developers page) — used for legacy `hmac` in the POST body.
 *
 * @return string|null
 */
function rm_payment_try_resolve_api_salt(string $environment): ?string
{
    $option_key = $environment === 'live' ? 'hitpay_live_salt' : 'hitpay_test_salt';
    $env_key = $environment === 'live' ? 'HITPAY_LIVE_SALT' : 'HITPAY_TEST_SALT';

    return rm_payment_try_resolve_configured_secret($option_key, $env_key);
}

/**
 * Per-webhook-endpoint salt (Developers → Webhooks → your endpoint) — used for `Hitpay-Signature` header.
 *
 * @return string|null
 */
function rm_payment_try_resolve_webhook_endpoint_salt(string $environment): ?string
{
    $option_key = $environment === 'live' ? 'hitpay_live_webhook_salt' : 'hitpay_test_webhook_salt';
    $env_key = $environment === 'live' ? 'HITPAY_LIVE_WEBHOOK_SALT' : 'HITPAY_TEST_WEBHOOK_SALT';

    return rm_payment_try_resolve_configured_secret($option_key, $env_key);
}

/**
 * @return string|null
 */
function rm_payment_try_resolve_salt(string $environment): ?string
{
    return rm_payment_try_resolve_api_salt($environment);
}

/**
 * @return string|null
 */
function rm_payment_try_resolve_configured_secret(string $option_key, string $env_key): ?string
{
    $secret = get_option($option_key);
    if (!is_string($secret) || trim($secret) === '') {
        $secret = getenv($env_key);
    }
    if (!is_string($secret) || trim($secret) === '') {
        if (defined($env_key)) {
            $secret = constant($env_key);
        }
    }

    if (!is_string($secret) || trim($secret) === '') {
        return null;
    }

    return trim($secret);
}

/**
 * @return list<string>
 */
function rm_payment_collect_salts(string $kind): array
{
    $resolver = $kind === 'webhook'
        ? 'rm_payment_try_resolve_webhook_endpoint_salt'
        : 'rm_payment_try_resolve_api_salt';

    $salts = [];
    foreach (['live', 'test'] as $environment) {
        $salt = $resolver($environment);
        if ($salt !== null) {
            $salts[] = $salt;
        }
    }

    return array_values(array_unique($salts));
}

function rm_payment_get_webhook_header(string $header_name): string
{
    $header_name = strtolower(trim($header_name));
    if ($header_name === '') {
        return '';
    }

    $server_key = 'HTTP_' . strtoupper(str_replace('-', '_', $header_name));
    if (isset($_SERVER[$server_key])) {
        return trim((string) wp_unslash($_SERVER[$server_key]));
    }

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strtolower((string) $name) === $header_name) {
                    return trim((string) $value);
                }
            }
        }
    }

    return '';
}

function rm_payment_get_webhook_signature(): string
{
    return strtolower(trim(rm_payment_get_webhook_header('Hitpay-Signature')));
}

/**
 * Detect common misconfiguration (webhook URL pasted where the signing secret belongs).
 */
function rm_payment_webhook_salt_config_hint(): string
{
    foreach (['live', 'test'] as $environment) {
        $salt = rm_payment_try_resolve_webhook_endpoint_salt($environment);
        if ($salt === null) {
            continue;
        }

        if (preg_match('#^https?://#i', $salt) !== 0) {
            return 'HITPAY_' . strtoupper($environment) . '_WEBHOOK_SALT looks like a URL;'
                . ' use the Webhook Salt from HitPay Developers → Webhooks, not the endpoint URL.';
        }
    }

    return '';
}

/**
 * @return array{event_type: string, event_object: string}
 */
function rm_payment_get_webhook_event_headers(): array
{
    return [
        'event_type'   => sanitize_key(rm_payment_get_webhook_header('Hitpay-Event-Type')),
        'event_object' => sanitize_key(rm_payment_get_webhook_header('Hitpay-Event-Object')),
    ];
}

function rm_payment_verify_webhook_signature(string $raw_payload, string $signature, string $salt): bool
{
    if ($raw_payload === '' || $signature === '' || $salt === '') {
        return false;
    }

    $computed = hash_hmac('sha256', $raw_payload, $salt);
    $signature = strtolower(trim($signature));
    $computed = strtolower($computed);

    if (strlen($signature) !== strlen($computed)) {
        return false;
    }

    return hash_equals($computed, $signature);
}

/**
 * Legacy HitPay webhook: sort keys (excluding hmac), concatenate key+value, HMAC-SHA256 with API-key salt.
 *
 * @param array<string, mixed> $payload
 */
function rm_payment_build_legacy_webhook_signature_string(array $payload): string
{
    $fields = $payload;
    unset($fields['hmac']);

    $hmac_source = [];
    foreach ($fields as $key => $value) {
        if (!is_scalar($value)) {
            continue;
        }

        $hmac_source[(string) $key] = (string) $key . (string) $value;
    }

    ksort($hmac_source, SORT_STRING);

    return implode('', array_values($hmac_source));
}

/**
 * @param array<string, mixed> $payload
 */
function rm_payment_verify_legacy_webhook_hmac(array $payload, string $salt): bool
{
    $hmac = isset($payload['hmac']) ? trim((string) $payload['hmac']) : '';
    if ($hmac === '' || $salt === '') {
        return false;
    }

    $computed = hash_hmac('sha256', rm_payment_build_legacy_webhook_signature_string($payload), $salt);

    return hash_equals($computed, $hmac);
}

/**
 * @param array<string, mixed> $payload
 */
function rm_payment_verify_webhook_request(string $raw_payload, string $header_signature, array $payload = []): bool
{
    $payload_hmac = isset($payload['hmac']) ? trim((string) $payload['hmac']) : '';

    if ($payload_hmac !== '') {
        foreach (rm_payment_collect_salts('api') as $salt) {
            if (rm_payment_verify_legacy_webhook_hmac($payload, $salt)) {
                return true;
            }
        }
    }

    if ($raw_payload !== '' && $header_signature !== '') {
        foreach (rm_payment_collect_salts('webhook') as $salt) {
            if (rm_payment_verify_webhook_signature($raw_payload, $header_signature, $salt)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * @param array<string, mixed> $payload
 */
function rm_payment_is_webhook_payment_completed(array $payload): bool
{
    $status = isset($payload['status']) ? strtolower(sanitize_text_field((string) $payload['status'])) : '';

    if (in_array($status, ['completed', 'succeeded'], true)) {
        return true;
    }

    return rm_payment_is_completed($payload);
}

/**
 * @param array<string, mixed> $payload
 */
function rm_payment_extract_webhook_payment_request_id(array $payload): string
{
    if (!empty($payload['payment_request_id'])) {
        return sanitize_text_field((string) $payload['payment_request_id']);
    }

    $event_headers = rm_payment_get_webhook_event_headers();
    $has_reference_number = isset($payload['reference_number'])
        && trim((string) $payload['reference_number']) !== '';

    if ($event_headers['event_object'] === 'payment_request' || $has_reference_number) {
        return isset($payload['id']) ? sanitize_text_field((string) $payload['id']) : '';
    }

    return '';
}

function rm_payment_is_production_site(): bool
{
    $home = untrailingslashit(home_url());
    $home_host = wp_parse_url($home, PHP_URL_HOST);

    if (!is_string($home_host) || $home_host === '') {
        return false;
    }

    if (wp_parse_url($home, PHP_URL_SCHEME) !== 'https') {
        return false;
    }

    $host = strtolower($home_host);

    return $host === 'biblesociety.sg' || $host === 'www.biblesociety.sg';
}

function rm_payment_webhooks_enabled(): bool
{
    return rm_payment_is_production_site();
}

/**
 * Webhooks are enabled only on production (https://biblesociety.sg).
 * All other environments finalize via the payment-return redirect.
 */
function rm_payment_webhook_url(): ?string
{
    if (!rm_payment_webhooks_enabled()) {
        return null;
    }

    return home_url('/registration-manager/webhook.php');
}

/**
 * @param array<string, mixed>|null $data
 */
function rm_payment_format_api_error(?array $data, int $status_code = 0): string
{
    if (is_array($data)) {
        if (!empty($data['message']) && is_string($data['message'])) {
            return sanitize_text_field($data['message']);
        }

        if (!empty($data['error']) && is_string($data['error'])) {
            return sanitize_text_field($data['error']);
        }
    }

    if ($status_code > 0) {
        return 'HitPay API returned an error (HTTP ' . $status_code . ').';
    }

    return 'HitPay API returned an error.';
}

/**
 * @return list<string>
 */
function rm_payment_methods(string $environment): array
{
    if ($environment === 'live') {
        return ['paynow_online', 'cards'];
    }

    return ['paynow_online'];
}

function rm_payment_reference_for_pending(int $pending_id, int $event_id): string
{
    return 'RM-' . max(0, $pending_id) . '-' . max(0, $event_id);
}

/**
 * @return array{pending_id: int, event_id: int}
 */
function rm_payment_parse_reference(string $reference): array
{
    $reference = trim($reference);
    if ($reference === '') {
        return ['pending_id' => 0, 'event_id' => 0];
    }

    if (preg_match('/^RM-(\d+)-(\d+)$/', $reference, $matches) === 1) {
        return [
            'pending_id' => absint($matches[1]),
            'event_id'   => absint($matches[2]),
        ];
    }

    if (preg_match('/^RM-(\d+)$/', $reference, $matches) === 1) {
        return [
            'pending_id' => absint($matches[1]),
            'event_id'   => 0,
        ];
    }

    if (ctype_digit($reference)) {
        return [
            'pending_id' => absint($reference),
            'event_id'   => 0,
        ];
    }

    return ['pending_id' => 0, 'event_id' => 0];
}

function rm_payment_parse_pending_id(string $reference): int
{
    return rm_payment_parse_reference($reference)['pending_id'];
}

function rm_payment_parse_event_id(string $reference): int
{
    return rm_payment_parse_reference($reference)['event_id'];
}

function rm_payment_normalize_option(string $hitpay_type): string
{
    $hitpay_type = strtolower(trim($hitpay_type));

    if ($hitpay_type === 'paynow_online' || $hitpay_type === 'paynow') {
        return 'PayNow';
    }

    if ($hitpay_type === 'card' || $hitpay_type === 'cards' || str_contains($hitpay_type, 'card')) {
        return 'Credit Card';
    }

    return $hitpay_type !== '' ? sanitize_text_field($hitpay_type) : 'N/A';
}

/**
 * @return array{ok: bool, data: array<string, mixed>|null, error: string, status_code: int}
 */
function rm_payment_api_request(string $method, string $path, string $environment, ?array $payload = null): array
{
    try {
        $api_key = rm_payment_resolve_api_key($environment);
    } catch (RuntimeException $exception) {
        return [
            'ok'          => false,
            'data'        => null,
            'error'       => $exception->getMessage(),
            'status_code' => 0,
        ];
    }

    $endpoint = rm_payment_api_base($environment) . $path;
    $headers = [
        'X-BUSINESS-API-KEY' => $api_key,
        'X-Requested-With'   => 'XMLHttpRequest',
        'Accept'             => 'application/json',
    ];

    $args = [
        'headers' => $headers,
        'timeout' => 20,
    ];

    if ($payload !== null) {
        $headers['Content-Type'] = 'application/json';
        $args['headers'] = $headers;
        $args['body'] = wp_json_encode($payload);
    }

    $response = strtoupper($method) === 'POST'
        ? wp_remote_post($endpoint, $args)
        : wp_remote_get($endpoint, $args);

    if (is_wp_error($response)) {
        error_log('[rm_payment] API request failed: ' . $response->get_error_message());

        return [
            'ok'          => false,
            'data'        => null,
            'error'       => $response->get_error_message(),
            'status_code' => 0,
        ];
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    $data = is_array($data) ? $data : null;

    if ($status_code < 200 || $status_code >= 300) {
        error_log('[rm_payment] API non-2xx: ' . $status_code . ' body: ' . $body);

        return [
            'ok'          => false,
            'data'        => $data,
            'error'       => rm_payment_format_api_error($data, $status_code),
            'status_code' => $status_code,
        ];
    }

    return [
        'ok'          => true,
        'data'        => $data,
        'error'       => '',
        'status_code' => $status_code,
    ];
}

/**
 * @param array<string, mixed> $event
 * @param array<string, string> $registrant
 * @return array{ok: bool, id: string, url: string, error: string}
 */
function rm_payment_create_request(
    int $pending_id,
    array $event,
    array $registrant,
    float $amount,
    string $event_code
): array {
    $event_id = isset($event['id']) ? absint($event['id']) : 0;
    $environment = rm_payment_environment($event_id);

    $full_name = trim(
        ($registrant['christianName'] ?? '') . ' ' . ($registrant['familyName'] ?? '')
    );
    $title = isset($event['title']) ? sanitize_text_field((string) $event['title']) : '';
    $start_date = isset($event['startDate']) ? (string) $event['startDate'] : '';
    $reference = rm_payment_reference_for_pending($pending_id, $event_id);

    $redirect_url = add_query_arg(
        [
            'action'     => 'payment-return',
            'event_code' => $event_code,
            'pending_id' => $pending_id,
        ],
        rm_page_url()
    );

    $payload = [
        'amount'                  => round($amount, 2),
        'payment_methods'         => rm_payment_methods($environment),
        'currency'                => 'SGD',
        'name'                    => $full_name,
        'email'                   => $registrant['email'] ?? '',
        'phone'                   => $registrant['contact'] ?? '',
        'purpose'                 => $title . '_' . $reference . '_' . $start_date,
        'reference_number'        => $reference,
        'allow_repeated_payments' => false,
        'expires_after'           => '3 days',
        'send_email'              => true,
        'send_sms'                => true,
        'redirect_url'            => $redirect_url,
    ];

    $result = rm_payment_api_request('POST', '/payment-requests', $environment, $payload);
    if (!$result['ok'] || !is_array($result['data'])) {
        return [
            'ok'    => false,
            'id'    => '',
            'url'   => '',
            'error' => $result['error'] !== '' ? $result['error'] : 'Failed to create payment request.',
        ];
    }

    $id = isset($result['data']['id']) ? sanitize_text_field((string) $result['data']['id']) : '';
    $url = isset($result['data']['url']) ? esc_url_raw((string) $result['data']['url']) : '';

    if ($id === '' || $url === '') {
        return [
            'ok'    => false,
            'id'    => '',
            'url'   => '',
            'error' => 'HitPay response was missing payment details.',
        ];
    }

    return [
        'ok'    => true,
        'id'    => $id,
        'url'   => $url,
        'error' => '',
    ];
}

/**
 * @return array{ok: bool, data: array<string, mixed>|null, error: string}
 */
function rm_payment_get_request(string $payment_request_id, int $event_id = 0): array
{
    $payment_request_id = sanitize_text_field($payment_request_id);
    if ($payment_request_id === '') {
        return [
            'ok'    => false,
            'data'  => null,
            'error' => 'Payment reference is required.',
        ];
    }

    $environment = rm_payment_environment($event_id);
    $result = rm_payment_api_request(
        'GET',
        '/payment-requests/' . rawurlencode($payment_request_id),
        $environment
    );

    $allow_fallback = apply_filters('rm_payment_allow_environment_fallback', true, $event_id, $environment);
    if (
        !$result['ok']
        && $allow_fallback
        && rm_payment_response_is_model_not_found($result['data'], $result['status_code'])
    ) {
        $alternate = $environment === 'live' ? 'test' : 'live';
        $retry = rm_payment_api_request(
            'GET',
            '/payment-requests/' . rawurlencode($payment_request_id),
            $alternate
        );
        if ($retry['ok']) {
            $result = $retry;
        }
    }

    return [
        'ok'    => $result['ok'],
        'data'  => $result['data'],
        'error' => $result['error'],
    ];
}

function rm_payment_store_request_id(int $pending_id, string $request_id): bool
{
    global $wpdb;

    if ($pending_id < 1 || $request_id === '') {
        return false;
    }

    if (rm_event_registration_tables_exist() && rm_v2_load_pending_header($pending_id) !== null) {
        $updated = $wpdb->update(
            'event_registration_pendings',
            ['payment_request_id' => sanitize_text_field($request_id)],
            ['id' => $pending_id],
            ['%s'],
            ['%d']
        );

        return $updated !== false;
    }

    $updated = $wpdb->update(
        'bss_registrant_pendings',
        ['payment' => sanitize_text_field($request_id)],
        ['id' => $pending_id],
        ['%s'],
        ['%d']
    );

    return $updated !== false;
}

/**
 * @return array<string, mixed>|null
 */
function rm_payment_load_pending(int $pending_id): ?array
{
    global $wpdb;

    if ($pending_id < 1) {
        return null;
    }

    if (rm_event_registration_tables_exist()) {
        $v2_header = rm_v2_load_pending_header($pending_id);
        if ($v2_header !== null) {
            $primary = rm_v2_load_pending_primary_registrant($pending_id);

            return [
                'id'       => $pending_id,
                'events'   => isset($v2_header['event_id']) ? (int) $v2_header['event_id'] : 0,
                'amount'   => isset($v2_header['total_amount']) ? (float) $v2_header['total_amount'] : 0.0,
                'payment'  => $v2_header['payment_request_id'] ?? null,
                'email'    => $primary['email'] ?? $v2_header['primary_email'] ?? '',
                '_v2'      => true,
                '_header'  => $v2_header,
                '_primary' => $primary,
            ];
        }
    }

    $pending = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM `bss_registrant_pendings` WHERE `id` = %d LIMIT 1',
            $pending_id
        ),
        ARRAY_A
    );

    return is_array($pending) && $pending !== [] ? $pending : null;
}

/**
 * @param array<string, mixed> $hitpay_data
 */
function rm_payment_is_completed(array $hitpay_data): bool
{
    $status = isset($hitpay_data['status']) ? strtolower((string) $hitpay_data['status']) : '';

    if ($status === 'completed') {
        return true;
    }

    if (!empty($hitpay_data['payments']) && is_array($hitpay_data['payments'])) {
        foreach ($hitpay_data['payments'] as $payment) {
            if (!is_array($payment)) {
                continue;
            }

            $payment_status = isset($payment['status']) ? strtolower((string) $payment['status']) : '';
            if ($payment_status === 'succeeded' || $payment_status === 'completed') {
                return true;
            }
        }
    }

    return false;
}

/**
 * @param array<string, mixed> $hitpay_data
 */
function rm_payment_extract_option(array $hitpay_data): string
{
    if (!empty($hitpay_data['payments']) && is_array($hitpay_data['payments'])) {
        $first = $hitpay_data['payments'][0] ?? null;
        if (is_array($first) && !empty($first['payment_type'])) {
            return rm_payment_normalize_option((string) $first['payment_type']);
        }
    }

    return 'N/A';
}

/**
 * @param array<string, mixed> $hitpay_data
 */
function rm_payment_amount_matches(array $hitpay_data, float $expected_amount): bool
{
    $paid_amount = null;

    if (isset($hitpay_data['amount']) && is_numeric($hitpay_data['amount'])) {
        $paid_amount = (float) $hitpay_data['amount'];
    } elseif (!empty($hitpay_data['payments'][0]['amount']) && is_numeric($hitpay_data['payments'][0]['amount'])) {
        $paid_amount = (float) $hitpay_data['payments'][0]['amount'];
    }

    if ($paid_amount === null) {
        return false;
    }

    return abs($paid_amount - round($expected_amount, 2)) < 0.01;
}

/**
 * @return array{ok: bool, order_number: string, error: string}
 */
function rm_payment_handle_completed(int $pending_id, string $payment_request_id): array
{
    if ($pending_id < 1) {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'Pending registration could not be found.',
        ];
    }

    $pending = rm_payment_load_pending($pending_id);
    if ($pending === null) {
        $lookup = rm_payment_get_request($payment_request_id);
        if ($lookup['ok'] && is_array($lookup['data'])) {
            $resolved_pending_id = rm_payment_parse_pending_id(
                (string) ($lookup['data']['reference_number'] ?? '')
            );
            if ($resolved_pending_id > 0) {
                $pending_id = $resolved_pending_id;
                $pending = rm_payment_load_pending($pending_id);
            }
        }

        if ($pending === null) {
            global $wpdb;
            $existing = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT `orderNumber`, `email`, `events` FROM `bss_registrant` WHERE `payment` = %s LIMIT 1',
                    sanitize_text_field($payment_request_id)
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

            if (rm_event_registration_tables_exist()) {
                $v2_existing = $wpdb->get_row(
                    $wpdb->prepare(
                        'SELECT `primary_order_number` FROM `event_registration` WHERE `payment_request_id` = %s LIMIT 1',
                        sanitize_text_field($payment_request_id)
                    ),
                    ARRAY_A
                );

                if (is_array($v2_existing) && !empty($v2_existing['primary_order_number'])) {
                    return [
                        'ok'           => true,
                        'order_number' => (string) $v2_existing['primary_order_number'],
                        'error'        => '',
                    ];
                }
            }

            return [
                'ok'           => false,
                'order_number' => '',
                'error'        => 'Pending registration could not be found.',
            ];
        }
    }

    $event_id = isset($pending['events']) ? absint($pending['events']) : 0;
    $expected_amount = isset($pending['amount']) ? (float) $pending['amount'] : 0.0;

    $lookup = rm_payment_get_request($payment_request_id, $event_id);
    if (!$lookup['ok'] || !is_array($lookup['data'])) {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => $lookup['error'] !== '' ? $lookup['error'] : 'Could not verify payment.',
        ];
    }

    $hitpay_data = $lookup['data'];

    if (!rm_payment_is_completed($hitpay_data)) {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'Payment is not completed.',
        ];
    }

    $parsed_reference = rm_payment_parse_reference((string) ($hitpay_data['reference_number'] ?? ''));
    if ($parsed_reference['pending_id'] > 0 && $parsed_reference['pending_id'] !== $pending_id) {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'Payment reference does not match this registration.',
        ];
    }

    if ($parsed_reference['event_id'] > 0 && $parsed_reference['event_id'] !== $event_id) {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'Payment reference does not match this event.',
        ];
    }

    if (!rm_payment_amount_matches($hitpay_data, $expected_amount)) {
        error_log(
            '[rm_payment] Amount mismatch for pending ' . $pending_id
            . ' expected ' . $expected_amount
        );

        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'Payment amount does not match the registration fee.',
        ];
    }

    $api_payment_id = isset($hitpay_data['id']) ? sanitize_text_field((string) $hitpay_data['id']) : $payment_request_id;
    $payment_option = rm_payment_extract_option($hitpay_data);

    return rm_finalize_paid_registration($pending_id, $api_payment_id, $payment_option);
}

function rm_payment_is_allowed_checkout_host(string $host): bool
{
    $host = strtolower(trim($host));

    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1') {
        return false;
    }

    return $host === 'hit-pay.com' || str_ends_with($host, '.hit-pay.com');
}

function rm_payment_is_allowed_checkout_url(string $url): bool
{
    $url = esc_url_raw($url);
    if ($url === '') {
        return false;
    }

    $host = wp_parse_url($url, PHP_URL_HOST);

    return is_string($host) && rm_payment_is_allowed_checkout_host($host);
}

function rm_payment_redirect_to_checkout(string $url): bool
{
    if (!rm_payment_is_allowed_checkout_url($url)) {
        error_log('[rm_payment] Blocked redirect to non-HitPay URL: ' . $url);

        return false;
    }

    wp_redirect(esc_url_raw($url), 302, 'Registration Manager');
    exit;
}

/**
 * @param array<string, mixed> $event
 * @param array<string, string> $registrant
 * @return array{ok: bool, url: string, error: string}
 */
function rm_payment_initiate_checkout(
    int $pending_id,
    array $event,
    array $registrant,
    string $event_code
): array {
    if ($pending_id < 1) {
        return [
            'ok'    => false,
            'url'   => '',
            'error' => 'Pending registration could not be found.',
        ];
    }

    $pending_row = rm_payment_load_pending($pending_id);
    if ($pending_row !== null && isset($pending_row['amount']) && is_numeric($pending_row['amount'])) {
        $amount = (float) $pending_row['amount'];
    } else {
        $amount = rm_event_registration_price($event);
    }

    if ($amount <= 0) {
        return [
            'ok'    => false,
            'url'   => '',
            'error' => 'This event does not require payment.',
        ];
    }

    if (
        is_array($pending_row)
        && !empty($pending_row['_v2'])
        && is_array($pending_row['_primary'])
    ) {
        $registrant = rm_v2_registrant_for_payment($pending_row['_primary']);
    }

    $created = rm_payment_create_request($pending_id, $event, $registrant, $amount, $event_code);
    if (!$created['ok']) {
        return [
            'ok'    => false,
            'url'   => '',
            'error' => $created['error'],
        ];
    }

    if (!rm_payment_store_request_id($pending_id, $created['id'])) {
        error_log('[rm_payment] Failed to store payment request id for pending ' . $pending_id);
    }

    return [
        'ok'    => true,
        'url'   => $created['url'],
        'error' => '',
    ];
}

/**
 * @return array<string, mixed>
 */
function rm_payment_parse_webhook_payload(string $raw_payload = ''): array
{
    $data = [];

    if ($raw_payload !== '') {
        $decoded = json_decode($raw_payload, true);
        if (is_array($decoded)) {
            $data = $decoded;
        } else {
            $parsed = [];
            parse_str($raw_payload, $parsed);
            if (is_array($parsed) && $parsed !== []) {
                $data = wp_unslash($parsed);
            }
        }
    }

    if ($data === [] && !empty($_POST) && is_array($_POST)) {
        $data = wp_unslash($_POST);
    }

    return is_array($data) ? $data : [];
}

/**
 * @param array<string, mixed> $payload
 * @param array<string, mixed> $context
 * @return array<string, mixed>
 */
function rm_payment_summarize_webhook_transaction(array $payload, array $context = []): array
{
    $payment_request_id = rm_payment_extract_webhook_payment_request_id($payload);
    $parsed_reference = rm_payment_parse_reference((string) ($payload['reference_number'] ?? ''));
    $payment = is_array($payload['payments'][0] ?? null) ? $payload['payments'][0] : [];

    $transaction = [
        'payment_request_id' => $payment_request_id,
        'reference_number'   => sanitize_text_field((string) ($payload['reference_number'] ?? '')),
        'status'             => sanitize_text_field((string) ($payload['status'] ?? '')),
        'amount'             => isset($payload['amount']) ? (string) $payload['amount'] : '',
        'currency'           => sanitize_text_field((string) ($payload['currency'] ?? '')),
        'name'               => sanitize_text_field((string) ($payload['name'] ?? '')),
        'email'              => sanitize_email((string) ($payload['email'] ?? '')),
        'phone'              => sanitize_text_field((string) ($payload['phone'] ?? '')),
        'purpose'            => sanitize_text_field((string) ($payload['purpose'] ?? '')),
        'payment_id'         => sanitize_text_field((string) ($payment['id'] ?? '')),
        'payment_status'     => sanitize_text_field((string) ($payment['status'] ?? '')),
        'payment_method'     => !empty($payment['payment_type'])
            ? rm_payment_normalize_option((string) $payment['payment_type'])
            : 'N/A',
        'payment_amount'     => isset($payment['amount']) ? (string) $payment['amount'] : '',
        'fees'               => isset($payment['fees']) ? (string) $payment['fees'] : '',
        'created_at'         => sanitize_text_field((string) ($payload['created_at'] ?? '')),
        'updated_at'         => sanitize_text_field((string) ($payload['updated_at'] ?? '')),
        'pending_id'         => isset($context['pending_id'])
            ? absint($context['pending_id'])
            : $parsed_reference['pending_id'],
        'event_id'           => isset($context['event_id'])
            ? absint($context['event_id'])
            : $parsed_reference['event_id'],
        'order_number'       => sanitize_text_field((string) ($context['order_number'] ?? '')),
        'finalized'          => !empty($context['finalized']),
    ];

    if (!empty($context['error']) && is_string($context['error'])) {
        $transaction['error'] = sanitize_text_field($context['error']);
    }

    return $transaction;
}

/**
 * @param array<string, mixed> $payload
 * @param array<string, mixed> $transaction
 * @return array{handled: bool, message: string, transaction: array<string, mixed>}
 */
function rm_payment_build_webhook_response(
    array $payload,
    bool $handled,
    string $message,
    array $transaction = []
): array {
    return [
        'handled'     => $handled,
        'message'     => $message,
        'transaction' => $transaction !== []
            ? $transaction
            : rm_payment_summarize_webhook_transaction($payload),
    ];
}

/**
 * @param array<string, mixed> $payload
 * @return array{handled: bool, message: string, transaction: array<string, mixed>}
 */
function rm_payment_process_webhook_payload(array $payload): array
{
    $payment_request_id = rm_payment_extract_webhook_payment_request_id($payload);

    if ($payment_request_id === '' || !rm_payment_is_webhook_payment_completed($payload)) {
        $event_headers = rm_payment_get_webhook_event_headers();
        error_log(
            '[rm_payment] Webhook ignored.'
            . ' event_object=' . ($event_headers['event_object'] !== '' ? $event_headers['event_object'] : 'unknown')
            . ' event_type=' . ($event_headers['event_type'] !== '' ? $event_headers['event_type'] : 'unknown')
            . ' status=' . sanitize_text_field((string) ($payload['status'] ?? ''))
            . ' payment_request_id=' . ($payment_request_id !== '' ? 'yes' : 'no')
        );

        return rm_payment_build_webhook_response(
            $payload,
            false,
            'Webhook received (non-completed state).'
        );
    }

    $parsed_reference = rm_payment_parse_reference((string) ($payload['reference_number'] ?? ''));
    $pending_id = $parsed_reference['pending_id'];
    $event_id = $parsed_reference['event_id'];

    $lookup = rm_payment_get_request($payment_request_id, $event_id);
    if (!$lookup['ok'] || !is_array($lookup['data'])) {
        error_log('[rm_payment] Webhook could not verify payment: ' . $payment_request_id);

        return rm_payment_build_webhook_response(
            $payload,
            false,
            'Webhook received but payment could not be verified.',
            rm_payment_summarize_webhook_transaction($payload, [
                'pending_id' => $pending_id,
                'event_id'   => $event_id,
                'error'      => $lookup['error'] !== '' ? $lookup['error'] : 'Could not verify payment.',
            ])
        );
    }

    if ($pending_id < 1 || $event_id < 1) {
        $lookup_reference = rm_payment_parse_reference(
            (string) ($lookup['data']['reference_number'] ?? '')
        );
        if ($pending_id < 1) {
            $pending_id = $lookup_reference['pending_id'];
        }
        if ($event_id < 1) {
            $event_id = $lookup_reference['event_id'];
        }
    }

    if ($pending_id < 1) {
        return rm_payment_build_webhook_response(
            $payload,
            false,
            'Webhook received but pending id missing.',
            rm_payment_summarize_webhook_transaction($payload, [
                'event_id' => $event_id,
                'error'    => 'Pending registration id could not be resolved from reference.',
            ])
        );
    }

    $result = rm_payment_handle_completed($pending_id, $payment_request_id);
    if (!$result['ok']) {
        error_log(
            '[rm_payment] Webhook finalize failed for pending ' . $pending_id . ': ' . $result['error']
        );

        return rm_payment_build_webhook_response(
            $payload,
            false,
            'Webhook received but registration was not finalized.',
            rm_payment_summarize_webhook_transaction($payload, [
                'pending_id'   => $pending_id,
                'event_id'     => $event_id,
                'order_number' => $result['order_number'],
                'error'        => $result['error'],
            ])
        );
    }

    return rm_payment_build_webhook_response(
        $payload,
        true,
        'Payment processed.',
        rm_payment_summarize_webhook_transaction($payload, [
            'pending_id'   => $pending_id,
            'event_id'     => $event_id,
            'order_number' => $result['order_number'],
            'finalized'    => true,
        ])
    );
}
