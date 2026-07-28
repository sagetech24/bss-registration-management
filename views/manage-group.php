<?php
$event_present = is_array($event_present ?? null) ? $event_present : null;
$needs_login = !empty($needs_login);
$access_ok = !empty($access_ok);
$can_add = !empty($can_add);
$error_message = (string) ($error_message ?? '');
$success_message = (string) ($success_message ?? '');
$members = is_array($members ?? null) ? $members : [];
$group_meta = is_array($group_meta ?? null) ? $group_meta : [
    'incomplete' => false,
    'member_count' => 0,
    'member_max' => 0,
    'slots_remaining' => 0,
];
$package_label = (string) ($package_label ?? '');
$confirmation_number = (string) ($confirmation_number ?? '');
$form_schema = is_array($form_schema ?? null) ? $form_schema : ['fields' => []];
$form_errors = is_array($form_errors ?? null) ? $form_errors : [];
$member_input = is_array($member_input ?? null) ? $member_input : [];
$registration_config = is_array($registration_config ?? null) ? $registration_config : [];
$manage_token = (string) ($manage_token ?? '');
$page_url = (string) ($page_url ?? '');
$event_landing_href = (string) ($event_landing_href ?? '');
$event_currency = (string) ($event_currency ?? 'SGD');
$locale = (string) ($locale ?? 'en');
$ui_strings = is_array($ui_strings ?? null) ? $ui_strings : (function_exists('rm_public_ui_strings') ? rm_public_ui_strings($locale) : []);
$t = static function (string $key) use ($ui_strings, $locale): string {
    if (isset($ui_strings[$key])) {
        return $ui_strings[$key];
    }

    return function_exists('rm__') ? rm__($key, $locale) : $key;
};
?>

<section class="space-y-6">
    <?php if ($error_message !== '' && $event_present === null) : ?>
        <div class="bg-white border border-rose-200 rounded-xl shadow-sm p-6">
            <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800">
                <?php echo esc_html($error_message); ?>
            </div>
        </div>
    <?php else : ?>
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 space-y-4">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-600"><?php echo esc_html($t('manage.heading')); ?></p>
                <h2 class="mt-1 text-2xl font-semibold text-slate-900"><?php echo esc_html($t('email.add_remaining')); ?></h2>
                <p class="mt-2 text-sm text-slate-600">
                    <?php echo esc_html($t('manage.new_members_note')); ?>
                </p>
            </div>

            <?php if ($success_message !== '') : ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-800 text-sm">
                    <?php echo esc_html($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($access_ok) : ?>
                <?php if ($error_message !== '') : ?>
                    <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800 text-sm">
                        <?php echo esc_html($error_message); ?>
                    </div>
                <?php endif; ?>
                <?php include __DIR__ . '/partials/manage-group-roster.php'; ?>
            <?php elseif ($needs_login) : ?>
                <?php include __DIR__ . '/partials/manage-group-login.php'; ?>
            <?php elseif ($error_message !== '') : ?>
                <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800 text-sm">
                    <?php echo esc_html($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($event_landing_href !== '') : ?>
                <div class="border-t border-slate-200 pt-4">
                    <a
                        href="<?php echo esc_url($event_landing_href); ?>"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
                    >
                        <?php echo esc_html($t('manage.close_page')); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
