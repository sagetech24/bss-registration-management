<?php

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    status_header(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Method not allowed';
    exit;
}

$payload = rm_payment_parse_webhook_payload();
$result = rm_payment_process_webhook_payload($payload);

status_header(200);
header('Content-Type: text/plain; charset=utf-8');
echo $result['message'];
exit;
