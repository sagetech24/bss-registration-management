<?php
/**
 * Add-ons / guests tab — all addon registrant rows for this event.
 */
$addon_rows = is_array($addon_rows ?? null) ? $addon_rows : [];
$addon_columns = is_array($addon_columns ?? null) ? $addon_columns : [];
$addon_label_singular = (string) ($addon_label_singular ?? 'Guest');
$addon_label_plural = (string) ($addon_label_plural ?? 'Guests');
$addon_total = (int) ($addon_total ?? 0);
$addon_error = (string) ($addon_error ?? '');
$addon_search = (string) ($addon_search ?? '');
$addon_pagination = is_array($addon_pagination ?? null) ? $addon_pagination : [
    'current_page' => 1,
    'total_pages'  => 1,
    'per_page'     => 12,
    'total'        => 0,
    'has_prev'     => false,
    'has_next'     => false,
    'from'         => 0,
    'to'           => 0,
];
$event_currency = (string) ($event_currency ?? 'SGD');
$selected_event_code = (string) ($selected_event_code ?? '');
$selected_event_id = (int) ($selected_event_id ?? 0);
$selected_event_source = (string) ($selected_event_source ?? '');
$page_url = (string) ($page_url ?? rm_page_url());

$addon_page_base_args = rm_args_with_event_source([
    'tab' => 'addons',
], $selected_event_source);
if ($addon_search !== '') {
    $addon_page_base_args['addon_search'] = $addon_search;
}

$addon_prev_href = !empty($addon_pagination['has_prev'])
    ? rm_event_profile_url($selected_event_code, $selected_event_id, array_merge($addon_page_base_args, [
        'addon_page' => max(1, (int) $addon_pagination['current_page'] - 1),
    ]))
    : '';
$addon_next_href = !empty($addon_pagination['has_next'])
    ? rm_event_profile_url($selected_event_code, $selected_event_id, array_merge($addon_page_base_args, [
        'addon_page' => (int) $addon_pagination['current_page'] + 1,
    ]))
    : '';

$filtered_total = (int) ($addon_pagination['total'] ?? 0);
$has_search = $addon_search !== '';
?>

<div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-200 flex flex-col gap-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900"><?php echo esc_html($addon_label_plural); ?></h2>
                <p class="mt-1 text-sm text-slate-500">
                    <?php echo esc_html($addon_label_singular); ?> records submitted with registrations for this event.
                </p>
            </div>
            <div class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm text-slate-700">
                <span class="font-semibold text-slate-900"><?php echo esc_html((string) $addon_total); ?></span>
                <span class="ml-1.5"><?php echo esc_html($addon_total === 1 ? $addon_label_singular : $addon_label_plural); ?></span>
            </div>
        </div>

        <form method="get" action="<?php echo esc_url($page_url); ?>" class="flex flex-col sm:flex-row gap-2 sm:items-end">
            <input type="hidden" name="action" value="get-event-profile" />
            <input type="hidden" name="event_code" value="<?php echo esc_attr($selected_event_code); ?>" />
            <input type="hidden" name="event_id" value="<?php echo esc_attr((string) $selected_event_id); ?>" />
            <input type="hidden" name="tab" value="addons" />
            <?php if ($selected_event_source !== '') : ?>
                <input type="hidden" name="event_source" value="<?php echo esc_attr($selected_event_source); ?>" />
            <?php endif; ?>

            <div class="flex-1 min-w-0">
                <label for="addon_search" class="block text-xs font-medium text-slate-600 mb-1">Search</label>
                <input
                    id="addon_search"
                    type="search"
                    name="addon_search"
                    value="<?php echo esc_attr($addon_search); ?>"
                    placeholder="Search name, email, order number, or custom fields"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                />
            </div>
            <div class="flex gap-2 shrink-0">
                <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-700 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-800">
                    Search
                </button>
                <?php if ($has_search) : ?>
                    <a
                        href="<?php echo esc_url(rm_event_profile_url($selected_event_code, $selected_event_id, rm_args_with_event_source(['tab' => 'addons'], $selected_event_source))); ?>"
                        class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($addon_error !== '') : ?>
        <div class="m-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <?php echo esc_html($addon_error); ?>
        </div>
    <?php elseif ($addon_total === 0) : ?>
        <div class="p-10 text-center">
            <h3 class="text-lg font-semibold text-slate-700">No <?php echo esc_html(strtolower($addon_label_plural)); ?> yet</h3>
            <p class="mt-2 text-sm text-slate-500 max-w-md mx-auto">
                When registrants add <?php echo esc_html(strtolower($addon_label_plural)); ?> during registration, their details will appear here.
            </p>
        </div>
    <?php elseif ($filtered_total === 0) : ?>
        <div class="p-10 text-center">
            <h3 class="text-lg font-semibold text-slate-700">No matching <?php echo esc_html(strtolower($addon_label_plural)); ?></h3>
            <p class="mt-2 text-sm text-slate-500 max-w-md mx-auto">
                No results for “<?php echo esc_html($addon_search); ?>”. Try a different name, email, order number, or custom field value.
            </p>
        </div>
    <?php else : ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Order number</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Primary registrant</th>
                        <?php foreach ($addon_columns as $column) : ?>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                <?php echo esc_html((string) ($column['label'] ?? '')); ?>
                            </th>
                        <?php endforeach; ?>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Amount</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <?php foreach ($addon_rows as $row) : ?>
                        <?php
                        if (!is_array($row)) {
                            continue;
                        }
                        $field_values = is_array($row['field_values'] ?? null) ? $row['field_values'] : [];
                        $is_paid = !empty($row['is_paid']);
                        $status_label = (string) ($row['payment_status_label'] ?? '—');
                        $amount_display = (string) ($row['amount_display'] ?? '—');
                        $primary_order = trim((string) ($row['primary_order_number'] ?? ''));
                        ?>
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3 text-sm font-mono text-slate-700 whitespace-nowrap">
                                <?php echo esc_html((string) ($row['order_number'] ?? 'N/A')); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700">
                                <div><?php echo esc_html((string) ($row['primary_name'] ?? 'N/A')); ?></div>
                                <?php if ($primary_order !== '') : ?>
                                    <div class="mt-0.5 font-mono text-xs text-slate-500"><?php echo esc_html($primary_order); ?></div>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($addon_columns as $column) : ?>
                                <?php
                                $col_key = (string) ($column['key'] ?? '');
                                $col_value = trim((string) ($field_values[$col_key] ?? ''));
                                ?>
                                <td class="px-4 py-3 text-sm text-slate-700 max-w-xs break-words">
                                    <?php echo esc_html($col_value !== '' ? $col_value : '—'); ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">
                                <?php
                                if ($amount_display !== '' && $amount_display !== '—') {
                                    echo esc_html($event_currency . ' ' . $amount_display);
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?php echo $is_paid ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'; ?>">
                                    <?php echo esc_html($status_label); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ((int) ($addon_pagination['total_pages'] ?? 1) > 1 || $has_search) : ?>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-slate-200 px-4 py-3 bg-slate-50">
                <p class="text-sm text-slate-600">
                    Showing
                    <span class="font-semibold text-slate-900"><?php echo esc_html((string) (int) ($addon_pagination['from'] ?? 0)); ?></span>
                    to
                    <span class="font-semibold text-slate-900"><?php echo esc_html((string) (int) ($addon_pagination['to'] ?? 0)); ?></span>
                    of
                    <span class="font-semibold text-slate-900"><?php echo esc_html((string) $filtered_total); ?></span>
                    <?php if ($has_search) : ?>
                        <span class="text-slate-500">(filtered from <?php echo esc_html((string) $addon_total); ?>)</span>
                    <?php endif; ?>
                </p>
                <?php if ((int) ($addon_pagination['total_pages'] ?? 1) > 1) : ?>
                    <div class="flex items-center gap-2">
                        <?php if ($addon_prev_href !== '') : ?>
                            <a href="<?php echo esc_url($addon_prev_href); ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Previous
                            </a>
                        <?php else : ?>
                            <span class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm font-medium text-slate-400 cursor-not-allowed">Previous</span>
                        <?php endif; ?>
                        <span class="text-sm text-slate-600">
                            Page
                            <span class="font-semibold text-slate-900"><?php echo esc_html((string) (int) ($addon_pagination['current_page'] ?? 1)); ?></span>
                            of
                            <span class="font-semibold text-slate-900"><?php echo esc_html((string) (int) ($addon_pagination['total_pages'] ?? 1)); ?></span>
                        </span>
                        <?php if ($addon_next_href !== '') : ?>
                            <a href="<?php echo esc_url($addon_next_href); ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Next
                            </a>
                        <?php else : ?>
                            <span class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm font-medium text-slate-400 cursor-not-allowed">Next</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
