<?php
$receipt = is_array($registration_receipt ?? null) ? $registration_receipt : null;
if ($receipt === null) {
    return;
}

// TEMP DEBUG — remove after diagnosing PayNow QR second-device return.
// echo '<pre style="max-width:100%;overflow:auto;background:#111;color:#0f0;padding:1rem;font-size:12px;text-align:left;">';
// echo "=== registration-receipt.php dump ===\n";
// echo "--- \$receipt ---\n";
// var_dump($receipt);
// echo "\n--- \$_GET (current page) ---\n";
// var_dump($_GET);
// echo "\n--- payment return debug (from flash) ---\n";
// var_dump($receipt['debug'] ?? null);
// echo '</pre>';

$status = (string) ($receipt['status'] ?? 'confirmed');
$is_failed = $status === 'payment_failed';
$title = (string) ($receipt['title'] ?? 'Registration confirmed');
$confirmation_email = trim((string) ($receipt['confirmation_email'] ?? ''));
$register_another_href = (string) ($receipt['register_another_href'] ?? '');
$event_landing_href = (string) ($receipt['event_landing_href'] ?? '');
$show_event_landing = !empty($receipt['show_event_landing']) && $event_landing_href !== '';

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
<?php if (!$is_failed) : ?>
    <div class="flex flex-col gap-4 justify-center items-center">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-16 stroke-green-500">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
        <p class="text-4xl lg:text-3xl font-semibold text-green-500 text-center">Your registration has been confirmed!</p>
        <p class="mt-3 text-lg lg:text-sm text-green-700 text-center">Full details of your registration have been sent to your email address <span class="font-medium text-green-900"><?php echo esc_html($confirmation_email); ?></span>.</p>
        <div class="mt-8 flex justify-center items-center gap-2">
            <a href="<?php echo esc_url($event_landing_href); ?>" class="inline-flex items-center justify-center rounded-lg bg-green-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-green-600 transition">
                Go back to Event Page
            </a>
            <a href="<?php echo esc_url($register_another_href); ?>" class="inline-flex items-center justify-center border border-slate-300 rounded-lg bg-slate-200 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 transition">
                Register Another
            </a>
        </div>
    </div>
<?php else : ?>

    <div class="flex flex-col gap-4 justify-center items-center">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-16 stroke-red-500">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
        </svg>
        <p class="text-4xl lg:text-3xl font-semibold text-red-500 text-center">Registration Failed</p>
        <p class="mt-3 text-lg lg:text-sm text-red-700 text-center"><?php echo esc_html((string) $receipt['message']); ?></p>
        <div class="mt-8 flex justify-center items-center gap-2">
            <a href="<?php echo esc_url($event_landing_href); ?>" class="inline-flex items-center justify-center rounded-lg bg-red-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-red-600 transition">
                Go back to Event Page
            </a>
        </div>
    </div>
<?php endif; ?>
