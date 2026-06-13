<?php

require_once __DIR__ . '/bootstrap.php';

$context = rm_build_context();

if ($context['view_action'] === 'register') {
    $view = 'register';
} elseif ($context['view_action'] === 'get-event') {
    $view = 'registrants';
} else {
    $view = 'events';
}

rm_render($view, $context);
