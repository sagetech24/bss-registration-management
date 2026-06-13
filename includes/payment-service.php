<?php

/**
 * HitPay payment integration for registration-manager.
 * Default environment is sandbox (test). Live switch: apply_filters('rm_payment_environment', 'test', $event_id).
 */

/**
 * @return 'test'|'live'
 */
function rm_payment_environment(int $event_id = 0): string
{
    $environment = apply_filters('rm_payment_environment', 'test', $event_id);

    return $environment === 'live' ? 'live' : 'test';
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

function rm_payment_webhooks_enabled(): bool
{
    $home = untrailingslashit(home_url());
    $production = 'https://biblesociety.sg';

    if ($home === $production) {
        return true;
    }

    $home_host = wp_parse_url($home, PHP_URL_HOST);
    $production_host = wp_parse_url($production, PHP_URL_HOST);

    return is_string($home_host)
        && is_string($production_host)
        && strtolower($home_host) === strtolower($production_host)
        && wp_parse_url($home, PHP_URL_SCHEME) === 'https';
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

function rm_payment_reference_for_pending(int $pending_id): string
{
    return 'RM-' . max(0, $pending_id);
}

function rm_payment_parse_pending_id(string $reference): int
{
    $reference = trim($reference);
    if ($reference === '') {
        return 0;
    }

    if (preg_match('/^RM-(\d+)$/', $reference, $matches) === 1) {
        return absint($matches[1]);
    }

    if (ctype_digit($reference)) {
        return absint($reference);
    }

    return 0;
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
    $reference = rm_payment_reference_for_pending($pending_id);

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

    $webhook_url = rm_payment_webhook_url();
    if (is_string($webhook_url) && $webhook_url !== '') {
        $payload['webhook'] = $webhook_url;
    }

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

    $resolved_pending_id = rm_payment_parse_pending_id((string) ($hitpay_data['reference_number'] ?? ''));
    if ($resolved_pending_id > 0 && $resolved_pending_id !== $pending_id) {
        return [
            'ok'           => false,
            'order_number' => '',
            'error'        => 'Payment reference does not match this registration.',
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

    $amount = rm_event_registration_price($event);
    if ($amount <= 0) {
        return [
            'ok'    => false,
            'url'   => '',
            'error' => 'This event does not require payment.',
        ];
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
function rm_payment_parse_webhook_payload(): array
{
    $raw = file_get_contents('php://input');
    $data = [];

    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    if ($data === [] && !empty($_POST) && is_array($_POST)) {
        $data = wp_unslash($_POST);
    }

    return is_array($data) ? $data : [];
}

/**
 * @param array<string, mixed> $payload
 * @return array{handled: bool, message: string}
 */
function rm_payment_process_webhook_payload(array $payload): array
{
    $status = isset($payload['status']) ? sanitize_text_field((string) $payload['status']) : '';
    $payment_request_id = isset($payload['id']) ? sanitize_text_field((string) $payload['id']) : '';

    if ($payment_request_id === '' && isset($payload['payment_request_id'])) {
        $payment_request_id = sanitize_text_field((string) $payload['payment_request_id']);
    }

    if ($status !== 'completed' || $payment_request_id === '') {
        return [
            'handled' => false,
            'message' => 'Webhook received (non-completed state).',
        ];
    }

    $lookup = rm_payment_get_request($payment_request_id);
    if (!$lookup['ok'] || !is_array($lookup['data'])) {
        error_log('[rm_payment] Webhook could not verify payment: ' . $payment_request_id);

        return [
            'handled' => false,
            'message' => 'Webhook received but payment could not be verified.',
        ];
    }

    $pending_id = rm_payment_parse_pending_id((string) ($lookup['data']['reference_number'] ?? ''));
    if ($pending_id < 1) {
        return [
            'handled' => false,
            'message' => 'Webhook received but pending id missing.',
        ];
    }

    $result = rm_payment_handle_completed($pending_id, $payment_request_id);
    if (!$result['ok']) {
        error_log(
            '[rm_payment] Webhook finalize failed for pending ' . $pending_id . ': ' . $result['error']
        );

        return [
            'handled' => false,
            'message' => 'Webhook received but registration was not finalized.',
        ];
    }

    return [
        'handled' => true,
        'message' => 'Payment processed.',
    ];
}
