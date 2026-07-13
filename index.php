<?php

require_once __DIR__ . '/bootstrap.php';

$view_action = rm_get_view_action();

if ($view_action === 'payment-transactions-data') {
    rm_require_login();
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    echo wp_json_encode(rm_build_payment_transactions_data());
    exit;
}

if ($view_action === 'event-registrants-data') {
    rm_require_login();
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    echo wp_json_encode(rm_build_event_registrants_data());
    exit;
}

if ($view_action === 'registrant-payment-details') {
    rm_require_login();
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    echo wp_json_encode(rm_build_registrant_payment_details());
    exit;
}

if ($view_action === 'registrant-profile') {
    rm_require_login();
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    echo wp_json_encode(rm_build_registrant_profile());
    exit;
}

$context = rm_build_context();

if (!empty($context['event_not_found'])) {
    status_header(404);
    nocache_headers();
    rm_render('404', $context);

    return;
}

if ($context['view_action'] === 'register') {
    $view = 'register';
} elseif ($context['view_action'] === 'get-event-registrants') {
    if (!empty($context['event_not_found'])) {
        status_header(404);
        nocache_headers();
        $view = '404';
    } else {
        $view = 'registrants';
    }
} elseif ($context['view_action'] === 'get-event-profile') {
    if (!empty($context['event_not_found'])) {
        status_header(404);
        nocache_headers();
        $view = '404';
    } else {
        $view = 'event-profile';
    }
} elseif ($context['view_action'] === 'payment-transactions') {
    $view = 'payment-transactions';
} else {
    $view = 'events';
}

rm_render($view, $context);
