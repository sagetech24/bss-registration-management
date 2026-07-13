<?php
$selected_event = $selected_event ?? null;
$selected_event_code = $selected_event_code ?? '';
$selected_event_id = (int) ($selected_event_id ?? 0);

// Prefer the Event Dashboard registrants tab.
if (is_array($selected_event) && ($selected_event_code !== '' || $selected_event_id > 0)) {
    $redirect_code = $selected_event_code !== ''
        ? $selected_event_code
        : trim((string) ($selected_event['programCode'] ?? ''));
    $redirect_args = ['tab' => 'registrants'];
    $package_filter = rm_get_package_filter();
    if ($package_filter !== 'all') {
        $redirect_args['package_filter'] = $package_filter;
    }
    wp_safe_redirect(rm_event_profile_url($redirect_code, $selected_event_id, $redirect_args));
    exit;
}

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
$event_title = is_array($selected_event) ? ($selected_event['title'] ?? 'Selected Event') : 'Selected Event';
$event_is_free = is_array($selected_event) && rm_event_is_free($selected_event);
$registrants_config = [
    'apiUrl'               => esc_url_raw($registrants_api_url),
    'paymentDetailsUrl'    => esc_url_raw($payment_details_api_url),
    'profileUrl'           => esc_url_raw($profile_api_url),
    'eventId'              => $selected_event_id,
    'eventIsFree'          => $event_is_free,
    'initialPackageFilter' => rm_get_package_filter(),
];
?>

<section class="space-y-6">
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
        <h2 class="text-lg font-semibold text-slate-900"><?php echo esc_html((string) $event_title); ?></h2>
        <p class="mt-1 text-sm text-slate-500">Select an event from the Events list to view registrants.</p>
        <a href="<?php echo esc_url($page_url ?? rm_page_url()); ?>" class="mt-3 inline-flex text-sm font-medium text-indigo-700 hover:text-indigo-900">Back to events</a>
    </div>
</section>
