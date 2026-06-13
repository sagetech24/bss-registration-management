<?php

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
