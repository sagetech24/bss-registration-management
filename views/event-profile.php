<?php
$selected_event = is_array($selected_event ?? null) ? $selected_event : null;
$selected_event_code = (string) ($selected_event_code ?? '');
$selected_event_id = (int) ($selected_event_id ?? 0);
$uses_v2 = !empty($uses_v2);
$summary = is_array($summary ?? null) ? $summary : [
    'total'         => 0,
    'paid_count'    => 0,
    'pending_count' => 0,
    'total_revenue' => 0.0,
];
$active_package_count = (int) ($active_package_count ?? 0);
$event_card = is_array($event_card ?? null) ? $event_card : [];
$profile_flash = is_array($profile_flash ?? null) ? $profile_flash : null;
$registration_href = (string) ($registration_href ?? '');
$event_landing_href = (string) ($event_landing_href ?? '');
$event_price_display = (string) ($event_price_display ?? 'FREE');
$profile_error = (string) ($profile_error ?? '');
$profile_tab = (string) ($profile_tab ?? rm_get_event_profile_tab());
$profile_tabs = is_array($profile_tabs ?? null) ? $profile_tabs : rm_event_profile_tabs();

$event_title = $selected_event !== null
    ? (string) ($selected_event['title'] ?? 'Untitled event')
    : 'Event';
$code_label = $selected_event_code !== '' ? rtrim($selected_event_code, '_') : '';
$thumb_url = (string) ($event_card['thumb_url'] ?? '');
$date_block = (string) ($event_card['date_block'] ?? '');
$venue_show = (string) ($event_card['venue_show'] ?? '');
$event_categories = [];
if (!empty($event_card['categories']) && is_array($event_card['categories'])) {
    $event_categories = $event_card['categories'];
} elseif (is_array($selected_event) && !empty($selected_event['categories']) && is_array($selected_event['categories'])) {
    $event_categories = $selected_event['categories'];
}
?>

<?php if ($selected_event === null) : ?>
    <div class="p-6 bg-white border border-slate-200 rounded-xl text-slate-600">
        <?php echo esc_html($profile_error !== '' ? $profile_error : 'Event could not be loaded.'); ?>
        <div class="mt-4">
            <a href="<?php echo esc_url($page_url); ?>" class="text-sm font-medium text-indigo-700 hover:text-indigo-900">Back to events</a>
        </div>
    </div>
<?php else : ?>

<section class="space-y-6">
    <?php if (is_array($profile_flash) && ($profile_flash['message'] ?? '') !== '') : ?>
        <?php $flash_ok = ($profile_flash['type'] ?? '') === 'success'; ?>
        <div class="rounded-lg border px-4 py-3 text-sm <?php echo $flash_ok ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'; ?>">
            <?php echo esc_html((string) $profile_flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="flex flex-col md:flex-row">
            <?php if ($thumb_url !== '') : ?>
                <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($event_title); ?>" class="md:w-96 md:h-64 object-cover shrink-0" />
            <?php else : ?>
                <div class="md:w-96 md:h-64 bg-slate-100 flex items-center justify-center text-slate-400 text-sm shrink-0">No image</div>
            <?php endif; ?>

            <div class="flex-1 p-5 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div class="min-w-0">
                        <!-- <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Event dashboard</p> -->
                        <h1 class="mt-1 text-2xl font-semibold text-slate-900"><?php echo $event_title; ?></h1>
                        <?php if ($code_label !== '') : ?>
                            <span class="my-1.5 inline-flex items-center rounded-full px-2.5 py-1 font-mono text-xs font-bold bg-indigo-50 text-indigo-700">
                                Code: <?php echo esc_html($code_label); ?>
                            </span>
                        <?php endif; ?>
                        <div class="my-1 text-sm text-slate-700">
                            <strong>Price:</strong> &nbsp;<?php echo esc_html($event_price_display); ?>
                        </div>
                        <?php if ($date_block !== '') : ?>
                            <p class="mt-2 text-sm text-slate-700">
                                <strong>Date:</strong> <?php echo $date_block; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($venue_show !== '') : ?>
                            <p class="mt-1 text-sm text-slate-500"><strong>Venue:</strong> <?php echo esc_html($venue_show); ?></p>
                        <?php endif; ?>
                        <?php if ($event_categories !== []) : ?>
                            <div class="mt-4 flex flex-wrap gap-1.5">
                                <?php foreach ($event_categories as $category_name) : ?>
                                    <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                        <?php echo esc_html((string) $category_name); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex flex-wrap gap-2 shrink-0">
                        <?php if ($event_landing_href !== '' && $event_landing_href !== home_url('/')) : ?>
                            <a href="<?php echo esc_url($event_landing_href); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Go to event page
                            </a>
                        <?php endif; ?>
                        <?php if ($registration_href !== '') : ?>
                            <a href="<?php echo esc_url($registration_href); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-lg bg-indigo-700 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-800">
                                Go to registration form
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($profile_tab !== 'registrants') : ?>
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
        <?php
        $stat_tiles = [
            ['label' => 'Total', 'value' => (string) (int) ($summary['total'] ?? 0)],
            ['label' => 'Paid', 'value' => (string) (int) ($summary['paid_count'] ?? 0)],
            ['label' => 'Pending', 'value' => (string) (int) ($summary['pending_count'] ?? 0)],
            ['label' => 'Revenue', 'value' => rm_format_currency((float) ($summary['total_revenue'] ?? 0), (string) ($event_currency ?? 'SGD'), false)],
            ['label' => 'Active packages', 'value' => (string) $active_package_count],
        ];
        foreach ($stat_tiles as $tile) :
            ?>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400"><?php echo esc_html($tile['label']); ?></p>
                <p class="mt-2 text-xl font-semibold text-slate-900"><?php echo esc_html($tile['value']); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="border-b border-slate-200">
        <nav class="-mb-px flex flex-wrap gap-1" aria-label="Event dashboard tabs">
            <?php foreach ($profile_tabs as $tab_key => $tab_label) : ?>
                <?php
                $is_active = $profile_tab === $tab_key;
                $tab_href = rm_event_profile_url($selected_event_code, $selected_event_id, ['tab' => $tab_key]);
                $tab_classes = $is_active
                    ? 'border-indigo-600 text-indigo-700 bg-indigo-50'
                    : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300';
                ?>
                <a
                    href="<?php echo esc_url($tab_href); ?>"
                    class="inline-flex items-center rounded-t-lg border-b-2 px-4 py-2.5 text-sm font-medium transition <?php echo esc_attr($tab_classes); ?>"
                    <?php echo $is_active ? 'aria-current="page"' : ''; ?>
                >
                    <?php echo esc_html($tab_label); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <div>
        <?php if ($profile_tab === 'packages') : ?>
            <?php include __DIR__ . '/partials/event-profile-packages.php'; ?>
        <?php elseif ($profile_tab === 'promo-codes') : ?>
            <?php include __DIR__ . '/partials/event-profile-promo-codes.php'; ?>
        <?php elseif ($profile_tab === 'registrants') : ?>
            <?php include __DIR__ . '/partials/event-profile-registrants.php'; ?>
        <?php elseif ($profile_tab === 'custom-form') : ?>
            <?php include __DIR__ . '/partials/event-profile-custom-form.php'; ?>
        <?php else : ?>
            <?php include __DIR__ . '/partials/event-profile-settings.php'; ?>
        <?php endif; ?>
    </div>
</section>

<style>[x-cloak]{display:none!important;}</style>

<?php endif; ?>
