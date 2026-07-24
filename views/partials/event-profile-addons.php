<?php
/**
 * Add-ons / guests tab — all addon registrant rows for this event.
 */
$addon_rows = is_array($addon_rows ?? null) ? $addon_rows : [];
$addon_columns = is_array($addon_columns ?? null) ? $addon_columns : [];
$addon_label_singular = (string) ($addon_label_singular ?? 'Guest');
$addon_label_plural = (string) ($addon_label_plural ?? 'Guests');
$addon_total = (int) ($addon_total ?? count($addon_rows));
$addon_error = (string) ($addon_error ?? '');
$event_currency = (string) ($event_currency ?? 'SGD');
?>

<div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
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

    <?php if ($addon_error !== '') : ?>
        <div class="m-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <?php echo esc_html($addon_error); ?>
        </div>
    <?php elseif ($addon_rows === []) : ?>
        <div class="p-10 text-center">
            <h3 class="text-lg font-semibold text-slate-700">No <?php echo esc_html(strtolower($addon_label_plural)); ?> yet</h3>
            <p class="mt-2 text-sm text-slate-500 max-w-md mx-auto">
                When registrants add <?php echo esc_html(strtolower($addon_label_plural)); ?> during registration, their details will appear here.
            </p>
        </div>
    <?php else : ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Order number</th>
                        <!-- <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Name</th> -->
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Primary registrant</th>
                        <?php foreach ($addon_columns as $column) : ?>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                <?php echo esc_html((string) ($column['label'] ?? '')); ?>
                            </th>
                        <?php endforeach; ?>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Amount</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                        <!-- <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Registered</th> -->
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
                            <!-- <td class="px-4 py-3 text-sm text-slate-900">
                                <div class="font-medium"><?php //echo esc_html((string) ($row['full_name'] ?? 'N/A')); ?></div>
                                <div class="mt-0.5">
                                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">
                                        <?php //echo esc_html((string) ($row['role_label'] ?? $addon_label_singular)); ?>
                                    </span>
                                </div>
                            </td> -->
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
                            <!-- <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap">
                                <?php //echo esc_html((string) ($row['date_display'] ?? '—')); ?>
                            </td> -->
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
