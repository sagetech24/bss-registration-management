<?php
$error_message = (string) ($error_message ?? '');
$page_url = (string) ($page_url ?? '');
$locale = (string) ($locale ?? 'en');
$event_addon_full = !empty($event_addon_full);
$guest_label_plural = trim((string) ($guest_label_plural ?? 'Guests'));
if ($guest_label_plural === '') {
    $guest_label_plural = 'Guests';
}
$ui_strings = is_array($ui_strings ?? null) ? $ui_strings : (function_exists('rm_public_ui_strings') ? rm_public_ui_strings($locale) : []);
$t = static function (string $key, array $replace = []) use ($ui_strings, $locale): string {
    if (isset($ui_strings[$key])) {
        $text = $ui_strings[$key];
        foreach ($replace as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }

        return $text;
    }

    return function_exists('rm__') ? rm__($key, $locale, $replace) : $key;
};
$input_class = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
$disabled_input_class = 'w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-500 cursor-not-allowed';
?>

<?php if ($event_addon_full) : ?>
    <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-sm">
        <?php echo esc_html($t('guest_manage.error.event_full', ['guest' => $guest_label_plural])); ?>
    </div>
<?php elseif ($error_message !== '') : ?>
    <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800 text-sm">
        <?php echo esc_html($error_message); ?>
    </div>
<?php endif; ?>

<div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
    <p class="text-sm font-medium text-slate-800"><?php echo esc_html($t('guest_manage.lookup_title')); ?></p>
    <p class="mt-1 text-sm text-slate-600"><?php echo esc_html($t('guest_manage.lookup_desc')); ?></p>
</div>
<br />
<form method="post" action="<?php echo esc_url($page_url); ?>" class="space-y-4">
    <?php wp_nonce_field('rm_guest_manage', 'rm_guest_manage_nonce'); ?>
    <input type="hidden" name="rm_guest_manage_action" value="login" />
    <input type="hidden" name="lang" value="<?php echo esc_attr($locale); ?>" />

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-5">
        <div class="col-span-1">
            <label for="rm_ag_confirmation" class="block text-sm font-medium text-slate-700 mb-1.5"><?php echo esc_html($t('manage.confirmation_number_label')); ?></label>
            <input
                id="rm_ag_confirmation"
                type="text"
                name="confirmation_number"
                <?php echo $event_addon_full ? 'disabled' : 'required'; ?>
                autocomplete="off"
                class="text-sm <?php echo esc_attr($event_addon_full ? $disabled_input_class : $input_class); ?>"
                placeholder="<?php echo esc_attr($t('manage.confirmation_placeholder')); ?>"
            />
        </div>
    
        <div class="col-span-3">
            <label for="rm_ag_email" class="block text-sm font-medium text-slate-700 mb-1.5"><?php echo esc_html($t('manage.primary_email_label')); ?></label>
            <input
                id="rm_ag_email"
                type="email"
                name="email"
                <?php echo $event_addon_full ? 'disabled' : 'required'; ?>
                autocomplete="email"
                class="text-sm <?php echo esc_attr($event_addon_full ? $disabled_input_class : $input_class); ?>"
                placeholder="<?php echo esc_attr($t('manage.email_placeholder')); ?>"
            />
        </div>
    </div>
    <button
        type="submit"
        <?php echo $event_addon_full ? 'disabled' : ''; ?>
        class="inline-flex items-center justify-center rounded-lg bg-indigo-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-800 transition disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-indigo-700"
    >
        <?php echo esc_html($t('manage.continue')); ?>
    </button>
    <br />
    <br />
</form>
