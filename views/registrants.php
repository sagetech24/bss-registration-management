<?php
$selected_event = $selected_event ?? null;
$selected_event_code = $selected_event_code ?? '';
$registrant_rows = $registrant_rows ?? [];
$registrants_summary = $registrants_summary ?? [
    'total'         => 0,
    'paid_count'    => 0,
    'pending_count' => 0,
    'total_revenue' => 0.0,
];
$registrants_error = $registrants_error ?? '';
$event_title = is_array($selected_event) ? ($selected_event['title'] ?? 'Selected Event') : 'Selected Event';
$event_code_label = is_array($selected_event)
    ? ($selected_event['programCode'] ?? $selected_event_code)
    : $selected_event_code;
?>

<section class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
    <div class="lg:col-span-12 space-y-6">
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
            <?php if ($selected_event_code !== '' && is_array($selected_event)) : ?>
                <div class="p-5 border-b border-slate-200">
                    <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600">Registrant directory</p>
                    <h3 class="mt-2 text-2xl font-semibold text-slate-900">
                        <?php echo $event_title; ?>
                        <?php //echo esc_html((string) $event_title); ?>
                    </h3>
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm text-slate-600">
                        <p>
                            Program code:
                            <span class="font-medium text-slate-800"><?php echo esc_html((string) $event_code_label); ?></span>
                        </p>
                        <?php if (!empty($selected_event['venue'])) : ?>
                            <p>
                                Venue:
                                <span class="font-medium text-slate-800"><?php echo esc_html((string) $selected_event['venue']); ?></span>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($selected_event['date'])) : ?>
                            <p>
                                Date:
                                <span class="font-medium text-slate-800"><?php echo esc_html((string) $selected_event['date']); ?></span>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($selected_event['time'])) : ?>
                            <p>
                                Time:
                                <span class="font-medium text-slate-800"><?php echo esc_html((string) $selected_event['time']); ?></span>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total registrants</p>
                            <p class="mt-2 text-3xl font-bold text-slate-900"><?php echo esc_html(number_format_i18n($registrants_summary['total'])); ?></p>
                        </div>
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">Paid / confirmed</p>
                            <p class="mt-2 text-3xl font-bold text-emerald-800"><?php echo esc_html(number_format_i18n($registrants_summary['paid_count'])); ?></p>
                        </div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-amber-700">Pending / unpaid</p>
                            <p class="mt-2 text-3xl font-bold text-amber-800"><?php echo esc_html(number_format_i18n($registrants_summary['pending_count'])); ?></p>
                        </div>
                        <div class="rounded-xl border border-green-200 bg-green-50 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-green-700">Total revenue</p>
                            <p class="mt-2 text-3xl font-bold text-green-800"><?php echo esc_html('$' . number_format_i18n($registrants_summary['total_revenue'], 2)); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($registrants_error !== '') : ?>
            <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800">
                <?php echo esc_html($registrants_error); ?>
            </div>
        <?php elseif ($selected_event_code === '') : ?>
            <div class="p-6 text-slate-600 border border-slate-200 rounded-xl bg-white">
                Select an event to view registrants.
            </div>
        <?php elseif (empty($registrant_rows)) : ?>
            <div class="p-6 text-slate-600 border border-slate-200 rounded-xl bg-white text-center">
                <h3 class="text-lg font-semibold text-slate-700">No registrants yet</h3>
                <p class="mt-1 text-sm text-slate-500">There are currently no registration records for this event.</p>
            </div>
        <?php else : ?>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Order number</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Registrant</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Contact</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Amount</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Email sent</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Registered</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php foreach ($registrant_rows as $row) : ?>
                                <tr class="hover:bg-slate-50/80">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-slate-800">
                                        <?php echo $row['order_number'] !== '' ? esc_html($row['order_number']) : 'N/A'; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <p class="font-medium text-slate-800"><?php echo esc_html($row['full_name']); ?></p>
                                        <?php if ($row['is_pending']) : ?>
                                            <p class="text-xs text-amber-700 mt-1">Pending table</p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        <p><?php echo $row['email'] !== '' ? esc_html($row['email']) : 'N/A'; ?></p>
                                        <p class="text-xs text-slate-500 mt-1"><?php echo $row['phone'] !== '' ? esc_html($row['phone']) : 'No phone'; ?></p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?php echo $row['is_paid'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'; ?>">
                                            <?php echo esc_html($row['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                                        <?php echo esc_html($row['amount_display']); ?>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?php echo $row['email_sent'] ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'; ?>">
                                            <?php echo esc_html($row['email_sent_label']); ?>
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                                        <?php echo esc_html($row['date_display']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
