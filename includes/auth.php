<?php

function rm_export_api_token(): string
{
    if (defined('RM_EXPORT_API_TOKEN')) {
        $token = trim((string) RM_EXPORT_API_TOKEN);
        if ($token !== '') {
            return $token;
        }
    }

    if (defined('BSS_API_BEARER_TOKEN')) {
        return trim((string) BSS_API_BEARER_TOKEN);
    }

    return '';
}

/**
 * Read Authorization / HTTP_AUTHORIZATION header value.
 */
function rm_get_authorization_header(): string
{
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim((string) wp_unslash($_SERVER['HTTP_AUTHORIZATION']));
    }

    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return trim((string) wp_unslash($_SERVER['REDIRECT_HTTP_AUTHORIZATION']));
    }

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strtolower((string) $name) === 'authorization') {
                    return trim((string) $value);
                }
            }
        }
    }

    return '';
}

/**
 * Require a valid Bearer token for the registrants export API.
 * Responds with 401 JSON and exits on failure.
 */
function rm_require_export_api_bearer(): void
{
    $expected = rm_export_api_token();
    if ($expected === '') {
        status_header(401);
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode([
            'ok'    => false,
            'error' => 'Unauthorized.',
        ]);
        exit;
    }

    $header = rm_get_authorization_header();
    $provided = '';
    if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $matches) === 1) {
        $provided = (string) $matches[1];
    }

    if ($provided === '' || !hash_equals($expected, $provided)) {
        status_header(401);
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode([
            'ok'    => false,
            'error' => 'Unauthorized.',
        ]);
        exit;
    }
}

function rm_require_login(): void
{
    if (!is_user_logged_in()) {
        wp_redirect(home_url('/login'));
        exit;
    }
}

function rm_get_welcome_name(): string
{
    $user_id = get_current_user_id();
    if (!$user_id) {
        return 'Guest';
    }

    $user = get_userdata($user_id);
    if (!$user) {
        return 'Guest';
    }

    $first_last_name = trim(
        sprintf(
            '%s %s',
            (string) get_user_meta($user_id, 'first_name', true),
            (string) get_user_meta($user_id, 'last_name', true)
        )
    );

    return $user->display_name
        ?: ($first_last_name !== '' ? $first_last_name : '')
        ?: $user->nickname
        ?: $user->user_nicename
        ?: $user->user_login
        ?: $user->user_email
        ?: 'Guest';
}
