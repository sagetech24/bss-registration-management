<?php

require_once __DIR__ . '/bootstrap.php';

$context = rm_build_context();

if (!empty($context['event_not_found'])) {
    status_header(404);
    nocache_headers();
    rm_render('404', $context);

    return;
}

if ($context['view_action'] === 'register') {
    $view = 'register';
} elseif ($context['view_action'] === 'get-event') {
    if (!empty($context['event_not_found'])) {
        status_header(404);
        nocache_headers();
        $view = '404';
    } else {
        $view = 'registrants';
    }
} else {
    $view = 'events';
}

rm_render($view, $context);
