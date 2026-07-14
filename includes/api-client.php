<?php

function rm_api_bearer_token(): string
{
    return defined('BSS_API_BEARER_TOKEN') ? (string) BSS_API_BEARER_TOKEN : '';
}

function rm_api_base_url(): string
{
    $use_local = defined('RM_USE_LOCAL_ENDPOINT') && RM_USE_LOCAL_ENDPOINT;

    if ($use_local && defined('RM_ENDPOINT_LOCAL_URL')) {
        $base = (string) RM_ENDPOINT_LOCAL_URL;
    } elseif (defined('RM_ENDPOINT_LIVE_URL')) {
        $base = (string) RM_ENDPOINT_LIVE_URL;
    } else {
        $base = 'https://biblesociety.sg/wp-json/BSS/v2';
    }

    return untrailingslashit($base) . '/query';
}

/**
 * @return array{ok: bool, data: mixed, error: string}
 */
function rm_api_get(string $action, array $params = []): array
{
    $token = rm_api_bearer_token();
    if ($token === '') {
        return [
            'ok'    => false,
            'data'  => null,
            'error' => 'API token is not configured.',
        ];
    }

    $query = array_merge(['action' => $action], $params);
    $url = add_query_arg($query, rm_api_base_url());

    $response = wp_remote_get($url, [
        'timeout' => 15,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        return [
            'ok'    => false,
            'data'  => null,
            'error' => $response->get_error_message(),
        ];
    }

    $status = wp_remote_retrieve_response_code($response);
    $body   = wp_remote_retrieve_body($response);
    $json   = json_decode($body, true);

    if ($status === 200 && is_array($json) && array_key_exists('data', $json)) {
        return [
            'ok'    => true,
            'data'  => $json['data'],
            'error' => '',
        ];
    }

    return [
        'ok'    => false,
        'data'  => null,
        'error' => 'API request failed. HTTP ' . (string) $status,
    ];
}

/**
 * @return array{events: array, error: string}
 */
function rm_fetch_registration_events(): array
{
    $result = rm_api_get('all-events', ['scope' => 'registration-management']);

    if (!$result['ok']) {
        return [
            'events' => [],
            'error'  => $result['error'],
        ];
    }

    $events = is_array($result['data']) ? $result['data'] : [];

    return [
        'events' => $events,
        'error'  => '',
    ];
}

/**
 * @return array{event: array|null, error: string}
 */
function rm_fetch_event(string $event_code): array
{
    if ($event_code === '') {
        return [
            'event' => null,
            'error' => '',
        ];
    }

    $result = rm_api_get('get-event', ['event_code' => $event_code]);

    if ($result['ok']) {
        $event = is_array($result['data']) ? $result['data'] : null;
        if (is_array($event) && $event !== []) {
            return [
                'event' => $event,
                'error' => '',
            ];
        }
    }

    // Fallback: WordPress CPT events (long-term replacement for bss_events).
    if (function_exists('rm_get_cpt_event_by_code')) {
        $cpt_event = rm_get_cpt_event_by_code($event_code);
        if (is_array($cpt_event) && $cpt_event !== []) {
            return [
                'event' => $cpt_event,
                'error' => '',
            ];
        }
    }

    if (!$result['ok']) {
        return [
            'event' => null,
            'error' => $result['error'],
        ];
    }

    return [
        'event' => null,
        'error' => '',
    ];
}

/**
 * @return array{registrants: array, error: string}
 */
function rm_fetch_registrants(string $event_code): array
{
    if ($event_code === '') {
        return [
            'registrants' => [],
            'error'       => '',
        ];
    }

    $result = rm_api_get('get-registrant', ['event_code' => $event_code]);

    if (!$result['ok']) {
        return [
            'registrants' => [],
            'error'       => $result['error'],
        ];
    }

    $registrants = is_array($result['data']) ? $result['data'] : [];

    return [
        'registrants' => $registrants,
        'error'       => '',
    ];
}

/**
 * @return array{registrants: array, error: string}
 */
function rm_fetch_pending_registrants(string $event_code): array
{
    if ($event_code === '') {
        return [
            'registrants' => [],
            'error'       => '',
        ];
    }

    $result = rm_api_get('get-pending-registrants', ['event_code' => $event_code]);

    if (!$result['ok']) {
        return [
            'registrants' => [],
            'error'       => $result['error'],
        ];
    }

    $registrants = is_array($result['data']) ? $result['data'] : [];

    return [
        'registrants' => $registrants,
        'error'       => '',
    ];
}
