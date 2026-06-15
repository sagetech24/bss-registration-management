<?php

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    status_header(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Method not allowed';
    exit;
}

$raw_payload = file_get_contents('php://input');
$raw_payload = is_string($raw_payload) ? $raw_payload : '';
$signature = rm_payment_get_webhook_signature();
$payload = rm_payment_parse_webhook_payload($raw_payload);

if (!rm_payment_verify_webhook_request($raw_payload, $signature, $payload)) {
    error_log(
        '[rm_payment] Webhook signature verification failed.'
        . ' has_hmac=' . (isset($payload['hmac']) && (string) $payload['hmac'] !== '' ? 'yes' : 'no')
        . ' has_header=' . ($signature !== '' ? 'yes' : 'no')
        . ' raw_len=' . strlen($raw_payload)
        . ' api_salts=' . count(rm_payment_collect_salts('api'))
        . ' webhook_salts=' . count(rm_payment_collect_salts('webhook'))
    );
    status_header(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid signature';
    exit;
}

$result = rm_payment_process_webhook_payload($payload);

status_header(200);
header('Content-Type: text/plain; charset=utf-8');
echo $result['message'];
exit;
