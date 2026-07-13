<?php
/**
 * Registrants tab — reuses the shared registrants panel.
 */
$selected_event_id = (int) ($selected_event_id ?? 0);
$selected_event_code = (string) ($selected_event_code ?? '');
$selected_event = is_array($selected_event ?? null) ? $selected_event : null;
$event_is_free = !empty($event_is_free) || (is_array($selected_event) && rm_event_is_free($selected_event));

$registrants_api_url = $registrants_api_url ?? add_query_arg(
    [
        'action'     => 'event-registrants-data',
        'event_id'   => $selected_event_id,
        'event_code' => $selected_event_code,
    ],
    $page_url ?? rm_page_url()
);
$payment_details_api_url = $payment_details_api_url ?? add_query_arg(
    [
        'action'   => 'registrant-payment-details',
        'event_id' => $selected_event_id,
    ],
    $page_url ?? rm_page_url()
);
$profile_api_url = $profile_api_url ?? add_query_arg(
    [
        'action'   => 'registrant-profile',
        'event_id' => $selected_event_id,
    ],
    $page_url ?? rm_page_url()
);

$registrants_config = [
    'apiUrl'               => esc_url_raw($registrants_api_url),
    'paymentDetailsUrl'    => esc_url_raw($payment_details_api_url),
    'profileUrl'           => esc_url_raw($profile_api_url),
    'eventId'              => $selected_event_id,
    'eventIsFree'          => $event_is_free,
    'initialPackageFilter' => rm_get_package_filter(),
];

include __DIR__ . '/registrants-panel.php';
?>
