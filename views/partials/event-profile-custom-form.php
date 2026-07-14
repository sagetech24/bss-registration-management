<?php
/**
 * Custom Form Options tab — field selection lives under Event Settings → Custom preset.
 */
$settings_href = rm_event_profile_url($selected_event_code, $selected_event_id, ['tab' => 'settings']);
$preset_value = (string) (($registration_config['form']['preset'] ?? 'full'));
$is_custom_preset = $preset_value === 'custom';
?>
<div class="bg-white border border-slate-200 rounded-xl shadow-sm">
    <div class="p-5 border-b border-slate-200">
        <h2 class="text-lg font-semibold text-slate-900">Custom Form Options</h2>
        <p class="mt-1 text-sm text-slate-500">Customize the fields shown on this event’s public registration form.</p>
    </div>
    <div class="p-8 text-center">
        <?php if ($is_custom_preset) : ?>
            <p class="text-sm text-slate-600 max-w-md mx-auto">
                This event uses the <span class="font-medium text-slate-800">Custom</span> form preset.
                Choose core fields and add extras (Age, Employer, Civil Status, etc.) in Event Settings.
            </p>
        <?php else : ?>
            <p class="text-sm text-slate-600 max-w-md mx-auto">
                To customize fields, open Event Settings and select the <span class="font-medium text-slate-800">Custom</span> form preset.
                Given name, Family name, and Email stay required; you can add your own fields there.
            </p>
        <?php endif; ?>
        <a
            href="<?php echo esc_url($settings_href); ?>"
            class="inline-flex items-center justify-center mt-4 rounded-lg bg-indigo-700 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-800 transition"
        >
            Open Event Settings
        </a>
    </div>
</div>
