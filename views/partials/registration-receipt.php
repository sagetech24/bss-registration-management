<?php
$receipt = is_array($registration_receipt ?? null) ? $registration_receipt : null;
if ($receipt === null) {
    return;
}

$status = (string) ($receipt['status'] ?? 'confirmed');
$is_failed = $status === 'payment_failed';
$is_processing = $status === 'payment_processing';
$is_pending = $status === 'pending_payment';
$confirmation_email = trim((string) ($receipt['confirmation_email'] ?? ''));
$register_another_href = (string) ($receipt['register_another_href'] ?? '');
$event_landing_href = (string) ($receipt['event_landing_href'] ?? '');
$message = trim((string) ($receipt['message'] ?? ''));

/**
 * @param list<array{label: string, value: string}> $rows
 */
$render_detail_table = static function (array $rows): void {
    if ($rows === []) {
        return;
    }

    echo '<dl class="divide-y divide-slate-100">';
    foreach ($rows as $row) {
        echo '<div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[11rem_1fr] sm:gap-4">';
        echo '<dt class="text-sm font-medium text-slate-500">' . esc_html($row['label']) . '</dt>';
        echo '<dd class="text-sm text-slate-900 break-words">' . esc_html($row['value']) . '</dd>';
        echo '</div>';
    }
    echo '</dl>';
};
?>
<?php if ($is_failed) : ?>
    <div class="flex flex-col gap-4 justify-center items-center">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-16 stroke-red-500">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
        </svg>
        <p class="text-4xl lg:text-3xl font-semibold text-red-500 text-center">Payment not completed</p>
        <p class="mt-3 text-lg lg:text-sm text-red-700 text-center"><?php echo esc_html($message !== '' ? $message : (string) ($receipt['message'] ?? '')); ?></p>
        <div class="mt-8 flex justify-center items-center gap-2">
            <a href="<?php echo esc_url($event_landing_href); ?>" class="inline-flex items-center justify-center rounded-lg bg-red-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-red-600 transition">
                Go back to Event Page
            </a>
        </div>
    </div>
<?php elseif ($is_processing || $is_pending) : ?>
    <div class="flex flex-col gap-4 justify-center items-center">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-16 stroke-amber-500">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
        <p class="text-4xl lg:text-3xl font-semibold text-amber-600 text-center">
            <?php echo esc_html($is_processing ? 'Confirming your payment' : 'Registration received'); ?>
        </p>
        <p class="mt-3 text-lg lg:text-sm text-amber-800 text-center"><?php echo esc_html($message); ?></p>
        <div class="mt-8 flex justify-center items-center gap-2">
            <a href="<?php echo esc_url($event_landing_href); ?>" class="inline-flex items-center justify-center rounded-lg bg-amber-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-amber-600 transition">
                Go back to Event Page
            </a>
            <?php if ($register_another_href !== '') : ?>
                <a href="<?php echo esc_url($register_another_href); ?>" class="inline-flex items-center justify-center border border-slate-300 rounded-lg bg-slate-200 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 transition">
                    Register Another
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else : ?>
    <div class="flex flex-col gap-4 justify-center items-center">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-16 stroke-green-500">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
        <p class="text-4xl lg:text-3xl font-semibold text-green-500 text-center">Your registration has been confirmed!</p>
        <p class="mt-3 text-lg lg:text-sm text-green-700 text-center">Full details of your registration have been sent to your email address <span class="font-medium text-green-900"><?php echo esc_html($confirmation_email); ?></span>.</p>
        <?php
        $group_incomplete = !empty($receipt['group_incomplete']);
        $manage_group_url = trim((string) ($receipt['manage_group_url'] ?? ''));
        $group_slots_remaining = (int) ($receipt['group_slots_remaining'] ?? 0);
        $group_member_count = (int) ($receipt['group_member_count'] ?? 0);
        $group_member_max = (int) ($receipt['group_member_max'] ?? 0);
        ?>
        <?php if ($group_incomplete && $manage_group_url !== '') : ?>
            <div class="mt-6 w-full max-w-xl rounded-xl border border-indigo-200 bg-indigo-50 p-5 text-left">
                <p class="text-sm font-semibold text-indigo-900">Complete your group roster</p>
                <p class="mt-2 text-sm text-indigo-800">
                    You still have <?php echo esc_html((string) $group_slots_remaining); ?> open slot<?php echo $group_slots_remaining === 1 ? '' : 's'; ?>
                    (<?php echo esc_html((string) $group_member_count); ?> of <?php echo esc_html((string) $group_member_max); ?> registered).
                    Add remaining members at no extra charge.
                </p>
                <a
                    href="<?php echo esc_url($manage_group_url); ?>"
                    class="mt-4 inline-flex items-center justify-center rounded-lg bg-indigo-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-800 transition"
                >
                    Add remaining members
                </a>
            </div>
        <?php endif; ?>
        <div class="mt-8 flex justify-center items-center gap-2">
            <a href="<?php echo esc_url($event_landing_href); ?>" class="inline-flex items-center justify-center rounded-lg bg-green-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-green-600 transition">
                Go back to Event Page
            </a>
            <a href="<?php echo esc_url($register_another_href); ?>" class="inline-flex items-center justify-center border border-slate-300 rounded-lg bg-slate-200 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 transition">
                Register Another
            </a>
        </div>
    </div>
<?php endif; ?>
